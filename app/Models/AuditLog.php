<?php

namespace App\Models;

class AuditLog extends TenantModel
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [];
    }
}
