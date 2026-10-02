# Plan — Feature 020 PatientAI Conversation v1.1

**État :** implémentation locale réalisée conformément au plan ; recette Render non exécutée.

## A. Choix d’organisation

Créer Feature 020 dédiée plutôt que modifier rétroactivement Feature 019 : 019 porte l’historique et les garanties v1.0. 020 dépend de 019 et améliore l’interface conversationnelle sans redéfinir son autorisation ni clôturer une recette de production.

## B. Architecture cible

Conserver l’orchestration Laravel. Séparer clairement normalisation/résolution d’intent, SafetyPolicy, dispatcher Laravel fermé, outils read-only existants, DTO minimal et rendu Fake déterministe. Tout remplacement de composant doit conserver ces frontières. Le texte, intent et slots du patient restent non fiables.

## C. Lots d’implémentation réalisés localement

1. **Contrat et tests du routeur** : normalisation Unicode, alias FR/EN, suffixes de salutation, priorité des refus, ambiguïté et fallback ; table d’alias bornée/versionnée, aucune similarité générique pour les intents métier.
2. **Résolveur de références** : candidat par intent, requête Laravel limitée au PatientContext courant, résultat zéro/unique/multiple et présentation sans UUID ; aucune décision de droit dans le résolveur linguistique.
3. **État de conversation borné** : référent structuré issu uniquement d’un DTO/outillage Laravel réussi ; revalidation à chaque tour, effacement/cascade et rétention de la conversation. Ne pas réutiliser la mémoire v0.8.
4. **Orchestration questionnaire et rendez-vous** : intents sémantiques vers outils read-only existants ; follow-up « son statut », « explique-le », « quand ? » uniquement si référent du bon type toujours autorisé.
5. **Réponse minimisante et refus** : `about_my_data` statique sans lecture de données ; textes par catégorie de refus sans révéler l’existence d’une ressource.
6. **Fake provider et renderer** : vérifier que le fake reste déterministe/sans réseau et qu’une sortie fabriquée ne peut pas altérer les faits du DTO.
7. **Régression et recette locale** : matrice et suites v1.0 exécutées sans réseau ; flag/configuration conservés. Aucun E2E, validation Aiven ou recette Render revendiqués.

## D. Décisions retenues

- Le référent minimal est stocké dans `AiConversation.conversation_context`, chiffré par cast et effacé avec la ligne ; migration additive `2026_10_02_120000_add_conversation_context_to_ai_conversations`, rollback limité à la colonne ajoutée.
- Les clarifications ne présentent que questionnaire/statut libellés issus des DTO autorisés ; aucune ressource interdite/inexistante n’est distinguée.
- Les contrats v0.4–v0.9 sont réutilisés ; aucun nouvel accès métier ni mutation ajouté.
- `documentation` distingue le guide approuvé des recherches RAG explicites ; les demandes métiers connues gardent priorité sur le wrapper documentaire.
- Feature flag inchangé ; provider `fake` uniquement ; toute mise en service Render demeure une décision/recette distincte.

## E. Validation locale exécutée / distante pending

- Tests unitaires déterministes routeur/normalisation et tables d’alias.
- Tests Feature de résolution zéro/un/multiples, patient/tenant, visibilité, publication, consentement et follow-ups.
- Tests négatifs SafetyPolicy avant tout outil/provider, absence de données sensibles/UUID technique dans les réponses et logs, aucune mutation métier, aucun HTTP sortant.
- Contrôles effacement/rétention du contexte, mémoire v0.8 inchangée, provider fake, flag ON/OFF.
- `tests/Feature/PatientAiConversationTest.php` : 14 tests / 115 assertions ; `php artisan test --compact --filter=PatientAi` : 300 tests / 3 251 assertions ; `php artisan test` : 345 tests / 3 660 assertions.
- Pint et suite ciblée après formatage passés ; aucun appel HTTP externe. Route list : six routes PatientAI existantes.
- Recette réelle Render/Aiven et navigateur/E2E non exécutées ; elles restent pending et ne découlent pas des tests locaux.

## F. Hors périmètre

Aucun appel OpenAI/provider réel, aucune donnée ou migration Aiven, aucun déploiement Render, aucune clé/API, aucun changement du modèle de permission. La migration additive existe dans le dépôt mais n’a été appliquée que dans les bases temporaires des tests.