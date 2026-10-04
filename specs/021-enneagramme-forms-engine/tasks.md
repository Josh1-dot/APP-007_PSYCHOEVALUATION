# Tasks — Feature 021 Ennéagramme Forms Engine

**Statut au 4 octobre 2026 :** périmètre technique local implémenté et testé ; données DEMO uniquement. Source/licence réelle et approbation psychométrique de contenu restent en attente. Voir docs/VALIDATION.md pour les preuves et limites.

## SPEC — décisions et preuves initiales
- [x] E021-SPEC-001 Auditer les définitions, le moteur, seeds, routes et tests existants.
- [x] E021-SPEC-002 Constater qu’aucune source psychométrique officielle/licenciée n’est présente dans le dépôt.
- [x] E021-SPEC-003 Choisir le snapshot JSON AssessmentDefinition/Assessment, sans seconde banque redondante.
- [x] E021-SPEC-004 Définir les statuts DEMO/DRAFT/REVIEWED/APPROVED, scoring versionné, rotation et égalité.
- [x] E021-SPEC-005 Définir sécurité, PatientAI, matrice d’acceptation et non-objectifs.

## Pré-implémentation
- [x] E021-REV-001 Confirmer que la démo synthétique reste étiquetée DEMO/non validée.
- [ ] E021-REV-002 Confirmer toute source officielle/licenciée avant de pouvoir marquer une forme APPROVED. **Non fournie : aucune forme opérationnelle APPROVED livrée. Garde-fou implémenté/testé, contenu réel en attente.**

## Backend et scoring
- [x] E021-001 Migration additive de métadonnées form/scoring/approval et backfill sûr des définitions Ennéagramme présentes.
- [x] E021-002 Validation stricte des items, dimensions, réponse→points, poids, reverse et provenance.
- [x] E021-003 Workflow versionné, immutabilité des formes utilisées et transitions review/approve auditées.
- [x] E021-004 `enneagramme-weighted-v1` déterministe, normalisé, neuf dimensions, égalités explicites, legacy `self-report-v1` conservé.
- [x] E021-005 Trois formes DEMO versionnées, banque suffisante pour les neuf dimensions et scoring du pipeline.
- [x] E021-006 Rotation serveur au moment de l’assignation, sans répétition avant épuisement et reuse LRU déterministe.
- [x] E021-007 Snapshot historique inchangé et soumission verrouillée sans modification d’items en cours.

## Interfaces et PatientAI
- [x] E021-008 Interface pro expose formes, version, état, provenance et approval actions.
- [x] E021-009 Formulaire patient affiche la forme/version/DEMO, reprend une assignation et soumet selon workflow existant.
- [x] E021-010 Résultat technique par dimension au professionnel ; visibilité patient limitée à la publication.
- [x] E021-011 PatientAI couvre disponibilité, retake non-mutatif, explication de forme et résultat publié uniquement.
- [x] E021-012 Tests adversariaux PatientAI et régressions Features 019/020 sans affaiblissement.

## Validation, documentation, livraison
- [x] E021-013 Tests scoring, égalité, neuf dimensions, déterminisme et règles invalides.
- [x] E021-014 Tests rotation A/B/C, exhaustion/reuse, deux patients, tenant, concurrents/transaction locale.
- [x] E021-015 Tests migration up/down sous SQLite RefreshDatabase, historiques et compatibilité legacy.
- [x] E021-016 Tests Feature workflow patient/pro/consent/lock/published-only.
- [x] E021-017 Tests PatientAI messages, refus manipulation, resultat non publié et outil de résultat publié.
- [x] E021-018 Suite PatientAI, suite complète, Pint, routes, diff/secret checks.
- [x] E021-019 Mettre à jour ROADMAP, TRACEABILITY, ARCHITECTURE, VALIDATION et readiness distincte.
- [x] E021-020 Commit local uniquement ; aucun push, accès Aiven ou déploiement Render.

## Stop conditions

Ne pas marquer `APPROVED` sans source exacte/licence et approbation professionnelle vérifiables. Toute démo reste `DEMO`. Arrêter si le changement exige une modification destructive, un accès Aiven/Render, l’exposition du score privé à PatientAI ou une modification du modèle de permissions 019/020.

## Preuves et limites de livraison

- E021-001–017 : 68 tests / 361 assertions, dont les 11 tests initiaux conservés (57 assertions). Suite PatientAI 312 / 3 360 et suite complète 413 / 4 026 vertes.
- E021-014 : transaction/lock du Client et assignations successives vérifiées localement ; contention/concurrence MySQL réelle non testée, aucun accès distant.
- E021-015 : RefreshDatabase SQLite et roundtrip up/down DatabaseMigrations avec historique existant ; aucune migration sur la base applicative.
- E021-018 : Pint des fichiers livrés PASS ; Pint global révèle deux écarts préexistants dans Backups.php et PatientAiAppointmentTest.php, confirmés sur HEAD et laissés inchangés. Routes/Blade/PDF/diff/scan ciblé secrets validés.
- E021-019 : code readiness et local test readiness PASS ; psychometric/content readiness PENDING ; Aiven/Render/recette réelle PENDING. Aucun statut APPROVED officiel inventé.
- E021-020 : livraison locale seulement ; fichier d’audit de workflow préexistant conservé comme trace historique, aucun push/Aiven/Render.
