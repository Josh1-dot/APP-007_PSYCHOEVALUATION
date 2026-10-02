<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PatientMemoryService
{
    public const CONSENT_VERSION = 'patientai-memory-v0.8';

    public const CONSENT_TEXT = 'J’autorise la réutilisation de ma préférence de présentation (standard ou concise) entre mes conversations avec mémoire. Un nouveau choix remplace la préférence précédente. Aucun fait clinique ni texte libre n’est mémorisé. Cette préférence est chiffrée, exportable, effaçable et soumise au même délai que sa conversation source. Sous suspension de conservation, elle est désactivée sans destruction.';

    public function __construct(public PatientContextFactory $contexts) {}

    /** @return array{response_style: string}|null */
    public function preference(?string $style): ?array
    {
        if (! in_array($style, ['standard', 'concise'], true) || (int) config('patientai.memory.max_items') < 1) {
            return null;
        }
        $value = ['response_style' => $style];

        return strlen(json_encode($value)) <= min(128, max(0, (int) config('patientai.memory.max_bytes'))) ? $value : null;
    }

    private function owned(PatientContext $context): Builder
    {
        return AiConversation::where('tenant_id', $context->tenantId)->where('user_id', $context->userId)->where('client_id', $context->clientId);
    }

    public function context(AiConversation $target): PatientMemoryData
    {
        abort_unless(config('patientai.enabled'), 404);
        $context = $this->contexts->fromAuthenticatedUser();
        $target = $this->owned($context)->whereKey($target->id)->firstOrFail(['id', 'status', 'updated_at', 'memory_enabled', 'memory_consent_version', 'memory_consented_at']);
        abort_unless($target->status === 'active', 409);
        abort_if(app(PatientAiLifecycle::class)->expired($target), 410);
        if (! $target->memory_enabled || $target->memory_consent_version !== self::CONSENT_VERSION || ! $target->memory_consented_at) {
            return new PatientMemoryData;
        }
        $sources = $this->owned($context)->where('status', 'active')->where('memory_enabled', true)
            ->where('memory_consent_version', self::CONSENT_VERSION)->whereNotNull('memory_consented_at')->whereNotNull('memory')
            ->where('updated_at', '>=', now()->subDays(max(1, (int) config('patientai.retention_days'))))
            ->orderByDesc('updated_at')->orderByDesc('id')->limit(min(20, max(1, (int) config('patientai.memory.max_sources'))))
            ->get(['id', 'memory']);
        foreach ($sources as $source) {
            $memory = $source->memory;
            if (! is_array($memory) || array_keys($memory) !== ['response_style'] || ! is_string($memory['response_style'])) {
                continue;
            }
            $preference = $this->preference($memory['response_style']);
            if ($preference !== null) {
                return new PatientMemoryData($preference['response_style']);
            }
        }

        return new PatientMemoryData;
    }

    public function replacePrevious(AiConversation $target): void
    {
        $context = $this->contexts->fromAuthenticatedUser(lockClient: true);
        abort_unless($context->owns($target->tenant_id, $target->user_id, $target->client_id), 404);
        if ($target->memory === null) {
            return;
        }
        $client = Client::whereKey($context->clientId)->where('tenant_id', $context->tenantId)->firstOrFail(['id', 'retention_hold']);
        $values = $client->retention_hold ? ['memory_enabled' => false] : ['memory' => null];
        $this->owned($context)->whereKeyNot($target->id)->whereNotNull('memory')->toBase()->update($values);
    }

    public function clear(): bool
    {
        abort_unless(config('patientai.enabled'), 404);

        return DB::transaction(function (): bool {
            $context = $this->contexts->fromAuthenticatedUser(lockClient: true);
            $client = Client::where('tenant_id', $context->tenantId)->whereKey($context->clientId)->firstOrFail(['id', 'retention_hold']);
            $values = ['memory_enabled' => false];
            if (! $client->retention_hold) {
                $values['memory'] = null;
            }
            $this->owned($context)->toBase()->update($values);
            Access::audit($client->retention_hold ? 'patientai.memoire_desactivee' : 'patientai.memoire_effacee', $client);

            return ! $client->retention_hold;
        });
    }
}
