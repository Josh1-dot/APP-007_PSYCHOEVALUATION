<?php

namespace App\Services;

final readonly class PatientAssessmentResult
{
    /** @param list<PatientAssessmentData> $items */
    public function __construct(public array $items = [], public bool $hasMore = false, public bool $available = true) {}
}
