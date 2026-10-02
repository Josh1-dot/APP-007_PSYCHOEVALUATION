<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class PatientRagChunk extends TenantModel
{
    use HasFactory;

    protected $hidden = ['content'];

    protected function casts(): array
    {
        return ['content' => 'encrypted', 'ordinal' => 'integer'];
    }
}
