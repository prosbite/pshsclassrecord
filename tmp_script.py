from pathlib import Path
path = Path('resources/js/Pages/Students/Dashboard.vue')
text = path.read_text()
start = text.index('<div v-for="assessment in props.quarterlyAssessments"')
end = text.index('        </div>\n    </StudentLayout>')
new_text = text[:start] + text[end:]
path.write_text(new_text)
