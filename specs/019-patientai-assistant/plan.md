

## L. ConversationIntentRouter
Prévoir les intentions `greeting`, `courtesy`, `farewell`, `identity`, `capabilities`, `site_help`, `business_tool`, `restricted_internal`, `unknown`. L'autorisation finale reste dans `SafetyPolicy`.

## M. Documentation et audience
Ajouter `audience` aux documents RAG. Le retriever patient autorise uniquement `PATIENT_PUBLIC` et `PATIENT_CONTEXTUAL` après contrôle du contexte.

## N. Contact institutionnel
Configuration indicative :
`PATIENT_AI_SUPPORT_DISPLAY_NAME="Mr l'ingénieur Joshua"`
`PATIENT_AI_SUPPORT_ROLE="Responsable technique"`

Aucune coordonnée privée n'est injectée automatiquement.

## O. Tests sécurité
Tester extraction de prompt, secrets, configuration, données d'autrui, usurpation de nom, demandes indirectes et prompt injection documentaire. Vérifier aussi qu'aucun secret n'est envoyé au provider.
