<?php

namespace App\Models;

class Message extends TenantModel
{
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'read_at' => 'datetime'];
    }
}
