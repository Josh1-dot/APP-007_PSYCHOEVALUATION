<?php

namespace App\Services;

class FakeLlmProvider implements LlmProvider
{
    public function __construct(public PromptRegistry $prompts = new PromptRegistry) {}

    public function reply(string $intent, PatientAssessmentResult|PatientGuideData|QuestionnaireHelpData|PatientAppointmentResult|null $assessments = null): string
    {
        if ($assessments instanceof PatientAppointmentResult) {
            return (new PatientAppointmentFormatter)->format($assessments);
        }
        if ($assessments instanceof PatientGuideData || $assessments instanceof QuestionnaireHelpData) {
            return (new PatientHelpFormatter)->format($assessments);
        }

        return $assessments === null ? $this->prompts->response($intent) : (new PatientAssessmentFormatter)->format($assessments);
    }
}
