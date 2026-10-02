<?php

namespace Database\Factories;

use App\Models\PatientRagChunk;
use App\Models\PatientRagDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientRagChunk> */
class PatientRagChunkFactory extends Factory
{
    public function definition(): array
    {
        $text = 'Extrait de démonstration non indexé.';

        return ['patient_rag_document_id' => PatientRagDocument::factory(), 'tenant_id' => fn (array $attributes): int => PatientRagDocument::findOrFail($attributes['patient_rag_document_id'])->tenant_id, 'ordinal' => 1, 'content' => $text, 'checksum' => hash('sha256', $text)];
    }
}
