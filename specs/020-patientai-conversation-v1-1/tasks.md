# Tasks — Feature 020 PatientAI Conversation v1.1

**Statut global :** SPECIFICATION COMPLETE / IMPLEMENTATION NOT STARTED. Les coches `x` ci-dessous attestent seulement la production des artefacts documentaires de cette mission, pas l’approbation métier ou une validation logicielle.

## SPEC — livraison documentaire
- [x] PAI-11-SPEC-001 Choisir une feature 020 distincte de l’historique d’implémentation 019.
- [x] PAI-11-SPEC-002 Définir frontières de normalisation, intents et traitement des ambiguïtés.
- [x] PAI-11-SPEC-003 Définir le contexte structuré conversation-scoped et la réautorisation à chaque tour.
- [x] PAI-11-SPEC-004 Définir résolution zéro/unique/multiple sans UUID utilisateur.
- [x] PAI-11-SPEC-005 Définir questionnaire help et réponse privacy-preserving à « Que sais-tu de moi ? ».
- [x] PAI-11-SPEC-006 Définir taxonomie de refus et ordre SafetyPolicy avant outils.
- [x] PAI-11-SPEC-007 Définir séparation NLU / dispatch autorisé / DTO / Fake renderer.
- [x] PAI-11-SPEC-008 Définir critères d’acceptation et matrice de recette.
- [x] PAI-11-SPEC-009 Marquer Roadmap et Traceability comme spécifié, non implémenté.

## Pré-implémentation — revue requise
- [ ] PAI-11-REV-001 Revue humaine du spec/plan/acceptance/matrice avec les responsables PatientAI et sécurité.
- [ ] PAI-11-REV-002 Vérifier les contrats réellement présents des outils v0.4–v0.9 et confirmer l’allowlist.
- [ ] PAI-11-REV-003 Décider et documenter le stockage conversationnel borné sans mémoire v0.8 ; évaluer explicitement la nécessité d’une migration additive.
- [ ] PAI-11-REV-004 Approuver les libellés de clarification et refus, y compris non-énumération des ressources.
- [ ] PAI-11-REV-005 Approuver le périmètre d’activation du flag et la procédure de recette séparée.

## Implémentation v1.1 — NOT STARTED
- [ ] PAI-11-001 Normaliseur Unicode et routeur déterministe à alias bornés, tests table-driven FR/EN.
- [ ] PAI-11-002 Priorité de refus SafetyPolicy et tests « demande permise + interdite ».
- [ ] PAI-11-003 Résolveur d’évaluation/questionnaire zéro/un/multiples, sans UUID dans le dialogue normal.
- [ ] PAI-11-004 Référent conversationnel borné, chiffré si persistant, réautorisé et effacé/rétention alignés à AiConversation.
- [ ] PAI-11-005 Suivis assessment/status/questionnaire et appointment/when avec type de référent exact.
- [ ] PAI-11-006 `about_my_data` statique, sans accès métier, RAG ni mémoire.
- [ ] PAI-11-007 Taxonomie de refus sémantiquement adaptée, non révélatrice, régressions v1.0.
- [ ] PAI-11-008 Rendu contrôlé compatible FakeLlmProvider ; vérifier qu’aucun appel externe n’est nécessaire.
- [ ] PAI-11-009 Exécuter intégralement `conversation-test-matrix.md`, tests PatientAI et suite complète.
- [ ] PAI-11-010 Revue de sécurité/privacy, Pint/checks et documentation factuelle après implémentation.

## Release — NOT STARTED
- [ ] PAI-11-REL-001 Revue de diff et confirmation d’absence de secrets/données privées.
- [ ] PAI-11-REL-002 Vérifier toute migration éventuelle en procédure distincte et approbation explicite ; aucune migration destructive.
- [ ] PAI-11-REL-003 Recette navigateur/Render éventuelle seulement avec autorisation distincte ; ne pas déduire la production-ready des tests locaux.
- [ ] PAI-11-REL-004 Mettre à jour les preuves et statuts après validations réellement exécutées.

## Stop conditions

Arrêter l’implémentation et demander une décision si elle exige d’élargir l’autorisation, d’accéder à un champ non exposé, d’utiliser du texte libre de mémoire, de révéler l’existence d’une ressource non autorisée, d’ajouter un provider externe, ou si la persistance du contexte requiert une migration non approuvée. Aucune tâche de ce fichier n’autorise un accès Aiven/Render ou une migration.