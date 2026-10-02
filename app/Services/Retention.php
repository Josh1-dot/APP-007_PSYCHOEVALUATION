<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Client;
use App\Models\ClinicalNote;
use App\Models\Comparison;
use App\Models\Consent;
use App\Models\Document;
use App\Models\Interpretation;
use App\Models\Letter;
use App\Models\LocalMail;
use App\Models\Message;
use App\Models\PrivacyRequest;
use App\Models\Tenant;
use App\Models\UserInvitation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Retention
{
    public function lastActivity(Client $client): Carbon
    {
        $dates = [$client->updated_at, $client->created_at, $client->last_activity_at];
        foreach ([Assessment::class, Appointment::class, ClinicalNote::class, Document::class, Letter::class, Consent::class, PrivacyRequest::class] as $model) {
            $dates[] = $model::where('client_id', $client->id)->max('updated_at');
        }
        $dates[] = Appointment::where('client_id', $client->id)->where('status', 'planifie')->max('starts_at');
        if ($client->user_id) {
            $dates[] = Message::where(fn ($q) => $q->where('sender_id', $client->user_id)->orWhere('recipient_id', $client->user_id))->max('created_at');
        }

        return collect($dates)->filter()->map(fn ($date) => Carbon::parse($date))->sort()->last();
    }

    public function eligible(Client $client): bool
    {
        $tenant = Tenant::findOrFail($client->tenant_id);

        return $client->trashed() && ! $client->anonymized_at && ! $client->retention_hold && $this->lastActivity($client)->lt(now()->subDays($tenant->retention_days));
    }

    public function erase(Client $client): void
    {
        abort_unless($this->eligible($client), 422, 'Le dossier doit être archivé, hors délai de conservation et sans suspension.');
        $paths = [];
        DB::transaction(function () use ($client, &$paths) {
            $client = Client::withTrashed()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->eligible($client), 422);
            app(PatientAiLifecycle::class)->erase($client);
            $ids = Assessment::where('client_id', $client->id)->pluck('id');
            Comparison::where(fn ($q) => $q->whereIn('first_assessment_id', $ids)->orWhereIn('second_assessment_id', $ids))->delete();
            Interpretation::whereIn('assessment_id', $ids)->delete();
            Assessment::whereIn('id', $ids)->delete();
            Letter::where('client_id', $client->id)->delete();
            $paths = Document::where('client_id', $client->id)->pluck('path')->all();
            Document::where('client_id', $client->id)->delete();
            foreach ([ClinicalNote::class, Consent::class, Appointment::class] as $model) {
                $model::where('client_id', $client->id)->delete();
            }
            PrivacyRequest::where('client_id', $client->id)->update(['details' => null, 'response' => null, 'status' => 'traitee', 'resolved_at' => now()]);
            if ($user = $client->user) {
                Message::where(fn ($q) => $q->where('sender_id', $user->id)->orWhere('recipient_id', $user->id))->delete();
                UserInvitation::where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                foreach (LocalMail::all() as $mail) {
                    if ($mail->recipient === $user->email) {
                        $mail->delete();
                    }
                }
                $user->update(['name' => 'Compte anonymisé', 'email' => 'efface-'.Str::uuid().'@example.invalid', 'active' => false, 'password' => Str::random(64), 'remember_token' => null, 'auth_version' => $user->auth_version + 1, 'organization_id' => null]);
            }
            $client->update(['first_name' => 'Dossier', 'last_name' => 'anonymisé #'.$client->id, 'email' => 'efface-'.Str::uuid().'@example.invalid', 'phone' => null, 'birth_date' => null, 'reason' => null, 'organization_id' => null, 'user_id' => null, 'retention_note' => null, 'anonymized_at' => now()]);
            Access::audit('dossier.anonymise', $client);
        });
        foreach ($paths as $path) {
            Storage::disk('local')->delete($path);
        }
    }
}
