<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Client;
use App\Models\ClinicalNote;
use App\Models\Consent;
use App\Models\Document;
use App\Models\Message;
use App\Models\PrivacyRequest;
use App\Services\Access;
use App\Services\Retention;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PrivacyController extends Controller
{
    public function index(Retention $retention): View
    {
        abort_unless(auth()->user()->canPublish(), 403);
        $clients = Client::withTrashed()->whereNull('anonymized_at')->orderBy('last_name')->get()->map(function ($client) use ($retention) {
            $client->review_last_activity = $retention->lastActivity($client);
            $client->review_eligible = $retention->eligible($client);

            return $client;
        });

        return view('modules.privacy', ['requests' => PrivacyRequest::with('client')->latest()->get(), 'clients' => $clients]);
    }

    public function request(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'patient' && $request->user()->client, 403);
        $data = $request->validate(['kind' => 'required|in:acces,rectification,effacement', 'details' => 'required|string|max:10000']);
        $record = PrivacyRequest::create([...$data, 'client_id' => $request->user()->client->id]);
        Access::audit('droits.demande_creee', $record);

        return back()->with('success', 'Votre demande a été enregistrée et transmise au cabinet.');
    }

    public function resolve(Request $request, PrivacyRequest $privacyRequest): RedirectResponse
    {
        Access::publisher();
        $data = $request->validate(['status' => 'required|in:ouverte,en_traitement,traitee,refusee', 'response' => 'required|string|max:10000']);
        $privacyRequest->update([...$data, 'handled_by' => auth()->id(), 'resolved_at' => in_array($data['status'], ['traitee', 'refusee']) ? now() : null]);
        Access::audit('droits.demande_traitee', $privacyRequest);

        return back()->with('success', 'Réponse enregistrée dans le portail du patient.');
    }

    public function hold(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $client = Client::withTrashed()->findOrFail($id);
        $data = $request->validate(['retention_hold' => 'required|boolean', 'retention_note' => 'required|string|max:2000']);
        $client->update($data);
        Access::audit('conservation.suspension_modifiee', $client);

        return back()->with('success', 'Décision de conservation enregistrée.');
    }

    public function erase(Request $request, int $id, Retention $retention): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $client = Client::withTrashed()->findOrFail($id);
        $request->validate(['confirmation' => ['required', Rule::in(['ANONYMISER #'.$id])], 'current_password' => 'required|current_password']);
        $retention->erase($client);

        return back()->with('success', 'Dossier anonymisé et contenus associés effacés. Les sauvegardes historiques restent soumises à leur durée de conservation.');
    }

    public function export(int $id): Response
    {
        $client = Client::withTrashed()->findOrFail($id);
        Access::client($client);
        $professional = auth()->user()->canPublish();
        $assessments = Assessment::where('client_id', $id)->with('definition', 'interpretation')->get()->map(function ($a) use ($professional) {
            return ['id' => $a->id, 'questionnaire' => $a->definition->name, 'version' => $a->definition->version, 'questions' => $a->definition->questions, 'answers' => $a->answers, 'submitted_at' => $a->submitted_at?->toIso8601String(), 'results' => ($professional || $a->status === 'publie') ? $a->results : null, 'interpretation' => $a->interpretation?->published_content];
        });
        $messages = $client->user_id ? Message::where(fn ($q) => $q->where('sender_id', $client->user_id)->orWhere('recipient_id', $client->user_id))->get(['sender_id', 'recipient_id', 'body', 'created_at']) : collect();
        $data = ['exported_at' => now()->toIso8601String(), 'client' => $client->only(['id', 'first_name', 'last_name', 'email', 'phone', 'birth_date', 'reason']), 'consents' => Consent::where('client_id', $id)->get(['version', 'text', 'accepted_at', 'revoked_at']), 'assessments' => $assessments, 'appointments' => Appointment::where('client_id', $id)->get(['title', 'starts_at', 'duration', 'location', 'status']), 'documents' => Document::where('client_id', $id)->when(! $professional, fn ($q) => $q->where('shared', true))->get(['id', 'name', 'mime', 'size']), 'messages' => $messages, 'privacy_requests' => PrivacyRequest::where('client_id', $id)->get(['kind', 'details', 'status', 'response', 'created_at', 'resolved_at'])];
        if ($professional) {
            $data['clinical_notes'] = ClinicalNote::where('client_id', $id)->get(['body', 'created_at']);
        }
        Access::audit('dossier.exporte', $client);

        return response()->streamDownload(fn () => print (json_encode($data,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 'dossier-'.$id.'.json', ['Content-Type' => 'application/json']);
    }
}
