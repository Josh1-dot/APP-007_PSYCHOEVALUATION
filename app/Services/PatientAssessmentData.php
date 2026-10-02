<?php

namespace App\Services;

final readonly class PatientAssessmentData
{
    public function __construct(public string $uuid, public string $questionnaireName, public string $status, public string $statusLabel, public string $url) {}
}
