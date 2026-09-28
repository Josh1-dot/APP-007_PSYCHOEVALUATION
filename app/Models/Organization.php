<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends TenantModel
{
    use SoftDeletes;

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    protected function casts(): array
    {
        return [];
    }
}
