<?php

return [
    'enabled' => (bool) env('PATIENT_AI_ENABLED', false),
    'provider' => 'fake',
    'max_message_length' => (int) env('PATIENT_AI_MAX_MESSAGE_LENGTH', 2000),
    'messages_per_minute' => (int) env('PATIENT_AI_MESSAGES_PER_MINUTE', 10),
    'retention_days' => (int) env('PATIENT_AI_RETENTION_DAYS', 30),
    'consent_version' => 'patientai-v0.1',
    'consent_text' => 'J’accepte d’utiliser PatientAI, assistant de démonstration local. Mes messages sont conservés chiffrés pendant le délai indiqué, exportables et supprimables. Aucun fournisseur externe ne les reçoit. Cet assistant ne fournit pas de diagnostic et ne consulte pas mon dossier clinique.',
    'support_display_name' => env('PATIENT_AI_SUPPORT_DISPLAY_NAME', "Mr l'ingénieur Joshua"),
    'support_role' => env('PATIENT_AI_SUPPORT_ROLE', 'Responsable technique'),
];
