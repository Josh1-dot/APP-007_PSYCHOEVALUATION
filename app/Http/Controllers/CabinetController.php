<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClinicalNote;
use App\Models\Consent;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CabinetController extends Controller
{
    public function dashboard()
    {
        $u = auth()->user();
        if ($u->role === 'entreprise') {
            return view('modules.company', ['organization' => Organization::find($u->organization_id)]);
        }
        $assessments = Assessment::with(['client', 'definition', 'interpretation']);
        $appointments = Appointment::with('client');
        if (! $u->isProfessional()) {
            $id = $u->client?->id ?? 0;
            $assessments->where('client_id', $id);
            $appointments->where('client_id', $id);
        }

        return view('dashboard', ['assessments' => $assessments->latest()->get(), 'appointments' => $appointments->where('status', 'planifie')->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->limit(5)->get(), 'clientCount' => $u->isProfessional() ? Client::count() : 1]);
    }

    public function clients(Request $r)
    {
        Access::professional();
        $q = Client::with('organization')->withCount('assessments');
        if ($r->filled('q')) {
            $s = mb_substr($r->string('q'), 0, 100);
            $q->where(fn ($q) => $q->where('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }

        return view('clients.index', ['clients' => $q->latest()->paginate(12)->withQueryString(), 'organizations' => Organization::all()]);
    }

    public function saveClient(Request $r, ?Client $client = null)
    {
        Access::professional();
        $d = $r->validate(['first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:255', Rule::unique('clients')->where('tenant_id', auth()->user()->tenant_id)->ignore($client?->id)], 'phone' => 'nullable|string|max:40', 'birth_date' => 'nullable|date|before:today', 'organization_id' => 'nullable|integer', 'reason' => 'nullable|string|max:10000']);
        if ($d['organization_id'] ?? null) {
            Organization::findOrFail($d['organization_id']);
        }
        $client = DB::transaction(function () use ($client, $d) {
            if ($client) {
                $client->update($d);
            } else {
                $client = Client::create($d);
            } Access::audit('client.enregistre', $client);

            return $client;
        });

        return redirect()->route('clients.show', $client)->with('success', 'Dossier enregistré.');
    }

    public function client(Client $client)
    {
        Access::client($client);
        Access::audit('client.consulte', $client);

        return view('clients.show', ['client' => $client->load('organization', 'assessments.definition', 'consents'), 'organizations' => Organization::all(), 'notes' => auth()->user()->canPublish() ? ClinicalNote::where('client_id', $client->id)->with('author')->latest()->get() : collect()]);
    }

    public function archive(Client $client)
    {
        Access::professional();
        DB::transaction(function () use ($client) {
            Access::audit('client.archive', $client);
            $client->user?->update(['active' => false]);
            $client->delete();
        });

        return redirect('/clients')->with('success', 'Dossier archivé ; accès patient désactivé.');
    }

    public function note(Request $r, Client $client)
    {
        Access::publisher();
        $d = $r->validate(['body' => 'required|string|max:20000']);
        DB::transaction(function () use ($client, $d) {
            $n = ClinicalNote::create([...$d, 'client_id' => $client->id, 'author_id' => auth()->id()]);
            Access::audit('note.creee', $n);
        });

        return back()->with('success', 'Note confidentielle enregistrée.');
    }

    public function consent(Request $r)
    {
        abort_unless($r->user()->role === 'patient', 403);
        $c = $r->user()->client;
        abort_unless($c, 404);
        $r->validate(['accepted' => 'accepted']);
        DB::transaction(function () use ($c) {
            $c = Client::whereKey($c->id)->lockForUpdate()->firstOrFail();
            if (! $c->hasConsent()) {
                $record = Consent::create(['client_id' => $c->id, 'version' => config('psycho.consent_version'), 'text' => config('psycho.consent_text'), 'accepted_at' => now()]);
                Access::audit('consentement.accepte', $record);
            }
        });

        return back()->with('success', 'Consentement enregistré.');
    }

    public function revoke()
    {
        abort_unless(auth()->user()->role === 'patient', 403);
        $c = auth()->user()->client;
        abort_unless($c, 404);
        DB::transaction(function () use ($c) {
            Client::whereKey($c->id)->lockForUpdate()->firstOrFail();
            $c->consents()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            Access::audit('consentement.retire', $c);
        });

        return back()->with('success', 'Consentement retiré. Les nouvelles réponses sont bloquées.');
    }

    public function organizations()
    {
        Access::professional();

        return view('modules.organizations', ['organizations' => Organization::withCount('clients')->get()]);
    }

    public function organization(Request $r)
    {
        Access::professional();
        $d = $r->validate(['name' => 'required|string|max:200', 'email' => 'nullable|email', 'phone' => 'nullable|string|max:40', 'address' => 'nullable|string|max:1000']);
        $o = Organization::create($d);
        Access::audit('organisation.creee', $o);

        return back()->with('success', 'Organisation créée.');
    }

    public function trash()
    {
        Access::publisher();

        return view('modules.trash', ['clients' => Client::onlyTrashed()->whereNull('anonymized_at')->get()]);
    }

    public function restore(int $id)
    {
        Access::publisher();
        $c = Client::onlyTrashed()->whereNull('anonymized_at')->findOrFail($id);
        $c->restore();
        Access::audit('client.restaure', $c);

        return back()->with('success', 'Dossier restauré. Réactivez le compte dans Administration si nécessaire.');
    }

    public function admin()
    {
        abort_unless(auth()->user()->role === 'admin', 403);

        return view('modules.admin', ['users' => User::where('tenant_id', auth()->user()->tenant_id)->get(), 'clients' => Client::all(), 'invitations' => UserInvitation::with('user')->whereNull('accepted_at')->latest()->get(), 'organizations' => Organization::all(), 'logs' => AuditLog::with('user')->latest()->paginate(25)]);
    }

    public function user(Request $r)
    {
        abort_unless(auth()->user()->role === 'admin', 403);
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users', 'password' => 'required|string|min:12|max:128', 'role' => 'required|in:admin,psychologue,conseiller,patient,entreprise', 'client_id' => 'nullable|integer', 'organization_id' => 'nullable|integer']);
        DB::transaction(function () use ($d) {
            $client = null;
            if ($d['role'] === 'patient') {
                $client = Client::whereNull('user_id')->lockForUpdate()->findOrFail($d['client_id'] ?? 0);
            } if ($d['role'] === 'entreprise') {
                Organization::findOrFail($d['organization_id'] ?? 0);
            }
            $u = User::create(['name' => $d['name'], 'email' => $d['email'], 'password' => $d['password'], 'role' => $d['role'], 'tenant_id' => auth()->user()->tenant_id, 'organization_id' => $d['role'] === 'entreprise' ? $d['organization_id'] : null]);
            if ($client) {
                $client->update(['user_id' => $u->id]);
            } Access::audit('utilisateur.cree', $u);
        });

        return back()->with('success', 'Compte créé. Transmettez ses accès par un canal sûr.');
    }

    public function toggleUser(User $user)
    {
        abort_unless(auth()->user()->role === 'admin' && $user->tenant_id === auth()->user()->tenant_id && $user->id !== auth()->id(), 403);
        abort_if(UserInvitation::where('user_id', $user->id)->whereNull('accepted_at')->exists(), 422, 'Le compte doit accepter son invitation avant activation.');
        $user->update(['active' => ! $user->active, 'auth_version' => $user->auth_version + 1]);
        DB::table('sessions')->where('user_id', $user->id)->delete();
        Access::audit('utilisateur.acces_modifie', $user);

        return back()->with('success', 'Accès modifié.');
    }

    public function settings(Request $r)
    {
        abort_unless(auth()->user()->role === 'admin', 403);
        $d = $r->validate(['name' => 'required|string|max:200', 'email' => 'nullable|email', 'address' => 'nullable|string|max:1000', 'retention_days' => 'required|integer|min:30|max:36500']);
        auth()->user()->tenant->update($d);
        Access::audit('cabinet.modifie', auth()->user()->tenant);

        return back()->with('success', 'Paramètres enregistrés.');
    }
}
