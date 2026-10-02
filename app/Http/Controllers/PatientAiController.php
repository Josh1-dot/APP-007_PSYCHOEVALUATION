<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\Client;
use App\Services\Access;
use App\Services\PatientAiChat;
use App\Services\PatientAiLifecycle;
use App\Services\PatientContext;
use App\Services\PatientContextFactory;
use App\Services\PatientMemoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class PatientAiController extends Controller
{
    public function __construct(public PatientContextFactory $contexts) {}

    private function patient(): PatientContext
    {
        abort_unless(config('patientai.enabled'), 404);

        return $this->contexts->fromAuthenticatedUser();
    }

    private function authorizeConversation(AiConversation $conversation): PatientContext
    {
        $context = $this->patient();
        abort_unless($context->owns($conversation->tenant_id, $conversation->user_id, $conversation->client_id), 404);

        return $context;
    }

    public function index(Request $request): View
    {
        $context = $this->patient();

        return view('modules.patientai', ['conversations' => AiConversation::where('tenant_id', $context->tenantId)->where('user_id', $context->userId)->where('client_id', $context->clientId)->latest('updated_at')->paginate(20), 'conversation' => null, 'messages' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->patient();
        $data = $request->validate([
            'accepted' => 'accepted',
            'memory_enabled' => 'sometimes|boolean',
            'memory_accepted' => $request->boolean('memory_enabled') ? 'required|accepted' : 'sometimes|accepted',
            'memory_style' => 'nullable|in:standard,concise|prohibited_unless:memory_enabled,1',
        ]);
        $conversation = DB::transaction(function () use ($data): AiConversation {
            $context = $this->contexts->fromAuthenticatedUser(lockClient: true);
            $conversation = AiConversation::create(['tenant_id' => $context->tenantId, 'user_id' => $context->userId, 'client_id' => $context->clientId, 'consent_version' => config('patientai.consent_version'), 'consent_text' => config('patientai.consent_text'), 'consented_at' => now(), 'memory_enabled' => (bool) ($data['memory_enabled'] ?? false), 'memory_consent_version' => ($data['memory_enabled'] ?? false) ? PatientMemoryService::CONSENT_VERSION : null, 'memory_consented_at' => ($data['memory_enabled'] ?? false) ? now() : null, 'memory' => ($data['memory_enabled'] ?? false) ? app(PatientMemoryService::class)->preference($data['memory_style'] ?? null) : null]);
            Access::audit('patientai.conversation_creee', $conversation);
            if ($conversation->memory_enabled) {
                app(PatientMemoryService::class)->replacePrevious($conversation);
                Access::audit('patientai.memoire_autorisee', $conversation);
            }

            return $conversation;
        });

        return redirect()->route('patientai.show', $conversation);
    }

    public function show(Request $request, AiConversation $conversation, PatientAiLifecycle $lifecycle): View
    {
        $this->authorizeConversation($conversation);
        abort_if($lifecycle->expired($conversation), 410, 'Conversation expirée.');

        return view('modules.patientai', ['conversations' => null, 'conversation' => $conversation, 'messages' => $conversation->messages()->latest('id')->paginate(40)]);
    }

    public function message(Request $request, AiConversation $conversation, PatientAiChat $chat, PatientAiLifecycle $lifecycle): RedirectResponse
    {
        $this->authorizeConversation($conversation);
        abort_if($lifecycle->expired($conversation), 410, 'Conversation expirée.');
        abort_unless($conversation->status === 'active', 409, 'Accord PatientAI retiré.');
        $data = $request->validate(['content' => 'required|string|max:'.max(1, (int) config('patientai.max_message_length'))]);
        try {
            $chat->send($conversation, $data['content']);
        } catch (Throwable) {
            return back()->withErrors(['patientai' => 'PatientAI est indisponible. Aucun message n’a été enregistré. Réessayez plus tard.']);
        }

        return redirect()->route('patientai.show', $conversation);
    }

    public function clearMemory(Request $request, PatientMemoryService $memory): RedirectResponse
    {
        $this->patient();
        $deleted = $memory->clear();

        return redirect()->route('patientai.index')->with('success', $deleted ? 'Mémoire effacée et désactivée dans toutes vos conversations.' : 'Mémoire désactivée dans toutes vos conversations, sans destruction pendant la suspension de conservation. Après sa levée, demandez à nouveau l’effacement.');
    }

    public function destroy(Request $request, AiConversation $conversation): RedirectResponse
    {
        $this->authorizeConversation($conversation);
        $deleted = DB::transaction(function () use ($conversation): bool {
            $context = $this->contexts->fromAuthenticatedUser(lockClient: true);
            $client = Client::select(['id', 'retention_hold'])->where('tenant_id', $context->tenantId)->whereKey($context->clientId)->firstOrFail();
            $conversation = AiConversation::where('tenant_id', $context->tenantId)->where('user_id', $context->userId)
                ->where('client_id', $context->clientId)->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            abort_unless($context->owns($conversation->tenant_id, $conversation->user_id, $conversation->client_id), 404);
            if ($client->retention_hold) {
                $conversation->update(['status' => 'withdrawn']);
                Access::audit('patientai.accord_retire', $conversation);

                return false;
            }
            Access::audit('patientai.conversation_effacee', $conversation);
            $conversation->delete();

            return true;
        });

        return redirect()->route('patientai.index')->with('success', $deleted ? 'Conversation supprimée ; accord retiré pour cette conversation.' : 'Accord retiré. Les nouveaux messages sont bloqués ; la conversation est conservée pendant la suspension du dossier.');
    }
}
