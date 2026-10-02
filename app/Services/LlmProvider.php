<?php

namespace App\Services;

interface LlmProvider
{
    public function reply(string $intent, PatientAssessmentResult|PatientGuideData|QuestionnaireHelpData|PatientAppointmentResult|null $assessments = null): string;
}
