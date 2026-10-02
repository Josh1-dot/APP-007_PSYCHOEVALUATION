<?php

namespace App\Services;

interface LlmProvider
{
    public function reply(string $intent, PatientAssessmentResult|PatientGuideData|QuestionnaireHelpData|null $assessments = null): string;
}
