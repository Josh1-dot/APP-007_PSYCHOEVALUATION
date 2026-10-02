<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\Client;
use Illuminate\Support\Facades\DB;

class PatientAiLifecycle
{
    public function expired(AiConversation $conversation): bool
    {
        return $conversation->updated_at->lt(now()->subDays(max(1, (int) config('patientai.retention_days'))));
    }

    public function erase(Client $client): void
    {
        AiConversation::where('tenant_id', $client->tenant_id)->where('client_id', $client->id)->delete();
    }

    /**
     * @return list<array{uuid: string, status: string, consent_version: string, consent_text: string, consented_at: string, memory_enabled: bool, memory_consent_version: ?string, memory_consented_at: ?string, memory: mixed, messages: list<array{role: string, content: string, created_at: mixed}>}>
     */
    public function export(Client $client): array
    {
        return AiConversation::where('tenant_id', $client->tenant_id)->where('client_id', $client->id)
            ->with('messages')->orderBy('id')->get()->map(fn (AiConversation $conversation): array => [
                'uuid' => $conversation->uuid,
                'status' => $conversation->status,
                'consent_version' => $conversation->consent_version,
                'consent_text' => $conversation->consent_text,
                'consented_at' => $conversation->consented_at->toIso8601String(),
                'memory_enabled' => $conversation->memory_enabled,
                'memory_consent_version' => $conversation->memory_consent_version,
                'memory_consented_at' => $conversation->memory_consented_at?->toIso8601String(),
                'memory' => $conversation->memory,
                'messages' => $conversation->messages->map(fn ($message): array => $message->only(['role', 'content', 'created_at']))->all(),
            ])->all();
    }

    public function purgeTenant(int $tenantId): int
    {
        return DB::transaction(function () use ($tenantId): int {
            $heldClients = Client::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('retention_hold', true)->pluck('id');

            return AiConversation::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->whereNotIn('client_id', $heldClients)
                ->where('updated_at', '<', now()->subDays(max(1, (int) config('patientai.retention_days'))))->delete();
        });
    }
}
