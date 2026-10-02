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
            $request = $refusal === null ? $this->router->assessmentRequest($message) : null;
            $helpRequest = $refusal === null ? $this->router->helpRequest($message) : null;
            $appointmentRequest = $refusal === null ? $this->router->appointmentRequest($message) : null;
            $resultRequest = $refusal === null ? $this->router->publishedResultRequest($message) : null;
            if ($resultRequest !== null) {
                $data = app(PatientPublishedResultTool::class)->getMyPublishedResult($resultRequest);
                $reply = $this->provider->reply('published_result', $data);
                if ($reply !== (new PatientPublishedResultFormatter)->format($data)) {
                    throw new RuntimeException('Invalid published result response.');
                }
            } elseif ($appointmentRequest !== null && $appointmentRequest !== 'read_only') {
                $tools = app(PatientAppointmentTools::class);
                $result = $appointmentRequest === 'list' ? $tools->listMyUpcomingAppointments() : $tools->getMyNextAppointment();
                $reply = $this->provider->reply('appointments', $result);
                if ($reply !== (new PatientAppointmentFormatter)->format($result)) {
                    throw new RuntimeException('Invalid appointment response.');
                }
            } elseif ($appointmentRequest === 'read_only') {
                $data = app(PatientGuideRegistry::class)->guide('appointments');
                $reply = $this->provider->reply('help', $data);
                if ($reply !== (new PatientHelpFormatter)->format($data)) {
                    throw new RuntimeException('Invalid help response.');
                }
            } elseif ($helpRequest !== null) {
                $data = isset($helpRequest['topic']) ? app(PatientGuideRegistry::class)->guide($helpRequest['topic']) : app(QuestionnaireHelpTool::class)->getQuestionnaireHelp($helpRequest['uuid'], $helpRequest['question_id']);
                $reply = $this->provider->reply('help', $data);
                if ($reply !== (new PatientHelpFormatter)->format($data)) {
                    throw new RuntimeException('Invalid help response.');
                }
            } elseif ($request !== null) {
                $tools = app(PatientAssessmentTools::class);
                $result = $request['tool'] === 'list' ? $tools->listMyAssessments($request['filters']) : $tools->getMyAssessmentStatus($request['uuid']);
                $reply = $this->provider->reply('assessments', $result);
                if ($reply !== (new PatientAssessmentFormatter)->format($result)) {
                    throw new RuntimeException('Invalid assessment response.');
                }
            } else {
                $intent = in_array($this->router->normalize($message), ['quelle est ma preference de reponse', 'quelle est ma preference de presentation', 'que sais tu de ma preference de presentation'], true) ? 'memory' : $this->router->route($message);
                if ($refusal !== null) {
                    $reply = $refusal;
                } elseif (($conversation->memory_enabled && $intent !== 'unknown') || $intent === 'memory') {
                    $memory = app(PatientMemoryService::class)->context($conversation);
                    $reply = $this->provider->reply($intent, $memory);
                    if ($reply !== (new PatientMemoryFormatter)->format($intent, $memory)) {
                        throw new RuntimeException('Invalid memory response.');
                    }
                    if ($memory->responseStyle !== null) {
                        Access::audit('patientai.memoire_utilisee', $conversation);
                    }
                } else {
                    $reply = $this->provider->reply($intent);
                }
            }
            if (trim($reply) === '' || mb_strlen($reply) > 10000) {
                throw new RuntimeException('Invalid provider response.');
            }
            $conversation->messages()->create(['role' => 'patient', 'content' => $message]);
            $conversation->messages()->create(['role' => 'assistant', 'content' => $reply, 'provider' => $refusal === null ? 'fake' : 'policy']);
            $conversation->touch();
            Access::audit('patientai.message_envoye', $conversation);
        });
    }
}
