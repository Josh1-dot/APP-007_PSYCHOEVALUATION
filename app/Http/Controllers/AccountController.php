<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LocalMail;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Access;
use App\Services\AccountMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    private function admin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function forgot(Request $request, AccountMail $mail): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:255']);
        try {
            Password::sendResetLink(['email' => mb_strtolower(trim($data['email'])), 'active' => true], function (User $user, string $token) use ($mail) {
                $mail->reset($user, $token);
            });
        } catch (\Throwable $e) {
            report(new \RuntimeException('Échec de livraison du message de récupération.'));
        }

        return back()->with('success', 'Si un compte actif correspond à cette adresse, un lien de récupération a été préparé.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email', 'token' => 'required|string', 'password' => 'required|string|min:12|max:128|confirmed']);
        $status = Password::reset([...$data, 'active' => true], function (User $user, string $password) {
            DB::transaction(function () use ($user, $password) {
                $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                abort_unless($user->active, 403);
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'auth_version' => $user->auth_version + 1])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Lien invalide ou expiré. Demandez un nouveau lien.']);
        }

        return redirect('/connexion')->with('success', 'Mot de passe modifié. Vous pouvez vous connecter.');
    }

    public function invite(Request $request, AccountMail $mail): RedirectResponse
    {
        $this->admin();
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users', 'role' => 'required|in:admin,psychologue,conseiller,patient,entreprise', 'client_id' => 'nullable|integer', 'organization_id' => 'nullable|integer']);
        DB::transaction(function () use ($data, $mail) {
            $client = $data['role'] === 'patient' ? Client::whereNull('user_id')->lockForUpdate()->findOrFail($data['client_id'] ?? 0) : null;
            if ($data['role'] === 'entreprise') {
                Organization::findOrFail($data['organization_id'] ?? 0);
            }
            $user = User::create(['tenant_id' => auth()->user()->tenant_id, 'name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => Str::random(64), 'role' => $data['role'], 'active' => false, 'organization_id' => $data['role'] === 'entreprise' ? $data['organization_id'] : null]);
            $client?->update(['user_id' => $user->id]);
            $this->issue($user, $mail);
            Access::audit('invitation.creee', $user);
        });

        return back()->with('success', 'Invitation préparée. Le compte restera inactif jusqu’à son acceptation.');
    }

    private function issue(User $user, AccountMail $mail): void
    {
        $token = Str::random(64);
        UserInvitation::updateOrCreate(['user_id' => $user->id], ['tenant_id' => $user->tenant_id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(2), 'accepted_at' => null]);
        $url = rtrim(config('app.url'), '/').'/invitation/'.$token;
        $mail->send($user, 'Votre invitation au cabinet', "Bonjour,\n\nVotre cabinet vous invite à créer votre mot de passe. Le lien est valable 48 heures :\n".$url);
    }

    public function resend(UserInvitation $invitation, AccountMail $mail): RedirectResponse
    {
        $this->admin();
        abort_if($invitation->accepted_at, 409);
        DB::transaction(function () use ($invitation, $mail) {
            $this->issue($invitation->user, $mail);
        });

        return back()->with('success', 'Nouvelle invitation préparée ; l’ancien lien est invalidé.');
    }

    public function revoke(UserInvitation $invitation): RedirectResponse
    {
        $this->admin();
        abort_if($invitation->accepted_at, 409);
        $invitation->update(['expires_at' => now()->subSecond()]);
        Access::audit('invitation.revoquee', $invitation);

        return back()->with('success', 'Invitation révoquée.');
    }

    public function invitation(string $token): View
    {
        $invite = UserInvitation::withoutGlobalScopes()->where('token_hash', hash('sha256', $token))->whereNull('accepted_at')->where('expires_at', '>', now())->firstOrFail();

        return view('auth.invitation', ['token' => $token, 'name' => $invite->user->name]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate(['password' => 'required|string|min:12|max:128|confirmed']);
        DB::transaction(function () use ($token, $data) {
            $invitation = UserInvitation::withoutGlobalScopes()->where('token_hash', hash('sha256', $token))->whereNull('accepted_at')->where('expires_at', '>', now())->lockForUpdate()->firstOrFail();
            $user = User::whereKey($invitation->user_id)->lockForUpdate()->firstOrFail();
            if ($user->role === 'patient') {
                abort_unless(Client::withoutGlobalScopes()->whereNull('deleted_at')->where('tenant_id', $user->tenant_id)->where('user_id', $user->id)->exists(), 403);
            }
            $user->update(['password' => $data['password'], 'active' => true, 'email_verified_at' => now()]);
            $invitation->update(['accepted_at' => now()]);
        });

        return redirect('/connexion')->with('success', 'Invitation acceptée. Connectez-vous avec votre nouveau mot de passe.');
    }

    public function role(Request $request, User $user): RedirectResponse
    {
        $this->admin();
        abort_unless($user->tenant_id === auth()->user()->tenant_id, 404);
        abort_if($user->id === auth()->id(), 422, 'Votre propre rôle ne peut pas être modifié ici.');
        $data = $request->validate(['role' => 'required|in:admin,psychologue,conseiller,patient,entreprise', 'client_id' => 'nullable|integer', 'organization_id' => 'nullable|integer']);
        DB::transaction(function () use ($user, $data) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $client = null;
            if ($data['role'] === 'patient') {
                $client = Client::where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))->lockForUpdate()->findOrFail($data['client_id'] ?? 0);
            }
            if ($data['role'] === 'entreprise') {
                Organization::findOrFail($data['organization_id'] ?? 0);
            }
            Client::withTrashed()->where('user_id', $user->id)->update(['user_id' => null]);
            $client?->update(['user_id' => $user->id]);
            $user->update(['role' => $data['role'], 'organization_id' => $data['role'] === 'entreprise' ? $data['organization_id'] : null, 'auth_version' => $user->auth_version + 1]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            UserInvitation::where('user_id', $user->id)->whereNull('accepted_at')->update(['expires_at' => now()->subSecond()]);
            Access::audit('utilisateur.role_modifie', $user);
        });

        return back()->with('success', 'Rôle modifié et anciennes sessions fermées. Une invitation en attente doit être renvoyée.');
    }

    public function mailbox(): View
    {
        $this->admin();
        abort_unless(app()->environment(['local', 'testing']), 404);

        return view('modules.mailbox', ['mails' => LocalMail::where('expires_at', '>', now())->latest()->paginate(15)]);
    }
}
