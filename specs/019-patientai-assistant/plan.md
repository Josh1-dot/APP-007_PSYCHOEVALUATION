# Plan — Feature 019 PatientAI

## A. Intégration
Réutiliser sessions Laravel, `ActiveAccount`, `User`, `Client`, `TenantModel`, conventions `Access`/`AuditLog`, Blade et casts `encrypted`. Ajouter des contrôles PatientAI explicites car `TenantModel` seul n'isole pas deux patients du même cabinet.

## B. Configuration
Créer `config/patientai.php` avec feature flag OFF, provider `fake`, limites, rétention et contact public. Aucun secret dans Git.

## C. Provider
Créer `LlmProvider` et `FakeLlmProvider`. Le fake doit être déterministe et ne faire aucun appel réseau. Préserver une abstraction permettant plus tard un provider local ou distant.

## D. Stockage v0.1
Tables dédiées `ai_conversations` et `ai_messages`.
Conversation : UUID, tenant, user, client, statut, timestamps.
Message : conversation, rôle, contenu chiffré, provider/model/prompt version si pertinent, timestamps.
Les clés de propriété sont imposées côté serveur.

## E. Accès
Contrôler rôle patient, compte actif, tenant et propriétaire pour index/show/create/message. Ne pas réutiliser `Access::client()` comme unique protection si celui-ci autorise aussi des professionnels.

## F. ConversationIntentRouter
Intentions v0.1 : `greeting`, `courtesy`, `farewell`, `identity`, `capabilities`, `unknown`.
Préparer sans activer prématurément : `site_help`, `business_tool`, `restricted_internal`.

## G. UI
Page Blade intégrée au portail patient : historique, bulles, formulaire, états d'erreur, échappement sûr, CSRF, navigation conditionnelle au feature flag.

## H. Audit et confidentialité
Auditer événements/métadonnées nécessaires sans texte du message. Ne pas écrire le contenu sensible dans logs/exceptions. Définir l'intégration future avec export/effacement/rétention avant de considérer v0.1 terminé.

## I. Versions ultérieures
v0.2 PromptRegistry/SafetyPolicy ; v0.3 PatientContext ; v0.4 outils évaluations ; v0.5 guide + questionnaire help ; v0.6 rendez-vous ; v0.7 résultats publiés ; v0.8 mémoire ; v0.9 RAG ; v1.0 hardening.

## J. RAG
Documents avec audience, statut, version, checksum, source, approbateur. Seuls documents approuvés et autorisés sont récupérables. Les vérités métier dynamiques viennent des outils APP-007, pas du RAG.

## K. Validation
Tests sans réseau. Tester deux patients du même tenant, tenants différents, patient vs professionnel, compte inactif, feature flag, chiffrement, persistance, XSS/CSRF, provider failure, aucune fuite de contenu, salutations.

## L. Déploiement
Aucune dépendance à OpenAI pour v0.1. Migration d'abord hors production puis Aiven selon procédure existante. Render seulement après tests et revue documentaire.
