<?php

namespace App\Models;

class ClinicalNote extends TenantModel
{
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }
}
