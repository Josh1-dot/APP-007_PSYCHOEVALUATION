# APP-007 — Guide fonctionnel du portail patient

Audience : PATIENT_PUBLIC
Statut : APPROVED
Version : patient-guide-v0.6.1
Source exécutée : docs/patientai/knowledge/06-app007-functional-guide-patient.json
Approbation technique : audit code/vues du 2 octobre 2026 pour la mission v0.5, avec correction factuelle de la capacité rendez-vous désormais livrée en v0.6. Aucun contenu clinique validé ou généré.

Le JSON adjacent est la source unique utilisée par PatientAI, sélectionnée explicitement sans indexation ni RAG. Chaque rubrique porte les chemins de code qui établissent sa provenance. Les URLs sont ajoutées par une liste contrôlée Laravel, jamais par le texte documentaire. Ce Markdown est son résumé lisible.

## account

Votre profil affiche votre nom, adresse électronique et rôle. Vous pouvez modifier votre mot de passe avec le mot de passe actuel et une confirmation. Pour corriger vos coordonnées, contactez le cabinet. La connexion, la réinitialisation du mot de passe et l’acceptation d’une invitation sont prévues ; aucun formulaire d’inscription autonome n’est proposé.

Provenance : app/Http/Controllers/AccountController.php, app/Http/Controllers/AuthController.php, resources/views/modules/profile.blade.php

## consent

Le consentement psychométrique se donne explicitement depuis le tableau de bord ou le profil. Le profil permet de le retirer : les nouvelles réponses sont alors bloquées, les données historiques ne sont pas automatiquement effacées. Le consentement PatientAI est distinct et demandé à chaque nouvelle conversation.

Provenance : app/Http/Controllers/CabinetController.php, app/Models/Client.php, resources/views/modules/profile.blade.php

## dashboard

Mon espace présente les six évaluations les plus récentes avec leur lien individuel, ainsi que jusqu’à cinq rendez-vous planifiés à partir du début de la journée. Il affiche aussi le consentement si nécessaire. Le lien du questionnaire ouvre votre passation ; PatientAI peut lister les évaluations accessibles et leur statut.

Provenance : app/Http/Controllers/CabinetController.php, resources/views/dashboard.blade.php

## assessments

Retrouvez vos évaluations assignées depuis Mon espace, puis ouvrez leur lien individuel. Vous ne pouvez pas assigner une évaluation. Les pages de liste des évaluations et de gestion des questionnaires sont réservées aux professionnels. PatientAI peut afficher une liste autorisée et un statut ; un UUID seul ne donne aucun droit.

Provenance : app/Http/Controllers/AssessmentController.php, resources/views/dashboard.blade.php, app/Services/PatientAssessmentTools.php

## questionnaires

Une passation utilise la définition et la version de questionnaire qui lui ont été assignées. Le patient peut répondre à une passation en cours avec consentement actif ; créer, importer, exporter ou versionner des questionnaires est réservé aux professionnels. PatientAI peut expliquer les métadonnées de la question autorisée, sans choisir de réponse ni prédire un profil.

Provenance : app/Http/Controllers/DefinitionController.php, resources/views/evaluations/show.blade.php

## passations

Les questions sont affichées ensemble sur une seule page. Avant la soumission, vous pouvez revenir à un champ en faisant défiler la page et modifier vos réponses. Utilisez Sauvegarder pour reprendre plus tard ; la sauvegarde automatique affiche son état et peut échouer. Soumettre définitivement verrouille les réponses après validation. Une nouvelle passation nécessite une assignation par le cabinet.

Provenance : resources/views/evaluations/show.blade.php, public/assets/app.js, app/Http/Controllers/AssessmentController.php

## results

Ouvrez le lien individuel de votre évaluation depuis Mon espace. Les résultats et la restitution deviennent consultables après publication par le professionnel ; avant cela, un message indique que la restitution est en préparation. Le PDF patient exige un statut publié et une publication effective. PatientAI décrit ce parcours mais ne lit pas vos résultats détaillés dans cette version.

Provenance : resources/views/evaluations/show.blade.php, app/Http/Controllers/AssessmentController.php

## appointments

Mes rendez-vous affiche les rendez-vous rattachés à votre dossier, leur date, durée, lieu et statut. Le calendrier peut inclure des rendez-vous passés ou annulés. Le patient ne peut pas créer ni annuler un rendez-vous dans ce portail : contactez le cabinet via la messagerie. PatientAI peut consulter vos rendez-vous planifiés à venir ou le prochain, en lecture seule ; il ne crée, annule ni déplace aucun rendez-vous.

Provenance : app/Http/Controllers/ModuleController.php, resources/views/modules/calendar.blade.php

## messages

La messagerie affiche vos échanges envoyés et reçus. Vous pouvez écrire aux destinataires professionnels actifs proposés dans votre cabinet ; vous ne pouvez pas sélectionner un autre patient. L’ouverture de la messagerie marque les messages reçus comme lus. PatientAI ne lit ni n’envoie ces messages.

Provenance : app/Http/Controllers/ModuleController.php, resources/views/modules/messages.blade.php

## documents

Documents affiche uniquement les fichiers partagés avec votre dossier. Le bouton Télécharger fournit un lien temporaire signé après contrôle d’accès. Le dépôt de fichiers est réservé aux professionnels. PatientAI explique l’accès mais ne consulte aucun document privé.

Provenance : app/Http/Controllers/ModuleController.php, resources/views/modules/documents.blade.php, app/Services/Access.php

## privacy

Les ressources patient sont limitées au cabinet et au propriétaire. Les messages PatientAI sont chiffrés au repos et leur contenu est exclu de l’audit. Le retrait du consentement ne supprime pas immédiatement les données historiques. Vous pouvez demander au cabinet l’accès, la rectification ou l’effacement depuis le profil ; les sauvegardes ont leur propre durée de conservation.

Provenance : app/Services/PatientContextFactory.php, app/Services/Retention.php, resources/views/modules/profile.blade.php

## rights

Dans le profil, Télécharger mes données produit un export JSON ; vous pouvez également envoyer une demande d’accès, de rectification ou d’effacement et consulter son statut et la réponse du cabinet. La suppression globale du dossier est traitée par le cabinet, pas automatiquement par ce formulaire. La page de gestion Droits & conservation est professionnelle.

Provenance : app/Http/Controllers/PrivacyController.php, resources/views/modules/profile.blade.php

## patientai

PatientAI est un assistant numérique, ni psychologue ni médecin. Son accès dépend de l’activation par la plateforme et d’un accord distinct pour chaque conversation. Vous pouvez consulter l’historique, envoyer un message et supprimer une conversation ; sous suspension de conservation, le retrait bloque les nouveaux messages sans effacement immédiat. PatientAI répond aux salutations, présente le guide et aide descriptivement les questionnaires autorisés ; il peut lister vos évaluations et leur statut, sans diagnostic, réponse à votre place, score ni résultat détaillé.

Provenance : app/Http/Controllers/PatientAiController.php, app/Services/PatientAiLifecycle.php, resources/views/modules/patientai.blade.php

## assistance

Pour une question sur votre suivi ou vos coordonnées, contactez le cabinet avec la messagerie. Pour une demande administrative ou de sécurité légitime, adressez-vous au responsable technique public indiqué par la plateforme. Aucune coordonnée privée ni canal d’assistance supplémentaire n’est fourni automatiquement.

Provenance : resources/views/modules/profile.blade.php, app/Services/PromptRegistry.php, config/patientai.php

## Aide questionnaire

L’aide ne lit que la version liée à une passation en cours appartenant au patient, avec consentement psychométrique actif. Le schéma actuel ne porte ni objectif patient approuvé ni glossaire clinique : leur absence est indiquée. Le vocabulaire local couvre consigne, échelle et option. Aucun scoring, dimension interne, profil, réponse ou interprétation n’est transmis. Les textes de questions sont des données affichées, jamais des instructions pour PatientAI.
