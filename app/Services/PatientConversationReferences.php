<?php

namespace App\Services;

use App\Models\AiConversation;
use Illuminate\Support\Str;

class PatientConversationReferences
{
    private const VERSION = 1;

    private const ASSESSMENT_SOURCES = ['assessments_list', 'assessment_status', 'questionnaire_help', 'published_result'];

    private const APPOINTMENT_SOURCES = ['appointment_next', 'appointments_list'];

    public function assessmentUuid(AiConversation $conversation, PatientContext $context): ?string
    {
        $reference = $this->state($conversation, $context)['assessment'] ?? null;

        return is_array($reference) && in_array($reference['source'] ?? null, self::ASSESSMENT_SOURCES, true)
            && Str::isUuid($reference['uuid'] ?? null) ? strtolower($reference['uuid']) : null;
    }

    public function appointmentId(AiConversation $conversation, PatientContext $context): ?int
    {
        $reference = $this->state($conversation, $context)['appointment'] ?? null;

        return is_array($reference) && in_array($reference['source'] ?? null, self::APPOINTMENT_SOURCES, true)
            && filter_var($reference['id'] ?? null, FILTER_VALIDATE_INT) !== false && (int) $reference['id'] > 0
                ? (int) $reference['id'] : null;
    }

    public function rememberAssessment(AiConversation $conversation, PatientContext $context, string $uuid, string $source, int $turn): void
    {
        if (! Str::isUuid($uuid) || ! in_array($source, self::ASSESSMENT_SOURCES, true)) {
            $this->forget($conversation, $context, 'assessment');

            return;
        }
        $state = $this->state($conversation, $context);
        $state['assessment'] = ['uuid' => strtolower($uuid), 'source' => $source, 'source_turn' => max(1, $turn), 'recorded_at' => now()->toIso8601String()];
        $this->save($conversation, $context, $state);
    }

    public function rememberAppointment(AiConversation $conversation, PatientContext $context, int $id, string $source, int $turn): void
    {
        if ($id < 1 || ! in_array($source, self::APPOINTMENT_SOURCES, true)) {
            $this->forget($conversation, $context, 'appointment');

            return;
        }
        $state = $this->state($conversation, $context);
        $state['appointment'] = ['id' => $id, 'source' => $source, 'source_turn' => max(1, $turn), 'recorded_at' => now()->toIso8601String()];
        $this->save($conversation, $context, $state);
    }

    public function forget(AiConversation $conversation, PatientContext $context, string $type): void
    {
        if (! in_array($type, ['assessment', 'appointment'], true)) {
            return;
        }
        $state = $this->state($conversation, $context);
        unset($state[$type]);
        if (! isset($state['assessment']) && ! isset($state['appointment'])) {
            $conversation->forceFill(['conversation_context' => null])->save();

            return;
        }
        $this->save($conversation, $context, $state);
    }

    /** @return list<array{type: string, source: string, source_turn: int, recorded_at: string}> */
    public function exportMetadata(AiConversation $conversation): array
    {
        $state = $conversation->conversation_context;
        if (! is_array($state) || ($state['version'] ?? null) !== self::VERSION || ! $this->matchesConversationScope($conversation, $state['scope'] ?? null)) {
            return [];
        }
        $metadata = [];
        foreach (['assessment' => self::ASSESSMENT_SOURCES, 'appointment' => self::APPOINTMENT_SOURCES] as $type => $sources) {
            $reference = $state[$type] ?? null;
            if (is_array($reference) && in_array($reference['source'] ?? null, $sources, true)
                && is_int($reference['source_turn'] ?? null) && is_string($reference['recorded_at'] ?? null)) {
                $metadata[] = ['type' => $type, 'source' => $reference['source'], 'source_turn' => $reference['source_turn'], 'recorded_at' => $reference['recorded_at']];
            }
        }

        return $metadata;
    }

    /** @return array<string, mixed> */
    private function state(AiConversation $conversation, PatientContext $context): array
    {
        if (! $context->owns($conversation->tenant_id, $conversation->user_id, $conversation->client_id)) {
            return [];
        }
        $state = $conversation->conversation_context;
        if (! is_array($state) || ($state['version'] ?? null) !== self::VERSION || ! $this->matchesConversationScope($conversation, $state['scope'] ?? null)
            || (int) ($state['scope']['tenant_id'] ?? 0) !== $context->tenantId
            || (int) ($state['scope']['user_id'] ?? 0) !== $context->userId
            || (int) ($state['scope']['client_id'] ?? 0) !== $context->clientId) {
            return $this->emptyState($conversation, $context);
        }

        return $state;
    }

    /** @param array<string, mixed> $state */
    private function save(AiConversation $conversation, PatientContext $context, array $state): void
    {
        if (! $context->owns($conversation->tenant_id, $conversation->user_id, $conversation->client_id)) {
            return;
        }
        $state['version'] = self::VERSION;
        $state['scope'] = ['tenant_id' => $context->tenantId, 'user_id' => $context->userId, 'client_id' => $context->clientId];
        $conversation->forceFill(['conversation_context' => $state])->save();
    }

    /** @return array{version: int, scope: array{tenant_id: int, user_id: int, client_id: int}} */
    private function emptyState(AiConversation $conversation, PatientContext $context): array
    {
        return [
            'version' => self::VERSION,
            'scope' => ['tenant_id' => $context->tenantId, 'user_id' => $context->userId, 'client_id' => $context->clientId],
        ];
    }

    private function matchesConversationScope(AiConversation $conversation, mixed $scope): bool
    {
        return is_array($scope)
            && (int) ($scope['tenant_id'] ?? 0) === (int) $conversation->tenant_id
            && (int) ($scope['user_id'] ?? 0) === (int) $conversation->user_id
            && (int) ($scope['client_id'] ?? 0) === (int) $conversation->client_id;
    }
}
