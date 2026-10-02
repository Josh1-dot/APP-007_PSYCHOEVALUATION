<?php

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiMessage> */
class AiMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'tenant_id' => fn (array $attributes): int => AiConversation::findOrFail($attributes['ai_conversation_id'])->tenant_id,
            'role' => 'patient',
            'content' => 'Bonjour',
        ];
    }
}
