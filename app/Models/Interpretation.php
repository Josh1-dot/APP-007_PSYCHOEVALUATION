<?php

namespace App\Models;

class Interpretation extends TenantModel
{
    protected $hidden = ['ai_generations'];

    protected function casts(): array
    {
        return ['ai_generations' => 'encrypted:array', 'draft' => 'encrypted', 'published_content' => 'encrypted', 'input_snapshot' => 'encrypted:array', 'published_at' => 'datetime'];
    }
}
