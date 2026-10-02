<?php

namespace App\Services;

interface LlmProvider
{
    public function reply(string $intent, ?PatientAssessmentResult $assessments = null): string;
}
