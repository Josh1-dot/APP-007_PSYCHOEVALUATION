<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PatientAiChat
{
    public function __construct(public LlmProvider $provider, public ConversationIntentRouter $router) {}

    public function send(AiConversation $conversation, string $message): void
    {
        if (config('patientai.provider') !== 'fake') {
            throw new RuntimeException('Provider unavailable.');
        }
        DB::transaction(function () use ($conversation, $message): void {
            $client = Client::whereKey($conversation->client_id)->lockForUpdate()->firstOrFail();
            abort_unless($client->user_id === auth()->id() && auth()->user()->role === 'patient', 403);
            $conversation = AiConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            abort_unless($conversation->user_id === auth()->id() && $conversation->client_id === $client->id && $conversation->status === 'active', 409);
            abort_if(app(PatientAiLifecycle::class)->expired($conversation), 410);
            $reply = $this->provider->reply($this->router->route($message));
            if (trim($reply) === '' || mb_strlen($reply) > 10000) {
                throw new RuntimeException('Invalid provider response.');
            }
            $conversation->messages()->create(['role' => 'patient', 'content' => $message]);
            $conversation->messages()->create(['role' => 'assistant', 'content' => $reply, 'provider' => 'fake']);
            $conversation->touch();
            Access::audit('patientai.message_envoye', $conversation);
        });
    }
}
