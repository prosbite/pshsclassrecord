with open('resources/js/Components/Assessments/AssessmentSummary.vue', encoding='utf-8') as f:
    for line in f:
        if 'label = grade' in line:
            print(repr(line))
            break
