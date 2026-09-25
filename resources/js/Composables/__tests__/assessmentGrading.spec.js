import { describe, expect, it } from 'vitest';
import { getAdjectivalEquivalent } from '../utilities.js';
import {
    buildQuarterResult,
    buildQuarterResultFromEntries,
    buildSegmentEntries,
    buildSummaryRows,
    buildTentativesByLearner,
    detailEntriesForSegment,
    groupAssessmentsBySegment,
    hasSegmentAssessments,
    mapTypeCodeToSegment,
    metricsForEntries,
    resolveTentative,
    truncateDecimal,
} from '../assessmentGrading.js';

const learnerId = 7;

const makeAssessment = ({ id, code, date, perfect = 100, score = null, title = null, name = null }) => ({
    id,
    title,
    assessment_date: date,
    perfect_score: perfect,
    assessmentType: { code, name },
    learners: score === null ? [] : [{ id: learnerId, pivot: { score } }],
});

const scoresFor = (assessments) => ({
    [learnerId]: Object.fromEntries(assessments.map((a) => [a.id, a.learners[0]?.pivot?.score ?? null])),
});

describe('mapTypeCodeToSegment', () => {
    it('maps canonical codes', () => {
        expect(mapTypeCodeToSegment('long_test')).toBe('long_test');
        expect(mapTypeCodeToSegment('alternative')).toBe('aa');
        expect(mapTypeCodeToSegment('formative')).toBe('fa');
    });

    it('falls back to type names for legacy rows', () => {
        expect(mapTypeCodeToSegment(null, 'Long Test')).toBe('long_test');
        expect(mapTypeCodeToSegment(null, 'Alternative Assessment')).toBe('aa');
        expect(mapTypeCodeToSegment(null, 'Formative Assessment')).toBe('fa');
        expect(mapTypeCodeToSegment(null, 'Something Else')).toBeNull();
    });
});

describe('groupAssessmentsBySegment', () => {
    it('orders long tests by date then id and ignores extras', () => {
        const grouped = groupAssessmentsBySegment([
            makeAssessment({ id: 3, code: 'long_test', date: '2026-03-01', title: 'LT3' }),
            makeAssessment({ id: 1, code: 'long_test', date: '2026-01-01', title: 'LT1' }),
            makeAssessment({ id: 2, code: 'long_test', date: '2026-02-01', title: 'LT2' }),
        ]);

        expect(grouped.lt1.id).toBe(1);
        expect(grouped.lt2.id).toBe(2);
    });

    it('breaks ties on id when dates match', () => {
        const grouped = groupAssessmentsBySegment([
            makeAssessment({ id: 9, code: 'long_test', date: '2026-01-01' }),
            makeAssessment({ id: 4, code: 'long_test', date: '2026-01-01' }),
        ]);

        expect(grouped.lt1.id).toBe(4);
        expect(grouped.lt2.id).toBe(9);
    });

    it('buckets aa and fa and keeps them sorted', () => {
        const grouped = groupAssessmentsBySegment([
            makeAssessment({ id: 5, code: 'formative', date: '2026-02-01' }),
            makeAssessment({ id: 6, code: 'formative', date: '2026-01-01' }),
            makeAssessment({ id: 7, code: 'alternative', date: '2026-01-15' }),
        ]);

        expect(grouped.aa.map((a) => a.id)).toEqual([7]);
        expect(grouped.fa.map((a) => a.id)).toEqual([6, 5]);
        expect(hasSegmentAssessments(grouped)).toBe(true);
    });

    it('returns an empty grouping when nothing matches', () => {
        const grouped = groupAssessmentsBySegment([]);
        expect(hasSegmentAssessments(grouped)).toBe(false);
        expect(grouped.lt1).toBeNull();
        expect(grouped.lt2).toBeNull();
    });
});

describe('buildSegmentEntries', () => {
    it('emits the legacy entry shape with stable keys and perfect scores', () => {
        const lt = makeAssessment({ id: 11, code: 'long_test', date: '2026-01-01', perfect: 50, score: 40, title: 'LT One' });
        const grouped = groupAssessmentsBySegment([lt]);
        const entries = buildSegmentEntries(grouped, scoresFor([lt]), learnerId);

        expect(entries.lt1).toHaveLength(1);
        expect(entries.lt1[0]).toMatchObject({
            key: 'lt1-11',
            fieldType: 'score',
            value: 40,
            perfectScore: 50,
            assessmentId: 11,
            segment: 'lt1',
        });
        expect(entries.lt2).toEqual([]);
    });

    it('marks entries as tentative from the learner tentative map', () => {
        const lt = makeAssessment({ id: 21, code: 'long_test', date: '2026-01-01', perfect: 50, score: 40, title: 'LT One' });
        const grouped = groupAssessmentsBySegment([lt]);
        const entries = buildSegmentEntries(grouped, scoresFor([lt]), learnerId, { [learnerId]: { 21: true } });

        expect(entries.lt1[0].tentative).toBe(true);
    });

    it('defaults entries to non-tentative when no map is supplied', () => {
        const fa = makeAssessment({ id: 22, code: 'formative', date: '2026-01-01', perfect: 20, score: 15 });
        const grouped = groupAssessmentsBySegment([fa]);
        const entries = buildSegmentEntries(grouped, scoresFor([fa]), learnerId);

        expect(entries.fa[0].tentative).toBe(false);
    });

    it('treats a missing learner score as null but keeps the perfect score', () => {
        const fa = makeAssessment({ id: 12, code: 'formative', date: '2026-01-01', perfect: 20, score: null, title: 'Quiz' });
        const grouped = groupAssessmentsBySegment([fa]);
        const entries = buildSegmentEntries(grouped, {}, learnerId);

        expect(entries.fa[0].value).toBeNull();
        expect(entries.fa[0].perfectScore).toBe(20);
    });
});

describe('buildTentativesByLearner', () => {
    it('maps learner and assessment ids to a boolean tentative flag', () => {
        const assessments = [
            { id: 1, learners: [{ id: 7, pivot: { score: 10, tentative: 1 } }] },
            { id: 2, learners: [{ id: 7, pivot: { score: 20, tentative: 0 } }] },
        ];

        const map = buildTentativesByLearner(assessments);

        expect(map[7][1]).toBe(true);
        expect(map[7][2]).toBe(false);
    });

    it('treats a missing pivot flag as false', () => {
        const map = buildTentativesByLearner([{ id: 3, learners: [{ id: 7, pivot: { score: 5 } }] }]);

        expect(map[7][3]).toBe(false);
    });
});

describe('resolveTentative', () => {
    it('prefers the learner map and falls back to a top-level flag', () => {
        expect(resolveTentative({ id: 5 }, { 7: { 5: true } }, 7)).toBe(true);
        expect(resolveTentative({ id: 5, tentative: true }, {}, 7)).toBe(true);
        expect(resolveTentative({ id: 5 }, {}, 7)).toBe(false);
    });
});

describe('metricsForEntries', () => {
    it('sums scores and perfect scores and applies the 0.25 weight', () => {
        const entries = [
            { fieldType: 'score', value: 80, perfectScore: 100 },
            { fieldType: 'score', value: 20, perfectScore: 50 },
        ];

        const metrics = metricsForEntries(entries);
        expect(metrics.score).toBe(100);
        expect(metrics.perfectScore).toBe(150);
        expect(metrics.percentage).toBeCloseTo(66.6667, 3);
        expect(metrics.weighted).toBeCloseTo(16.6667, 3);
    });

    it('returns null percentage and weighted when perfect scores are missing', () => {
        const metrics = metricsForEntries([{ fieldType: 'score', value: 10, perfectScore: 0 }]);
        expect(metrics.percentage).toBeNull();
        expect(metrics.weighted).toBeNull();
    });

    it('still counts tentative entries', () => {
        const metrics = metricsForEntries([
            { fieldType: 'score', value: 80, perfectScore: 100, tentative: true },
            { fieldType: 'score', value: 20, perfectScore: 50, tentative: false },
        ]);

        expect(metrics.score).toBe(100);
        expect(metrics.perfectScore).toBe(150);
    });
});

describe('truncateDecimal', () => {
    it('truncates rather than rounds', () => {
        expect(truncateDecimal(1.9999, 3)).toBe(1.999);
        expect(truncateDecimal(null)).toBeNull();
    });
});

describe('buildQuarterResult', () => {
    const perfectQuarter = () => [
        makeAssessment({ id: 1, code: 'long_test', date: '2026-01-01', score: 100 }),
        makeAssessment({ id: 2, code: 'long_test', date: '2026-01-02', score: 100 }),
        makeAssessment({ id: 3, code: 'alternative', date: '2026-01-03', score: 100 }),
        makeAssessment({ id: 4, code: 'formative', date: '2026-01-04', score: 100 }),
    ];

    const failingQuarter = () => [
        makeAssessment({ id: 5, code: 'long_test', date: '2025-10-01', score: 0 }),
        makeAssessment({ id: 6, code: 'alternative', date: '2025-10-02', score: 0 }),
    ];

    it('computes TW%, current GE and falls back to current GE for Q1', () => {
        const assessments = perfectQuarter();
        const grouped = groupAssessmentsBySegment(assessments);
        const result = buildQuarterResult(grouped, scoresFor(assessments), learnerId, null);

        expect(result.twPercent).toBeCloseTo(100, 5);
        expect(result.currentGe).toBe(1.0);
        expect(result.previousGe).toBeNull();
        expect(result.previousThird).toBeNull();
        expect(result.finalGe).toBe(1.0);
        expect(result.adjectival).toBe('Excellent');
    });

    it('blends the previous quarter GE using the 2/3 and 1/3 chain', () => {
        const assessments = perfectQuarter();
        const grouped = groupAssessmentsBySegment(assessments);
        const result = buildQuarterResult(grouped, scoresFor(assessments), learnerId, 5.0);

        expect(result.currentGe).toBe(1.0);
        expect(result.currentThird).toBeCloseTo(2 / 3, 6);
        expect(result.previousThird).toBeCloseTo(5 / 3, 6);
        expect(result.trunc).toBeCloseTo(2.333, 3);
        expect(result.finalGe).toBe(2.25);
    });

    it('treats an empty quarter as no data instead of grade 5', () => {
        const grouped = groupAssessmentsBySegment([]);
        const result = buildQuarterResult(grouped, {}, learnerId, 1.25);

        expect(result.hasAssessments).toBe(false);
        expect(result.twPercent).toBeNull();
        expect(result.currentGe).toBeNull();
        expect(result.finalGe).toBeNull();
        expect(result.adjectival).toBe(getAdjectivalEquivalent(null));
    });

    it('contributes 0 to TW% for missing perfect scores', () => {
        const assessments = [
            makeAssessment({ id: 1, code: 'long_test', date: '2026-01-01', perfect: 0, score: 50 }),
            makeAssessment({ id: 2, code: 'alternative', date: '2026-01-02', perfect: 100, score: 90 }),
        ];
        const grouped = groupAssessmentsBySegment(assessments);
        const result = buildQuarterResult(grouped, scoresFor(assessments), learnerId, null);

        expect(result.segments.lt1.weighted).toBeNull();
        expect(result.segments.aa.weighted).toBeCloseTo(22.5, 5);
        expect(result.twPercent).toBeCloseTo(22.5, 5);
    });

    it('uses the low previous quarter GE to raise the final grade', () => {
        const failing = failingQuarter();
        const failingGrouped = groupAssessmentsBySegment(failing);
        const previous = buildQuarterResult(failingGrouped, scoresFor(failing), learnerId, null);
        expect(previous.currentGe).toBe(5.0);

        const current = perfectQuarter();
        const result = buildQuarterResult(
            groupAssessmentsBySegment(current),
            scoresFor(current),
            learnerId,
            previous.currentGe,
        );

        expect(result.finalGe).toBe(2.25);
    });

    it('flags hasTentative without changing the grade math', () => {
        const assessments = perfectQuarter();
        const grouped = groupAssessmentsBySegment(assessments);
        const scores = scoresFor(assessments);
        const plain = buildQuarterResult(grouped, scores, learnerId, null);
        const flagged = buildQuarterResult(grouped, scores, learnerId, null, { [learnerId]: { 1: true } });

        expect(flagged.hasTentative).toBe(true);
        expect(plain.hasTentative).toBe(false);
        expect(flagged.twPercent).toBeCloseTo(plain.twPercent, 6);
        expect(flagged.finalGe).toBe(plain.finalGe);
    });
});

describe('buildQuarterResultFromEntries', () => {
    it('computes the chain from provided entry values', () => {
        const entries = {
            lt1: [{ fieldType: 'score', value: 100, perfectScore: 100 }],
            lt2: [{ fieldType: 'score', value: 100, perfectScore: 100 }],
            aa: [{ fieldType: 'score', value: 100, perfectScore: 100 }],
            fa: [{ fieldType: 'score', value: 100, perfectScore: 100 }],
        };

        const result = buildQuarterResultFromEntries(entries, 5.0);

        expect(result.currentGe).toBe(1.0);
        expect(result.finalGe).toBe(2.25);
    });

    it('returns nulls when there are no entries', () => {
        const result = buildQuarterResultFromEntries({ lt1: [], lt2: [], aa: [], fa: [] }, null);

        expect(result.hasAssessments).toBe(false);
        expect(result.currentGe).toBeNull();
    });

    it('reports hasTentative from tentative entries', () => {
        const entries = {
            lt1: [{ fieldType: 'score', value: 100, perfectScore: 100, tentative: true }],
            lt2: [],
            aa: [],
            fa: [],
        };

        const result = buildQuarterResultFromEntries(entries, null);

        expect(result.hasTentative).toBe(true);
    });
});

describe('buildSummaryRows', () => {
    it('builds one row per learner with per-assessment scores and summary chain', () => {
        const learner = { id: 1, first_name: 'Ada', last_name: 'Lovelace', middle_name: 'Byron' };
        const assessments = [
            {
                id: 100,
                title: 'LT1',
                assessment_date: '2026-01-01',
                perfect_score: 100,
                assessmentType: { code: 'long_test' },
                learners: [{ id: 1, pivot: { score: 90 } }],
            },
            {
                id: 101,
                title: 'LT2',
                assessment_date: '2026-02-01',
                perfect_score: 100,
                assessmentType: { code: 'long_test' },
                learners: [{ id: 1, pivot: { score: 80 } }],
            },
        ];

        const { rows, segments } = buildSummaryRows({ assessments, learners: [learner], previousQuarterAssessments: [] });

        expect(segments.lt1.id).toBe(100);
        expect(segments.lt2.id).toBe(101);
        expect(rows).toHaveLength(1);
        expect(rows[0].scores[100]).toBe(90);
        expect(rows[0].scores[101]).toBe(80);
        expect(rows[0].summary.currentGe).toBe(4.0);
        expect(rows[0].summary.finalGe).toBe(4.0);
    });

    it('chains the previous quarter GE into the summary rows', () => {
        const learner = { id: 1 };
        const score = (id, code, date, value) => ({
            id,
            assessment_date: date,
            perfect_score: 100,
            assessmentType: { code },
            learners: [{ id: 1, pivot: { score: value } }],
        });

        const current = [
            score(1, 'long_test', '2026-01-01', 100),
            score(2, 'long_test', '2026-01-02', 100),
            score(3, 'alternative', '2026-01-03', 100),
            score(4, 'formative', '2026-01-04', 100),
        ];
        const previous = [score(10, 'long_test', '2025-10-01', 0)];

        const { rows } = buildSummaryRows({
            assessments: current,
            learners: [learner],
            previousQuarterAssessments: previous,
        });

        expect(rows[0].summary.currentGe).toBe(1.0);
        expect(rows[0].summary.previousGe).toBe(5.0);
        expect(rows[0].summary.finalGe).toBe(2.25);
    });

    it('exposes a tentative map keyed by assessment id on each row', () => {
        const learner = { id: 1 };
        const assessments = [
            {
                id: 100,
                title: 'LT1',
                assessment_date: '2026-01-01',
                perfect_score: 100,
                assessmentType: { code: 'long_test' },
                learners: [{ id: 1, pivot: { score: 90, tentative: true } }],
            },
            {
                id: 101,
                title: 'AA1',
                assessment_date: '2026-01-02',
                perfect_score: 100,
                assessmentType: { code: 'alternative' },
                learners: [{ id: 1, pivot: { score: 70, tentative: false } }],
            },
        ];

        const { rows } = buildSummaryRows({ assessments, learners: [learner], previousQuarterAssessments: [] });

        expect(rows[0].tentatives[100]).toBe(true);
        expect(rows[0].tentatives[101]).toBe(false);
        expect(rows[0].scores[100]).toBe(90);
    });
});

describe('detailEntriesForSegment', () => {
    it('drops two-thirds and duplicate weighted entries for fa only', () => {
        const entries = [
            { label: 'FA1', fieldType: 'score' },
            { label: 'W1', fieldType: 'weighted' },
            { label: 'W2', fieldType: 'weighted' },
            { label: 'Two-Thirds', fieldType: 'weighted' },
            { label: 'FA2', fieldType: 'score' },
        ];

        expect(detailEntriesForSegment(entries, 'aa')).toHaveLength(5);
        expect(detailEntriesForSegment(entries, 'fa')).toHaveLength(3);
    });
});
