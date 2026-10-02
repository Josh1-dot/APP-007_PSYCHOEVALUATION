# Tasks — Feature 019 PatientAI

## P0 — consolidation et décisions
- [x] PAI-001 Vérifier routes/auth/portail patient et conventions APP-007.
- [x] PAI-002 Définir propriétaire conversation = tenant + user patient + client.
- [x] PAI-003 Définir chiffrement des messages et interdiction de contenu dans logs/audit.
- [x] PAI-004 Définir rétention configurable, effacement et export des conversations.
- [x] PAI-005 Décider/implémenter la règle d'activation/consentement PatientAI sans réutiliser implicitement le consentement psychométrique.
- [x] PAI-006 Définir limites de longueur/rate limit et erreurs.
- [x] PAI-007 Vérifier que toute future exécution async porte explicitement tenant/patient.
- [x] PAI-008 Mettre à jour docs/ARCHITECTURE.md et docs/VALIDATION.md uniquement avec décisions/validations réelles.

## v0.1 — chat minimal
- [x] PAI-010 Ajouter configuration PatientAI, feature flag OFF par défaut et provider fake.
- [x] PAI-011 Créer contrat `LlmProvider`.
- [x] PAI-012 Implémenter `FakeLlmProvider` déterministe sans réseau.
- [x] PAI-013 Créer migrations conversations/messages.
- [x] PAI-014 Ajouter modèles/relations/casts chiffrés.
- [x] PAI-015 Ajouter contrôles d'accès tenant + propriétaire patient.
- [x] PAI-016 Créer orchestrateur minimal.
- [x] PAI-017 Créer `ConversationIntentRouter`.
- [x] PAI-018 Implémenter salutations, courtoisie, clôture, identité, capacités et fallback.
- [x] PAI-019 Créer routes/contrôleur patient.
- [x] PAI-020 Créer page Blade et navigation conditionnelle.
- [x] PAI-021 Gérer erreur provider sans fuite.
- [x] PAI-022 Auditer uniquement métadonnées autorisées.
- [x] PAI-023 Tests authentification/compte actif/rôle patient.
- [x] PAI-024 Tests isolation entre tenants.
- [x] PAI-025 Tests isolation entre deux patients du même tenant.
- [x] PAI-026 Tests persistance et chiffrement.
- [x] PAI-027 Tests CSRF/XSS selon architecture.
- [x] PAI-028 Tests feature flag.
- [x] PAI-029 Tests panne provider et absence d'appel réseau.
- [x] PAI-030 Tests salutations/variantes raisonnables.
- [x] PAI-031 Vérifier intégration rétention/export/effacement définie en P0.
- [x] PAI-032 Exécuter suite pertinente puis suite complète adaptée.
- [x] PAI-033 Mettre à jour docs/ARCHITECTURE.md et docs/VALIDATION.md factuellement.
- [x] PAI-034 Commit Git descriptif.

## v0.2 — politique/prompt
- [ ] PAI-040 PromptRegistry versionné.
- [ ] PAI-041 SafetyPolicy de base.
- [ ] PAI-042 Tests refus/diagnostic/invention/réponse au questionnaire.

## v0.3 — contexte
- [ ] PAI-050 PatientContextFactory minimal.
- [ ] PAI-051 DTO sans modèle Eloquent complet.
- [ ] PAI-052 Tests IDOR/propriété.

## v0.4 — évaluations
- [ ] PAI-060 `list_my_assessments`.
- [ ] PAI-061 `get_my_assessment_status`.
- [ ] PAI-062 Tests visibilité/statuts/liens serveur.

## v0.5 — guide et questionnaires
- [ ] PAI-070 Valider le guide fonctionnel contre le portail réel.
- [ ] PAI-071 `get_questionnaire_help`.
- [ ] PAI-072 Tests interdiction de choisir/suggérer une réponse.
- [ ] PAI-073 Tests fonctions patient réellement disponibles.

## v0.6 — rendez-vous
- [ ] PAI-080 Outils rendez-vous read-only.
- [ ] PAI-081 Tests propriété/fuseau horaire.

## v0.7 — résultats
- [ ] PAI-090 Outil résultat publié.
- [ ] PAI-091 Bloquer draft/ai_generations/notes.
- [ ] PAI-092 Tests publication et propriété.

## v0.8 — mémoire
- [ ] PAI-100 Mémoire bornée/chiffrée.
- [ ] PAI-101 Effacement/rétention/nouvelle conversation sans mémoire.

## v0.9 — RAG
- [ ] PAI-110 Modèles documents/chunks.
- [ ] PAI-111 Workflow draft → review → approved → indexed → retired.
- [ ] PAI-112 Classification audience.
- [ ] PAI-113 Retriever filtré.
- [ ] PAI-114 Provenance.
- [ ] PAI-115 Défense prompt injection.
- [ ] PAI-116 Tests absence de résultat/information interdite.

## v1.0 — hardening
- [ ] PAI-120 Refus institutionnel et contact public configurable.
- [ ] PAI-121 Anti-usurpation.
- [ ] PAI-122 Rate limiting/kill switch.
- [ ] PAI-123 Tests adversariaux.
- [ ] PAI-124 Observabilité sans contenu sensible.
- [ ] PAI-125 E2E sécurité/UX.
- [ ] PAI-126 Revue finale rétention/export/effacement/confidentialité.


## Livraison P0 + v0.1 — 2 octobre 2026
Décisions et limites dans docs/ARCHITECTURE.md ; preuves dans docs/VALIDATION.md. PAI-031 intégré à l'export, l'anonymisation et la purge avec suspensions. 33 cas PatientAI / 237 assertions ; suite 76 / 620. PAI-034 correspond au commit de livraison contenant ce suivi. Aucun statut de v0.2+ modifié ; déploiement et recette navigateur non réalisés.
