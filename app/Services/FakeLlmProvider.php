<?php

namespace App\Services;

class FakeLlmProvider implements LlmProvider
{
    public function __construct(public PromptRegistry $prompts = new PromptRegistry) {}

    public function reply(string $intent, ?PatientAssessmentResult $assessments = null): string
    {
        return $assessments === null ? $this->prompts->response($intent) : (new PatientAssessmentFormatter)->format($assessments);
    }
}
