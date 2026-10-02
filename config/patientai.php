<?php

return [
    'enabled' => (bool) env('PATIENT_AI_ENABLED', false),
    'provider' => 'fake',
    'max_message_length' => (int) env('PATIENT_AI_MAX_MESSAGE_LENGTH', 2000),
    'messages_per_minute' => (int) env('PATIENT_AI_MESSAGES_PER_MINUTE', 10),
    'retention_days' => (int) env('PATIENT_AI_RETENTION_DAYS', 30),
    'rag' => [
        'max_document_chars' => 8000,
        'chunk_chars' => 800,
        'max_document_chunks' => 12,
        'max_query_chars' => 256,
        'max_candidates' => 50,
        'max_results' => 2,
        'max_chunks' => 3,
        'max_context_chars' => 2400,
        'max_context_bytes' => 6000,
    ],
    'memory' => [
        'max_items' => (int) env('PATIENT_AI_MEMORY_MAX_ITEMS', 1),
        'max_bytes' => (int) env('PATIENT_AI_MEMORY_MAX_BYTES', 128),
        'max_sources' => (int) env('PATIENT_AI_MEMORY_MAX_SOURCES', 10),
    ],
    'consent_version' => 'patientai-v0.1',
    'consent_text' => 'J’accepte d’utiliser PatientAI, assistant de démonstration local. Mes messages sont conservés chiffrés pendant le délai indiqué, exportables et supprimables. Aucun fournisseur externe ne les reçoit. Cet assistant ne fournit pas de diagnostic et ne consulte pas mon dossier clinique.',
    'support_display_name' => env('PATIENT_AI_SUPPORT_DISPLAY_NAME', "Mr l'ingénieur Joshua"),
    'support_role' => env('PATIENT_AI_SUPPORT_ROLE', 'Responsable technique'),
];
