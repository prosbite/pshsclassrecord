import {
    calculatePercentage,
    calculateWeightedPercentage,
    getAdjectivalEquivalent,
    getGradeEquivalentFromPercent,
    getGradeEquivalentFromValue,
} from './utilities.js';

export const SEGMENT_WEIGHT = 0.25;

export const SEGMENT_ORDER = ['lt1', 'lt2', 'aa', 'fa'];

export const SEGMENT_LABELS = {
    lt1: 'Long Test 1',
    lt2: 'Long Test 2',
    aa: 'Alternative Assessments',
    fa: 'Formative Assessments',
};

const TYPE_CODE_TO_SEGMENT = {
    long_test: 'long_test',
    alternative: 'aa',
    formative: 'fa',
};

const FALLBACK_LABELS = {
    long_test: 'Long Test',
    aa: 'Alternative Assessment',
    fa: 'Formative Assessment',
};

const toNumericValue = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const numericValue = Number(value);
    return Number.isFinite(numericValue) ? numericValue : null;
};

const relationData = (assessment) => assessment?.assessmentType ?? assessment?.assessment_type ?? {};

export function assessmentTypeCode(assessment) {
    return relationData(assessment)?.code ?? null;
}

export function assessmentTypeName(assessment) {
    return relationData(assessment)?.name ?? null;
}

/**
 * Map a structured assessment's type to a grading segment. Codes are canonical; the
 * type name is only a fallback for rows created before the `code` backfill.
 */
export function mapTypeCodeToSegment(code, name = '') {
    const normalizedCode = (code ?? '').toString().trim().toLowerCase();

    if (TYPE_CODE_TO_SEGMENT[normalizedCode]) {
        return TYPE_CODE_TO_SEGMENT[normalizedCode];
    }

    const normalizedName = (name ?? '').toString().trim().toLowerCase();

    if (!normalizedName) {
        return null;
    }

    if (normalizedName.includes('long')) {
        return 'long_test';
    }

    if (normalizedName.includes('alternative') || normalizedName === 'aa') {
        return 'aa';
    }

    if (normalizedName.includes('formative') || normalizedName === 'fa') {
        return 'fa';
    }

    return null;
}

const dateValue = (value) => {
    if (!value) {
        return null;
    }

    const timestamp = new Date(value).getTime();
    return Number.isNaN(timestamp) ? null : timestamp;
};

export const compareAssessmentOrder = (a, b) => {
    const aDate = dateValue(a?.assessment_date);
    const bDate = dateValue(b?.assessment_date);

    if (aDate !== null && bDate !== null && aDate !== bDate) {
        return aDate - bDate;
    }

    if (aDate === null && bDate !== null) {
        return 1;
    }

    if (aDate !== null && bDate === null) {
        return -1;
    }

    return (a?.id ?? 0) - (b?.id ?? 0);
};

/**
 * Group assessments into segments. Long tests sort by date (then id); only the first
 * two are used as lt1/lt2 and any extra long tests are ignored.
 */
export function groupAssessmentsBySegment(assessments = []) {
    const longTests = [];
    const aa = [];
    const fa = [];

    (assessments ?? []).forEach((assessment) => {
        const segment = mapTypeCodeToSegment(
            assessmentTypeCode(assessment),
            assessmentTypeName(assessment),
        );

        if (segment === 'long_test') {
            longTests.push(assessment);
        } else if (segment === 'aa') {
            aa.push(assessment);
        } else if (segment === 'fa') {
            fa.push(assessment);
        }
    });

    longTests.sort(compareAssessmentOrder);
    aa.sort(compareAssessmentOrder);
    fa.sort(compareAssessmentOrder);

    return {
        lt1: longTests[0] ?? null,
        lt2: longTests[1] ?? null,
        aa,
        fa,
    };
}

export function hasSegmentAssessments(grouped) {
    if (!grouped) {
        return false;
    }

    return Boolean(grouped.lt1 || grouped.lt2 || grouped.aa?.length || grouped.fa?.length);
}

const resolveScore = (assessment, scoresByLearner, learnerId) => {
    const map = scoresByLearner ?? {};

    if (learnerId !== null && learnerId !== undefined) {
        const learnerMap = map[learnerId] ?? map[String(learnerId)];

        if (learnerMap && Object.prototype.hasOwnProperty.call(learnerMap, assessment.id)) {
            return toNumericValue(learnerMap[assessment.id]);
        }
    }

    if (Object.prototype.hasOwnProperty.call(assessment ?? {}, 'score')) {
        return toNumericValue(assessment.score);
    }

    const learners = assessment?.learners;

    if (Array.isArray(learners)) {
        const match = learners.find((entry) => String(entry?.id) === String(learnerId));

        if (match) {
            return toNumericValue(match?.pivot?.score ?? match?.score);
        }
    }

    return null;
};

export function resolveTentative(assessment, tentativesByLearner = {}, learnerId = null) {
    const map = tentativesByLearner ?? {};

    if (learnerId !== null && learnerId !== undefined) {
        const learnerMap = map[learnerId] ?? map[String(learnerId)];

        if (learnerMap && Object.prototype.hasOwnProperty.call(learnerMap, assessment.id)) {
            return Boolean(learnerMap[assessment.id]);
        }
    }

    if (Object.prototype.hasOwnProperty.call(assessment ?? {}, 'tentative')) {
        return Boolean(assessment.tentative);
    }

    const learners = assessment?.learners;

    if (Array.isArray(learners)) {
        const match = learners.find((entry) => String(entry?.id) === String(learnerId));

        if (match) {
            return Boolean(match?.pivot?.tentative ?? match?.tentative ?? false);
        }
    }

    return false;
}

const defaultEntryLabel = (segment, index) => {
    const base = FALLBACK_LABELS[segment] ?? 'Score';
    return index > 0 ? `${base} ${index + 1}` : base;
};

const buildEntry = (segment, assessment, score, index, tentative = false) => {
    const title = (assessment?.title ?? '').toString().trim();
    const label = title !== '' ? title : defaultEntryLabel(segment, index);

    return {
        key: `${segment}-${assessment.id}`,
        label,
        rawLabel: label,
        fieldType: 'score',
        value: score,
        tentative: Boolean(tentative),
        perfectScore: toNumericValue(assessment?.perfect_score),
        assessmentId: assessment.id,
        segment,
        index,
    };
};

export function buildScoresByLearner(assessments = []) {
    const map = {};

    (assessments ?? []).forEach((assessment) => {
        (assessment?.learners ?? []).forEach((learner) => {
            const learnerId = learner?.id ?? learner?.learner_id;

            if (learnerId === null || learnerId === undefined) {
                return;
            }

            map[learnerId] = map[learnerId] ?? {};
            map[learnerId][assessment.id] = toNumericValue(learner?.pivot?.score ?? learner?.score);
        });
    });

    return map;
}

export function buildTentativesByLearner(assessments = []) {
    const map = {};

    (assessments ?? []).forEach((assessment) => {
        (assessment?.learners ?? []).forEach((learner) => {
            const learnerId = learner?.id ?? learner?.learner_id;

            if (learnerId === null || learnerId === undefined) {
                return;
            }

            map[learnerId] = map[learnerId] ?? {};
            map[learnerId][assessment.id] = Boolean(learner?.pivot?.tentative ?? false);
        });
    });

    return map;
}

export function buildSegmentEntries(grouped, scoresByLearner = {}, learnerId = null, tentativesByLearner = {}) {
    const toEntries = (assessments, segment) =>
        (assessments ?? []).map((assessment, index) =>
            buildEntry(
                segment,
                assessment,
                resolveScore(assessment, scoresByLearner, learnerId),
                index,
                resolveTentative(assessment, tentativesByLearner, learnerId),
            ));

    return {
        lt1: grouped?.lt1
            ? [buildEntry(
                'lt1',
                grouped.lt1,
                resolveScore(grouped.lt1, scoresByLearner, learnerId),
                0,
                resolveTentative(grouped.lt1, tentativesByLearner, learnerId),
            )]
            : [],
        lt2: grouped?.lt2
            ? [buildEntry(
                'lt2',
                grouped.lt2,
                resolveScore(grouped.lt2, scoresByLearner, learnerId),
                0,
                resolveTentative(grouped.lt2, tentativesByLearner, learnerId),
            )]
            : [],
        aa: toEntries(grouped?.aa, 'aa'),
        fa: toEntries(grouped?.fa, 'fa'),
    };
}

export function metricsForEntries(entries = []) {
    const scoreEntries = (entries ?? []).filter((entry) => entry?.fieldType === 'score');

    const score = scoreEntries.reduce((sum, entry) => sum + (toNumericValue(entry.value) ?? 0), 0);
    const perfectScore = scoreEntries.reduce((sum, entry) => sum + (toNumericValue(entry.perfectScore) ?? 0), 0);

    return {
        score,
        perfectScore,
        percentage: calculatePercentage(score, perfectScore),
        weighted: calculateWeightedPercentage(score, perfectScore, SEGMENT_WEIGHT),
    };
}

export function truncateDecimal(value, digits = 3) {
    const numericValue = toNumericValue(value);

    if (numericValue === null) {
        return null;
    }

    const factor = 10 ** digits;
    return Math.trunc(numericValue * factor) / factor;
}

const isTwoThirdEntry = (label) => /(tw%?|two.*third)/i.test((label ?? '').toString().toLowerCase());

/**
 * Mirror the legacy detail filtering: the FA segment hides `two-thirds` markers and
 * keeps only the first `weighted` entry. Structured entries are all `score` entries,
 * so this is a no-op for structured data but keeps legacy payloads rendering the same.
 */
export function detailEntriesForSegment(entries, segment) {
    if (!Array.isArray(entries)) {
        return [];
    }

    if (segment !== 'fa') {
        return entries;
    }

    let weightedSeen = false;

    return entries.filter((entry) => {
        if (isTwoThirdEntry(entry?.label)) {
            return false;
        }

        if (entry?.fieldType !== 'weighted') {
            return true;
        }

        if (weightedSeen) {
            return false;
        }

        weightedSeen = true;
        return true;
    });
}

/**
 * Compute the quarter chain from already-built segment entries. This lets callers
 * (e.g. simulation mode) feed draft-adjusted entry values through the same math.
 */
export function buildQuarterResultFromEntries(entries, previousQuarterGe = null) {
    const resolvedEntries = {
        lt1: entries?.lt1 ?? [],
        lt2: entries?.lt2 ?? [],
        aa: entries?.aa ?? [],
        fa: entries?.fa ?? [],
    };

    const segments = {
        lt1: metricsForEntries(resolvedEntries.lt1),
        lt2: metricsForEntries(resolvedEntries.lt2),
        aa: metricsForEntries(resolvedEntries.aa),
        fa: metricsForEntries(resolvedEntries.fa),
    };

    const hasAssessments = SEGMENT_ORDER.some((segment) => resolvedEntries[segment].length > 0);
    const hasTentative = SEGMENT_ORDER.some((segment) =>
        resolvedEntries[segment].some((entry) => Boolean(entry?.tentative)));
    const twPercent = hasAssessments
        ? SEGMENT_ORDER.reduce((sum, segment) => sum + (segments[segment].weighted ?? 0), 0)
        : null;

    const currentGe = twPercent === null ? null : getGradeEquivalentFromPercent(twPercent);
    const currentThird = currentGe === null ? null : currentGe * (2 / 3);
    const previousGe = previousQuarterGe === null || previousQuarterGe === undefined
        ? null
        : previousQuarterGe;
    const previousThird = previousGe === null ? null : previousGe * (1 / 3);

    const truncSource = currentThird !== null && previousThird !== null
        ? currentThird + previousThird
        : currentGe;
    const trunc = truncateDecimal(truncSource, 3);
    const finalGe = previousThird !== null ? getGradeEquivalentFromValue(trunc) : currentGe;
    const adjectival = getAdjectivalEquivalent(finalGe);

    return {
        entries: resolvedEntries,
        segments,
        hasAssessments,
        hasTentative,
        twPercent,
        currentGe,
        currentThird,
        previousGe,
        previousThird,
        trunc,
        finalGe,
        adjectival,
    };
}

export function buildQuarterResult(grouped, scoresByLearner = {}, learnerId = null, previousQuarterGe = null, tentativesByLearner = {}) {
    return buildQuarterResultFromEntries(
        buildSegmentEntries(grouped, scoresByLearner, learnerId, tentativesByLearner),
        previousQuarterGe,
    );
}

export function buildSummaryRows({ assessments = [], learners = [], previousQuarterAssessments = [] } = {}) {
    const grouped = groupAssessmentsBySegment(assessments);
    const previousGrouped = groupAssessmentsBySegment(previousQuarterAssessments);
    const allAssessments = [
        ...(assessments ?? []),
        ...(previousQuarterAssessments ?? []),
    ];
    const scoresByLearner = buildScoresByLearner(allAssessments);
    const tentativesByLearner = buildTentativesByLearner(allAssessments);

    const rows = (learners ?? []).map((learner) => {
        const learnerId = learner?.id ?? learner?.learner_id ?? null;

        const previousResult = hasSegmentAssessments(previousGrouped)
            ? buildQuarterResult(previousGrouped, scoresByLearner, learnerId, null, tentativesByLearner)
            : null;

        const result = buildQuarterResult(
            grouped,
            scoresByLearner,
            learnerId,
            previousResult?.currentGe ?? null,
            tentativesByLearner,
        );

        return {
            learner,
            learnerId,
            scores: scoresByLearner[learnerId] ?? {},
            tentatives: tentativesByLearner[learnerId] ?? {},
            entries: result.entries,
            segments: result.segments,
            summary: {
                twPercent: result.twPercent,
                currentGe: result.currentGe,
                currentThird: result.currentThird,
                previousGe: result.previousGe,
                previousThird: result.previousThird,
                trunc: result.trunc,
                finalGe: result.finalGe,
                adjectival: result.adjectival,
            },
        };
    });

    return {
        segments: grouped,
        rows,
    };
}
