<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyRequest extends TenantModel
{
    protected function casts(): array
    {
        return ['details' => 'encrypted', 'response' => 'encrypted', 'resolved_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }
}
