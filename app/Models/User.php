<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'tenant_id', 'role', 'organization_id', 'active', 'auth_version', 'email_verified_at'];

    protected $attributes = ['active' => true, 'role' => 'patient', 'auth_version' => 0];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime', 'active' => 'boolean', 'auth_version' => 'integer'];
    }

    public function isProfessional(): bool
    {
        return in_array($this->role, ['admin', 'psychologue', 'conseiller']);
    }

    public function canPublish(): bool
    {
        return in_array($this->role, ['admin', 'psychologue']);
    }

    public function client()
    {
        return $this->hasOne(Client::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
