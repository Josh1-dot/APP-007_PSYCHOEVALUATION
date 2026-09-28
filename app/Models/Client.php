<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends TenantModel
{
    use SoftDeletes;

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    public function consents()
    {
        return $this->hasMany(Consent::class);
    }

    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function hasConsent(): bool
    {
        return $this->consents()->where('version', config('psycho.consent_version'))->whereNull('revoked_at')->exists();
    }

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'reason' => 'encrypted', 'retention_note' => 'encrypted', 'last_activity_at' => 'datetime', 'anonymized_at' => 'datetime', 'retention_hold' => 'boolean'];
    }
}
