<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class QuestionnaireHelpTool
{
    public function __construct(public PatientContextFactory $contexts, public PatientAssessmentTools $assessments, public PatientGuideRegistry $guides) {}

    public function getQuestionnaireHelp(string $assessmentUuid, ?string $questionId = null): QuestionnaireHelpData
    {
        $context = $this->contexts->fromAuthenticatedUser();
        if (! Str::isUuid($assessmentUuid) || ($questionId !== null && ! preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/D', $questionId))) {
            return new QuestionnaireHelpData;
        }
        $assessment = $this->assessments->authorizedQuery($context)->where('uuid', strtolower($assessmentUuid))->where('status', 'en_cours')->without('definition')->first();
        $client = Client::select('id')->where('tenant_id', $context->tenantId)->where('user_id', $context->userId)->whereKey($context->clientId)->first();
        if (! $assessment || ! $client?->hasConsent()) {
            return new QuestionnaireHelpData;
        }
        $guide = $this->guides->approved();
        if ($guide === null) {
            return new QuestionnaireHelpData;
        }
        $definition = $assessment->definition()->where('tenant_id', $context->tenantId)->first(['id', 'name', 'version', 'is_demo', 'kind', 'questions']);
        if (! $definition || ! is_array($definition->questions)) {
            return new QuestionnaireHelpData;
        }
        $question = null;
        if ($questionId !== null) {
            $matches = array_values(array_filter($definition->questions, fn (mixed $item): bool => is_array($item) && ($item['id'] ?? null) === $questionId));
            if (count($matches) !== 1 || ! $this->validQuestion($matches[0])) {
                return new QuestionnaireHelpData;
            }
            $item = $matches[0];
            $question = new QuestionHelpData($item['id'], $item['label'], $item['type'], in_array($definition->kind, ['gordon', 'enneagramme'], true) || ($item['required'] ?? true), $item['type'] === 'scale' ? (int) $item['min'] : null, $item['type'] === 'scale' ? (int) $item['max'] : null, $item['type'] === 'choice' ? array_values($item['options']) : []);
        }
        $help = $guide['questionnaire'];

        return new QuestionnaireHelpData(true, $assessment->uuid, $definition->name, (int) $definition->version, (bool) $definition->is_demo, route('evaluations.show', $assessment->id), $guide['version'], $guide['source'], $help['objective'], $help['instructions'], $help['navigation'], $help['vocabulary'], $question);
    }

    /** @param array<string, mixed> $question */
    private function validQuestion(array $question): bool
    {
        $rules = ['id' => 'required|string|regex:/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/D', 'label' => 'required|string|max:1000', 'type' => 'required|in:boolean,scale,choice,text', 'required' => 'sometimes|boolean'];
        if (($question['type'] ?? null) === 'scale') {
            $rules += ['min' => 'required|integer|min:0|max:100', 'max' => 'required|integer|min:1|max:100|gt:min'];
        }
        if (($question['type'] ?? null) === 'choice') {
            $rules += ['options' => 'required|array|min:2|max:30', 'options.*' => 'required|string|max:300'];
        }

        return ! Validator::make($question, $rules)->fails();
    }
}
