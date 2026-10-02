<?php

namespace App\Services;

use App\Models\Assessment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PatientAssessmentTools
{
    public const STATUS_LABELS = ['en_cours' => 'En cours', 'termine' => 'À réviser', 'publie' => 'Publié'];

    public function __construct(public PatientContextFactory $contexts) {}

    /** @param array<string, mixed> $filters */
    public function listMyAssessments(array $filters = []): PatientAssessmentResult
    {
        $context = $this->contexts->fromAuthenticatedUser();
        $validated = Validator::make(['filters' => $filters], [
            'filters' => 'array:status,page,limit',
            'filters.status' => ['sometimes', 'required', Rule::in(array_keys(self::STATUS_LABELS))],
            'filters.page' => 'sometimes|required|integer|min:1|max:100',
            'filters.limit' => 'sometimes|required|integer|min:1|max:20',
        ])->validate()['filters'];
        $limit = (int) ($validated['limit'] ?? 10);
        $query = $this->visible($context);
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        $rows = $query->orderByDesc('id')->offset(((int) ($validated['page'] ?? 1) - 1) * $limit)->limit($limit + 1)->get();

        return new PatientAssessmentResult($rows->take($limit)->map(fn (Assessment $assessment): PatientAssessmentData => $this->data($assessment))->all(), $rows->count() > $limit);
    }

    public function getMyAssessmentStatus(string $uuid): PatientAssessmentResult
    {
        $context = $this->contexts->fromAuthenticatedUser();
        if (! Str::isUuid($uuid)) {
            return new PatientAssessmentResult(available: false);
        }
        $assessment = $this->visible($context)->where('uuid', strtolower($uuid))->first();

        return $assessment ? new PatientAssessmentResult([$this->data($assessment)]) : new PatientAssessmentResult(available: false);
    }

    private function visible(PatientContext $context): Builder
    {
        return Assessment::query()->select(['id', 'uuid', 'assessment_definition_id', 'status'])
            ->where('tenant_id', $context->tenantId)->where('client_id', $context->clientId)->whereNotNull('uuid')
            ->whereHas('client', fn (Builder $query): Builder => $query->where('tenant_id', $context->tenantId)->where('user_id', $context->userId)->whereNull('deleted_at')->whereNull('anonymized_at'))
            ->whereHas('definition', fn (Builder $query): Builder => $query->where('tenant_id', $context->tenantId))
            ->whereIn('status', array_keys(self::STATUS_LABELS))
            ->where(fn (Builder $query): Builder => $query->where('status', '!=', 'publie')->orWhereHas('interpretation', fn (Builder $query): Builder => $query->where('tenant_id', $context->tenantId)->whereNotNull('published_at')))
            ->with('definition:id,name');
    }

    private function data(Assessment $assessment): PatientAssessmentData
    {
        return new PatientAssessmentData($assessment->uuid, $assessment->definition->name, $assessment->status, self::STATUS_LABELS[$assessment->status], route('evaluations.show', $assessment->id));
    }
}
