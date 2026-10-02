<?php

namespace Database\Seeders;

use App\Services\PatientRagWorkflow;
use Illuminate\Database\Seeder;

class PatientRagDocumentSeeder extends Seeder
{
    public function run(): void
    {
        app(PatientRagWorkflow::class)->importGuide();
    }
}
