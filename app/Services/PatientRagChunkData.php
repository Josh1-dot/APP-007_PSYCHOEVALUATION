<?php

namespace App\Services;

final readonly class PatientRagChunkData
{
    public function __construct(public string $text, public PatientRagProvenance $provenance) {}
}
