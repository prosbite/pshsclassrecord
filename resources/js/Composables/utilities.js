export function formatDate(value) {
    if (!value) {
        return 'â€”';
    }

    const date = new Date(value);
    if (Number.isNaN(date.valueOf())) {
        return 'â€”';
    }

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    });
}

export const TW_PERCENT_GRADE_EQUIVALENTS = [
    { min: 96, value: 1.0 },
    { min: 90, value: 1.25 },
    { min: 84, value: 1.5 },
    { min: 78, value: 1.75 },
    { min: 72, value: 2.0 },
    { min: 66, value: 2.25 },
    { min: 60, value: 2.5 },
    { min: 55, value: 2.75 },
    { min: 50, value: 3.0 },
    { min: 40, value: 4.0 },
    { min: 0, value: 5.0 },
];

export const GRADE_EQUIVALENT_THRESHOLDS = [
    { min: 4.501, value: 5.0 },
    { min: 3.501, value: 4.0 },
    { min: 2.876, value: 3.0 },
    { min: 2.626, value: 2.75 },
    { min: 2.376, value: 2.5 },
    { min: 2.126, value: 2.25 },
    { min: 1.876, value: 2.0 },
    { min: 1.626, value: 1.75 },
    { min: 1.376, value: 1.5 },
    { min: 1.126, value: 1.25 },
    { min: 1.0, value: 1.0 },
];

export const GRADE_EQUIVALENT_SCALE = GRADE_EQUIVALENT_THRESHOLDS.map((entry) => entry.value);

export const ADJECTIVAL_EQUIVALENTS = {
    1.0: 'Excellent',
    1.25: 'Very Satisfactory',
    1.5: 'Very Satisfactory',
    1.75: 'Good',
    2.0: 'Good',
    2.25: 'Satisfactory',
    2.5: 'Satisfactory',
    2.75: 'Fair',
    3.0: 'Fair',
    4.0: 'Failed on Condition',
    5.0: 'Failed',
};

const LOOKUP_EPSILON = 1e-10;

const normalizeNumber = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const numericValue = Number(value);
    return Number.isFinite(numericValue) ? numericValue : null;
};

const pickThresholdValue = (value, thresholds) => {
    const numericValue = normalizeNumber(value);
    if (numericValue === null) {
        return null;
    }

    const match = thresholds.find((entry) => numericValue + LOOKUP_EPSILON >= entry.min);
    return match ? match.value : null;
};

export function getGradeEquivalentFromPercent(percent) {
    return pickThresholdValue(percent, TW_PERCENT_GRADE_EQUIVALENTS);
}

export function getGradeEquivalentFromValue(value) {
    return pickThresholdValue(value, GRADE_EQUIVALENT_THRESHOLDS);
}

export function getAdjectivalEquivalent(gradeEquivalent) {
    const normalizedGradeEquivalent = getGradeEquivalentFromValue(gradeEquivalent);

    if (normalizedGradeEquivalent === null) {
        return 'â€”';
    }

    return ADJECTIVAL_EQUIVALENTS[normalizedGradeEquivalent] ?? 'â€”';
}

export function formatGradeEquivalent(value) {
    const numericValue = normalizeNumber(value);

    if (numericValue === null) {
        return 'â€”';
    }

    return numericValue.toFixed(2);
}

export function calculatePercentage(score, perfectScore) {
    const scoreValue = normalizeNumber(score);
    const perfectValue = normalizeNumber(perfectScore);

    if (scoreValue === null || perfectValue === null || perfectValue <= 0) {
        return null;
    }

    return (scoreValue / perfectValue) * 100;
}

export function calculateWeightedPercentage(score, perfectScore, weight = 0.25) {
    const percentage = calculatePercentage(score, perfectScore);

    if (percentage === null) {
        return null;
    }

    return percentage * weight;
}
