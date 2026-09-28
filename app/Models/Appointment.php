<?php

namespace App\Models;

class Appointment extends TenantModel
{
    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    protected function casts(): array
    {
        return ['starts_at' => 'datetime'];
    }
}
