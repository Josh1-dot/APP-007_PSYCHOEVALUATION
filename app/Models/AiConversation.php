<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiConversation extends TenantModel
{
    use HasFactory;

    protected $hidden = ['memory', 'conversation_context'];

    protected static function booted(): void
    {
        parent::booted();
        static::creating(function (AiConversation $conversation): void {
            $conversation->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'memory_enabled' => 'boolean', 'memory_consented_at' => 'datetime', 'memory' => 'encrypted:array', 'conversation_context' => 'encrypted:array'];
    }
}
