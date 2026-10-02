<?php

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiConversation> */
class AiConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): int => Tenant::create(['name' => 'Cabinet fictif'])->id,
            'user_id' => fn (array $attributes): int => User::factory()->create(['tenant_id' => $attributes['tenant_id'], 'role' => 'patient'])->id,
            'client_id' => fn (array $attributes): int => Client::create(['tenant_id' => $attributes['tenant_id'], 'user_id' => $attributes['user_id'], 'first_name' => 'Patient', 'last_name' => 'Fictif', 'email' => fake()->unique()->safeEmail()])->id,
            'consent_version' => config('patientai.consent_version'),
            'consent_text' => config('patientai.consent_text'),
            'consented_at' => now(),
        ];
    }
}
