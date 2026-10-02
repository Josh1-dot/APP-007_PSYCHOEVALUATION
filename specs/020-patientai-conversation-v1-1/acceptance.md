# Critères d’acceptation — PatientAI Conversation v1.1

**Statut :** critères spécifiés, non exécutés. Un critère PASS nécessitera des tests lors de l’implémentation.

## AC-01 — Normalisation bornée

- La casse, accents, ponctuation, apostrophes et espaces des phrases explicitement couvertes produisent le même intent attendu.
- « Bonjour PatientAI » et « bonjour PatientsAI » restent `greeting`; « HI » reste reconnu.
- Les fautes ne sont tolérées que par table finie et pour les intentions prévues. Aucune similarité ouverte ne transforme une phrase ambiguë/sensible en intent autorisé.
- Une phrase inconnue ou plusieurs intents incompatibles produisent clarification/fallback sans appel outil.

## AC-02 — Résolution sans privilège

- NLU retourne un intent enum versionné et des slots non fiables, jamais une décision d’autorisation.
- Le dispatcher n’appelle que l’outil allowlisté par l’intent.
- SafetyPolicy s’exécute avant tout outil métier ; une intention interdite mêlée à une intention permise est refusée.
- Pour chaque outil/follow-up, Laravel reconstruit le contexte authentifié et revérifie rôle actif, tenant, client, propriété et règles de visibilité/publication/consentement.

## AC-03 — Résolution sans UUID technique

- Exactement un dossier/assessment/questionnaire autorisé correspondant : réponse normale, UUID absent du texte destiné au patient.
- Plusieurs correspondances : demande de clarification utilisant uniquement des libellés non cliniques déjà autorisés ; pas de sélection arbitraire ni UUID.
- Zéro correspondance : réponse indisponible non énumérable ; aucune distinction qui révèle une ressource étrangère, supprimée, non publiée ou inexistante.
- UUID fourni par utilisateur/browser ne contourne aucune validation et n’est pas requis dans le parcours normal.

## AC-04 — Follow-ups et état conversationnel

- Un follow-up n’utilise qu’un référent créé par le serveur à partir d’un DTO autorisé unique dans la même conversation.
- Un référent d’évaluation ne répond pas à « quand ? » comme rendez-vous, et un rendez-vous ne répond pas à un statut d’évaluation.
- Absence, ambiguïté, changement d’accès, déplacement de tenant, révocation, expiration, suppression ou conversation étrangère invalide le référent et empêche l’outil.
- L’état est borné, sans contenu clinique ni texte libre, ne vient jamais de mémoire v0.8/provider/prompt, suit chiffrement, effacement/cascade et rétention de la conversation et ne prolonge pas sa durée de vie.

## AC-05 — Questionnaire help

- « Explique-moi mon questionnaire » marche seulement si une évaluation/passation autorisée est unique ou si un référent courant valide la désigne.
- Explications limitées à objectif documenté, instruction, navigation, vocabulaire, échelle/fonctionnement et question autorisée.
- Aucune réponse patient n’est transmise à la couche NLU/provider ou exposée par l’aide.
- Toute demande de réponse idéale, choix, prédiction de profil ou manipulation score est refusée avant outil.

## AC-06 — Privacy « Que sais-tu de moi ? »

- `about_my_data` renvoie un texte statique sur les catégories de capacités et exclusions ; aucun outil, query métier, RAG ou lecture de mémoire n’est appelé.
- Le texte ne confirme ni n’infirme une évaluation, un rendez-vous, résultat ou préférence particulière.
- L’accès ultérieur à une catégorie précise exige l’intent spécifique et son autorisation normale.

## AC-07 — Refus sémantiques sans fuite

- Les sept catégories de la taxonomie reçoivent des formulations adaptées, avec notes/brouillons traités comme contenu professionnel privé et secrets comme sécurité/administration.
- Un refus ne confirme pas l’existence d’un dossier ou d’un contenu interdit.
- Prompt injection directe, indirecte, citée, traduite, encodée ou documentaire ne change ni SafetyPolicy, ni le contexte, ni les permissions.

## AC-08 — Intégrité métier et confidentialité

- Outils read-only ; aucun changement aux réponses, rendez-vous, notes, interprétations, scores, publications, mémoire, consentements ou dossiers après les scénarios v1.1.
- DTO exacts et minimaux ; aucun modèle Eloquent complet, identité cible, secret, prompt système ou donnée autre patient dans le provider.
- Aucun texte sensible dans logs, audit, exceptions, flash session ou état de contexte.
- Provider demeure FakeLlmProvider/sans réseau ; falsification de réponse fake est rejetée sans persistance.

## AC-09 — Accès/flag et non-régression

- PatientContextFactory et les protections v1.0 restent inchangés en comportement ; patient inactif, autre rôle, mauvais tenant/client/conversation refusés.
- Flag OFF : pas d’interface ni de données PatientAI, intent tool, ni bypass direct.
- Réexécuter tests adversariaux v1.0 et tests de rétention/export/effacement ; aucune régression.

## AC-10 — Livraison

- Toute migration additive éventuelle est justifiée et revue avant exécution ; aucune migration destructive, aucun déploiement ou accès aux données Aiven dans ce lot.
- Test matrix complet, tests PatientAI ciblés, suite complète et checks de style passent avant d’annoncer un statut PASS.
- Les preuves et limites sont datées ; tests locaux ne sont pas présentés comme recette Render/Aiven.