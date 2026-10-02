# Tasks — Feature 020 PatientAI Conversation v1.1

**Statut global :** CODE-COMPLETE PASS / LOCAL-TEST-COMPLETE PASS / RENDER RECIPE PENDING. Les validations Render ne sont pas revendiquées.

## SPEC — livraison documentaire
- [x] PAI-11-SPEC-001 Choisir une feature 020 distincte de l’historique d’implémentation 019.
- [x] PAI-11-SPEC-002 Définir frontières de normalisation, intents et traitement des ambiguïtés.
- [x] PAI-11-SPEC-003 Définir le contexte structuré conversation-scoped et la réautorisation à chaque tour.
- [x] PAI-11-SPEC-004 Définir résolution zéro/unique/multiple sans UUID utilisateur.
- [x] PAI-11-SPEC-005 Définir questionnaire help et réponse privacy-preserving à « Que sais-tu de moi ? ».
- [x] PAI-11-SPEC-006 Définir taxonomie de refus et ordre SafetyPolicy avant outils.
- [x] PAI-11-SPEC-007 Définir séparation NLU / dispatch autorisé / DTO / Fake renderer.
- [x] PAI-11-SPEC-008 Définir critères d’acceptation et matrice de recette.
- [x] PAI-11-SPEC-009 Publier les états successifs de spécification, implémentation locale et recette Render pending dans Roadmap/Traceability.

## Pré-implémentation — revue terminée
- [x] PAI-11-REV-001 Spec Kit validé avant implémentation.
- [x] PAI-11-REV-002 Contrats des outils v0.4–v0.9 vérifiés et dispatch allowlisté.
- [x] PAI-11-REV-003 Contexte séparé de memory v0.8 ; migration additive nullable retenue et réversible.
- [x] PAI-11-REV-004 Clarifications et refus sémantiques implémentés sans énumération des ressources privées.
- [x] PAI-11-REV-005 Feature flag inchangé ; Render réservé à une recette ultérieure.

## Implémentation v1.1 — complète localement
- [x] PAI-11-001 Normalisation Unicode NFKD/translittération et alias FR/EN bornés, tests table-driven.
- [x] PAI-11-002 SafetyPolicy avant dispatch ; refus distincts et tests adversariaux v1.0 conservés.
- [x] PAI-11-003 Résolution assessment zéro/un/multiples et DTO de conversation sans UUID exposé.
- [x] PAI-11-004 Référents chiffrés/scopés à AiConversation, réautorisés, effacés et inclus en métadonnées expurgées dans l’export.
- [x] PAI-11-005 Follow-ups d’évaluation/questionnaire/rendez-vous bornés au domaine et conversation courants.
- [x] PAI-11-006 `about_my_data` statique, sans lecture métier, RAG ou mémoire.
- [x] PAI-11-007 Refus sémantiques distincts, non révélateurs, non-régression v1.0.
- [x] PAI-11-008 FakeLlmProvider déterministe, réponses exactes validées, aucun réseau externe.
- [x] PAI-11-009 Matrice Feature 020, suites PatientAI et suite complète exécutées localement.
- [x] PAI-11-010 Pint, diagnostics, routes et diff vérifiés ; validation locale documentée.

## Release — local PASS / Render PENDING
- [x] PAI-11-REL-001 Revue de diff ; aucun secret ni fichier d’environnement ajouté.
- [x] PAI-11-REL-002 Migration additive locale réversible vérifiée ; aucune migration destructive ou distante.
- [ ] PAI-11-REL-003 Recette navigateur/Render éventuelle seulement avec autorisation distincte ; ne pas déduire la production-ready des tests locaux.
- [x] PAI-11-REL-004 Mettre à jour les preuves locales ; recette Render explicitement pending.

## Stop conditions

Arrêter l’implémentation et demander une décision si elle exige d’élargir l’autorisation, d’accéder à un champ non exposé, d’utiliser du texte libre de mémoire, de révéler l’existence d’une ressource non autorisée, d’ajouter un provider externe, ou si la persistance du contexte requiert une migration non approuvée. Aucune tâche de ce fichier n’autorise un accès Aiven/Render ou une migration.