from pathlib import Path
path = Path('resources/js/Components/Assessments/AssessmentSummary.vue')
for i, line in enumerate(path.read_text().splitlines(), start=1):
    if 'const assessmentTypes' in line:
        print('assessmentTypes line', i)
    if 'const summaryRows' in line:
        print('summaryRows line', i)
    if '<table' in line and 'divide-y' in line:
        print('table line', i)
    if 'No quarterly breakdown' in line:
        print('no rows line', i)
