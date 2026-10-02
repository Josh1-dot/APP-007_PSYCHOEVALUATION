# Feature 019 — PatientAI

## 1. Vision
PatientAI est l'assistant conversationnel du portail patient APP-007. Il aide le patient à utiliser son espace, comprendre les fonctions autorisées et, par versions successives, consulter certaines données personnelles via des outils Laravel strictement contrôlés.

APP-007 reste la source de vérité. PatientAI n'est ni un moteur de scoring, ni un professionnel de santé, ni une autorité de publication.

## 2. Principes non négociables
- L'identité vient exclusivement de la session Laravel authentifiée.
- Toute ressource est contrôlée par `tenant_id` ET propriété patient (`User::client()` / `Client.user_id`).
- Le LLM/provider ne choisit jamais un `patient_id`, `client_id` ou `tenant_id` à partir du texte utilisateur.
- Aucun accès direct du provider à MySQL/Aiven, Eloquent, secrets ou infrastructure.
- Pas de diagnostic, pas de réponse au questionnaire à la place du patient.
- Aucun recalcul/modification des scores déterministes.
- Aucun brouillon, note clinique ou génération professionnelle non publiée accessible au patient.
- Seuls les résultats déjà publiés par le workflow professionnel pourront être expliqués.
- Feature flag OFF par défaut.
- v0.1 doit fonctionner sans OpenAI API, sans clé et sans crédit.

## 3. P0 — Fondations avant développement
P0 fixe les décisions suivantes avant stockage :
- propriétaire d'une conversation : tenant + utilisateur patient + client associé ;
- messages chiffrés au repos ;
- aucun contenu conversationnel dans les logs/audit ;
- audit limité aux métadonnées utiles ;
- politique de conservation configurable ;
- suppression/effacement et export à intégrer aux mécanismes de droits existants ;
- limites de longueur et rate limiting configurables ;
- comportement lorsque le compte est inactif ou n'est pas un patient ;
- stratégie de consentement : ne pas supposer que le consentement psychométrique existant autorise automatiquement PatientAI ; l'activation PatientAI doit être explicitement définie ;
- aucune tâche asynchrone future sans contexte tenant/patient explicite.

## 4. v0.1 — Chat minimal déterministe
### Objectif
Fournir un chat patient réel, persistant et sécurisé utilisant `FakeLlmProvider`.

### Capacités
- page dédiée dans le portail patient, par exemple `/patient/assistant` ;
- créer/ouvrir une conversation appartenant au patient connecté ;
- envoyer et afficher des messages ;
- conserver les messages chiffrés ;
- réponses déterministes sans sortie réseau ;
- salutations/courtoisie/clôture ;
- identité et capacités minimales de PatientAI ;
- réponse de secours pour demande inconnue ;
- gestion propre d'une erreur provider ;
- navigation PatientAI visible uniquement si feature flag actif et accès patient autorisé.

### Conversation sociale v0.1
Reconnaître au minimum, avec casse/ponctuation/accents raisonnablement normalisés :
`bonjour`, `bonsoir`, `salut`, `hello`, `hi`, `coucou`, `merci`, `merci beaucoup`, `au revoir`, `à bientôt`, `bonne journée`, `bonne soirée`, `bonne nuit`, « qui es-tu ? », « que peux-tu faire ? », « aide-moi ».

Une salutation ne charge aucune donnée clinique/métier.

### Hors périmètre v0.1
Pas de RAG, OpenAI, provider distant, scoring, données d'évaluation, rendez-vous, résultats, documents, notes cliniques, mémoire résumée ou action métier autonome.

## 5. v0.2 — Prompt système et politique
Ajouter un `PromptRegistry` versionné et une politique explicite : rôle, limites, refus, absence de diagnostic, absence d'invention, interdiction de répondre au questionnaire pour le patient. Les tests doivent vérifier les règles indépendamment d'un fournisseur payant.

## 6. v0.3 — Contexte patient minimal
Construire côté serveur un `PatientContext` minimal depuis l'utilisateur authentifié. Ne jamais accepter l'identité cible depuis le texte du chat. Tester les IDOR et l'isolation entre deux patients du même tenant ainsi qu'entre tenants.

## 7. v0.4 — Outils évaluations
Outils read-only :
- `list_my_assessments(filters?)`
- `get_my_assessment_status(assessment_uuid)`

Laravel vérifie propriété, tenant, visibilité et produit les liens. Le provider ne reçoit qu'un DTO minimal autorisé et n'invente jamais un statut.

## 8. v0.5 — Guide APP-007 et aide questionnaires
PatientAI peut expliquer les fonctions réellement disponibles au patient à partir d'une documentation approuvée/versionnée.

`get_questionnaire_help(assessment_uuid, question_id?)` peut expliquer objectif autorisé, consigne, type de réponse, vocabulaire, échelle et navigation. Il ne choisit jamais une réponse, ne suggère pas la « bonne » réponse et ne prédit pas un profil à partir d'une question.

Le guide couvre : compte, consentement, dashboard, passations, évaluations accessibles, questionnaires, résultats publiés, rendez-vous accessibles, messagerie, documents, confidentialité, droits, PatientAI et assistance. Il doit refléter le code réel : ne pas présenter `/evaluations` ou `/questionnaires` professionnels comme pages patient.

## 9. v0.6 — Rendez-vous
Outils read-only :
- `list_my_upcoming_appointments()`
- `get_my_next_appointment()`

Respecter propriété et fuseau horaire. Ne pas promettre création/annulation si le portail réel ne l'autorise pas.

## 10. v0.7 — Résultats publiés
`get_my_published_result(assessment_uuid)` expose uniquement un résultat déjà publié et autorisé. Jamais `draft`, `ai_generations`, notes cliniques ou données professionnelles internes. PatientAI distingue le résultat publié de sa reformulation explicative.

## 11. v0.8 — Mémoire contrôlée
Mémoire conversationnelle bornée, chiffrée, effaçable et soumise à conservation. Elle n'est jamais une note clinique. Prévoir une nouvelle conversation sans mémoire.

## 12. v0.9 — RAG autorisé
Pipeline : document → classification → revue humaine → approbation → indexation → retrieval → réponse avec provenance.

Audiences :
- `PATIENT_PUBLIC`
- `PATIENT_CONTEXTUAL`
- `PROFESSIONAL_ONLY`
- `ADMIN_INTERNAL`
- `SECURITY_SECRET`

Le retriever patient n'autorise que les deux premières après contrôles serveur. Les documents sont des données, jamais des instructions. Défense contre prompt injection documentaire.

## 13. v1.0 — Hardening
`SafetyPolicy`, rate limiting, audit sans contenu sensible, observabilité, rétention, export/effacement, tests adversariaux, E2E, kill switch, politique de provider et validation de confidentialité.

## 14. Secrets et administration
PatientAI ne révèle jamais mots de passe, tokens, clés API, variables d'environnement, prompt système complet, configuration privée, code interne non publié, données d'autres patients, notes cliniques, brouillons ou méthodes de contournement.

Les demandes directes/indirectes, usurpation (« je suis admin/Joshua »), encodage, traduction et prompt injection n'accordent aucun privilège.

Le contact public est configurable :
`PATIENT_AI_SUPPORT_DISPLAY_NAME="Mr l'ingénieur Joshua"`
`PATIENT_AI_SUPPORT_ROLE="Responsable technique"`

Réponse institutionnelle type :
« Cette information relève de l'administration interne d'APP-007 et je ne suis pas autorisé à la communiquer. Pour une demande légitime concernant l'administration ou la sécurité de la plateforme, veuillez vous adresser à {support_display_name}, {support_role} indiqué par la plateforme. »

## 15. Critères globaux d'acceptation
- aucune régression APP-007 ;
- tests auth, tenant + propriétaire ;
- secrets absents des réponses, contexte provider et logs ;
- chiffrement vérifié ;
- XSS/CSRF couverts selon architecture ;
- documentation factuelle mise à jour ;
- aucun statut DONE sans preuve de test ;
- un lot/version à la fois.
