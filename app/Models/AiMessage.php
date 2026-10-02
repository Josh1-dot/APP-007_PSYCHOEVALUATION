<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AiMessage extends TenantModel
{
    use HasFactory;

    protected function casts(): array
    {
        return ['content' => 'encrypted'];
    }
}
