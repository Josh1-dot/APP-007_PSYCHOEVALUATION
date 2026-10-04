<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentDefinition;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EnneagramFormRotation
{
    public function __construct(public EnneagramScoring $scoring) {}

    public function selectForAssignment(Client $client, AssessmentDefinition $anchor): AssessmentDefinition
    {
        abort_unless($anchor->tenant_id === $client->tenant_id && $anchor->kind === 'enneagramme' && $anchor->engine_version === EnneagramScoring::ENGINE_VERSION, 404);
        $contentStatus = $anchor->content_status;
        if (! in_array($contentStatus, ['DEMO', 'APPROVED'], true)) {
            throw ValidationException::withMessages(['assessment_definition_id' => 'Cette forme Ennéagramme n’est pas disponible à l’assignation.']);
        }
        if ($contentStatus === 'DEMO' && ! $anchor->is_demo) {
            throw ValidationException::withMessages(['assessment_definition_id' => 'Une forme DEMO doit rester explicitement marquée comme démonstration.']);
        }
        if ($contentStatus === 'APPROVED' && ($anchor->is_demo || ! $anchor->licensed || ! filled($anchor->source_reference) || ! $anchor->approved_by || ! $anchor->approved_at || ! $anchor->reviewed_by || ! $anchor->reviewed_at)) {
            throw ValidationException::withMessages(['assessment_definition_id' => 'La forme approuvée ne possède pas sa provenance et ses reçus de revue requis.']);
        }

        $definitions = AssessmentDefinition::query()
            ->where('tenant_id', $client->tenant_id)
            ->where('family', $anchor->family)
            ->where('kind', 'enneagramme')
            ->where('engine_version', EnneagramScoring::ENGINE_VERSION)
            ->where('content_status', $contentStatus)
            ->orderBy('form_key')->orderByDesc('version')->get();
        $candidates = $definitions->groupBy('form_key')->map(fn (Collection $versions): AssessmentDefinition => $versions->first())->values();
        foreach ($candidates as $candidate) {
            $this->scoring->validateDefinition($candidate);
            if ($contentStatus === 'DEMO' && ! $candidate->is_demo) {
                throw ValidationException::withMessages(['assessment_definition_id' => 'Le pool DEMO contient une forme non marquée comme démonstration.']);
            }
            if ($contentStatus === 'APPROVED' && ($candidate->is_demo || ! $candidate->licensed || ! filled($candidate->source_reference) || ! $candidate->approved_by || ! $candidate->approved_at || ! $candidate->reviewed_by || ! $candidate->reviewed_at)) {
                throw ValidationException::withMessages(['assessment_definition_id' => 'Le pool comprend une forme approuvée incomplète.']);
            }
        }
        if ($candidates->isEmpty()) {
            throw ValidationException::withMessages(['assessment_definition_id' => 'Aucune forme Ennéagramme éligible n’est disponible.']);
        }

        $history = Assessment::query()->where('tenant_id', $client->tenant_id)->where('client_id', $client->id)
            ->whereHas('definition', fn ($query) => $query->where('tenant_id', $client->tenant_id)->where('family', $anchor->family)
                ->where('kind', 'enneagramme')->where('engine_version', EnneagramScoring::ENGINE_VERSION)->where('content_status', $contentStatus))
            ->with('definition:id,form_key')->orderByDesc('created_at')->orderByDesc('id')->get(['id', 'assessment_definition_id', 'created_at']);
        $lastUseByForm = [];
        foreach ($history as $assessment) {
            $formKey = $assessment->definition?->form_key;
            if (is_string($formKey) && ! array_key_exists($formKey, $lastUseByForm)) {
                $lastUseByForm[$formKey] = $assessment->created_at;
            }
        }

        $unused = $candidates->reject(fn (AssessmentDefinition $candidate): bool => array_key_exists($candidate->form_key, $lastUseByForm))
            ->sortBy('form_key')->values();
        if ($unused->isNotEmpty()) {
            return $unused->first();
        }

        return $candidates->sort(function (AssessmentDefinition $first, AssessmentDefinition $second) use ($lastUseByForm): int {
            $firstUse = $lastUseByForm[$first->form_key] ?? null;
            $secondUse = $lastUseByForm[$second->form_key] ?? null;
            if ($firstUse === null || $secondUse === null) {
                return $firstUse === $secondUse ? strcmp($first->form_key, $second->form_key) : ($firstUse === null ? -1 : 1);
            }
            $timeOrder = $firstUse->getTimestamp() <=> $secondUse->getTimestamp();

            return $timeOrder !== 0 ? $timeOrder : strcmp($first->form_key, $second->form_key);
        })->first();
    }
}
