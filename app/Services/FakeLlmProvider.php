<?php

namespace App\Services;

class FakeLlmProvider implements LlmProvider
{
    public function reply(string $intent): string
    {
        return match ($intent) {
            'greeting' => 'Bonjour ! Je suis PatientAI. Vous pouvez me demander qui je suis ou ce que je peux faire.',
            'courtesy' => 'Je vous en prie.',
            'farewell' => 'À bientôt. Prenez soin de vous.',
            'identity' => 'Je suis PatientAI, l’assistant de démonstration du portail patient. Mes réponses sont prédéfinies ; je ne suis pas un professionnel de santé.',
            'capabilities' => 'Dans cette première version, je peux vous accueillir et présenter mes limites. Je ne consulte pas votre dossier, ne réponds pas aux questionnaires et ne fournis pas de diagnostic.',
            default => 'Cette version ne peut pas traiter cette demande. Je ne consulte aucune donnée métier et ne communique aucune information interne. Pour une demande légitime, contactez '.(config('patientai.support_display_name') ?: 'l’administrateur de la plateforme').', '.(config('patientai.support_role') ?: 'responsable de la plateforme').'.',
        };
    }
}
