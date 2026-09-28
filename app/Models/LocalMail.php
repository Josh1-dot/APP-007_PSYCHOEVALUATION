<?php

namespace App\Models;

class LocalMail extends TenantModel
{
    protected function casts(): array
    {
        return ['recipient' => 'encrypted', 'subject' => 'encrypted', 'body' => 'encrypted', 'expires_at' => 'datetime'];
    }
}
