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
- [x] PAI-040 PromptRegistry versionné.
- [x] PAI-041 SafetyPolicy de base.
- [x] PAI-042 Tests refus/diagnostic/invention/réponse au questionnaire.

## v0.3 — contexte
- [x] PAI-050 PatientContextFactory minimal.
- [x] PAI-051 DTO sans modèle Eloquent complet.
- [x] PAI-052 Tests IDOR/propriété.

## v0.4 — évaluations
- [x] PAI-060 `list_my_assessments`.
- [x] PAI-061 `get_my_assessment_status`.
- [x] PAI-062 Tests visibilité/statuts/liens serveur.

## v0.5 — guide et questionnaires
- [x] PAI-070 Valider le guide fonctionnel contre le portail réel.
- [x] PAI-071 `get_questionnaire_help`.
- [x] PAI-072 Tests interdiction de choisir/suggérer une réponse.
- [x] PAI-073 Tests fonctions patient réellement disponibles.

## v0.6 — rendez-vous
- [x] PAI-080 Outils rendez-vous read-only.
- [x] PAI-081 Tests propriété/fuseau horaire.

## v0.7 — résultats
- [x] PAI-090 Outil résultat publié.
- [x] PAI-091 Bloquer draft/ai_generations/notes.
- [x] PAI-092 Tests publication et propriété.

## v0.8 — mémoire
- [x] PAI-100 Mémoire bornée/chiffrée.
- [x] PAI-101 Effacement/rétention/nouvelle conversation sans mémoire.

## v0.9 — RAG
- [x] PAI-110 Modèles documents/chunks.
- [x] PAI-111 Workflow draft → review → approved → indexed → retired.
- [x] PAI-112 Classification audience.
- [x] PAI-113 Retriever filtré.
- [x] PAI-114 Provenance.
- [x] PAI-115 Défense prompt injection.
- [x] PAI-116 Tests absence de résultat/information interdite.

## v1.0 — hardening (statut documentaire réel)
- [x] PAI-120 Refus institutionnel et contact public configurable : vérifié localement dans le code et les tests d’anti-usurpation.
- [x] PAI-121 Anti-usurpation : contrôles de contexte, IDOR et refus répétés validés en tests locaux.
- [x] PAI-122 Rate limiting / kill switch : rate limiting serveur et feature flag OFF validés dans la suite locale.
- [x] PAI-123 Tests adversariaux : couverture locale de prompt injection, extraction, usurpation, IDOR et provider tampering.
- [x] PAI-124 Observabilité sans contenu sensible : audit et métadonnées locales vérifiés sans payload sensible.
- [ ] PAI-125 E2E navigateur réel : non exécuté dans cet environnement.
- [ ] PAI-126 Revue finale produit / MySQL / Aiven / scheduler / provider réel : validation production encore requise.

### Statut factuel v1.0
- PatientAI v1.0 code-complete : **PASS**
- PatientAI v1.0 local-test-complete : **PARTIAL**
- PatientAI v1.0 production-ready : **NON**

Le hardening v1.0 est documenté comme code-complete et couvert par la validation locale disponible, sans transformer les validations non exécutées en preuve de production.


## Livraison P0 + v0.1 — 2 octobre 2026
Décisions et limites dans docs/ARCHITECTURE.md ; preuves dans docs/VALIDATION.md. PAI-031 intégré à l'export, l'anonymisation et la purge avec suspensions. 33 cas PatientAI / 237 assertions ; suite 76 / 620. PAI-034 correspond au commit de livraison contenant ce suivi. Aucun statut de v0.2+ modifié ; déploiement et recette navigateur non réalisés.


## Livraison v0.2 — 2 octobre 2026
PAI-040 à 042 implémentées/testées : PromptRegistry patientai-v0.2, SafetyPolicy déterministe avant provider, refus/identité/fallback contrôlés. Tests PatientAI 68/1100 ; suite complète 111/1484. Détails et limites dans docs/ARCHITECTURE.md et docs/VALIDATION.md. Aucun développement v0.3+, aucune tâche v1.0 déclarée terminée.


## Livraison v0.3 — 2 octobre 2026
PAI-050 à 052 implémentées/testées : PatientContextFactory depuis identité Laravel authentifiée, DTO immuable userId/tenantId/clientId, intégration autorisation/orchestration et tests IDOR. Tests contexte 29/302 ; PatientAI 97/1530 ; suite complète 140/1910. Aucun contexte transmis au provider, aucune donnée clinique/évaluation, aucun outil v0.4. Décisions et preuves : docs/ARCHITECTURE.md, docs/VALIDATION.md.


## Livraison v0.4
PAI-060 à 062 implémentées/testées : PatientAssessmentTools, DTO minimaux readonly, statuts/liens Laravel et filtrage tenant/user/client/visibilité. Migration UUID additive sur Assessment réel, routes numériques conservées. Provider fake déterministe sans réseau ; rendu contrôlé pour empêcher l’invention. Voir docs/ARCHITECTURE.md et docs/VALIDATION.md pour règles exactes et preuves. 17 nouveaux cas / 86 assertions ; PatientAI 114/1595 ; suite complète 157/1977. Aucune tâche v0.5+ commencée.


## Livraison v0.5 — 2 octobre 2026
PAI-070 à 073 : guide local approuvé patient-guide-v0.5.1 audité contre routes/controllers/Blade, PatientGuideRegistry/DTO et liens allowlist ; QuestionnaireHelpTool/DTO depuis PatientContext, passation en cours/consentement/version liée et question validée ; SafetyPolicy prioritaire, formatter déterministe et tests fonctions réellement accessibles/IDOR/données inertes. Version active prompt patientai-v0.5, versions antérieures conservées. 40 cas / 346 assertions nouveaux ; PatientAI 154/1936 ; suite 197/2319. Résultats et limites dans docs/VALIDATION.md ; aucune tâche v0.6+ commencée.


## Livraison v0.6 — 2 octobre 2026
PAI-080/081 : PatientAppointmentTools listMyUpcomingAppointments/getMyNextAppointment depuis PatientContext, filtre commun tenant/propriétaire/planifie/début >= maintenant à la seconde, fuseau app.timezone et DTO readonly minimal ; formatter/provider contrôlé et guide sans action. 22 nouveaux cas / 178 assertions ; PatientAI 176/2111 ; suite complète 219/2494. Décisions temporelles/visibilité/limites : docs/ARCHITECTURE.md ; preuves : docs/VALIDATION.md. Aucun v0.7+ commencé.


## Livraison v0.7 — 2 octobre 2026
PAI-090 à 092 : PatientPublishedResultTool depuis PatientContext, publication réelle/tenant/propriétaire/version contrôlés, DTO readonly minimal, faits publiés distincts de l’explication déterministe ; exclusion draft/ai_generations/notes/réponses et contrôle strict du rendu provider. 26 cas / 157 assertions nouveaux ; PatientAI 202/2245 ; suite 245/2628. Preuves/limites dans docs/VALIDATION.md, critères exacts et cycle des anciennes réponses dans docs/ARCHITECTURE.md. Aucun v0.8+ commencé.


## Livraison v0.8 — 2 octobre 2026
PAI-100/101 : mémoire de préférence de présentation explicite/encrypted:array dans AiConversation, consentement dédié, DTO borné et modes avec/sans mémoire ; données métier prioritaires, aucun extracteur de texte libre. Rétention/hold/export/effacement/anonymisation P0 intégrés et testés. 39 nouveaux cas / 402 assertions ; PatientAI 241/2647 ; suite complète 284/3030. Pint/routes/Blade vérifiés. Migration additive créée, non appliquée en base applicative/Aiven. Décisions/bornes/limites : docs/ARCHITECTURE.md ; preuves : docs/VALIDATION.md. Aucun v0.9/RAG commencé, flag OFF.


## Livraison v0.9 — 2 octobre 2026
PAI-110 à 116 : PatientRagDocument/Chunk chiffrés, workflow humain explicitement attesté et index lexical-v0.9 ; 5 audiences, retriever patient filtré tenant/contexte avant chunks, version/checksums et DTO/provenance sûrs. Guide v0.5 importable en brouillons seulement, comportement/version conservés ; outil métier/guide prioritaire et documents comme données inertes. 43 nouveaux cas / 406 assertions ; PatientAI 284/3053 ; suite complète 327/3436. Pint/Blade/commande/diff vérifiés. Migration additive non appliquée, aucun corpus humain approuvé/indexé par l’agent en base applicative. Décisions/bornes/procédure : docs/ARCHITECTURE.md et docs/patientai/knowledge/09-rag-workflow.md ; preuves/limites : docs/VALIDATION.md. Flag OFF, aucun v1.0 commencé.
