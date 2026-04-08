from pathlib import Path
path = Path('resources/js/Components/Assessments/AssessmentSummary.vue')
text = path.read_text(encoding='utf-8')
if text.startswith('ï»¿'):
    text = text[3:]
    path.write_text(text, encoding='utf-8')
else:
    print('no prefix to remove')
