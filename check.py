from pathlib import Path
text = Path('resources/js/Components/Assessments/AssessmentSummary.vue').read_text(encoding='utf-8')
print(repr(text[:3]))
