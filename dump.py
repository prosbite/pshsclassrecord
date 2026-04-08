from pathlib import Path
data = Path('resources/js/Components/Assessments/AssessmentSummary.vue').read_bytes()
print(list(data[:5]))
print(data[:5])
