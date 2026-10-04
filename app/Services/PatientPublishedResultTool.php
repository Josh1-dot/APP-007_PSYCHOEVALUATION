<?php

namespace App\Services;

use App\Models\AssessmentDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PatientPublishedResultTool
{
    public const TEXT_LIMIT = 4000;

    public function __construct(public PatientContextFactory $contexts, public PatientAssessmentTools $assessments) {}

    public function getMyPublishedResult(string $uuid): PatientPublishedResultData
    {
        $context = $this->contexts->fromAuthenticatedUser();
        if (! Str::isUuid($uuid)) {
            return new PatientPublishedResultData;
        }

        return DB::transaction(function () use ($context, $uuid): PatientPublishedResultData {
            $assessment = $this->assessments->authorizedQuery($context)->where('uuid', strtolower($uuid))->where('status', 'publie')->without('definition')->lockForUpdate()->first();
            if (! $assessment) {
                return new PatientPublishedResultData;
            }
            $publication = $assessment->interpretation()->where('tenant_id', $context->tenantId)->whereNotNull('published_at')->first(['assessment_id', 'published_at', 'published_content']);
            if (! $publication || ! is_string($publication->published_content) || trim($publication->published_content) === '') {
                return new PatientPublishedResultData;
            }
            $definition = $assessment->definition()->where('tenant_id', $context->tenantId)->first(['id', 'name', 'version', 'kind', 'is_demo', 'questions', 'engine_version', 'form_key']);
            if (! $definition) {
                return new PatientPublishedResultData;
            }
            $resultRow = $assessment->newQuery()->whereKey($assessment->id)->where('tenant_id', $context->tenantId)->where('client_id', $context->clientId)->where('status', 'publie')->first(['id', 'results']);
            $results = $resultRow?->results ?? [];
            if (! is_array($results) || ! $this->coherent($results, $definition)) {
                return new PatientPublishedResultData;
            }
            $scores = $results['scores'] ?? [];
            $text = trim(html_entity_decode(strip_tags(Str::markdown($publication->published_content, ['html_input' => 'strip', 'allow_unsafe_links' => false])), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text === '') {
                return new PatientPublishedResultData;
            }

            return new PatientPublishedResultData(true, $assessment->uuid, $definition->name, (int) $definition->version, $publication->published_at->toIso8601String(), $scores, $scores === [] ? null : $results['maximum'], $scores === [] ? '' : ($results['method'] ?? 'Nombre de réponses affirmatives par dimension.'), mb_substr($text, 0, self::TEXT_LIMIT), mb_strlen($text) > self::TEXT_LIMIT, route('evaluations.show', $assessment->id), (bool) $definition->is_demo);
        });
    }

    /** @param array<string, mixed> $results */
    private function coherent(array $results, AssessmentDefinition $definition): bool
    {
        if ((isset($results['kind']) && $results['kind'] !== $definition->kind) || (isset($results['definition_version']) && $results['definition_version'] !== (int) $definition->version)) {
            return false;
        }
        if (! isset($results['scores'])) {
            return true;
        }
        if ($definition->engine_version === EnneagramScoring::ENGINE_VERSION && (($results['engine'] ?? null) !== EnneagramScoring::ENGINE_VERSION || ($results['method_version'] ?? null) !== EnneagramScoring::ENGINE_VERSION || ($results['form_key'] ?? null) !== $definition->form_key || ($results['maximum'] ?? null) !== 100 || ! is_array($results['scores'] ?? null) || array_keys($results['scores'] ?? []) !== EnneagramScoring::DIMENSIONS)) {
            return false;
        }
        $scores = $results['scores'];
        $maximum = $results['maximum'] ?? null;
        if (! is_array($scores) || count($scores) > 9 || ! $this->number($maximum) || $maximum <= 0 || (isset($results['method']) && (! is_string($results['method']) || mb_strlen($results['method']) > 1000))) {
            return false;
        }
        $allowed = match ($definition->kind) {
            'gordon' => ['A', 'B', 'C', 'D'],
            'enneagramme' => $definition->engine_version === EnneagramScoring::ENGINE_VERSION ? EnneagramScoring::DIMENSIONS : array_column($definition->questions ?? [], 'id'),
            default => [],
        };
        if (array_diff(array_keys($scores), $allowed)) {
            return false;
        }
        foreach ($scores as $value) {
            if (! $this->number($value) || $value < 0 || $value > $maximum) {
                return false;
            }
        }

        return true;
    }

    private function number(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value));
    }
}
