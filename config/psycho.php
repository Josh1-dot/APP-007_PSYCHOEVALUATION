<?php

return [
    'backup_timezone' => env('BACKUP_TIMEZONE', 'Africa/Kampala'),
    'mail_delivery' => env('PSYCHO_MAIL_DELIVERY', 'local'),
    'consent_version' => '2026-01',
    'consent_text' => 'J’accepte la collecte de mes réponses pour mon accompagnement par le cabinet. Les résultats sont relus par un professionnel avant leur publication. Je peux retirer mon consentement depuis mon profil et contacter le cabinet pour exercer mes droits. Aucun contenu n’est transmis à un fournisseur IA par défaut.',
    'ai_enabled' => (bool) env('AI_ENABLED', false),
    'ai_endpoint' => env('AI_ENDPOINT'), 'ai_key' => env('AI_API_KEY'), 'ai_model' => env('AI_MODEL'),
];
