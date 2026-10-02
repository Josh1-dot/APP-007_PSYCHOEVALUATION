<?php

namespace App\Services;

class FakeLlmProvider implements LlmProvider
{
    public function __construct(public PromptRegistry $prompts = new PromptRegistry) {}

    public function reply(string $intent, PatientAssessmentResult|PatientAssessmentConversationResult|PatientGuideData|QuestionnaireHelpData|PatientAppointmentResult|PatientPublishedResultData|PatientMemoryData|PatientRagResult|null $assessments = null): string
    {
        if ($assessments instanceof PatientAssessmentConversationResult) {
            return (new PatientAssessmentFormatter)->formatConversation($assessments);
        }
        if ($assessments instanceof PatientRagResult) {
            return (new PatientRagFormatter)->format($assessments);
        }
        if ($assessments instanceof PatientMemoryData) {
            return (new PatientMemoryFormatter($this->prompts))->format($intent, $assessments);
        }
        if ($assessments instanceof PatientPublishedResultData) {
            return (new PatientPublishedResultFormatter)->format($assessments);
        }
        if ($assessments instanceof PatientAppointmentResult) {
            return (new PatientAppointmentFormatter)->format($assessments);
        }
        if ($assessments instanceof PatientGuideData || $assessments instanceof QuestionnaireHelpData) {
            return (new PatientHelpFormatter)->format($assessments);
        }

        return $assessments === null ? $this->prompts->response($intent) : (new PatientAssessmentFormatter)->format($assessments);
    }
}
