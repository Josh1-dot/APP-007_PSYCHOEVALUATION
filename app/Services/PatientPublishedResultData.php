<?php

namespace App\Services;

final readonly class PatientPublishedResultData
{
    /** @param array<string|int, int|float> $scores */
    public function __construct(public bool $available = false, public string $assessmentUuid = '', public string $questionnaireName = '', public int $definitionVersion = 0, public string $publishedAt = '', public array $scores = [], public int|float|null $maximum = null, public string $method = '', public string $publishedText = '', public bool $isExcerpt = false, public string $url = '', public bool $isDemo = false) {}
}
