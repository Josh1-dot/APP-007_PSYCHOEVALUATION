# Plan — Feature 020 PatientAI Conversation v1.1

**État :** plan de spécification pour un lot ultérieur ; aucune tâche d’implémentation démarrée.

## A. Choix d’organisation

Créer Feature 020 dédiée plutôt que modifier rétroactivement Feature 019 : 019 porte l’historique et les garanties v1.0. 020 dépend de 019 et améliore l’interface conversationnelle sans redéfinir son autorisation ni clôturer une recette de production.

## B. Architecture cible

Conserver l’orchestration Laravel. Séparer clairement normalisation/résolution d’intent, SafetyPolicy, dispatcher Laravel fermé, outils read-only existants, DTO minimal et rendu Fake déterministe. Tout remplacement de composant doit conserver ces frontières. Le texte, intent et slots du patient restent non fiables.

## C. Lots d’implémentation proposés

1. **Contrat et tests du routeur** : normalisation Unicode, alias FR/EN, suffixes de salutation, priorité des refus, ambiguïté et fallback ; table d’alias bornée/versionnée, aucune similarité générique pour les intents métier.
2. **Résolveur de références** : candidat par intent, requête Laravel limitée au PatientContext courant, résultat zéro/unique/multiple et présentation sans UUID ; aucune décision de droit dans le résolveur linguistique.
3. **État de conversation borné** : référent structuré issu uniquement d’un DTO/outillage Laravel réussi ; revalidation à chaque tour, effacement/cascade et rétention de la conversation. Ne pas réutiliser la mémoire v0.8.
4. **Orchestration questionnaire et rendez-vous** : intents sémantiques vers outils read-only existants ; follow-up « son statut », « explique-le », « quand ? » uniquement si référent du bon type toujours autorisé.
5. **Réponse minimisante et refus** : `about_my_data` statique sans lecture de données ; textes par catégorie de refus sans révéler l’existence d’une ressource.
6. **Fake provider et renderer** : vérifier que le fake reste déterministe/sans réseau et qu’une sortie fabriquée ne peut pas altérer les faits du DTO.
7. **Régression et recette** : exécuter la matrice de `conversation-test-matrix.md`, tous les tests de sécurité v1.0 et la suite existante ; vérifier flag OFF, aucun effet métier, aucune API externe. Navigateur/E2E et validation de déploiement ne sont pas présumés.

## D. Décisions d’implémentation à confirmer avant tout code

- Localiser le stockage du référent minimal dans le cycle de vie de AiConversation. S’il est persistant, confirmer chiffrement, taille bornée, cascade/effacement et rétention. Si un schéma additif paraît indispensable, documenter et faire approuver la migration séparément avant sa création.
- Définir un rendu de clarification à partir d’informations non cliniques déjà autorisées, en limitant le nombre d’options et en masquant uniformément les ressources interdites/inexistantes.
- Vérifier les noms exacts/contrats des outils v0.4–v0.9 au moment d’implémenter ; ne pas inventer de nouvelles capacités ou colonnes à partir de ce plan.
- Valider si `documentation` doit être distinct de `questionnaire_help` dans le routeur tout en réutilisant la même allowlist documentaire/RAG existante.
- Garder le feature flag et la configuration de provider actuels ; toute évolution d’exploitation requiert une décision séparée.

## E. Validation prévue

- Tests unitaires déterministes routeur/normalisation et tables d’alias.
- Tests Feature de résolution zéro/un/multiples, patient/tenant, visibilité, publication, consentement et follow-ups.
- Tests négatifs SafetyPolicy avant tout outil/provider, absence de données sensibles/UUID technique dans les réponses et logs, aucune mutation métier, aucun HTTP sortant.
- Contrôles effacement/rétention du contexte, mémoire v0.8 inchangée, provider fake, flag ON/OFF.
- Réexécuter les suites PatientAI puis complète. Toute recette réelle Render/Aiven est hors de ce plan de spécification et nécessite autorisation distincte.

## F. Hors périmètre de ce plan

Aucun code, migration, dépendance, configuration d’environnement, donnée Aiven, service Render, clé/API, changement de permission ou déploiement. La présente livraison s’arrête à l’approbation documentaire.