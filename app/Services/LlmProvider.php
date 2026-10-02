<?php

namespace App\Services;

interface LlmProvider
{
    public function reply(string $intent, PatientAssessmentResult|PatientAssessmentConversationResult|PatientGuideData|QuestionnaireHelpData|PatientAppointmentResult|PatientPublishedResultData|PatientMemoryData|PatientRagResult|null $assessments = null): string;
}
