<?php

namespace Database\Factories;

use App\Models\PatientRagDocument;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientRagDocument> */
class PatientRagDocumentFactory extends Factory
{
    public function definition(): array
    {
        return ['tenant_id' => fn (): int => Tenant::create(['name' => 'Cabinet RAG fictif'])->id, 'document_key' => 'document-'.fake()->unique()->numberBetween(1, 999999), 'title' => 'Information patient', 'version' => 'demo-v1', 'source' => 'fixture-locale', 'source_label' => 'Documentation patient', 'audience' => 'PATIENT_PUBLIC', 'content' => 'La confidentialité des données patient est contrôlée par le cabinet.'];
    }
}
