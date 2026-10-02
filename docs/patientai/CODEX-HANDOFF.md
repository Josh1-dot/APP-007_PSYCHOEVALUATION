# Passage de relais — première mission Codex

Tu reprends `APP-007_PSYCHOEVALUATION` comme agent d'implémentation.

Lis d'abord :
- `AGENTS.md`
- `docs/ARCHITECTURE.md`
- `docs/VALIDATION.md`
- `docs/patientai/PATIENTAI-CONSTITUTION.md`
- `specs/019-patientai-assistant/spec.md`
- `specs/019-patientai-assistant/plan.md`
- `specs/019-patientai-assistant/tasks.md`

## Mission
Implémenter **uniquement P0 + PatientAI v0.1**. Ne commence pas v0.2+.

## Objectif
Ajouter au portail patient une page de chat PatientAI minimale, isolée par patient/cabinet, utilisant un `FakeLlmProvider` déterministe et ne nécessitant aucune clé ni aucun crédit OpenAI.

## Contraintes
- feature flag OFF par défaut ;
- aucune connexion OpenAI ;
- aucun accès direct du LLM à MySQL ;
- aucune donnée clinique métier injectée automatiquement dans v0.1 ;
- respecter auth, tenant isolation et conventions APP-007 ;
- ne pas casser les fonctionnalités existantes ;
- ne pas réimplémenter les services existants ;
- ne pas marquer v0.2+ comme implémenté.

## Validation
Ajoute les tests pertinents : auth patient, isolation, persistance conversation/messages, XSS/CSRF selon architecture, provider failure et feature flag.

Avant de terminer :
1. exécute les tests pertinents puis la suite adaptée ;
2. mets à jour `docs/ARCHITECTURE.md` et `docs/VALIDATION.md` avec uniquement ce qui a réellement été implémenté/testé ;
3. mets à jour ROADMAP/TRACEABILITY PatientAI ;
4. termine par un commit Git descriptif.

Retour attendu : fichiers principaux modifiés, tests exécutés/résultats, commit, limites/restant.

## Exigences v2 dès v0.1
Lire `docs/patientai/RESPONSE-POLICY.md`. En v0.1, implémenter au minimum les salutations/courtoisie déterministes sans accès métier inutile. Ne pas anticiper tout le RAG/v1.0, mais conserver l'architecture permettant `ConversationIntentRouter`, classification documentaire et `SafetyPolicy`.
