<?php

namespace App\Services;

final readonly class PatientAssessmentConversationData
{
    public function __construct(public string $questionnaireName, public string $statusLabel, public string $url) {}
}
