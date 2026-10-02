<?php

namespace App\Services;

final readonly class PatientRagResult
{
    /** @param list<PatientRagChunkData> $documents */
    public function __construct(public array $documents = []) {}
}
