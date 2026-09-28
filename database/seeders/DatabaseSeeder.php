<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Aucune donnée ajoutée. Utilisez cabinet:install pour créer votre cabinet ou cabinet:demo pour une démonstration locale.');
    }
}
