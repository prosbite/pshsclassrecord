import { calculatePercentage, calculateWeightedPercentage } from '@/Composables/utilities.js';

export const DETECTABLE_SEGMENTS = {
    lt1: { pattern: /(lt1|long\s?test\s?1|longtest1)/i },
    lt2: { pattern: /(lt2|long\s?test\s?2|longtest2)/i },
    aa: { pattern: /(aa|alternative)/i },
    fa: { pattern: /(fa|formative)/i },
    final: { pattern: /(final|trunc|ge|adjectival|two.*third|one.*third)/i },
};

export function normalizeHeaderLabel(header) {
    const trimmed = (header ?? '').trim();

    if (!trimmed) {
        return '';
    }

    if (/^t$/i.test(trimmed)) return 'Total';
    if (/^%$/i.test(trimmed)) return 'Percentage';
    if (
        /^w%$/i.test(trimmed)
        || /^w\s?%$/i.test(trimmed)
        || /^tw%$/i.test(trimmed)
        || /^total\s*weighted\s*%$/i.test(trimmed)
        || /^weighted\s?%$/i.test(trimmed)
    ) {
        return 'Weighted %';
    }

    return trimmed.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();
}

export function parseHeaderMeta(header) {
    const rawLabel = (header ?? '').toString().trim();
    const tentative = /\(\s*t\s*\)/i.test(rawLabel);
    const labelWithoutTentative = rawLabel.replace(/\s*\(\s*t\s*\)\s*/gi, ' ').replace(/\s+/g, ' ').trim();
    const normalizedLabel = normalizeHeaderLabel(labelWithoutTentative || rawLabel);
    const normalizedType = normalizedLabel.toLowerCase();

    return {
        rawLabel,
        label: normalizedLabel,
        fieldType: /^t$|^total$/i.test(normalizedType)
            ? 'total'
            : /^%$|^percentage$/i.test(normalizedType)
                ? 'percentage'
                : (
                    /^w%$|^tw%$|^total\s*weighted\s*%$/i.test(normalizedType)
                    || /^weighted\s?%$/i.test(normalizedType)
                )
                    ? 'weighted'
                    : /^ge$|^adjectival$|^trunc$|^truncated$/i.test(normalizedType)
                        ? 'final'
                        : 'score',
        tentative,
    };
}

export function sanitizePerfectScore(value) {
    if (value === null || value === undefined) {
        return null;
    }

    const candidate = String(value).trim();
    if (!candidate || candidate === '-' || candidate.toUpperCase() === '#REF!') {
        return null;
    }

    return candidate;
}

export function detectSegment(normalized) {
    if (!normalized) {
        return null;
    }

    for (const [segment, config] of Object.entries(DETECTABLE_SEGMENTS)) {
        if (config.pattern.test(normalized)) {
            return segment;
        }
    }

    return null;
}

export function isSubHeaderRow(row) {
    if (!Array.isArray(row)) {
        return false;
    }

    const first = trimCell(row[0]);
    const second = trimCell(row[1]);

    if (first !== '' || second !== '') {
        return false;
    }

    return row.some((value) => trimCell(value) !== '');
}

export function collectSubHeaders(rows, currentIndex) {
    const subHeaders = [];

    for (let i = currentIndex - 1; i >= 0; i -= 1) {
        const row = rows[i];

        if (isSubHeaderRow(row)) {
            subHeaders.unshift(row);
            continue;
        }

        const hasNames = trimCell(row?.[0]) !== '' || trimCell(row?.[1]) !== '';
        if (hasNames) {
            break;
        }
    }

    return subHeaders;
}

export function resolveSubheaderValues(subHeaders) {
    if (!Array.isArray(subHeaders) || !subHeaders.length) {
        return [];
    }

    const last = subHeaders[subHeaders.length - 1];
    return Array.isArray(last) ? [...last] : [];
}

export function isScoreEntry(entry) {
    return entry?.fieldType === 'score';
}

export function isTwoThirdEntry(label) {
    const normalized = (label ?? '').toLowerCase();
    return /(tw%?|two.*third)/i.test(normalized);
}

export function extractSegments(headers, values, subheaderValues = []) {
    const normalizedHeaders = Array.isArray(headers) ? headers : [];
    const normalizedValues = Array.isArray(values) ? values : [];
    const normalizedSubheaders = Array.isArray(subheaderValues) ? subheaderValues : [];

    const buckets = {
        lt1: [],
        lt2: [],
        aa: [],
        fa: [],
        final: [],
        other: [],
    };

    let currentSegment = null;

    normalizedHeaders.forEach((header, index) => {
        const normalized = (header ?? '').toLowerCase();
        const detected = detectSegment(normalized);

        if (detected) {
            currentSegment = detected;
        }

        if (/(family|given|middle|gender)/i.test(normalized)) {
            return;
        }

        const entry = {
            key: `${currentSegment ?? 'other'}-${index}-${header ?? `Column ${index + 1}`}`,
            ...parseHeaderMeta(header || `Column ${index + 1}`),
            value: normalizedValues[index] ?? '-',
            perfectScore: sanitizePerfectScore(normalizedSubheaders[index]),
            segment: currentSegment ?? 'other',
            index,
        };

        buckets[entry.segment] = buckets[entry.segment] ?? [];
        buckets[entry.segment].push(entry);
    });

    return buckets;
}

export function detailEntriesForSegment(entries, segment) {
    if (!Array.isArray(entries)) {
        return [];
    }

    if (segment !== 'fa') {
        return entries;
    }

    let weightedSeen = false;

    return entries.filter((entry) => {
        if (isTwoThirdEntry(entry.label)) {
            return false;
        }

        if (entry.fieldType !== 'weighted') {
            return true;
        }

        if (weightedSeen) {
            return false;
        }

        weightedSeen = true;
        return true;
    });
}

export function buildSegmentMetrics(entries, getValue = (entry) => entry?.value) {
    const scoreEntries = (entries ?? []).filter((entry) => isScoreEntry(entry));

    const score = scoreEntries.reduce((sum, entry) => {
        const numericValue = toNumericValue(getValue(entry)) ?? 0;
        return sum + numericValue;
    }, 0);

    const perfectScore = scoreEntries.reduce((sum, entry) => {
        const numericValue = toNumericValue(entry.perfectScore) ?? 0;
        return sum + numericValue;
    }, 0);

    return {
        score,
        perfectScore,
        percentage: calculatePercentage(score, perfectScore),
        weighted: calculateWeightedPercentage(score, perfectScore),
    };
}

export function buildQuarterlyTableView(headers, rows) {
    const normalizedHeaders = Array.isArray(headers)
        ? headers.map((header, index) => (header ?? `Column ${index + 1}`))
        : [];

    const normalizedRows = Array.isArray(rows)
        ? rows.map((row) => (Array.isArray(row) ? [...row] : []))
        : [];

    const rowViews = normalizedRows.map((row, rowIndex) => {
        const isSubHeader = isSubHeaderRow(row);
        const subHeaders = resolveSubheaderValues(collectSubHeaders(normalizedRows, rowIndex));
        const segments = extractSegments(normalizedHeaders, row, subHeaders);

        const metricsBySegment = Object.fromEntries(
            Object.entries(segments).map(([segment, entries]) => [
                segment,
                buildSegmentMetrics(entries, (entry) => entry.value),
            ]),
        );

        const entriesByIndex = new Map();
        Object.values(segments).flat().forEach((entry) => {
            entriesByIndex.set(entry.index, entry);
        });

        const cells = normalizedHeaders.map((header, columnIndex) => {
            const entry = entriesByIndex.get(columnIndex);
            const fallbackMeta = parseHeaderMeta(header || `Column ${columnIndex + 1}`);
            const cellMeta = entry ?? {
                key: `row-${rowIndex}-${columnIndex}`,
                ...fallbackMeta,
                value: row[columnIndex] ?? '',
                perfectScore: sanitizePerfectScore(subHeaders[columnIndex]),
                segment: detectSegment((header ?? '').toLowerCase()) ?? 'other',
                index: columnIndex,
            };

            const metrics = metricsBySegment[cellMeta.segment] ?? null;

            return {
                ...cellMeta,
                isSubHeader,
                isCalculation: cellMeta.fieldType !== 'score',
                displayValue: getDisplayValue({ ...cellMeta, isSubHeader }, metrics),
            };
        });

        return {
            index: rowIndex,
            isSubHeader,
            subHeaders,
            metricsBySegment,
            cells,
        };
    });

    return {
        headers: normalizedHeaders,
        rows: rowViews,
    };
}

export function buildQuarterlyTablePayload(headers, rows) {
    const view = buildQuarterlyTableView(headers, rows);

    return {
        headers: view.headers,
        rows: view.rows.map((row) => row.cells.map((cell) => cell.displayValue)),
    };
}

function trimCell(value) {
    return String(value ?? '').trim();
}

function toNumericValue(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const numericValue = Number(value);
    return Number.isFinite(numericValue) ? numericValue : null;
}

function getDisplayValue(entry, metrics) {
    if (entry.isSubHeader) {
        return entry.value ?? '';
    }

    if (entry.fieldType === 'total') {
        return metrics?.score ?? entry.value ?? '';
    }

    if (entry.fieldType === 'percentage') {
        return metrics?.percentage ?? entry.value ?? '';
    }

    if (entry.fieldType === 'weighted') {
        return metrics?.weighted ?? entry.value ?? '';
    }

    return entry.value ?? '';
}
