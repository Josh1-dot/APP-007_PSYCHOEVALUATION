# Psychoévaluation — Laravel & MySQL

Application de suivi de cabinet en français, construite à partir de l’audit APP-007. Laravel 13, PHP 8.3+, MySQL 8+, Blade, CSS et JavaScript natifs. Aucun service externe nécessaire pour l’interface. Les dépendances sont verrouillées dans `composer.lock`.

## Essayer la version locale préparée

L’application est accessible sur **http://127.0.0.1:8000** lorsque les processus locaux sont actifs.

| Espace | Identifiant de démonstration |
|---|---|
| Administrateur / professionnel | `admin@demo.test` |
| Patient | `patient@demo.test` |
| Entreprise | `entreprise@demo.test` |
| Conseiller | `conseiller@demo.test` |

Le mot de passe de démonstration est défini par `DEMO_PASSWORD` dans votre fichier `.env` local, qui n’est pas inclus dans Git. Ces comptes et leurs données sont fictifs. L’ajout de démos est bloqué hors environnement `local` ou `testing`.

La configuration `.env` utilise le **MySQL 8.0 installé sur cette machine**, via `/run/mysqld/mysqld.sock`, avec la base `psychoevaluation` et un compte applicatif dédié. PHP et Composer sont également installés. Le service `psychoevaluation` démarre automatiquement avec Linux. Les migrations et données existantes ont été conservées ; aucune dépendance à `/tmp` pour faire fonctionner l’application. Ne pas utiliser les comptes de démo pour des données réelles.

## Installation locale durable effectuée

La migration vers MySQL système a déjà été effectuée et vérifiée. **Aucune réinstallation n’est nécessaire.** Le script utilisé pour cette installation est conservé pour référence :

```bash
sudo bash /home/a-joshua/Desktop/APP-007_PSYCHOEVALUATION/scripts/installer-mysql-local.sh
```

La saisie du mot de passe système se fait exclusivement dans votre terminal. Le script :

1. Vérifie les accès à MySQL et installe les prérequis manquants.
2. Met l’application en maintenance, sauvegarde la base, `.env` et les fichiers privés.
3. Crée une base et un utilisateur dédiés sur `/run/mysqld/mysqld.sock`. Si une base `psychoevaluation` contient déjà des données, il la conserve et choisit un nouveau nom.
4. Importe les données et compare le contenu des tables par nombre de lignes et empreinte SHA-256 avant de modifier `.env`.
5. Conserve `APP_KEY` et les comptes, vérifie le déchiffrement, applique les migrations manquantes.
6. Installe le service local `psychoevaluation`, accessible uniquement sur `127.0.0.1:8000`, et active son démarrage automatique avec MySQL.

Les sauvegardes confidentielles restent dans `storage/app/private/migration-backups/`. Aucun compte ni aucune base existante n’est supprimé. En cas d’échec après la bascule, le script restaure l’ancien `.env` et tente de relancer l’application. Le MySQL temporaire est conservé ; après réussite, il ne sert plus à l’application et n’a pas besoin de redémarrer.

Contrôles ultérieurs : `systemctl status psychoevaluation --no-pager` et `systemctl status mysql --no-pager`. Pour les journaux : `journalctl -u psychoevaluation -n 50 --no-pager`. Le serveur PHP intégré convient à cette installation locale ; un hébergement public exige un serveur web adapté.

Validation effectuée : service local accessible, 28 tables après les nouvelles migrations, sauvegarde chiffrée restaurée dans une base distincte et déchiffrement réussi. La copie temporaire de contrôle a ensuite été supprimée.

## Préparation Render gratuit et Aiven

`render.yaml` décrit un service web Docker sur le plan gratuit. Le démarrage Apache utilise le port fourni par Render et son adresse HTTPS. Le certificat Aiven est transmis par `AIVEN_CA_BASE64`, puis écrit dans le stockage privé au démarrage ; sa lecture est contrôlée et PDO utilise ce certificat pour la connexion TLS.

Les fichiers locaux `.env.aiven` (opérations depuis cette machine) et `.env.render` (paramètres à saisir dans Render) sont privés et exclus de Git et de l’image Docker. Ne pas les publier ni les coller dans une conversation. `.env.render` conserve la clé de chiffrement existante. Les paramètres du serveur Render se saisissent dans **Environment → Add from .env** ; l’URL est déduite automatiquement de `RENDER_EXTERNAL_URL` sauf si `APP_URL` est définie explicitement.

Les tables de la base Aiven ont été créées avec `php artisan migrate --env=aiven --force`. Aucun dossier patient local n’est transféré par cette commande. Pour créer le premier cabinet en ligne, exécuter localement `php artisan cabinet:install --env=aiven` : le nom, l’e-mail et le mot de passe administrateur sont demandés dans le terminal. Il n’existe aucun compte par défaut sur cette nouvelle base. Les futures migrations sont à appliquer explicitement avant un déploiement compatible ; le conteneur ne les exécute pas automatiquement.

**Préparation en cours, pas encore déployée ni validée sur Render.** Docker n’est pas disponible sur cette machine pour construire l’image. Avant publication, choisir et configurer un stockage externe privé pour les documents : Render gratuit efface son disque aux redémarrages et ne propose pas de disque persistant. La version actuelle stocke encore ses documents sur disque local ; elle ne doit pas recevoir de fichiers à conserver sur Render. Les sauvegardes planifiées sur l’ordinateur ne sauvegardent pas automatiquement Aiven. Les e-mails et le planificateur de tâches doivent également être adaptés : la boîte de test est désactivée en production et les ports SMTP habituels sont bloqués sur Render gratuit.

Documentation : [Render gratuit](https://render.com/docs/free), [variables et secrets](https://render.com/docs/configure-environment-variables).

## Installation durable avec Docker et MySQL

Prérequis : Docker Engine avec Compose et Python 3 pour générer les secrets. Docker n’étant pas disponible sur la machine de construction, cette configuration est fournie mais n’a pas été exécutée ici.

Dans une nouvelle copie du projet :

```bash
python3 scripts/configure.py
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan cabinet:install
```

`cabinet:install` demande le nom du cabinet, l’e-mail, le nom de l’administrateur et son mot de passe. Le script ne crée aucun compte par défaut. Ouvrir http://localhost:8000.

Dans **le dossier déjà préparé**, pour garder la configuration locale et créer une configuration Docker indépendante :

```bash
python3 scripts/configure.py .env.docker
APP_ENV_FILE=.env.docker docker compose --env-file .env.docker up -d --build
APP_ENV_FILE=.env.docker docker compose --env-file .env.docker exec app php artisan migrate --force
APP_ENV_FILE=.env.docker docker compose --env-file .env.docker exec app php artisan cabinet:install
```

Libérer le port 8000 du serveur PHP de démonstration avant de lancer Docker, ou changer le port exposé dans `compose.yaml`. Les commandes Docker suivantes doivent utiliser les mêmes variables et le même fichier d’environnement. Les volumes `mysql_data` et `private_storage` conservent la base et les fichiers ; ne pas supprimer ces volumes pour une simple mise à jour.

Pour ajouter des données fictives avec Docker, en environnement local uniquement :

```bash
docker compose exec app php artisan cabinet:demo
```

Le mot de passe est la valeur `DEMO_PASSWORD` générée dans votre fichier d’environnement, et non celui de la démo préparée ici.

## Installation PHP / MySQL sans Docker

Installer PHP 8.3+ avec PDO MySQL, PDO SQLite pour les tests, mbstring, DOM, XML, XMLWriter, ctype, fileinfo, tokenizer, iconv, curl, zip et sodium ; Composer 2, MySQL 8+, `mysqldump`, `tar` et cron pour les sauvegardes. Les logos PDF acceptent le format JPEG, sans nécessiter GD.

```bash
composer install
python3 scripts/configure.py
# Créer la base psychoevaluation et son utilisateur MySQL.
# Ajuster DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME et DB_PASSWORD dans .env.
php artisan migrate
php artisan cabinet:install
php artisan serve --host=127.0.0.1 --port=8000
```

Si `.env` existe, le script refuse de l’écraser. Adapter cette configuration ou générer un fichier distinct. Donner au compte MySQL les droits nécessaires sur **la seule base applicative**. Le répertoire public du serveur web doit être `public/`. Aucune compilation Node n’est nécessaire : les assets sont dans `public/assets`.

## Parcours disponibles

- Cabinet : tableau de bord, dossiers patients modifiables et archivables, organisations, comptes et rôles, audit.
- Questionnaires : éditeur visuel, import/export JSON, références et autorisations d’utilisation, tests personnalisés (texte, choix, échelle), besoins, Ennéagramme déclaratif, grille Gordon. Chaque changement crée une version immuable.
- Passation : assignation, consentement versionné, reprise et sauvegarde automatique, validation des réponses, soumission et verrouillage serveur.
- Restitution : résultats, barres/radars, courbes des passations d’une même version, rédaction manuelle, publication explicite, retrait de publication, PDF avec logo JPEG. L’IA reste désactivée conformément au périmètre demandé.
- Suivi : notes cliniques, calendrier et annulation, messagerie interne, documents privés partagés, courriers PDF avec pièces jointes téléchargeables en ZIP, espace de travail personnel, comparaisons avec instantanés et PDF.
- Portails : patient limité à son dossier et à ses résultats publiés ; entreprise limitée à ses documents d’organisation et messages.

### Accès et boîte locale de test

Dans **Administration**, inviter une personne, rattacher explicitement son dossier ou son organisation, modifier les rôles et révoquer/renvoyer une invitation. Une invitation expire après 48 heures et s’utilise une seule fois. Un dossier archivé ne peut pas activer un compte par invitation.

Le lien **Mot de passe oublié** prépare un lien valable une heure. Les liens se trouvent dans **Administration → Boîte locale de test** ; ils ne sont pas envoyés à une adresse réelle. Les messages sont chiffrés, visibles uniquement par les administrateurs du cabinet, puis purgés après trois jours. La boîte de test est interdite hors environnement local/test. Le changement de mot de passe ou de rôle invalide les anciennes sessions.

`PSYCHO_MAIL_DELIVERY=local` est la configuration par défaut. Pour un futur envoi réel, configurer `PSYCHO_MAIL_DELIVERY=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` et `MAIL_FROM_ADDRESS` dans l’environnement serveur. Aucun compte e-mail externe n’est configuré.

### Exemple de questionnaire personnalisé

Dans **Questionnaires → Créer ou importer une version** :

```json
[
  {"id":"ressenti","label":"Comment vous sentez-vous ?","type":"text","required":true},
  {"id":"energie","label":"Votre niveau d’énergie","type":"scale","min":0,"max":10,"required":true},
  {"id":"format","label":"Format souhaité","type":"choice","options":["Individuel","Collectif"],"required":true}
]
```

Les versions déjà assignées ne sont jamais remplacées. Pour refaire un test, créer **une nouvelle assignation**.

## Référentiels à fournir

L’audit ne fournit ni les 60 libellés Gordon ni leur grille, ni les formulations originales des besoins et de l’Ennéagramme. Aucun questionnaire officiel n’a été reconstitué ou prétendu validé. Les contenus du seeder sont explicitement marqués **démonstration non validée**.

Le moteur `gordon-v1` exige 60 questions booléennes, 15 par dimension A/B/C/D. Une réponse vraie ajoute un point à sa dimension, maximum 15. La grille et les droits d’utilisation doivent être fournis par le cabinet. Exemple d’une question :

```json
{"id":"q1","label":"Libellé autorisé à fournir","type":"boolean","dimension":"A","required":true}
```

Ennéagramme : neuf échelles de 0 à 100, sans diagnostic ni score calculé. Besoins et tests personnalisés : réponses brutes, aucun moteur clinique générique inventé.

## IA optionnelle

Désactivée par défaut. Adaptateur HTTP compatible avec une réponse `choices[0].message.content` :

```dotenv
AI_ENABLED=true
AI_ENDPOINT=https://votre-fournisseur.example/v1/chat/completions
AI_API_KEY=...
AI_MODEL=...
```

La configuration est exclusivement côté serveur. L’appel requiert l’attestation du professionnel. Il transmet le type, la version et les résultats numériques, **sans identité ni réponses textuelles libres**. Les tests sans scores donnent donc un contexte limité. Le modèle, la version de prompt et l’entrée structurée sont conservés, chiffrés. L’appel ne publie jamais et ne change pas le statut de la passation. Le fournisseur réel n’a pas été appelé ; les échanges sont testés avec un faux serveur HTTP.

## Contrôles techniques

```bash
php artisan test
vendor/bin/pint --test
php artisan view:cache
node --check public/assets/app.js
```

Les tests utilisent SQLite en mémoire par défaut. Pour tester MySQL, utiliser **une base vide dédiée** : la suite recrée son schéma.

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=psycho_test \
DB_USERNAME=psycho_test DB_PASSWORD=... php artisan test
```

Résultats et limites de validation : [docs/VALIDATION.md](docs/VALIDATION.md).

## Exploitation

Avant une mise en service réelle : `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`, domaine `APP_URL`, adresse et informations du cabinet, référentiels autorisés et texte d’information adapté. Le texte fourni est un modèle fonctionnel, pas une attestation de conformité.

Sauvegarder ensemble la base MySQL, `storage/app/private` et **APP_KEY** dans un dispositif protégé. Perdre cette clé rend les contenus chiffrés illisibles. Ne jamais régénérer la clé d’une installation contenant des données. Les fichiers ne doivent pas être exposés par `storage:link`.

**Droits et conservation** : le patient dépose une demande d’accès, rectification ou effacement depuis son profil et consulte la réponse. Le professionnel traite ces demandes et exporte le dossier en JSON ; les notes cliniques restent réservées aux professionnels habilités, les résultats non publiés ne sont pas exportés au patient. Les fichiers restent disponibles par leurs téléchargements autorisés.

L’administrateur peut suspendre la purge avec un motif. Un effacement nécessite un dossier archivé, un délai de conservation dépassé sans activité récente ni rendez-vous à venir, le mot de passe administrateur et la confirmation exacte affichée. Les contenus associés sont supprimés et le dossier/compte anonymisés ; les sauvegardes historiques expirent séparément. `php artisan cabinet:retention-review` liste les dossiers éligibles sans les supprimer. Les demandes ne déclenchent aucune suppression automatique.

### Sauvegardes locales

La crontab de cet utilisateur exécute `php artisan schedule:run` chaque minute. Une sauvegarde a lieu à **2 h, heure Africa/Kampala**, si la machine est allumée ; `BACKUP_TIMEZONE` permet de changer le fuseau. Les archives locales chiffrées couvrent MySQL, `.env` (dont APP_KEY) et les fichiers privés, avec manifeste SHA-256 vérifié après déchiffrement. Elles sont conservées trente jours ; les anciennes sauvegardes de migration sont exclues et conservées séparément.

```bash
php artisan cabinet:backup
php artisan schedule:list
php artisan cabinet:backup-verify storage/app/private/backups/NOM.psyenc
php artisan cabinet:backup-extract storage/app/private/backups/NOM.psyenc /chemin/nouveau-dossier-prive
```

La dernière commande vérifie et extrait sans remplacer la base en cours. Le dossier extrait contient `database.sql`, `application.env`, `manifest.json` et `private/`. Pour une restauration complète, importer le SQL dans une nouvelle base dédiée avec `mysql --defaults-extra-file=/chemin/acces-prive.cnf nouvelle_base < database.sql`, remettre les fichiers privés et conserver l’APP_KEY de `application.env`. Adapter les paramètres MySQL, vérifier le déchiffrement avec `php scripts/local-mysql.php decrypt`, puis basculer l’application seulement après validation. La nouvelle base et les accès doivent être préparés au préalable ; ne pas importer par-dessus une base à conserver.

La clé des archives se trouve dans `storage/app/private/backups/.recovery-key` : conserver une copie séparée et protégée de cette clé et des archives. La protection contre une panne du disque nécessite cette copie hors machine ; aucune destination externe n’a été configurée. Pour restaurer sur une autre installation, placer les archives et leur clé dans son dossier privé `backups/` avant l’extraction. Ne jamais remplacer la clé d’une installation possédant ses propres archives.

Les détails des droits et de la couverture se trouvent dans [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md). Le projet n’est pas un dispositif clinique validé ni une certification de conformité.
