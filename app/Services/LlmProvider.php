<?php

namespace App\Services;

interface LlmProvider
{
    public function reply(string $intent, PatientAssessmentResult|PatientGuideData|QuestionnaireHelpData|PatientAppointmentResult|PatientPublishedResultData|null $assessments = null): string;
}
