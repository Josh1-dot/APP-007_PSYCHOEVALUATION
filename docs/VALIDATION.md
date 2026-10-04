# Validation effectuée

Mise à jour : 1 octobre 2026.

## Feature 009 — correction depuis e71138c

```yaml
Workflow IA interne : testé avec fake/mock
Persistance/versionnement : testé (générations réussies distinctes)
Révision humaine : testée
Publication : testée
Fournisseur OpenAI réel : NON TESTÉ / BLOQUÉ faute de crédits API
```

- `php artisan test --compact tests/Feature/AiInterpretationHistoryTest.php` : **12 tests/cas, 143 assertions réussies**, 1,26 s, SQLite en mémoire.
- `vendor/bin/pint --dirty --format agent` : réussi.
- `php artisan test --compact` : **43 tests, 383 assertions réussies**, 5,12 s. Les 31 tests préexistants restent verts.
- `php artisan view:cache --no-interaction` et `git diff --check` : réussis.
- Aucun appel IA réel : endpoint fictif et réponses déterministes via Http::fake ; Http::preventStrayRequests interdit les sorties non simulées de la nouvelle suite. Aucune clé ni crédit requis.

### Preuves persistées

Admin et psychologue : génération A conservée, édition B sans modification de A, publication B sans modification de A, régénération C ajoutée sans modification de A ni de la publication B/date/validateur. Messages exacts, modèle demandé/retourné, identifiant réponse, demandeur, date, prompt et snapshot conservés. Réponse modèle absente stockée null ; original intégral même au-delà de la limite du brouillon.

Chiffrement du champ vérifié en base, historique exclu de toArray, affichage échappé, invisibilité patient avant/après publication et régénération, absence dans export patient, refus des rôles non autorisés et d’un autre cabinet. Audit de génération vérifié, erreurs HTTP/contenu invalide simulées ne modifient aucun champ existant ; panne de connexion simulée ne crée aucune interprétation.

### Migration et limites

`2026_10_01_160120_add_ai_generations_to_interpretations_table.php` ajoute une colonne nullable, sans backfill ni modification des anciennes migrations. Test sur schéma préexistant et ligne ancienne : valeurs chiffrées et champs historiques inchangés, original inconnu conservé comme inconnu ; une nouvelle génération crée seulement son propre enregistrement. Ce test a d’abord révélé une comparaison de l’objet créé sans relecture des valeurs par défaut ; l’assertion a été corrigée pour comparer la ligne réellement persistée avant/après. Résultats ci-dessus obtenus après correction.

**Validation de déploiement communiquée par le propriétaire après le commit `0d2deb0` :**

- `php artisan migrate --env=aiven` exécuté avec succès.
- Migration `2026_10_01_160120_add_ai_generations_to_interpretations_table` appliquée sur Aiven en **batch 2** ; `php artisan migrate:status --env=aiven` confirme **[2] Ran**.
- Commit `0d2deb0` poussé sur `main` ; Render a redéployé cette version avec le statut **Deploy succeeded**.

Ces opérations ont été effectuées par le propriétaire et ne sont pas rejouées lors de cette mise à jour documentaire. Elles confirment l’application de la migration et le déploiement, pas une exécution des tests fonctionnels sur MySQL ni un appel fournisseur réel. Diff documentaire vérifié ; tests applicatifs non relancés, aucun comportement modifié.

MySQL 8.4 isolé non testé, concurrence réelle non testée. Aucun modèle fournisseur/version réel validé. L’historique technique n’archive pas toutes les révisions humaines et ne reconstitue pas les sorties anciennes déjà perdues. Les assistants IA spécialisés restent non implémentés. **009 passe de CONTRADICTS à PARTIAL, pas DONE ; 010 conserve son workflow.** Les sections suivantes décrivent la baseline historique avant cette correction.

## Audit de convergence Spec Kit — 1 octobre 2026

- Lecture des 18 triplets spec/plan/tasks et des trois documents de gouvernance dans la copie externe identifiée dans [SPEC-CONVERGENCE.md](SPEC-CONVERGENCE.md). Ils n’étaient pas présents dans le dépôt Laravel ; leurs empreintes figurent dans l’audit.
- Code audité : `990f470`. Comparaison statique des modèles, migrations, contrôleurs, services, vues, routes et assertions existantes. Documentation seule modifiée.
- Livrables contrôlés : 18 sections feature et 97 tâches classées ; liens vers fichiers existants vérifiés. Aucun fichier applicatif modifié. Aucun fichier environnement privé/certificat/clé suivi ; aucun des secrets locaux recherchés (valeurs sensibles d’au moins 12 caractères) retrouvé dans les cinq documents. Vérification ciblée, pas audit exhaustif de secrets inconnus.
- `composer show --direct` : Laravel 13.33.0, DomPDF wrapper 3.1.2, PHPUnit 12.5.36.
- `php artisan route:list --except-vendor --no-interaction` : 72 routes applicatives listées.
- `php artisan test --compact` : **31 tests, 240 assertions réussies, 6,41 s**, SQLite en mémoire. Suite inchangée.
- Requêtes HTTPS publiques `/up`, `/connexion`, `/assets/app.css`, `/assets/app.js` : **HTTP 200** pendant l’audit. Aucune authentification ni donnée métier de production utilisée. Après l’incident Aiven éteint signalé auparavant, ces réponses attestent un rétablissement public, pas une disponibilité durable ni une nouvelle preuve directe du TLS MySQL.
- Cookie Secure : constaté actif lors du diagnostic HTTP précédent après modification utilisateur. L’écart daté du 29 septembre ci-dessous est historique et a été corrigé.

### Ce que la suite verte ne démontre pas

Lors de la baseline e71138c, aucun test dédié de répétabilité Gordon, de conversion des anciens formats Ennéagramme, de conservation IA, de stabilité comparaison ou d’événements AuditLog n’existait. La correction 009 ajoute les tests de conservation IA et d’audit de génération décrits ci-dessus ; les autres manques restent inchangés. Les tests PDF vérifient principalement réponses/MIME et archive ; ils ne prouvent pas par extraction de texte l’absence de brouillon dans le PDF patient après révision. Les tests nommés « logo inclus » et « export sans scores non publiés » ne vérifient pas respectivement les pixels/logo incorporés et un résultat non publié créé dans ce scénario : leurs assertions ont été lues, leurs noms ne sont pas considérés comme preuve suffisante.

La reprise navigateur, la concurrence MySQL et les lectures croisées agenda/messages/entreprises ne sont pas intégralement couvertes. Aucun test E2E navigateur ou test de charge disponible exécuté ; aucun environnement MySQL 8.4 isolé établi pour cette mission. La suite RefreshDatabase n’est pas lancée contre Aiven applicatif. Aucun fournisseur IA réel, e-mail réel, build Docker local ou restauration distante testé.

Résultat historique de e71138c : **010 DONE ; 009 CONTRADICTS ; les 16 autres features PARTIAL**, avec tâches MISSING/BLOCKED détaillées. La contradiction IA est un constat de code (écrasement du champ draft), pas un test nouvellement exécuté. Prochaine tâche recommandée : conservation indépendante de la sortie IA et de ses générations, non implémentée dans cet audit.

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
- Conserver le cookie Secure désormais constaté actif ; contrôler les valeurs effectives `APP_ENV=production`, `APP_DEBUG=false` et `APP_URL` HTTPS.
- Compléter la recette authentifiée du site : assets et console navigateur sans Mixed Content, sessions, permissions, PDF et téléchargements signés. La connexion et le tableau de bord sont confirmés par l’utilisateur ; les contrôles de proxy supplémentaires sont locaux.
- Sauvegarde/restauration distante complète (Aiven, documents et clés), copie hors machine et tâches planifiées distantes.
- Tests navigateur ordinateur/mobile, comportements JavaScript et charge/concurrence.
- Référentiels autorisés et validation métier/clinique des questionnaires.

## Ce qui est reporté

- Activation et test d’un fournisseur IA réel ; les tests de l’adaptateur utilisent des réponses simulées. Les autres assistants IA spécialisés ne sont pas implémentés.
- Envoi d’e-mails externes, reporté par l’utilisateur : aucun envoi réel validé. La boîte locale de test est indisponible en production ; aucun transport adapté aux restrictions SMTP de Render gratuit n’est configuré.
- Synchronisations Google/Outlook et locale/distante non implémentées ; aucun calendrier de livraison défini.
- Audit externe de sécurité/conformité et certification clinique non réalisés.


## Feature 019 — P0 + PatientAI v0.1 — 2 octobre 2026

- `php artisan test --compact tests/Feature/PatientAiTest.php` : **33 cas, 237 assertions réussies** (SQLite en mémoire).
- `php artisan test --compact` : **76 tests, 620 assertions réussies** ; 43 cas préexistants conservés.
- `vendor/bin/pint --dirty --format agent` : réussi.
- `php artisan route:list --path=patient/assistant --no-interaction` : cinq routes PatientAI ; `php artisan view:cache --no-interaction` et `git diff --check` : réussis.

Preuves : authentification, quatre rôles non-patient rejetés, compte inactif, dossier manquant/détaché, flag OFF, accord séparé et identifiants imposés serveur ; refus lecture/envoi/suppression entre tenants et entre patients du même tenant ; persistance et chiffrement inspecté en base ; affichage XSS échappé ; requêtes POST/DELETE sans CSRF refusées 419 avec bypass de test désactivé, jeton valide accepté ; limites de longueur/types et quota par utilisateur (429 puis reprise) ; panne et réponse vide/provider non autorisé sans persistance ni fuite ; absence de texte dans audit et logs observés ; absence de contenu flashé.

21 variantes sociales testées avec routeur/provider déterministes, aucune requête SQL émise par ces composants et aucune requête HTTP. Le parcours d'envoi est aussi vérifié sans requête aux tables évaluations/interprétations/rendez-vous/notes/questionnaires/documents. Le provider reçoit uniquement une intention, vérifiée par mock pour une demande contenant du texte privé. `Http::preventStrayRequests`, fake et `Http::assertNothingSent` vérifient les sorties HTTP dans les scénarios pertinents. Revue statique du fake : aucun client réseau ni accès SQL/Eloquent. Aucun OpenAI, clé ou crédit utilisé ; aucune API externe appelée.

PAI-031 : export JSON des messages vérifié même flag OFF ; suppression patient et cascade ; retrait sous suspension conservant les messages/export mais interdisant l'envoi ; anonymisation réelle du dossier via route existante effaçant conversations/messages même flag OFF ; expiration à la borne de 30 jours, refus 410 après borne ; purge explicite tenant laissant autre tenant et suspension intacts ; commande globale testée. La migration additive est exécutée par RefreshDatabase sur SQLite, pas sur la base applicative ni Aiven.

Limites : aucune recette navigateur/mobile/E2E, aucune validation MySQL ou concurrence réelle, aucune activation/déploiement distant ni test scheduler en production. Les contrôles HTTP simulés et Blade compilé ne prouvent pas le rendu visuel. Aucun développement v0.2+. Le skill testing-best-practices demandé par AGENTS.md n'a pas été retrouvé dans les emplacements locaux disponibles ; les conventions PHPUnit du projet ont été suivies.


## Feature 019 — PatientAI v0.2 — 2 octobre 2026

- `php artisan test --compact tests/Feature/PatientAiTest.php tests/Feature/PatientAiPolicyTest.php` : **68 cas, 1100 assertions réussies**, SQLite en mémoire.
- `php artisan test --compact` : **111 tests, 1484 assertions réussies** ; 76 cas préexistants conservés.
- `vendor/bin/pint --dirty --format agent` et `git diff --check` : réussis.

35 nouveaux cas dans PatientAiPolicyTest : version explicite patientai-v0.2 et rejet d'une version inconnue, instructions/identité/capacités, 30 demandes de refus françaises/anglaises via parcours HTTP simulé, contact configurable et fallback sans contact, usurpation sans accès à une autre conversation, 21 variantes sociales inchangées, absence d'invention pour résultats/scores/rendez-vous/aide site non disponible.

Chaque cas de refus vérifie que le provider n'est pas appelé, que les deux messages persistent, que la réponse correspond à la catégorie, que le prompt complet est absent de la réponse et le texte utilisateur absent de l’audit, que le rôle patient ne change pas, et qu'aucune table métier clinique n'est interrogée. La politique/routeur/fake sont aussi testés sans SQL. Http::preventStrayRequests, fake et Http::assertNothingSent couvrent l'absence de requête HTTP externe. Revue statique : aucun client HTTP/SQL dans PromptRegistry/SafetyPolicy/FakeLlmProvider ; aucun secret ni .env lu pour construire le contexte, aucun nom institutionnel hardcodé dans la logique métier.

La suite v0.1 reste verte : isolation, chiffrement, CSRF/XSS, flag, consentement, limites, panne provider et cycle export/effacement/rétention. Aucun appel OpenAI/API/réseau externe, aucune clé API utilisée, aucune migration applicative/Aiven, aucun déploiement. Le skill testing-best-practices reste introuvable dans les emplacements locaux disponibles ; conventions PHPUnit existantes suivies.

Limites : tests locaux SQLite et HTTP simulé, sans navigateur/E2E ni concurrence MySQL. Règles lexicales bornées, pas de promesse de résistance universelle aux contournements. Prompt actif identifié par registre/Git, sans nouvelle colonne de version par message. v0.3 et versions suivantes non commencées.


## Feature 019 — PatientAI v0.3 — 2 octobre 2026

- `php artisan test --compact tests/Feature/PatientAiContextTest.php` : **29 cas, 302 assertions réussies**, SQLite en mémoire.
- `php artisan test --compact tests/Feature/PatientAiTest.php tests/Feature/PatientAiPolicyTest.php tests/Feature/PatientAiContextTest.php` : **97 cas, 1530 assertions réussies**.
- `php artisan test --compact` : **140 tests, 1910 assertions réussies** ; tous les cas préexistants v0.1/v0.2 et autres features restent verts. Le nombre d'assertions dans les tests existants inspectant chaque requête SQL augmente avec les lectures minimales d'autorisation.
- `vendor/bin/pint --dirty --format agent` et `git diff --check` : réussis.

Preuves nouvelles : DTO contenant uniquement trois entiers immuables ; User/tenant/Client résolus depuis auth()->id() et les relations réelles, trois requêtes minimales sans identité civile/champ clinique ; absence d'identité pour invité, non-patient, compte inactif, dossier absent/archivé/anonymisé ou Client dans un tenant incohérent ; relation Client falsifiée en mémoire ignorée et compte désactivé en base refusé malgré User de session obsolète.

Isolation dans les deux directions entre patients du même tenant et entre tenants : refus lecture/envoi/suppression/liste et appel direct à PatientAiChat d'une conversation étrangère. Tests de paramètres arbitraires client_id/user_id/tenant_id/conversation_id/conversation_uuid/patient_id : contexte inchangé, création rattachée au propriétaire de session, envoi limité à la conversation autorisée. Trois cas de conversation contenant une seule composante de propriété falsifiée sont refusés.

Cinq affirmations de texte (patient 42, client_id=42, Joshua, administrateur, dossier de Jean) testées avec capture du contexte réellement résolu par contrôleur et orchestrateur : aucune modification des trois identifiants. Provider mocké pour vérifier qu'un envoi reçoit uniquement greeting, sans DTO/identifiant. Requêtes d'évaluations/score/résultat vérifiées au fallback v0.2, sans SELECT clinique ou organisation partenaire, et sans sélection de champs sensibles Client. Http::preventStrayRequests/fake/assertNothingSent couvrent l'absence de requête HTTP externe ; aucune clé ou API OpenAI utilisée.

Le premier test de projection a signalé le SELECT * interne de EXISTS (sans hydratation de tenant) ; la requête d'existence a été rendue explicitement limitée à tenants.id puis le test a été relancé avec succès. Tests, migrations de fixture et données fictives restent exclusivement sur SQLite en mémoire ; aucune migration nouvelle ou appliquée en base applicative/Aiven. Skill testing-best-practices introuvable dans les emplacements locaux disponibles ; conventions PHPUnit existantes suivies.

Limites : aucun E2E/navigateur, aucune validation MySQL/concurrence réelle ni déploiement. Le DTO n'est ni un droit durable ni un contexte clinique ; aucune API de contexte exposée. v0.4 et versions suivantes non commencées.


## Feature 019 — PatientAI v0.4 — 2 octobre 2026

- Tests outils `php artisan test --compact tests/Feature/PatientAiAssessmentTest.php` : **17 cas / 86 assertions réussis**.
- Tests PatientAI `php artisan test --compact --filter=PatientAi` : **114 cas / 1595 assertions réussis**.
- Suite complète `php artisan test --compact` : **157 tests / 1977 assertions réussis** (SQLite en mémoire).
- `vendor/bin/pint --dirty --format agent`, compilation Blade puis vidage du cache, vérification evaluations.show et `git diff --check` : réussis ; aucune route modifiée.

Couverture : liste vide, plusieurs évaluations, pagination bornée et filtre de statut, huit filtres invalides/identifiants refusés ; DTO exact, URL Laravel patient accessible, statuts source relus après changement ; UUID valide/invalide/inexistant, même tenant/autre tenant, inconnu et publication absente masqués uniformément. Relations définition/Interprétation d’un autre tenant et publication retirée masquées. Statut publie visible uniquement après publication attestée, sans contenu publié chargé.

Capture provider : uniquement intention assessments et DTO readonly autorisé, aucune identité cible/modèle Eloquent. Requêtes SQL des outils inspectées sans colonnes answers/results/draft/published_content/ai_generations ni tables cliniques/rendez-vous. Snapshot de la table assessments inchangé après consultations. Fake/formatter testés sans aucune requête SQL ou HTTP. Un provider fabriquant statut/lien est refusé et aucun message n’est persisté. HTTP de consultation statut d’une évaluation étrangère retourne le même texte indisponible ; conversation étrangère refusée. Paramètres navigateur falsifiés ignorés et texte d’identité/client_id sans effet. Compte désactivé après authentification refusé par l’outil.

Migration UUID : rollback/up testés avec passation préexistante, UUID valide backfill et données métier/timestamps inchangés, route numérique conservée. Aucun migrate appliqué à la base applicative/Aiven. Une fixture initiale oubliait le draft obligatoire et le routeur initial ne normalisait pas l’accent de « évaluation » ; corrigés puis tests concernés et suites relancés avec succès.

Régressions v0.1/v0.2/v0.3 vertes : salutations, refus, consentement, chiffrement, CSRF/XSS, isolation, limites et rétention/export/effacement. Le test historique v0.3 du fallback exclut désormais la liste des évaluations, devenue autorisée v0.4 ; scores/résultats restent au fallback sans lecture métier. Version active PromptRegistry patientai-v0.4, ancienne version v0.2 toujours récupérable. Http::preventStrayRequests/fake/assertNothingSent et revue statique couvrent le parcours sans HTTP ; aucun client réseau/SQL dans le fake et le formatter, aucun OpenAI/API externe/clé utilisés. Diff et contrôle ciblé des secrets : aucun secret ajouté, fichiers .env non modifiés.

Limites : routage lexical volontairement borné, filtres page/limit seulement disponibles côté service ; URLs en texte échappé dans les bulles. Migration additive nécessaire avant activation ; MySQL/Aiven, concurrence et recette navigateur/E2E non validés. Skill testing-best-practices introuvable localement, conventions PHPUnit existantes suivies. Feature flag OFF conservé. Outils strictement read-only, aucun v0.5+, RAG, aide questionnaire, résultat détaillé ou action métier.


## Feature 019 — PatientAI v0.5 — 2 octobre 2026

- `php artisan test --compact tests/Feature/PatientAiHelpTest.php` : **40 cas / 346 assertions réussis**.
- `php artisan test --compact --filter=PatientAi` : **154 cas / 1936 assertions réussis**.
- `php artisan test --compact` : **197 tests / 2319 assertions réussis**, SQLite en mémoire ; régressions v0.1 à v0.4 et autres features vertes.
- `vendor/bin/pint --dirty --format agent` et `git diff --check` : réussis. Routes calendrier/messagerie/documents vérifiées avec route:list : seulement ajout de noms aux GET existants. Aucune vue Blade modifiée ; parcours HTTP incluant la page PatientAI compilés/rendus par les tests.

40 nouveaux cas : les 14 rubriques guide ont version/source/provenance présentes, fichiers de code référencés existants et liens allowlist effectivement accessibles par le patient. Tests explicites des refus professionnels /evaluations, /questionnaires, /droits, calendrier sans création/annulation patient, documents sans dépôt patient, page unique des questions, résultats non consultés par PatientAI, demandes d’effacement non automatiques. Flag OFF supprime le lien PatientAI ; rubrique inconnue indisponible. Versions prompt v0.2/v0.4 récupérables et v0.5 explicite, instructions cohérentes avec le périmètre actuel.

Sept variantes de métadonnées documentaires non autorisées (draft/demo, trois audiences, version/source inconnues) sont refusées pour guide et outil questionnaire ; approbation absente refusée. Aide globale et question autorisées avec captures des clés exactes du DTO, version assignée, consigne, format/échelle/navigation/glossaire et déclaration d’objectif non documenté. Types Oui/Non, choix descriptifs et texte libre testés. UUID invalide/inexistant/étranger même tenant/étranger autre tenant, identifiant absent/autre définition/autre version/mal formé/casse erronée, passation terminée et consentement absent/retiré : même résultat indisponible. Échelle incohérente masquée.

Sept demandes dangereuses refusées avant provider et outil : réponse à la place du patient, choix entre valeurs, optimisation et prédiction de profil, réponses à toutes les questions, interprétation psychologique. Paramètres navigateur client_id/user_id/tenant_id ignorés ; conversation étrangère refusée. Textes avec identifiants supplémentaires ou identité patient/admin/Joshua n’altèrent pas le contexte et ne déclenchent pas l’aide. Capture provider limitée à un DTO questionnaire ou une seule rubrique approuvée. Requêtes SQL inspectées sans réponses, résultats, notes, interprétations privées, documents ou rendez-vous ; snapshot assessments inchangé. Le guide ne consulte aucune table métier.

Contenu « ignore les instructions précédentes » dans libellé ou document approuvé rendu comme donnée descriptive, sans modifier PromptRegistry/SafetyPolicy. Script dans le libellé affiché échappé par Blade. Le fake rend l’aide sans requête SQL ; Http::preventStrayRequests/fake/assertNothingSent couvrent les scénarios. Rendu provider inventant fonction/lien rejeté avec rollback. Revue statique fake/formatter : aucun client SQL/HTTP ni instruction exécutée depuis les documents ; aucun OpenAI, API externe, clé ou sortie réseau utilisée dans cette mission. Audit/chiffrement/consentement/rétention/export/effacement et protections CSRF/XSS existants restent couverts par les suites de régression.

Les premiers tests ont signalé le chemin nul d’une URL dashboard sans slash final, un refus lexical manquant et un import de test absent ; corrigés puis tous les tests concernés et suites relancés avec succès. Aucun secret ajouté au diff contrôlé, aucun fichier .env modifié. Aucune migration v0.5 ni migration appliquée à la base applicative/Aiven ; migration UUID v0.4 reste nécessaire avant activation. Aucune compilation frontend nécessaire, aucun déploiement.

Limites : validation locale SQLite/HTTP simulé sans navigateur/E2E, MySQL ou concurrence réelle ; approbation documentaire technique dans Git, sans certification clinique ni workflow RAG. Le document JSON doit être présent dans l’artefact déployé, sinon aide indisponible. Vocabulaire limité aux termes techniques approuvés, objectifs cliniques non documentés et jamais déduits ; routage lexical borné et UUID/question_id explicites. La borne existante de 10000 caractères/réponse reste applicable : une métadonnée exceptionnellement longue peut provoquer une erreur générique sans persistance. URLs en texte échappé, sans nouvelle interface. Skill testing-best-practices demandé par AGENTS introuvable dans les emplacements locaux ; conventions PHPUnit existantes suivies. Feature flag OFF conservé. Aucun v0.6, outil rendez-vous, résultat détaillé v0.7, mémoire v0.8, RAG v0.9 ou écriture métier.


## Feature 019 — PatientAI v0.6 — 2 octobre 2026

- `php artisan test --compact tests/Feature/PatientAiAppointmentTest.php` : **22 cas / 178 assertions réussis**.
- `php artisan test --compact --filter=PatientAi` : **176 cas / 2111 assertions réussis**.
- `php artisan test --compact` : **219 tests / 2494 assertions réussis**, SQLite en mémoire ; régressions v0.1–v0.5 et autres features vertes.
- `vendor/bin/pint --dirty --format agent`, route:list calendar.index et `git diff --check` : réussis. Aucun Blade ni route modifié ; le lien calendrier est effectivement ouvert avec succès dans les tests.

Preuves : listes vide/un/multiples, filtre planifie (annulé/inconnu/passé exclus), ordre chronologique et tie-break id, prochain identique au premier de la liste, borne maintenant -1s/0/+1s et horloge avec microsecondes, limite 10/hasMore. Quatre cas temporels (Kampala, UTC, New York été/hiver) vérifient représentation ISO/fuseau/offset et changements de jour par rapport à l’horloge UTC. Aucune invention dans réponse vide et aucun lien individuel/professionnel.

Isolation testée dans les deux directions entre deux patients du même tenant et tenants différents, pour les deux outils ; Appointment avec tenant incohérent masqué, Client anonymisé refusé. Trois paramètres navigateur falsifiés client_id/user_id/tenant_id sans effet avec capture provider du seul DTO autorisé ; conversation étrangère refusée. Identité patient/admin déclarée, identifiant textuel, contournement et salutation ne consultent jamais Appointment. Requêtes d’outil inspectées sans lecture clinique/résultats/notes/interprétations ; DTO exact sans modèle/identité privée. Fake/formatter exécutés sans SQL et Http::preventStrayRequests/fake/assertNothingSent couvrent les scénarios.

Création, annulation et déplacement demandés au chat renvoient à la procédure approuvée, sans appeler les outils rendez-vous ; snapshot de toute la table Appointment inchangé. POST création et annulation du portail refusés au patient, aucune mutation après ces requêtes. Provider fabriquant date/statut/professionnel/lien refusé avec rollback, aucun message persisté. Les quatre formulations minimales demandées sont reconnues et donnent la date réelle ; versions historiques du prompt toujours récupérables. Le test de fallback ancien retire la demande de prochain rendez-vous, désormais autorisée, mais garde score/résultat au fallback. Le guide n’a changé que pour corriger la phrase devenue factuellement fausse sur la capacité d’agenda de PatientAI ; version/provenance actualisées.

Aucune migration créée/appliquée, aucun accès Aiven ou déploiement ; fichiers .env non modifiés. Revue statique du fake/formatter sans Eloquent/SQL/client réseau, contrôle ciblé des secrets et diff : aucun secret ajouté ni OpenAI/API/réseau externe utilisé. Feature flag OFF maintenu. Aucun outil résultat v0.7, note clinique, mémoire/RAG ou écriture métier. Skill testing-best-practices introuvable dans les emplacements locaux ; conventions PHPUnit existantes suivies.

Limites : tests locaux SQLite/HTTP simulé, sans recette navigateur/E2E, MySQL ou concurrence réelle. Fuseau global applicatif et stockage historique DATETIME local sans offset : ambiguïté d’heure civile répétée en DST et absence de fuseau individuel conservées, pas de migration temporelle. Liste limitée à 10, suite dans le calendrier ; routage lexical borné, pas de moteur de réservation. Le DTO reflète une lecture au moment de l’envoi, pas une garantie contre une replanification ultérieure. Le document guide JSON doit être présent dans l’artefact de déploiement. Migration UUID v0.4 toujours à appliquer selon procédure avant activation, sans exécution dans cette mission.


## Feature 019 — PatientAI v0.7 — 2 octobre 2026

- `php artisan test --compact tests/Feature/PatientAiPublishedResultTest.php` : **26 cas / 157 assertions réussis**.
- `php artisan test --compact --filter=PatientAi` : **202 cas / 2245 assertions réussis**.
- `php artisan test --compact` : **245 tests / 2628 assertions réussis**, SQLite en mémoire ; v0.1–v0.6 et autres features verts.
- `vendor/bin/pint --dirty --format agent` et `git diff --check` réussis ; route evaluations.show vérifiée. Aucun route/Blade modifié ; tests HTTP ouvrent le lien individuel patient et l’historique PatientAI.

Publication : résultat réellement publié disponible ; neuf scénarios privés/incomplets refusés (terminé/en cours, pas d’interprétation, brouillon, génération, timestamp/contenu absent/vide, aucun résultat/publication). Test du véritable POST publier puis dépublier : disponibilité puis refus. UUID invalide/inexistant, autre patient même tenant/autre tenant, Interpretation d’un autre tenant, définition étrangère ou relation Assessment/Interpretation déplacée : même DTO indisponible. Six incohérences version/kind/score/maximum rejetées.

DTO/fidélité : clés exactes du DTO, seules valeurs numériques publiques source conservées, Gordon réellement calculé dans la fixture puis jamais recalculé par l’outil (Scoring mocké sans calculate), Ennéagramme auto-déclaré et valeur décimale conservés. Avertissement questionnaire de démonstration conservé et testé. Résultat textuel sans scores autorisé, réponses raw-v1 privées éliminées, texte long borné avec indication explicite d’extrait et lien patient. Marqueurs distincts de brouillon/génération/prompt/snapshot/profil privé/réponses/note présents dans les fixtures et absents du DTO. Requêtes de l’outil inspectées sans sélection de ces colonnes privées ni ClinicalNote ; aucune mutation d’Assessment après consultation. Le conteneur chiffré results est nécessairement lu côté serveur puis filtré, comme documenté dans ARCHITECTURE.

Sections « Résultat publié » et « Explication PatientAI » testées, avec rappel de l’absence de nouvelle interprétation/validation. Quatre sorties provider fabriquant score/diagnostic/profil/texte/lien rejetées avec rollback et zéro message. Trois paramètres navigateur client_id/user_id/tenant_id falsifiés ignorés avec capture provider du seul DTO propriétaire ; conversation étrangère refusée. Identité déclarée, paramètre textuel, modification/diagnostic/draft ne déclenchent pas l’outil ; sans UUID aucun SELECT Assessment/Interpretation ni résolution implicite. Les quatre formulations bornées sont exécutées. Contenu publié « ignore les instructions » rendu inerte, balises script exclues/échappées, registre inchangé. Fake/formatter exécutés sans SQL ; Http::preventStrayRequests/fake/assertNothingSent couvrent l’absence HTTP.

Tests historiques de fallback retirent seulement la demande de résultat, maintenant routée vers indisponibilité générique sans UUID ; demandes de score restent sans accès métier. Guide et prompt corrigés pour refléter le périmètre v0.7, aucune promesse de résultat privé. Les droits, refus, chiffrement, consentement, rétention/export/effacement, rate limiting et outils précédents restent couverts par les suites. Contrôle ciblé des secrets et diff : aucun secret ajouté ni .env modifié. Aucun OpenAI/API/réseau externe, provider distant, migration/appel Aiven ou déploiement.

Limites : validation SQLite/HTTP simulé sans MySQL/concurrence/E2E ; verrou Assessment aligné sur publier/dépublier mais concurrence réelle non testée. Reformulation descriptive fixe, aucun raisonnement clinique ni définition de vocabulaire clinique non approuvée. Texte publié Markdown converti en texte, extrait signalé à 4000 caractères, ressources purement non textuelles non présentées. Les anciennes réponses restent conservées selon P0 après dépublication ; les nouvelles consultations sont refusées. Pas de sélection implicite d’évaluation ni historique injecté, routage lexical borné. Aucun v0.8/mémoire/RAG/action métier. Skill testing-best-practices introuvable localement, conventions PHPUnit existantes suivies. Flag OFF et migration UUID v0.4 encore non appliquée en base applicative.

## Feature 019 — PatientAI v0.8 — 2 octobre 2026

- `php artisan test --compact tests/Feature/PatientAiMemoryTest.php` : **39 cas / 402 assertions réussis**.
- `php artisan test --compact --filter=PatientAi` : **241 cas / 2647 assertions réussis**, régressions v0.1–v0.7 vertes.
- `php artisan test --compact` : **284 tests / 3030 assertions réussis**, SQLite en mémoire.
- `vendor/bin/pint --dirty --format agent`, `php artisan route:list --name=patientai --no-interaction` (6 routes), `php artisan view:cache --no-interaction` puis view:clear : réussis. Diff et contrôle ciblé des secrets vérifiés avant commit.

Mémoire : préférence explicite standard/concise chiffrée et cachée dans toArray ; lecture propriétaire sous DTO exact `{responseStyle: ?string}`, nouvelle conversation avec mémoire sans copie, nouvelle conversation sans mémoire sans recherche de sources antérieures. Accord PatientAI et accord mémoire distincts, mode par défaut désactivé, consentement absent/inconnu refusant l’injection. Huit saisies hors allowlist (dont instruction, diagnostic, score, questionnaire, rendez-vous, secret, structure et longueur excessive) refusées sans sauvegarde ni ClinicalNote, saisie non flashée. Limites items/octets/sources et remplacement déterministe testés ; ancien historique conservé, timestamp source non modifié par remplacement.

Cycle : export service et véritable route des droits contiennent la préférence et ses marqueurs d’accord, sans autre patient ; suppression globale du champ sans destruction des conversations, aucune réapparition dans nouveau chat. Sous hold, effacement désactive sans détruire, export reste possible ; levée de hold ne réactive rien, second effacement détruit. Remplacement sous hold conserve l’ancien ciphertext mais le rend non réutilisable. Source à 30 jours inclus utilisable, au-delà exclue même sous hold ; purge respecte hold puis supprime après levée. Retrait d’accord PatientAI bloque envoi et réutilisation ; suppression source cascade messages. Effacement global/anonymisation via parcours réel supprime aussi la mémoire, même feature flag OFF.

Sécurité : deux patients du même tenant et deux tenants, conversation étrangère, accès direct service étranger, client_id/user_id/tenant_id/memory_id/conversation_id falsifiés et identité dans le texte n’altèrent pas la cible. Nouvelle route d’effacement teste invité, quatre rôles non-patient, compte inactif, flag OFF, CSRF et quota partagé P0. Contenus mémoire falsifiés (instruction/clinique/métier/draft/génération) ignorés, PromptRegistry/PatientContext inchangés. Texte de chat ne crée aucun souvenir. Audit inspecté sans préférence/message ; logs info/error non invoqués. Sortie provider fabriquée rejetée atomiquement, aucun message ni audit d’utilisation persistant. Fake exécuté sans requête SQL ; Http::preventStrayRequests/fake/assertNothingSent appliqués à tous les cas.

Source de vérité : faux souvenir de rendez-vous/score/statut n’est jamais chargé par l’orchestration métier (service mémoire mocké sans appel). Rendez-vous réel, statut d’évaluation publié et résultat source A=7/15 restent exacts, également en conversation sans mémoire. Aucune note clinique, draft, ai_generation, réponse brute ou donnée professionnelle n’est transmise via la mémoire. Le provider ne reçoit ni modèle Eloquent ni identité.

Migration additive testée par RefreshDatabase, non appliquée à la base applicative/Aiven. Aucun .env modifié, aucune clé/API/OpenAI/provider distant ni sortie réseau ; aucun v0.9/RAG, résumé LLM, embedding/index vectoriel ou action métier. Documentation locale v0.5 inchangée. Feature flag OFF conservé. Skill testing-best-practices demandé par AGENTS introuvable dans les emplacements locaux ; conventions PHPUnit existantes suivies.

Limites : tests SQLite/HTTP simulé, pas de navigateur/E2E/MySQL/concurrence réelle ; mémoire limitée à une préférence, pas de faits libres ou résumés. L’export restitue en clair au demandeur habilité la préférence déchiffrée, y compris conservée sous hold. Destruction mémoire ne retire pas les messages historiques : suppression des conversations/dossier et délais des sauvegardes restent les mécanismes P0. Sous hold, copies inactives conservées jusqu’à effacement autorisé/purge P0. Migration v0.8 (et migrations antérieures nécessaires) à appliquer selon procédure avant activation ; scheduler distant non vérifié.

## Feature 019 — PatientAI v0.9 — 2 octobre 2026

- `php artisan test --compact tests/Feature/PatientAiRagTest.php` : **43 cas / 406 assertions réussis**.
- `php artisan test --compact --filter=PatientAi` : **284 cas / 3053 assertions réussis**, v0.1–v0.8 préservées.
- `php artisan test --compact` : **327 tests / 3436 assertions réussis**, SQLite en mémoire.
- `vendor/bin/pint --dirty --format agent`, `php artisan patientai:rag --help --no-interaction`, compilation Blade view:cache puis view:clear et `git diff --check` réussis. Aucune route/Blade modifiée ; rendu HTTP/XSS de la réponse documentaire vérifié.

Pipeline réel testé : document classifié draft, submit/review/approve/index séparés ; index prématuré et approbation sans revue refusés ; acteurs/dates reçus explicites. Import guide : 14 brouillons avec texte/version/source originaux, aucun approuvé automatiquement, comportement PatientGuideRegistry inchangé ; une rubrique devient récupérable uniquement après les étapes complètes. CLI exige attestations review/approve, refuse un patient, conserve l’identité antérieure ; import-json ne prend pas tenant/états/acteurs du fichier, n’exécute aucun PHP et refuse JSON invalide/wrapper FTP avant accès fichier.

Douze états/métadonnées invalides ou inactifs refusés (draft/review/approved/retired/rejected, revue pending/rejected, approbation pending, acteur/date manquants, audience absente/inconnue). Classification inconnue, contexte absent et définition étrangère refusés à la création. Revue rejetée exclue, contenu/chunk modifié après approbation exclu. Index de nouvelle version retire l’ancienne ; doublon ancien indexed falsifié ne supplante pas la nouvelle version, retrait prend effet sans cache. Corps/source/chunks chiffrés et masqués dans sérialisation implicite ; provenance/DTO exacts sans chemin interne ou métadonnée admin.

Audiences : PATIENT_PUBLIC disponible après revue/approbation/index ; PATIENT_CONTEXTUAL disponible seulement avec assignation autorisée de la définition liée, autre patient même tenant/autre définition/autre tenant exclus, publication invisible et définition déplacée inter-tenant exclues. PROFESSIONAL_ONLY/ADMIN_INTERNAL/SECURITY_SECRET restent techniquement indexables après approbation professionnelle mais jamais récupérables patient, même par texte exact, titre/clé/id connu, audience ou instruction « ignore permissions » ; aucune requête de chunks pour ces documents, aucun contenu/chemin/existence révélés. Changer seulement leur audience en PUBLIC invalide les reçus et ne les expose pas.

Bornes : chunking Unicode/empreintes déterministes, ordre/scoring stables, limitation à un document/deux chunks dans une configuration réduite, budget de caractères et JSON bloquant, fenêtre de candidats restrictive. Document >8000 caractères et nombre excessif de chunks refusés sans index partiel ; requêtes >256 caractères, un seul token, aucune pertinence, plus de 16 tokens utiles, « * / tous les documents » retournent le même contexte vide. Le fake ne fabrique aucune source lorsque la liste est vide et s’exécute sans SQL.

Cinq injections documentaires testées : ignore instructions, faux rôle SYSTEM/admin, divulgation de secrets, changement patient, appel d’outil/annulation. Elles restent du texte cité, sans modification PromptRegistry/SafetyPolicy/PatientContext, sans écriture Appointment/ClinicalNote. XSS échappé dans la vue PatientAI. Provider reçoit seulement PatientRagResult.documents autorisés, toute réponse inventée rejetée avec rollback/zéro messages/audit d’envoi ; logs info/error/debug/warning non invoqués. Http::preventStrayRequests/fake/assertNothingSent couvrent chaque cas, aucun provider/network SQL hors orchestration Laravel.

Priorité vérifiée sans appel RAG : rendez-vous, évaluations, résultats, questionnaire, guide, salutations et refus, y compris les cinq demandes métier/guide derrière « Recherche documentaire : ». Cinq paramètres navigateur client_id/user_id/tenant_id/document_id/assessment_definition_id falsifiés sans effet, conversation étrangère refusée ; identité déclarée dans le texte n’altère jamais le contexte et admin/Joshua/prompt sont refusés avant retrieval. Patient inactif, non-patient, invité et flag OFF refusés par le retriever. Standard/concise produit le même retrieval/rendu, service mémoire non consulté, aucun contenu RAG mémorisé automatiquement.

Migration et factories utilisées dans les fixtures SQLite ; seeder optionnel non exécuté ; aucun import, revue humaine réelle, approbation/indexation ou migration exécuté en base applicative/Aiven, aucun déploiement. Aucun secret ajouté au contrôle ciblé, aucun .env modifié ; feature flag OFF. Aucune API/OpenAI/embedding distant/vector DB externe ni sortie réseau effectuée. Aucun v1.0/hardening général commencé. Skill testing-best-practices demandé par AGENTS introuvable localement, conventions PHPUnit existantes suivies.

Limites : corpus opérationnel exige migration puis import/revue humaine attestée/approbation/indexation par un opérateur habilité ; aucune revue réelle fabriquée à partir de l’ancienne approbation technique v0.5. Fenêtre lexicale 50 documents, pas de synonymes/sémantique ni synthèse libre ; pertinence peut manquer une source hors fenêtre. Chunking peut couper mots/phrases. Les reçus sont un registre applicatif d’attestation, pas une preuve cryptographique de lecture humaine ni un audit externe inviolable. Pas de MySQL/concurrence réelle/E2E/navigateur/production ; le verrou de version est testé fonctionnellement sur SQLite. Réponses/citations anciennes conservées selon P0 après retrait d’une source ; nouvelles recherches refusées. Les tables RAG sont des connaissances générales, pas un dossier patient ou ClinicalNote, et ne remplacent jamais les outils métier.

## Feature 019 — PatientAI v1.0 — statut documentaire (2 octobre 2026)

### État réel vérifié dans l’environnement local

- `php artisan test --compact tests/Feature/PatientAiTest.php tests/Feature/PatientAiPolicyTest.php tests/Feature/PatientAiContextTest.php tests/Feature/PatientAiAssessmentTest.php tests/Feature/PatientAiHelpTest.php tests/Feature/PatientAiAppointmentTest.php tests/Feature/PatientAiPublishedResultTest.php tests/Feature/PatientAiMemoryTest.php tests/Feature/PatientAiRagTest.php` : **284 tests passés, 3 056 assertions**.
- `php artisan test --compact` : **327 tests passés, 3 437 assertions**.
- `vendor/bin/pint --dirty --format agent` : exécuté et conforme pour les fichiers modifiés du projet existant ; la documentation n’a pas été modifiée dans le code applicatif.

### Verdict v1.0 documenté

- PatientAI v1.0 code-complete : **PASS**
- PatientAI v1.0 local-test-complete : **PARTIAL**
- PatientAI v1.0 production-ready : **NON**

### PASS (validé localement)

- SafetyPolicy
- rate limiting serveur
- audit sans contenu sensible
- observabilité locale existante
- rétention
- export/effacement
- kill switch
- politique provider fake / fail-closed
- confidentialité provider
- tests adversariaux locaux
- non-régression v0.1 → v0.9

### PARTIAL

- MySQL / migrations : validation SQLite uniquement ; aucune validation MySQL/Aiven réelle effectuée.
- concurrence réelle : protections applicatives présentes, mais pas de validation MySQL/concurrence réelle.
- validation locale globale : la suite est verte, mais le niveau de preuve de production n’a pas été exécuté.

### NOT EXECUTED

- E2E navigateur réel
- déploiement Render/Aiven
- provider LLM réel
- validation scheduler de production

### BLOCKERS production

- validation MySQL réelle
- E2E navigateur réel
- validation Aiven/Render
- provider réel si un fournisseur externe est nécessaire
- vérification scheduler production

### Conditions documentées

- Le feature flag PatientAI reste **OFF**.
- Aucun OpenAI / API externe n’est activé dans cet environnement.
- Aucun déploiement n’a été effectué.
- Aucune migration Aiven n’a été appliquée.
- Aucun statut de production n’est revendiqué.

La hardening v1.0 est donc documentée comme code-complete et localement vérifiée dans les limites du dépôt, sans prétendre à une validation de production non exécutée.

## Feature 020 — PatientAI Conversation v1.1 (2 octobre 2026)

### Statut

- Code-complete : **PASS** localement.
- Local-test-complete : **PASS** ; `php artisan test --compact --filter=PatientAi` : **300 tests / 3 251 assertions** ; `php artisan test` : **345 tests / 3 660 assertions**.
- Render recipe : **PENDING**. Aucune recette navigateur/Render n’a été exécutée pour v1.1 ; les observations Render initiales restent les observations fournies par l’utilisateur, pas une revalidation de ce lot.

### Couverture locale

- Feature 020 ciblée : `tests/Feature/PatientAiConversationTest.php`, **14 tests / 115 assertions** ; variantes FR/EN, normalisation bornée, tous les intents, single/multiple/none, refus cross-tenant, follow-ups, domaine/conversation courants, chiffrement/révocation/effacement du référent, rollback local, mémoire séparée, résultat publié, documentation, export expurgé.
- Suites PatientAI : **300 tests / 3 251 assertions**. Suite totale : **345 / 3 660**.
- `vendor/bin/pint --dirty --format agent` : passé ; diagnostics PHP sans erreur. `php artisan route:list --path=patient/assistant` : six routes existantes conservées. `git diff --check` et scan ciblé des patterns de secrets : passés. Les routes/UI du portail restent derrière le feature flag et l’identité PatientContext.

### Implémentation / migration

- Nouveau routeur intent structuré et dispatcher Laravel allowlisté ; SafetyPolicy avant outil ; aucun droit issu du langage naturel.
- DTO assessment de conversation sans UUID ; outils assessment/questionnaire/résultat/rendez-vous restent propriétaires, tenant-scoped et read-only. Clarifications non cliniques pour plusieurs correspondances ; follow-up sans référent valide n’auto-sélectionne pas un autre domaine.
- `about_my_data` est statique et ne charge aucune donnée patient, mémoire ou RAG. Refus privés, cross-patient, sécurité, usurpation et injection ont des catégories distinctes.
- Migration **oui**, additive et locale : `2026_10_02_120000_add_conversation_context_to_ai_conversations`. Elle ajoute `conversation_context` nullable, chiffré par cast Laravel, réservé aux identifiants de référent/source/tour/date ; rollback `down()` retire cette seule colonne. Elle a été appliquée uniquement dans les bases temporaires de tests RefreshDatabase. **Aucune migration Aiven exécutée.**
- Provider reste `fake`; aucun appel réseau IA/OpenAI, aucun secret ajouté, aucun déploiement Render et aucun push.

### Limites

Les tests locaux ne prouvent pas le comportement effectif sur Render/Aiven, le schéma distant, la concurrence MySQL, le navigateur/E2E ou l’expérience de recette réelle. La migration additive doit être revue/appliquée via le processus de livraison autorisé avant activation du nouveau code en production ; ne pas activer le flag ou déployer par déduction de la suite locale. Voir [V1.1-READINESS.md](patientai/V1.1-READINESS.md) pour le tableau de statut.

## Feature 021 — livraison locale Ennéagramme (4 octobre 2026)

Reprise exacte de HEAD `dfac184` et du working tree Feature 021 non commité. Avant toute modification : git status, git diff --check, lecture des specs/fichiers existants et `php artisan test tests/Feature/EnneagramAssessmentTest.php` : **11 tests / 57 assertions**, verts. Ces 11 tests sont conservés, uniquement formatés par Pint. Laravel 13.33.0 / PHPUnit 12.5.36 confirmés par composer show --direct ; aucune dépendance changée.

### Commandes finales et résultats

| Contrôle | Résultat |
|---|---|
| `php artisan test tests/Feature/EnneagramAssessmentTest.php` | 11 tests / 57 assertions, succès |
| `php artisan test --compact tests/Feature/EnneagramAssessmentTest.php tests/Feature/EnneagramWorkflowTest.php tests/Feature/EnneagramMigrationTest.php` | 68 tests / 361 assertions, succès |
| `php artisan test --compact --filter=PatientAi` | 312 tests / 3 360 assertions, succès |
| `php artisan test --compact tests/Feature/EnneagramWorkflowTest.php tests/Feature/WorkflowTest.php tests/Feature/CompletionTest.php` | 84 tests / 524 assertions, succès |
| `php artisan test` | 413 tests / 4 026 assertions, succès |
| `vendor/bin/pint --dirty --format agent` | succès, fichiers modifiés formatés |
| `./vendor/bin/pint --test` sur les fichiers PHP de cette livraison | succès |
| `./vendor/bin/pint --test` global | échec sur 2 fichiers inchangés de HEAD : Backups.php et PatientAiAppointmentTest.php ; mêmes écarts confirmés sur copies de HEAD dans /tmp, aucune modification hors périmètre |
| `php artisan route:list --except-vendor --no-interaction` | 80 routes, dont revue/approbation professionnelles |
| `php artisan view:cache --no-interaction` | succès ; PDF réellement rendu dans les tests |
| `git diff --check` | succès |
| Scan ciblé diff + nouveaux fichiers + comparaison avec valeurs sensibles locales | aucun motif de clé privée/API, aucune valeur sensible locale retrouvée, aucun fichier .env/certificat/clé ajouté |

Les premiers tests additionnels ont révélé une acceptation de booléen pour une échelle : correction de la validation du moteur et du flux de réponses. Un premier passage complet a révélé deux échecs PDF causés par des directives Blade accolées : correction et couverture PDF ajoutée, puis suite complète relancée verte. L’ancien test conversationnel contenant en dur la version du guide vérifie désormais PatientGuideRegistry::VERSION ; aucun contrôle de sécurité affaibli. Les fixtures self-report existantes et les 14 rubriques/imports RAG sont préservés.

### Preuves Feature 021

- Trois formes synthétiques A/B/C : neuf items chacune, DEMO, non validées, licensed=false, provenance originale explicite, aucun reçu APPROVED. Demi-tour DEMO→revue interdit et nouvelle version DEMO ne peut pas perdre ce marquage. APPROVED n’est exercé que par fixtures de tests, avec fausses références réservées à SQLite ; aucune source officielle n’est revendiquée.
- Scoring : reproduction exacte, neuf dimensions, pondérations/multi-contributions, reverse, arrondi, égalités complètes et absence de gagnant arbitraire, booléens/choix et legacy. Vingt-six configurations négatives (provenance/langue/version/doublons/règles/maps/dimensions/poids/points/reverse/choix/bornes), six réponses invalides ; les infinis sont rejetés par le cast JSON avant calcul.
- Rotation : unused puis LRU, historique indépendant par patient, tenant, refus DRAFT, dernière version par forme, quatre assignations A/B/C/A par routes réelles sous transaction ; Client lockForUpdate englobe sélection et insertion. Pas de simulation de contention MySQL ni prétention de concurrence réelle validée sur SQLite.
- Workflow : catalogue et contrôles de rôle, export/import professionnel des questions/règles sans reprise d’approbation, consentement requis, sauvegarde partielle/reprise, paramètres de forme falsifiés sans effet, nouvelle version sans modification historique, impossibilité de rebind/mutation du snapshot, chiffrement en base, soumission complète et verrou 409, résultats techniques réservés aux professionnels avant publication, PDF patient après publication, retrait de publication.
- Migration : RefreshDatabase SQLite sur suites Feature et roundtrip up/down dédié DatabaseMigrations SQLite :memory: avec FK actives et historique référencé, conservation des questions et octets chiffrés de réponses/résultats ; backfill LEGACY/DEMO/DRAFT. Le rollback de FK SQLite requiert de sortir de la transaction englobante RefreshDatabase ; ce test utilise DatabaseMigrations, sans modifier la migration pour contourner une contrainte. Aucune migration sur la base applicative ou Aiven.
- PatientAI : disponibilité/en cours, refaire non-mutatif, explication des formes sans équivalence psychométrique, aide whitelistée de la version assignée et refus de question étrangère, refus scoring map/poids/meilleure réponse/stratégie de type/diagnostic/secrets/usurpation. Résultat terminé ou draft/génération sans publication indisponible ; résultat publié pondéré disponible et fidélité exacte aux faits persistés, DTO sans règles/réponses/draft/générations. Renderer provider mensonger rejeté avec rollback de messages ; aucun nouveau scoring ni résultat inventé.
- Isolation : autre patient du même cabinet et autre cabinet, UUID arbitraire, identifiants client/user/tenant falsifiés dans les requêtes. Les suites Features 019/020 couvrent aussi identité textuelle, conversations étrangères, compte inactif, consentement PatientAI, chiffrement, rétention/effacement/export, RAG/mémoire/injections et audit sans contenu. PatientContext/permissions/classifications n’ont pas été modifiés.
- Réseau : fake déterministe, Http::preventStrayRequests/Http::fake/Http::assertNothingSent dans les tests Feature 021. Aucun endpoint, provider ou clé externe introduit ; les appels professionnels historiques sont simulés par la suite existante. Aucun appel API réel réalisé.

### Readiness séparée

SPEC-COMPLETE / CODE-COMPLETE / LOCAL-TEST-COMPLETE pour le périmètre technique local. Pint du périmètre vert ; dette de formatage globale préexistante sur deux fichiers inchangés. Source psychométrique officielle, licence/autorisation réelle et recette professionnelle de contenu : **PENDING**. Aucune forme opérationnelle APPROVED livrée ; DEMO ne permet aucune déclaration de validation psychométrique. MySQL/concurrence réelle, Aiven, Render et recette de production : **NON EXÉCUTÉS / PENDING**. Aucun push, accès Aiven ou déploiement Render.


## Feature 021 — correction multi-formes du 4 octobre 2026

Correction locale du blocage de recette : l'import professionnel peut créer A v1 comme nouveau questionnaire, puis B v1 et C v1 comme nouvelles formes de la même famille. Une nouvelle version de A reste A et devient v2. Rotation existante conservée, aucun changement des permissions Features 019/020.

Preuves exécutées sous SQLite `:memory:` :
- `php artisan test tests/Feature/EnneagramAssessmentTest.php tests/Feature/EnneagramWorkflowTest.php tests/Feature/EnneagramMigrationTest.php --compact` : **77 tests / 421 assertions, PASS**. Les 11 tests initiaux / 57 assertions sont conservés.
- `php artisan test --filter=PatientAi --compact` : **312 tests / 3 359 assertions, PASS** ; les tests EnneagramWorkflowTest couvrent en plus published-only, manipulation, DTO minimal, impossibilité de falsifier un score et isolation.
- `php artisan test --compact` : **422 tests / 4 085 assertions, PASS**.
- Pint des fichiers PHP modifiés et nouvelle migration : PASS.
- `php artisan route:list --path=questionnaires --no-interaction` : cinq routes existantes, aucun ajout de route ; compilation `view:cache` PASS, cache de vues ensuite nettoyé.
- `git diff --check` et contrôle ciblé du diff pour secrets : PASS.

Neuf tests ajoutés (sept workflow, deux migration) : création familiale A/B/C à v1 et A v2, clés distinctes, import canonique/provenance, refus doublon/changement de clé/scoring invalide/perte DEMO, référence étrangère/non pondérée/absente, accès patient/conseiller refusé, audit et rotation unused-first/LRU, unicité SQL, conservation historique du backfill et rollback sans perte. Une assertion historique sur la version familiale a été remplacée par l'assertion de version propre à A, avec vérification du snapshot assigné inchangé.

Migration nécessaire `2026_10_04_165857_scope_definition_versions_to_enneagram_forms.php` : nouvelle colonne technique `version_scope`, remplacement de l'index familial par l'unicité tenant/family/scope/version, sans renumérotation ni modification des données patient. Rollback bloqué si l'ancien index est incompatible avec les nouvelles formes. Ne pas appliquer ce rollback en forçant une suppression de données.

Readiness : code et tests locaux PASS ; contenu scientifique/licencié PENDING (E021-REV-002) ; nouvelle migration MySQL, contention réelle et recette Render de cette correction PENDING. Cette mission n'a effectué aucun accès/écriture Aiven, aucun seeder distant, aucun push ni déploiement Render, aucun appel OpenAI. La garde local/testing de EnneagramDemoForms reste intacte. Les fichiers JSON temporaires DEMO ne sont pas ajoutés au dépôt.


## Feature 021 — correction du chargeur JSON professionnel (4 octobre 2026)

Cause : `data-load-json` relisait uniquement la zone texte et acceptait seulement une liste JSON. Le fichier sélectionné n'était jamais lu ; le snapshot d'export pondéré est un objet (`questions`, `form_key`, `scoring_rules`, `engine_version`), et non une liste.

Le bouton lit désormais le fichier sélectionné via `File.text()` (priorité au fichier, limite 200 Ko), ou la zone texte en l'absence de fichier. Il accepte les listes historiques et les exports Ennéagramme pondérés ; il valide la structure avant remplacement, affiche les neuf items et reprend la clé, les règles, le type et la provenance si la source est vide. DEMO doit être coché explicitement, licence/approval jamais importées. Échec JSON/structure/questions/règles/lecture : message `Import échoué`, rôle alert, éditeur précédent conservé. La validation Laravel/EnneagramScoring reste autorité finale ; JSON malformé produit une erreur de validation sur questions_file. L'URL app.js porte une version basée sur sa date de modification pour invalider les anciennes copies du navigateur.

Preuves locales :
- EnneagramAssessmentTest + EnneagramWorkflowTest + EnneagramMigrationTest : **80 tests / 451 assertions, PASS**.
- PatientAi (`--filter=PatientAi`) : **312 tests / 3 361 assertions, PASS**.
- Suite complète `php artisan test --compact` : **425 tests / 4 115 assertions, PASS**.
- RenderHttpsTest : **3 tests / 13 assertions, PASS**, HTTPS conservé avec URL du script versionnée.
- Régression JavaScript `tests/questionnaire-import.test.cjs` exécutée dans EnneagramWorkflowTest : gestionnaire réel app.js dans un DOM simulé, sélection/clic de A/B/C, neuf cartes/JSON/clé/règles, cases DEMO/licence inchangées, erreurs visibles sans remplacement, compatibilité liste et échec lecture. Ce test exige Node.js (v18 disponible localement), sans dépendance npm ni ajout de paquet.
- Même test exécuté séparément avec les fichiers canoniques `/tmp/feature021-demo-imports/enneagramme-demo-{A,B,C}.json` : PASS.
- Pint fichiers modifiés PASS, syntaxe JavaScript PASS, routes complètes et compilation Blade PASS, diff et recherche ciblée de secrets PASS.

Aucune nouvelle migration nécessaire. Aucun accès/écriture Aiven, seeder, création applicative de forme hors fixtures locales SQLite, appel OpenAI, push ou déploiement Render pendant cette correction. Permissions et published-only PatientAI inchangés ; E021-REV-002 toujours en attente. Validation réelle du navigateur Render après livraison reste à effectuer.


## Feature 021 × PatientAI — routage du résultat Ennéagramme publié (4 octobre 2026)

Cause reproduite localement : la phrase « Peux-tu m’expliquer le résultat de mon Ennéagramme DEMO que mon professionnel vient de publier ? » était absente de la liste bornée de ConversationIntentRouter. Elle aboutissait à unknown/fallback sans appeler PatientPublishedResultTool. Le portail patient utilise ses contrôles/publication directement et ne dépend pas de ce routage conversationnel. Le support du moteur pondéré existait déjà dans l'outil publié ; pas de nouveau retrieval/RAG ni changement de classification/permissions.

Ajout de sept formulations normalisées explicites, toutes dispatchées vers published_result. Réutilisation du PatientContext, des références serveur de conversation, de la résolution publiée avec clarification si plusieurs candidats, du DTO minimal et du provider fake. Publication effective/tenant/Client propriétaire, cohérence moteur/méthode/version/form_key et scores restent vérifiés par Laravel. Aucun assouplissement pour DEMO, brouillons, autres patients ou tenants.

DTO inchangé : disponibilité, UUID autorisé, nom/version, date de publication, neuf scores autorisés, maximum/méthode, texte publié borné (éventuel extrait), lien patient généré par Laravel et isDemo. Aucun modèle Eloquent, réponse brute, scoring_rules, score_map, dimension_weights, draft, ai_generation ou ClinicalNote transmis au provider. Réponse existante distingue faits publiés et explication descriptive, conserve l'avertissement démonstration/non validée et absence de diagnostic.

Preuves : deux régressions nouvelles, phrase exacte sans UUID + vecteur de recette [100,75,50,25,0,25,50,75,25], restitution publiée, exclusion des marqueurs privés, refus après dépublication et clarification de plusieurs publications. Http::assertNothingSent et fake provider, sans accès distant.
- PatientAI ciblé : **312 tests / 3 359 assertions, PASS**.
- Feature 021 ciblée : **82 tests / 470 assertions, PASS**, incluant régressions existantes published-only, draft/ai_generations, DTO privé, falsification de score, manipulation/règles/poids et isolation patient/tenant.
- Suite complète `php artisan test --compact` : **427 tests / 4 134 assertions, PASS**.
- Pint fichiers modifiés, routes et git diff --check, contrôle ciblé secrets : PASS.

Aucune migration. Aucun accès/écriture Aiven, modification de l'évaluation 2, nouvelle passation applicative, OpenAI, push ou déploiement Render. Fixtures locales seulement ; E021-REV-002 reste en attente. Routage volontairement borné : si plusieurs résultats sont possibles sans référence autorisée, clarification/UUID requis ; aucune sélection arbitraire. Recette réelle Render de cette correction reste à effectuer.
