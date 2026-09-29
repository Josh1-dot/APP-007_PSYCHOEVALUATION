# Validation effectuée

Mise à jour : 29 septembre 2026.

## Contrôles de préparation Aiven du 29 septembre 2026 (avant le relais)

| Contrôle | Résultat et portée |
|---|---|
| `php artisan test --compact` | **28 tests, 227 assertions réussies**, SQLite en mémoire, 4,29 s. Aucun test destructif sur Aiven ou la base locale applicative. |
| Syntaxe PHP (`php -l`) | 63 fichiers valides dans app, bootstrap (hors cache), config, database, routes et tests. |
| `php artisan view:cache` | Compilation Blade réussie ; ne prouve pas le rendu graphique dans un navigateur. |
| `node --check public/assets/app.js` | Syntaxe JavaScript valide ; pas d’exécution navigateur. |
| `sh -n docker/entrypoint.sh` | Syntaxe shell valide ; le conteneur et ses commandes système n’ont pas été exécutés. |
| Lecture de `render.yaml` avec Symfony YAML | Fichier analysable, service Docker et plan `free` confirmés. Pas de validation par l’API Render ni par son schéma complet. |
| `git check-ignore` | `.env`, `.env.aiven`, `.env.render` et le certificat privé sont ignorés. |
| Connexion PDO à Aiven | Connexion réussie avec CA fourni, vérification du certificat serveur activée et chiffrement TLS constaté. Requêtes de lecture uniquement. |
| Inventaire Aiven | 28 tables, 5 migrations enregistrées, 1 cabinet, 1 utilisateur et 0 dossier patient. Aucun identifiant personnel ni secret affiché dans le compte rendu. |

Le compte Aiven a été créé par l’utilisateur avec `cabinet:install --env=aiven`, qui a annoncé sa réussite. L’inventaire confirme la présence du compte ; sa connexion au portail n’avait pas été testée lors de cette préparation. Le relais utilisateur confirme désormais une authentification réussie en production.

## Validation après le relais Render du 29 septembre 2026

| Contrôle exécuté | Résultat et portée |
|---|---|
| Version installée et Git | Laravel 13.33.0 confirmé par Composer ; dépôt initialement propre à `d277679`, même commit sur `main` distant via `git ls-remote`. |
| `php artisan test --compact tests/Feature/RenderHttpsTest.php` | **3 tests, 13 assertions réussies** : assets HTTPS avec en-têtes de proxy, HTTP local sans ces en-têtes, téléchargement signé HTTPS accepté et URL modifiée refusée. |
| `vendor/bin/pint --dirty --format agent` | Réussi. |
| `php artisan test --compact` | **31 tests, 240 assertions réussies**, SQLite en mémoire, 5,02 s. Aucune mutation de la base Aiven. |
| `sh -n docker/entrypoint.sh` | Réussi après suppression du diagnostic temporaire. Pas de lancement du conteneur local. |
| `node --check public/assets/app.js` | Réussi ; contrôle syntaxique seulement. |
| `php artisan view:cache --no-interaction` | Réussi. |
| Requêtes publiques HTTPS Render | `/up`, `/connexion` : HTTP 200 ; `/` aboutit à `/connexion`. Validation normale du certificat HTTPS par le client HTTP. |
| CSS et JS publics | `/assets/app.css` et `/assets/app.js` : HTTP 200 en HTTPS, empreintes identiques au dépôt. La CSS de connexion est référencée par chemin relatif et résolue en HTTPS. |
| Cookie de session public | `HttpOnly`, `SameSite=Lax` présents ; **`Secure` absent**, écart à corriger dans les variables effectives Render. Valeurs des cookies non affichées. |
| Secrets et certificat | Aucun fichier `.env` réel ni fichier certificat/clé suivi. Les cinq commits de l’historique local ne contiennent ni les valeurs sensibles locales recherchées (au moins 12 caractères), ni leur CA PEM/base64, ni bloc PEM de certificat/clé privée. `.env.example` est un modèle, la clé PHPUnit est factice. Ce contrôle ciblé n’est pas un audit exhaustif de secrets inconnus. |
| CA local et TLS conservé | Empreinte SHA-256 PEM identique à celle du relais. Diff de l’entrypoint limité au diagnostic : décodage de `AIVEN_CA_BASE64`, validation OpenSSL et export de `MYSQL_ATTR_SSL_CA` conservés. |

**Confirmations manuelles de l’utilisateur :** premier déploiement Render réussi, TLS Render → Aiven fonctionnel après correction de `AIVEN_CA_BASE64`, connexion administrateur et tableau de bord testés. L’agent n’a pas rejoué l’authentification ni inspecté les paramètres privés Render. Les requêtes publiques ne suffisent pas à prouver à elles seules le chiffrement du trajet vers MySQL.

**Portée de la correction HTTPS :** `trustProxies(at: '*')` est une API documentée de Laravel 13, adaptée à une entrée par proxy cloud de confiance. Les tests passent avec un transport HTTP interne et `X-Forwarded-Proto: https`, sans imposer artificiellement HTTPS dans le test. Le téléchargement signé traverse le même mécanisme. Aucun test local ne prouve le filtrage réseau effectif de Render. Voir les sources et la limite du joker dans ARCHITECTURE.md.

Le JavaScript n’est pas référencé par la page publique de connexion. Sa disponibilité HTTPS est vérifiée, mais son chargement par le tableau de bord authentifié et l’absence de Mixed Content dans la console du navigateur restent à vérifier. Aucun secret ni compte administrateur n’a été utilisé pour ces contrôles publics.

## Validations antérieures, non réexécutées intégralement aujourd’hui

- Laravel 13.33.0 et PHP 8.3.6 ; dépendances verrouillées. Version Laravel confirmée par Composer lors de la préparation Render.
- Le 25 septembre, **28 tests et 226 assertions** ont réussi sur une base MySQL 8.0 isolée. L’assertion ajoutée ensuite (restauration interdite après anonymisation) est validée sur SQLite. La base MySQL temporaire de tests était indisponible le 28 septembre ; cette suite n’a pas été relancée sur MySQL aujourd’hui.
- Les cinq migrations ont réussi sur la base Aiven MySQL 8.4 lors de sa préparation. L’inventaire a été revérifié aujourd’hui ; les tests fonctionnels complets n’ont pas été exécutés sur MySQL 8.4.
- Les contrôles PHP sur 63 fichiers et YAML ci-dessus appartiennent à la préparation ; ils ne sont pas présentés comme réexécutés pendant le nettoyage.

## Scénarios couverts

1. Authentification, refus des identifiants incorrects, désactivation effective d’un compte.
2. Rendu de toutes les pages professionnelles.
3. Consentement, sauvegarde partielle, refus d’une soumission incomplète, soumission complète et verrouillage.
4. Brouillon invisible au patient, publication explicite, modification du brouillon sans altérer la publication et retrait de restitution.
5. Refus d’accès à un autre cabinet et à un autre patient.
6. Retrait du consentement bloquant les nouvelles réponses.
7. Notes cliniques et publication interdites au conseiller.
8. Chiffrement des fichiers, signature de téléchargement, expiration et contrôle de propriété.
9. Portail entreprise sans accès aux documents individuels des patients.
10. Calcul Gordon déterministe A/B/C/D et normalisation des booléens.
11. Versionnage conservant la définition des passations historiques, rejet d’une grille Gordon invalide.
12. Archivage/restauration et désactivation du compte patient.
13. Agenda, messages, courriers, espace de travail et PDF de courrier.
14. Comparaison, export PDF, création d’un compte et refus d’export pour le patient.
15. Contenu HTML supprimé de l’interprétation Markdown, scores non publiés masqués au patient.
16. IA désactivée par défaut, appel simulé sans identité/réponse libre et absence de publication automatique.

17. Invitations à usage unique, expiration, renvoi, refus pour dossier archivé et messages locaux chiffrés.
18. Récupération du mot de passe, réponse générique et invalidation du lien après utilisation.
19. Modification des rôles, révocation des sessions et isolation des accès administratifs.
20. Logo JPEG dans les PDF, pièces jointes du même patient et contenu ZIP vérifié.
21. Demandes de droits, réponse visible au patient et export sans notes cliniques non autorisées.
22. Effacement soumis au délai, à l’archivage, au mot de passe et à une confirmation exacte ; suspensions et rendez-vous empêchant la purge ; restauration interdite après anonymisation.
23. Références et autorisation des questionnaires, export JSON préservant le contenu.
24. Échappement des légendes des graphiques, historique patient limité aux résultats publiés et PDF.
25. Chiffrement des sauvegardes et refus d’une archive altérée.
26. Assets et téléchargements signés derrière un proxy HTTPS, URL signée altérée refusée, compatibilité HTTP locale.

Plusieurs scénarios sont réunis dans un même test fonctionnel.

## Sauvegardes et exploitation locale — contrôles antérieurs

- Services MySQL, psychoevaluation et cron actifs le 28 septembre ; page de connexion HTTP 200.
- Planificateur Laravel présent dans la crontab utilisateur ; sauvegarde quotidienne à 2 h, heure de Kampala, lorsque la machine est allumée.
- Sauvegarde chiffrée créée et vérifiée le 28 septembre : `psycho-20260928-094447-i5lfxo.psyenc`.
- Restauration réelle testée le 25 septembre dans une nouvelle base isolée : 28 tables importées, déchiffrement des contenus applicatifs réussi, copie de contrôle supprimée ensuite.
- Conservation des archives locales trente jours. Aucune copie hors machine configurée.

## Vérification HTTP réelle — contrôles antérieurs

Lors des vérifications locales des 25–28 septembre : connexion au serveur PHP avec le compte fictif administrateur, redirection vers le tableau de bord, pages patients/évaluations/questionnaires/agenda/messagerie/documents/administration, consultation de passations et téléchargement d’un fichier PDF réel : réponses HTTP 200. Une mutation sans jeton CSRF reçoit HTTP 419.

## Limites de validation

- Aucun navigateur n’est connecté à l’outil de contrôle visuel de cette session : pas de capture d’écran ni de vérification graphique sur téléphone. Les media queries et le HTML sont présents, mais le rendu visuel responsive reste à contrôler dans un navigateur.
- Le comportement JavaScript d’auto-sauvegarde est vérifié syntaxiquement ; les routes et verrouillages sont testés côté serveur. Pas de test E2E navigateur ni de test de charge/concurrence multi-utilisateur.
- Aucune construction Docker locale réalisée. Le déploiement Render est confirmé par le relais et les réponses HTTP publiques ; ce contrôle ne couvre pas toute la configuration du conteneur. La suite fonctionnelle complète n’a pas été exécutée sur MySQL 8.4.
- Aucun appel vers un fournisseur IA réel, aucun e-mail externe ni aucune synchronisation calendrier.
- Tests techniques uniquement : pas de validation des instruments psychométriques, ni audit de conformité ou de sécurité externe.


## Ce qui reste à vérifier ou implémenter

- Intégration d’un stockage privé persistant pour documents et pièces jointes ; le disque local utilisé actuellement ne convient pas à leur conservation sur Render gratuit.
- Publier et déployer le présent nettoyage, puis vérifier le démarrage sans diagnostic temporaire. Le premier déploiement est déjà fonctionnel ; il n’est pas à refaire depuis zéro.
- Corriger `SESSION_SECURE_COOKIE=true` dans Render et vérifier le cookie reçu après redéploiement ; contrôler les valeurs effectives `APP_ENV=production`, `APP_DEBUG=false` et `APP_URL` HTTPS.
- Compléter la recette authentifiée du site : assets et console navigateur sans Mixed Content, sessions, permissions, PDF et téléchargements signés. La connexion et le tableau de bord sont confirmés par l’utilisateur ; les contrôles de proxy supplémentaires sont locaux.
- Sauvegarde/restauration distante complète (Aiven, documents et clés), copie hors machine et tâches planifiées distantes.
- Tests navigateur ordinateur/mobile, comportements JavaScript et charge/concurrence.
- Référentiels autorisés et validation métier/clinique des questionnaires.

## Ce qui est reporté

- Activation et test d’un fournisseur IA réel ; les tests de l’adaptateur utilisent des réponses simulées. Les autres assistants IA spécialisés ne sont pas implémentés.
- Envoi d’e-mails externes, reporté par l’utilisateur : aucun envoi réel validé. La boîte locale de test est indisponible en production ; aucun transport adapté aux restrictions SMTP de Render gratuit n’est configuré.
- Synchronisations Google/Outlook et locale/distante non implémentées ; aucun calendrier de livraison défini.
- Audit externe de sécurité/conformité et certification clinique non réalisés.
