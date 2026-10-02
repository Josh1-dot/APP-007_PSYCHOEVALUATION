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
            if ($request !== null) {
                $tools = app(PatientAssessmentTools::class);
                $result = $request['tool'] === 'list' ? $tools->listMyAssessments($request['filters']) : $tools->getMyAssessmentStatus($request['uuid']);
                $reply = $this->provider->reply('assessments', $result);
                if ($reply !== (new PatientAssessmentFormatter)->format($result)) {
                    throw new RuntimeException('Invalid assessment response.');
                }
            } else {
                $reply = $refusal ?? $this->provider->reply($this->router->route($message));
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
