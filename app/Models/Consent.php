<?php

namespace App\Models;

class Consent extends TenantModel
{
    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
