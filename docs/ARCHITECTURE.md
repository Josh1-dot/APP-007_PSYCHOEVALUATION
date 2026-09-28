# Architecture et couverture

État vérifié le 29 septembre 2026.

## Périmètre

Le document d’audit est utilisé comme description des besoins, pas comme une source de commandes à exécuter. L’application est reconstruite avec Laravel ; aucun ancien code Base44 ni aucune donnée personnelle historique n’a été importé.

Interface Blade responsive, assets locaux sans CDN, sessions Laravel et contrôleurs serveur. `TenantModel` applique un scope de cabinet aux objets métier. En requête web sans identité, ce scope ne retourne aucune donnée. Les commandes d’administration sont locales et ne passent pas par une API publique.

## Droits

| Fonction | Administrateur | Psychologue | Conseiller | Patient | Entreprise |
|---|---|---|---|---|---|
| Dossiers du cabinet, assignation | Oui | Oui | Oui | Son dossier en lecture | Non |
| Notes cliniques | Oui | Oui | Non | Non | Non |
| Réponses / résultats non publiés | Oui | Oui | Oui | Ses réponses ; scores après publication | Non |
| Modifier sa passation en cours | Non | Non | Non | Oui avec consentement | Non |
| Réviser / publier / dépublier | Oui | Oui | Non | Non | Non |
| Créer une version de questionnaire | Oui | Oui | Non | Non | Non |
| Messages | Ses échanges | Ses échanges | Ses échanges | Avec les professionnels | Avec les professionnels |
| Documents | Cabinet | Cabinet | Cabinet | Ceux partagés avec lui | Ceux partagés avec son organisation |
| Espace de travail | Personnel | Personnel | Personnel | Non | Non |
| Comptes et identité cabinet | Oui | Non | Non | Non | Non |

L’administrateur a ici un rôle métier de responsable du cabinet : il dispose donc de l’accès clinique. Créer un rôle d’administrateur purement technique nécessiterait une politique distincte. Aucun dossier n’est automatiquement associé à un compte par simple rapprochement d’e-mail.

Les employés d’une organisation ne donnent **pas** accès à leurs évaluations au portail entreprise. Un document d’entreprise doit être rattaché uniquement à l’organisation, sans `client_id`.

## Modèle

- `Tenant`, `User`, `Organization`, `Client` : cabinet, identités d’accès, partenaires, dossiers.
- `UserInvitation`, `LocalMail`, `PrivacyRequest` : invitations à usage unique, boîte de test chiffrée, demandes et réponses relatives aux droits.
- `Consent` : texte, version, acceptation, retrait. Le retrait ne supprime pas les données historiques.
- `AssessmentDefinition` : famille UUID, version, type, questions, dimensions et règles associées, version de moteur.
- `Assessment` : référence immuable à une version, réponses et résultat instantané chiffrés, dates et statut.
- `Interpretation` : brouillon, source, modèle, prompt, entrée structurée, contenu publié distinct, validateur et date.
- `ClinicalNote`, `Appointment`, `Message`, `Document`, `Letter`, `WorkspaceDocument`, `Comparison`, `AuditLog` : suivi et traçabilité.

Les évaluations Gordon/Ennéagramme, tests personnalisés et questionnaires de besoins sont unifiés dans le modèle versionné. Les dimensions et scores sont des structures JSON versionnées dans la définition et le résultat ; ils ne sont pas dupliqués dans des tables `Dimension` ou `Score` séparées.

Clés étrangères et restrictions de suppression en base. Le scope cabinet et les contrôles de propriété s’exécutent côté Laravel : il ne s’agit **pas** de RLS native MySQL. L’archivage est réversible et désactive le compte patient. L’effacement définitif des contenus et l’anonymisation sont réservés à l’administrateur, après vérification du délai, de l’activité, des suspensions et d’une double confirmation.

## Transitions

```mermaid
stateDiagram-v2
    [*] --> en_cours: Assignation d’une version
    en_cours --> en_cours: Sauvegarde + consentement actif
    en_cours --> termine: Réponses complètes + calcul
    termine --> termine: Brouillon / révision
    termine --> publie: Validation explicite par professionnel habilité
    publie --> publie: Révision du brouillon sans modifier la publication
    publie --> termine: Retrait de publication
```

La soumission verrouille le dossier et la passation dans une transaction ; le retrait de consentement verrouille le même dossier. Les sauvegardes navigateur sont sérialisées et attendues avant soumission. Une soumission répétée ou tardive reçoit HTTP 409. Aucun rôle ne contourne le verrouillage par la route de réponses : une nouvelle assignation conserve l’historique.

## Confidentialité

- Réponses, résultats, notes, messages, courriers, brouillons, comparaisons et fichiers chiffrés avec la clé d’application Laravel.
- Identité et coordonnées restent en clair en base pour le CRM ; archives de sauvegarde chiffrées par secretstream XChaCha20-Poly1305, clé distincte de l’APP_KEY. Le chiffrement du disque et une copie hors machine restent à organiser selon l’hébergement.
- Sessions chiffrées, cookie HttpOnly/SameSite, CSRF, limitation des tentatives de connexion, invalidation logique des comptes désactivés à chaque requête.
- HTML échappé par Blade. Markdown publié avec suppression du HTML brut et refus des liens dangereux.
- Fichiers sous `storage/app/private/vault`, noms de stockage aléatoires ; limites de type et taille. Téléchargement forcé, signature de cinq minutes et contrôle de rôle/propriété même avec la signature.
- Journal des consultations de dossiers et évaluations, mutations principales et téléchargements ; aucune réponse clinique enregistrée dans le journal. Ce journal applicatif n’est pas un journal inviolable externe.
- La publication copie le brouillon validé dans un champ distinct. Les modifications suivantes restent privées jusqu’à une nouvelle publication.

## Couverture et limites explicites

| Domaine de l’audit | Livraison |
|---|---|
| CRM, portails, questionnaires, suivi, consentement | Parcours opérationnels décrits dans le README |
| Gordon | Moteur et validation de grille opérationnels ; référentiel réel à importer |
| Ennéagramme / besoins | Structures opérationnelles ; textes originaux absents, démos étiquetées |
| IA d’interprétation | Adaptateur optionnel, appels simulés dans les tests ; intégration avec un fournisseur réel non validée |
| IA comparateur / conception / courriers | Travail manuel disponible ; automatisations IA spécialisées non implémentées |
| Graphiques | Barres, radars individuels/comparatifs et courbes chronologiques ; historique patient limité aux restitutions publiées |
| PDF individuel, courrier, comparaison | Exports réels avec identité et logo JPEG du cabinet, graphiques selon le rapport |
| Logo, pièces jointes aux courriers | Logo JPEG privé chiffré ; sélection de documents du même patient, PDF et annexes en ZIP |
| Google/Outlook, e-mail externe | Agenda et messagerie internes ; boîte locale de test opérationnelle pour les accès, SMTP à configurer plus tard selon le choix utilisateur. Aucune synchronisation calendrier externe |
| Conservation | Demandes, réponses, export, examen des activités, suspensions motivées et effacement confirmé des dossiers archivés éligibles |
| Sauvegardes et hébergement local | MySQL système, démarrage automatique, sauvegardes chiffrées quotidiennes et restauration testée ; copie hors machine à prévoir |
| Référentiels et validation clinique | Import prêt ; textes et autorisations authentiques restent à fournir par le cabinet |

Les restaurations de dossier ne réactivent pas silencieusement un accès : l’administrateur décide de réactiver le compte. La création de comptes, l’activation/désactivation, l’édition de rôle, les invitations et la récupération du mot de passe sont disponibles. Les messages d’accès passent par la boîte locale privée ; aucun fournisseur réel n’est configuré. Les sessions sont révoquées après modification des droits ou du mot de passe.


## Installation locale et préparation de l’hébergement

Deux bases distinctes existent ; aucune synchronisation n’est implémentée :

- **Locale** : `.env` conserve MySQL système et les données de la démonstration. La préparation Aiven n’a pas remplacé cette configuration.
- **Aiven** : `.env.aiven` permet les opérations depuis cette machine sur MySQL distant. Les cinq migrations ont été appliquées : 28 tables. Le 29 septembre, un contrôle en lecture seule avec vérification du certificat TLS constate un cabinet, un compte utilisateur et aucun dossier patient. L’utilisateur a exécuté `cabinet:install --env=aiven` et signalé sa réussite. La connexion interactive au portail avec ce compte n’a pas été testée.

Les paramètres `.env`, `.env.aiven`, `.env.render` et le certificat sous `storage/app/private` sont exclus de Git ; les fichiers d’environnement et fichiers privés sont aussi exclus du contexte Docker. Aucune clé ni aucun mot de passe ne doit être ajouté aux documents ou au dépôt. La clé APP_KEY préparée pour Render est conservée pour permettre le déchiffrement ; elle n’est pas régénérée au démarrage.

### Ce qui est implémenté pour Render

- `render.yaml` décrit un service web Docker, plan `free`, branche `main`, région `frankfurt`, contrôle de santé `/up` et paramètres secrets à renseigner.
- `docker/entrypoint.sh` adapte Apache à `PORT`, utilise `RENDER_EXTERNAL_URL` lorsque `APP_URL` est absent et décode `AIVEN_CA_BASE64` dans un fichier privé. Il vérifie que le certificat est lisible et fournit son chemin à PDO via `MYSQL_ATTR_SSL_CA`.
- La configuration prévoit des sessions chiffrées en base, un cookie sécurisé, le cache en base et les journaux sur stderr. L’IA est désactivée dans le Blueprint.
- Les migrations et la création du cabinet restent des opérations explicites. Aucun import de dossiers locaux ni création de comptes de démonstration n’est effectué au démarrage du conteneur.

Le contrôle YAML et la syntaxe shell ont réussi, mais **cela ne valide pas un déploiement**. Docker n’est pas disponible sur cette machine ; ni la construction de l’image ni l’exécution Apache dans le conteneur n’ont été testées. Aucun site Render accessible n’a été vérifié. Le dernier envoi GitHub tenté depuis cette session a échoué faute d’authentification ; la présence des changements Render sur la branche distante n’est pas confirmée.

### Ce qui reste avant publication

1. **Documents persistants** : choisir et intégrer un stockage privé durable. Les opérations documentaires utilisent encore `Storage::disk('local')` ; la présence d’une configuration S3 ne constitue pas une intégration opérationnelle. Render gratuit ne conserve pas les fichiers locaux aux redémarrages. Ne pas y déposer de documents à conserver dans cet état.
2. **Déploiement réel** : envoyer les changements GitHub, configurer les secrets Render, construire l’image et vérifier démarrage, HTTPS, authentification, sessions, permissions, PDF et liens signés derrière le proxy.
3. **Exploitation distante** : définir la sauvegarde/restauration de la base Aiven, du futur stockage documentaire et des clés. La crontab locale sauvegarde la base définie dans `.env`, pas automatiquement celle de `.env.aiven`. Aucun planificateur ni worker distant n’a été déployé.
4. **Recette visuelle** : contrôler les parcours ordinateur/mobile et l’auto-sauvegarde dans un navigateur ; aucun test E2E ni test de charge réalisé.
5. **Questionnaires réels** : obtenir les textes, grilles et autorisations nécessaires puis valider leur import. Les contenus de démonstration ne sont pas des référentiels validés.

### Éléments reportés ou non implémentés

- Fournisseur IA réel et aides IA spécialisées aux courriers, comparaisons et questions : activation reportée pendant la préparation de l’hébergement.
- E-mails externes : configuration reportée par l’utilisateur. La boîte de test est limitée à local/testing ; elle ne fonctionnera pas avec `APP_ENV=production`. Le transport SMTP générique existe mais n’a pas été testé avec un fournisseur et les restrictions réseau de Render gratuit nécessitent une solution adaptée.
- Synchronisation Google/Outlook et synchronisation des bases locale/distante : non implémentées.
- Copie des sauvegardes hors machine, audit externe et validation clinique : non réalisés. Les tests techniques ne constituent pas une certification.
