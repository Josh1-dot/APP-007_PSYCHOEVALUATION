<?php

namespace App\Services;

final readonly class PatientGuideData
{
    /** @param list<string> $provenance @param list<string> $links */
    public function __construct(public string $topic, public string $version = '', public string $source = '', public array $provenance = [], public string $text = '', public array $links = [], public bool $available = true) {}
}
