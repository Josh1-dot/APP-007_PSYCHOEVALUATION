<?php

namespace App\Models;

class Document extends TenantModel
{
    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    protected function casts(): array
    {
        return ['shared' => 'boolean'];
    }
}
