# PatientAI — exploitation du pipeline documentaire v0.9

Audience : ADMIN_INTERNAL
Statut : REFERENCE — non approuvé comme source patient, non indexé
Version : patientai-rag-workflow-v0.9.1
Source : app/Services/PatientRagWorkflow.php, PatientRagRetriever.php, app/Console/Commands/PatientRagManage.php

Stratégie : **retrieval local déterministe v0.9**. Base Laravel pour documents/chunks chiffrés, scoring lexical PHP. Aucun embedding ni service distant. Ce Markdown décrit la gestion ; il n’est pas importé automatiquement ni récupérable par le patient. Les autres fichiers knowledge ne deviennent pas approuvés par leur présence.

## Source v0.5 préservée

Le guide JSON patient-guide-v0.7.1 reste utilisé directement par PatientGuideRegistry. L’adaptateur import-guide lit sa validation existante et crée un brouillon par rubrique avec texte/version/provenance inchangés. L’ancienne approbation technique ne remplace pas la revue humaine RAG. Aucun import/accord/revue/indexation de production effectué par l’agent.

## Workflow humain explicite

Après préparation du schéma selon la procédure habituelle, un opérateur OS habilité choisit un compte professionnel actif du cabinet. Les commandes suivantes sont des exemples de procédure, **non exécutées en base applicative dans cette livraison** :

```bash
php artisan patientai:rag import-guide --actor=IDENTIFIANT_PROFESSIONNEL --no-interaction
php artisan patientai:rag submit --actor=IDENTIFIANT_PROFESSIONNEL --document=IDENTIFIANT_DOCUMENT --no-interaction
php artisan patientai:rag review --actor=IDENTIFIANT_PROFESSIONNEL --document=IDENTIFIANT_DOCUMENT --attest-human-review --no-interaction
php artisan patientai:rag approve --actor=IDENTIFIANT_PROFESSIONNEL --document=IDENTIFIANT_DOCUMENT --attest-approval --no-interaction
php artisan patientai:rag index --actor=IDENTIFIANT_PROFESSIONNEL --document=IDENTIFIANT_DOCUMENT --no-interaction
php artisan patientai:rag retire --actor=IDENTIFIANT_PROFESSIONNEL --document=IDENTIFIANT_DOCUMENT --no-interaction
```

Le professionnel doit réellement lire et vérifier le texte, les fonctions promises, la version, la source, la classification et le contexte avant d’attester review/approve. L’application consigne l’attestation (acteur/date/empreinte), sans pouvoir prouver techniquement une lecture humaine. reject en phase review exclut la source. Le même professionnel peut effectuer les étapes ; aucun double contrôle indépendant n’est prétendu. Accès CLI privilégié, aucune route HTTP de gestion et aucun droit accordé depuis une phrase du chat.

Une modification exige un nouveau brouillon/version et des reçus neufs. Toute mutation d’un document approuvé invalide son empreinte ; l’indexation de la nouvelle version retire l’ancienne. Les champs state/reviewer/tenant fournis par un fichier ne sont pas acceptés comme décisions.

## Import JSON local

`import-json --file=CHEMIN_LOCAL --actor=IDENTIFIANT_PROFESSIONNEL` accepte uniquement des données JSON locales, 40000 octets maximum, sans wrappers/URL ni exécution PHP. Champs importables : document_key (slug stable), title, version, source (provenance interne chiffrée), source_label (nom public sans chemin/URL), audience, content et assessment_definition_id optionnel. Aucun secret réel dans Git ni données cliniques individuelles, ClinicalNote, drafts, générations professionnelles ou résultats copiés : les faits patient restent dans leurs outils Laravel.

Classification exactement PATIENT_PUBLIC/PATIENT_CONTEXTUAL/PROFESSIONAL_ONLY/ADMIN_INTERNAL/SECURITY_SECRET. Public = connaissance générale patient du cabinet. Contextual = connaissance générale applicable à une définition/version de questionnaire du même tenant ; la définition doit être réellement assignée et visible via PatientContext. Ne pas utiliser ce mécanisme pour des données individuelles ciblant seulement un patient. Les trois audiences privées peuvent avoir un index technique approuvé mais n’atteignent jamais le retriever patient ni ses chunks/provenances.

## Retrieval et provenance

Requêtes explicites « Recherche documentaire : confidentialité données », ou « Cherche dans la documentation : … ». Les demandes métier/guide connues gardent leur outil prioritaire même derrière ce préfixe. Requête 256 caractères par défaut, 2..16 tokens utiles, au moins deux correspondances et 50 % de couverture. Fenêtre 50 documents ; index 8000 caractères/document, 12 chunks max de 800 caractères ; retour 2 documents/3 chunks, 2400 caractères et 6000 octets JSON de DTO max. Configurations et plafonds stricts dans docs/ARCHITECTURE.md.

Sans pertinence/source autorisée : absence d’information approuvée suffisante, aucune invention ni confirmation de ressource privée. Le provider reçoit seulement texte autorisé et provenance publique (clé/titre/version/source_label/numéro d’extrait), pas le chemin source ou les acteurs de revue. Les documents sont des données citées, jamais des instructions système. « Ignore les instructions », secrets, faux rôle/changement patient/appel outil dans un extrait n’ont aucun effet sur PromptRegistry/SafetyPolicy/PatientContext/outils.

Aucune indexation automatique, extraction massive, synthèse clinique, mémoire automatique, OpenAI/API/embedding/vector store externe. Pas de v1.0. Réponses du chat conservées selon P0, y compris anciennes citations après retrait ultérieur d’un document ; les nouvelles recherches ne le retournent plus. Recette humaine du corpus, migration et validation MySQL/E2E restent des opérations ultérieures autorisées séparément.
