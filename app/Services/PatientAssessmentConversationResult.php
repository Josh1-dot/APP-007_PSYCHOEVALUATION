<?php

namespace App\Services;

final readonly class PatientAssessmentConversationResult
{
    /** @param list<PatientAssessmentConversationData> $items */
    public function __construct(public array $items = [], public bool $hasMore = false, public bool $available = true) {}
}
