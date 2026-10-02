<?php

namespace App\Services;

final readonly class PatientRagProvenance
{
    public function __construct(public string $documentKey, public string $title, public string $version, public string $sourceLabel, public int $chunk) {}
}
