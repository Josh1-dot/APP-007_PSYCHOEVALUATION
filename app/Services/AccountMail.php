<?php

namespace App\Services;

use App\Models\LocalMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class AccountMail
{
    public function send(User $user, string $subject, string $body): void
    {
        if (config('psycho.mail_delivery') === 'local') {
            if (! app()->environment(['local', 'testing'])) {
                throw new RuntimeException('La boîte de test est interdite hors environnement local. Configurez SMTP.');
            }
            $mail = new LocalMail(['tenant_id' => $user->tenant_id, 'recipient' => $user->email, 'subject' => $subject, 'body' => $body, 'expires_at' => now()->addDays(3)]);
            $mail->save();

            return;
        }
        if (config('psycho.mail_delivery') !== 'smtp') {
            throw new RuntimeException('Canal d’e-mail non configuré.');
        }
        Mail::mailer('smtp')->raw($body, fn ($message) => $message->to($user->email)->subject($subject));
    }

    public function reset(User $user, string $token): void
    {
        $url = rtrim(config('app.url'), '/').'/reinitialiser/'.$token.'?email='.rawurlencode($user->email);
        $this->send($user, 'Réinitialiser votre mot de passe', "Bonjour,\n\nPour choisir un nouveau mot de passe (lien valable une heure) :\n".$url."\n\nSi vous n’avez pas demandé ce changement, ignorez ce message.");
    }
}
