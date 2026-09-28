<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $guarded = ['id'];

    public function logoDataUri(): ?string
    {
        return $this->logo ? 'data:'.$this->logo_mime.';base64,'.$this->logo : null;
    }

    protected function casts(): array
    {
        return ['logo' => 'encrypted'];
    }
}
