<?php

namespace App\Services;

use App\Models\AiConversation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PatientAiChat
{
    public function __construct(public LlmProvider $provider, public ConversationIntentRouter $router, public SafetyPolicy $policy, public PatientContextFactory $contexts) {}

    public function send(AiConversation $conversation, string $message): void
    {
        abort_unless(config('patientai.enabled'), 404);
        if (config('patientai.provider') !== 'fake') {
            throw new RuntimeException('Provider unavailable.');
        }
        DB::transaction(function () use ($conversation, $message): void {
            $context = $this->contexts->fromAuthenticatedUser(lockClient: true);
            $conversation = AiConversation::where('tenant_id', $context->tenantId)->where('user_id', $context->userId)
                ->where('client_id', $context->clientId)->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            abort_unless($context->owns($conversation->tenant_id, $conversation->user_id, $conversation->client_id), 404);
            abort_unless($conversation->status === 'active', 409);
            abort_if(app(PatientAiLifecycle::class)->expired($conversation), 410);
            $refusal = $this->policy->refusal($message);
            $resolution = $refusal === null ? $this->router->resolve($message) : new ConversationIntentResolution('unknown');
            $turn = $conversation->messages()->where('role', 'patient')->count() + 1;
            $reply = $refusal ?? $this->dispatch($conversation, $context, $resolution, $turn, $message);
            if (trim($reply) === '' || mb_strlen($reply) > 10000) {
                throw new RuntimeException('Invalid provider response.');
            }
            $conversation->messages()->create(['role' => 'patient', 'content' => $message]);
            $conversation->messages()->create(['role' => 'assistant', 'content' => $reply, 'provider' => $refusal === null ? 'fake' : 'policy']);
            $conversation->touch();
            Access::audit('patientai.message_envoye', $conversation);
        });
    }

    private function dispatch(AiConversation $conversation, PatientContext $context, ConversationIntentResolution $resolution, int $turn, string $message): string
    {
        return match ($resolution->intent) {
            'assessments_list' => $this->assessmentList($conversation, $context, $resolution, $turn),
            'assessment_status' => $this->assessmentStatus($conversation, $context, $resolution, $turn),
            'questionnaire_help' => $this->questionnaireHelp($conversation, $context, $resolution, $turn),
            'appointment_next', 'appointments_list' => $this->appointments($conversation, $context, $resolution, $turn),
            'published_result' => $this->publishedResult($conversation, $context, $resolution, $turn),
            'documentation' => $this->documentation($resolution),
            'memory_status' => $this->memoryStatus($conversation),
            'about_my_data' => $this->staticResponse('about_my_data'),
            default => $this->socialOrFallback($conversation, $resolution->intent),
        };
    }

    private function assessmentList(AiConversation $conversation, PatientContext $context, ConversationIntentResolution $resolution, int $turn): string
    {
        $result = app(PatientAssessmentTools::class)->listMyAssessments($resolution->parameters['filters'] ?? []);
        if (count($result->items) === 1 && ! $result->hasMore) {
            app(PatientConversationReferences::class)->rememberAssessment($conversation, $context, $result->items[0]->uuid, 'assessments_list', $turn);
        } else {
            app(PatientConversationReferences::class)->forget($conversation, $context, 'assessment');
        }
        $formatter = new PatientAssessmentFormatter;
        $data = $formatter->conversationData($result);
        $reply = $this->provider->reply('assessments', $data);

        return $this->validated($reply, $formatter->formatConversation($data), 'Invalid assessment response.');
    }

    private function assessmentStatus(AiConversation $conversation, PatientContext $context, ConversationIntentResolution $resolution, int $turn): string
    {
        $references = app(PatientConversationReferences::class);
        $uuid = (string) ($resolution->parameters['uuid'] ?? '');
        if ($uuid === '') {
            $uuid = $references->assessmentUuid($conversation, $context) ?? '';
        }
        if ($uuid === '' && ($resolution->parameters['follow_up'] ?? false)) {
            return 'De quelle évaluation souhaitez-vous connaître le statut ?';
        }
        if ($uuid === '') {
            $list = app(PatientAssessmentTools::class)->listMyAssessments();
            if (count($list->items) === 1 && ! $list->hasMore) {
                $uuid = $list->items[0]->uuid;
            } elseif ($list->items !== []) {
                $references->forget($conversation, $context, 'assessment');

                return $this->assessmentClarification($list);
            }
        }
        if ($uuid === '') {
            return $this->assessmentUnavailable();
        }

        $result = app(PatientAssessmentTools::class)->getMyAssessmentStatus($uuid);
        if (! $result->available || count($result->items) !== 1) {
            $references->forget($conversation, $context, 'assessment');

            return $this->assessmentUnavailable();
        }
        $references->rememberAssessment($conversation, $context, $result->items[0]->uuid, 'assessment_status', $turn);
        $formatter = new PatientAssessmentFormatter;
        $data = $formatter->conversationData($result);
        $reply = $this->provider->reply('assessments', $data);

        return $this->validated($reply, $formatter->formatConversation($data), 'Invalid assessment response.');
    }

    private function questionnaireHelp(AiConversation $conversation, PatientContext $context, ConversationIntentResolution $resolution, int $turn): string
    {
        $references = app(PatientConversationReferences::class);
        $uuid = (string) ($resolution->parameters['uuid'] ?? '');
        if ($uuid === '') {
            $uuid = $references->assessmentUuid($conversation, $context) ?? '';
        }
        if ($uuid === '' && ($resolution->parameters['follow_up'] ?? false)) {
            return 'De quel questionnaire souhaitez-vous parler ?';
        }
        if ($uuid === '') {
            $candidates = app(PatientAssessmentTools::class)->listMyAssessments(['status' => 'en_cours']);
            if (count($candidates->items) === 1 && ! $candidates->hasMore) {
                $uuid = $candidates->items[0]->uuid;
            } elseif ($candidates->items !== []) {
                $references->forget($conversation, $context, 'assessment');

                return $this->assessmentClarification($candidates);
            }
        }
        if ($uuid === '') {
            return 'Cette aide n’est pas disponible dans votre espace.';
        }
        $data = app(QuestionnaireHelpTool::class)->getQuestionnaireHelp($uuid, $resolution->parameters['question_id'] ?? null);
        if (! $data->available) {
            $references->forget($conversation, $context, 'assessment');

            return 'Cette aide n’est pas disponible dans votre espace.';
        }
        $references->rememberAssessment($conversation, $context, $data->assessmentUuid, 'questionnaire_help', $turn);
        $formatter = new PatientHelpFormatter;
        $reply = $this->provider->reply('help', $data);

        return $this->validated($reply, $formatter->format($data), 'Invalid help response.');
    }

    private function appointments(AiConversation $conversation, PatientContext $context, ConversationIntentResolution $resolution, int $turn): string
    {
        $tools = app(PatientAppointmentTools::class);
        $references = app(PatientConversationReferences::class);
        $followUp = (bool) ($resolution->parameters['follow_up'] ?? false);
        if ($followUp) {
            $appointmentId = $references->appointmentId($conversation, $context);
            if ($appointmentId === null) {
                return 'De quel rendez-vous souhaitez-vous connaître l’horaire ?';
            }
            $result = $tools->getMyAppointmentByReference($appointmentId);
            if ($result->items === []) {
                $references->forget($conversation, $context, 'appointment');

                return 'Aucun rendez-vous à venir n’est actuellement disponible.';
            }
            $selectionId = $appointmentId;
        } elseif ($resolution->intent === 'appointments_list') {
            $selection = $tools->listMyUpcomingAppointmentsForConversation();
            $result = $selection->result;
            $selectionId = $selection->referenceId;
        } else {
            $selection = $tools->getMyNextAppointmentForConversation();
            $result = $selection->result;
            $selectionId = $selection->referenceId;
        }
        if ($selectionId !== null && count($result->items) === 1) {
            $references->rememberAppointment($conversation, $context, $selectionId, $resolution->intent, $turn);
        } else {
            $references->forget($conversation, $context, 'appointment');
        }
        $formatter = new PatientAppointmentFormatter;
        $reply = $this->provider->reply('appointments', $result);

        return $this->validated($reply, $formatter->format($result), 'Invalid appointment response.');
    }

    private function publishedResult(AiConversation $conversation, PatientContext $context, ConversationIntentResolution $resolution, int $turn): string
    {
        $references = app(PatientConversationReferences::class);
        $uuid = (string) ($resolution->parameters['uuid'] ?? '');
        if ($uuid === '') {
            $uuid = $references->assessmentUuid($conversation, $context) ?? '';
        }
        if ($uuid === '') {
            $candidates = app(PatientAssessmentTools::class)->listMyAssessments(['status' => 'publie']);
            if (count($candidates->items) === 1 && ! $candidates->hasMore) {
                $uuid = $candidates->items[0]->uuid;
            } elseif ($candidates->items !== []) {
                $references->forget($conversation, $context, 'assessment');

                return $this->assessmentClarification($candidates);
            }
        }
        if ($uuid === '') {
            return 'Aucun résultat publié correspondant n’est actuellement disponible.';
        }
        $data = app(PatientPublishedResultTool::class)->getMyPublishedResult($uuid);
        if (! $data->available) {
            $references->forget($conversation, $context, 'assessment');

            return 'Aucun résultat publié correspondant n’est actuellement disponible.';
        }
        $references->rememberAssessment($conversation, $context, $data->assessmentUuid, 'published_result', $turn);
        $formatter = new PatientPublishedResultFormatter;
        $reply = $this->provider->reply('published_result', $data);

        return $this->validated($reply, $formatter->format($data), 'Invalid published result response.');
    }

    private function documentation(ConversationIntentResolution $resolution): string
    {
        if (isset($resolution->parameters['query'])) {
            $data = app(PatientRagRetriever::class)->retrieve((string) $resolution->parameters['query']);
            $formatter = new PatientRagFormatter;
            $reply = $this->provider->reply('documentation', $data);

            return $this->validated($reply, $formatter->format($data), 'Invalid documentary response.');
        }
        $data = app(PatientGuideRegistry::class)->guide((string) ($resolution->parameters['topic'] ?? 'dashboard'));
        $formatter = new PatientHelpFormatter;
        $reply = $this->provider->reply('help', $data);

        return $this->validated($reply, $formatter->format($data), 'Invalid help response.');
    }

    private function memoryStatus(AiConversation $conversation): string
    {
        $memory = app(PatientMemoryService::class)->context($conversation);
        if ($memory->responseStyle !== null) {
            Access::audit('patientai.memoire_utilisee', $conversation);
        }
        $formatter = new PatientMemoryFormatter;
        $reply = $this->provider->reply('memory', $memory);

        return $this->validated($reply, $formatter->format('memory', $memory), 'Invalid memory response.');
    }

    private function socialOrFallback(AiConversation $conversation, string $intent): string
    {
        $social = ['greeting', 'courtesy', 'farewell', 'identity', 'capabilities'];
        if ($conversation->memory_enabled && in_array($intent, $social, true)) {
            $memory = app(PatientMemoryService::class)->context($conversation);
            $reply = $this->provider->reply($intent, $memory);
            $expected = (new PatientMemoryFormatter)->format($intent, $memory);
            if ($memory->responseStyle !== null) {
                Access::audit('patientai.memoire_utilisee', $conversation);
            }

            return $this->validated($reply, $expected, 'Invalid memory response.');
        }
        $reply = $this->provider->reply($intent);

        return $this->validated($reply, (new PromptRegistry)->response($intent), 'Invalid provider response for '.$intent.'.');
    }

    private function staticResponse(string $intent): string
    {
        $reply = $this->provider->reply($intent);

        return $this->validated($reply, (new PromptRegistry)->response($intent), 'Invalid provider response.');
    }

    private function assessmentClarification(PatientAssessmentResult $result): string
    {
        $options = array_map(fn (PatientAssessmentData $item): string => $item->questionnaireName.' — '.$item->statusLabel, $result->items);

        return 'Plusieurs évaluations sont accessibles. Laquelle souhaitez-vous consulter ? '.implode(' ; ', $options);
    }

    private function assessmentUnavailable(): string
    {
        return 'Aucune évaluation correspondante n’est actuellement disponible dans votre espace.';
    }

    private function validated(string $reply, string $expected, string $error): string
    {
        if ($reply !== $expected) {
            throw new RuntimeException($error);
        }

        return $reply;
    }
}
