<?php

namespace App\Models;

class Interpretation extends TenantModel
{
    protected function casts(): array
    {
        return ['draft' => 'encrypted', 'published_content' => 'encrypted', 'input_snapshot' => 'encrypted:array', 'published_at' => 'datetime'];
    }
}
