<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Letter extends TenantModel
{
    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(Document::class);
    }

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }
}
