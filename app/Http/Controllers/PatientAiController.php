<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\Client;
use App\Services\Access;
use App\Services\PatientAiChat;
use App\Services\PatientAiLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class PatientAiController extends Controller
{
    private function patient(Request $request): Client
    {
        abort_unless(config('patientai.enabled'), 404);
        abort_unless($request->user()->active && $request->user()->role === 'patient', 403);
        $client = $request->user()->client;
        abort_unless($client && $client->tenant_id === $request->user()->tenant_id && ! $client->anonymized_at, 403);

        return $client;
    }

    private function authorizeConversation(Request $request, AiConversation $conversation): void
    {
        $client = $this->patient($request);
        abort_unless($conversation->tenant_id === $request->user()->tenant_id && $conversation->user_id === $request->user()->id && $conversation->client_id === $client->id, 404);
    }

    public function index(Request $request): View
    {
        $client = $this->patient($request);

        return view('modules.patientai', ['conversations' => AiConversation::where('user_id', $request->user()->id)->where('client_id', $client->id)->latest('updated_at')->paginate(20), 'conversation' => null, 'messages' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $client = $this->patient($request);
        $request->validate(['accepted' => 'accepted']);
        $conversation = DB::transaction(function () use ($request, $client): AiConversation {
            $client = Client::whereKey($client->id)->lockForUpdate()->firstOrFail();
            abort_unless($client->user_id === $request->user()->id && ! $client->anonymized_at, 403);
            $conversation = AiConversation::create(['user_id' => $request->user()->id, 'client_id' => $client->id, 'consent_version' => config('patientai.consent_version'), 'consent_text' => config('patientai.consent_text'), 'consented_at' => now()]);
            Access::audit('patientai.conversation_creee', $conversation);

            return $conversation;
        });

        return redirect()->route('patientai.show', $conversation);
    }

    public function show(Request $request, AiConversation $conversation, PatientAiLifecycle $lifecycle): View
    {
        $this->authorizeConversation($request, $conversation);
        abort_if($lifecycle->expired($conversation), 410, 'Conversation expirée.');

        return view('modules.patientai', ['conversations' => null, 'conversation' => $conversation, 'messages' => $conversation->messages()->latest('id')->paginate(40)]);
    }

    public function message(Request $request, AiConversation $conversation, PatientAiChat $chat, PatientAiLifecycle $lifecycle): RedirectResponse
    {
        $this->authorizeConversation($request, $conversation);
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

    public function destroy(Request $request, AiConversation $conversation): RedirectResponse
    {
        $this->authorizeConversation($request, $conversation);
        $deleted = DB::transaction(function () use ($conversation): bool {
            $client = Client::whereKey($conversation->client_id)->lockForUpdate()->firstOrFail();
            $conversation = AiConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            abort_unless($client->user_id === auth()->id() && $conversation->user_id === auth()->id() && $conversation->client_id === $client->id, 404);
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
