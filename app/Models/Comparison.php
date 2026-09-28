<?php

namespace App\Models;

class Comparison extends TenantModel
{
    protected function casts(): array
    {
        return ['snapshot' => 'encrypted:array', 'analysis' => 'encrypted'];
    }
}
