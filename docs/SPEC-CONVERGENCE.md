# Convergence Spec Kit 001–018 — audit du 1 octobre 2026

## Périmètre et provenance

Baseline de convergence : `e71138c`, auditant le code `990f470`. Les constats des autres features restent ceux de cette baseline. Mise à jour ciblée 009 après implémentation : conservation des générations, migration additive et tests simulés ; aucun paramètre privé modifié. Les 54 fichiers `spec.md`, `plan.md`, `tasks.md` ont été lus avec la constitution, la roadmap et la traçabilité source.

**Le Spec Kit n’existe pas dans le dépôt Laravel audité.** La copie retrouvée et utilisée est `/home/a-joshua/Downloads/SPEC KIT/APP-007-SPEC-KIT-EXERCISE`. Elle se présente comme « exercice fondé sur l’audit APP-007 », constitution 1.0.0. Il s’agit de la référence de cette convergence, pas d’une preuve d’approbation métier officielle. La mission autorise un audit ; les plans sont des exigences à comparer, pas des commandes à exécuter. Les sources externes sont laissées intactes. ROADMAP.md et TRACEABILITY.md sont créés dans le dépôt Laravel à partir de cette référence ; aucun autre exemplaire n’y existait. Les sources restent externes : leur versionnement avec le projet reste à organiser, sans prétendre qu’elles sont déjà livrées dans Git.

Les tâches sont reproduites ci-dessous avec leurs identifiants ; les empreintes des sources lues sont conservées en annexe. Aucun statut n’est déduit des cases à cocher initiales.

## Méthode et statuts

- `DONE` : périmètre considéré implémenté et vérifiable dans le code/tests indiqués. Ne signifie ni certification clinique ni validation production exhaustive.
- `PARTIAL` : réalisation présente, mais exigence importante ou validation attendue incomplète.
- `MISSING` : réalisation ou test demandé absent ; distinguer explicitement un test absent d’une fonctionnalité absente.
- `BLOCKED` : information source, donnée, décision métier ou accès nécessaire manquant.
- `CONTRADICTS` : comportement contraire à une exigence explicite, et non simple différence de nom de classe ou d’organisation technique.

Une feature `PARTIAL` peut inclure des tâches `DONE`, `MISSING` ou `BLOCKED`. Une condition facultative absente est indiquée, sans la transformer en exigence obligatoire. L’absence d’un test n’est jamais une preuve que le code échoue. Les constats statiques ne sont pas présentés comme des reproductions dynamiques.

Preuves : `W` = `tests/Feature/WorkflowTest.php`, `C` = `tests/Feature/CompletionTest.php`, `H` = `tests/Feature/RenderHttpsTest.php`. Les noms abrégés ci-dessous correspondent aux méthodes `test_…` exactes. `M1` = `database/migrations/2026_09_25_000001_create_cabinet_tables.php`, `M2` = `database/migrations/2026_09_25_203858_complete_cabinet_features.php`. Les chemins complets et routes sont dans [TRACEABILITY.md](../TRACEABILITY.md).

## Synthèse

| Feature | Statut | Implémentation trouvée | Tests/preuves | Manques | Prochaine action |
|---|---|---|---|---|---|
| [001 — Identité, rôles et isolation](#feature-001) | **PARTIAL** | User, TenantModel, ActiveAccount, Access ; authentification et liens relationnels explicites. | Voir § 001 : méthodes exactes, code et routes dans la traçabilité. | Couverture inter-organisation incomplète ; audit des accès/invitations incomplet. | Compléter matrice négative et inventaire des actions auditées. |
| [002 — CRM Client / Organization](#feature-002) | **PARTIAL** | Client/Organization avec clés étrangères ; saveClient, archive/restore, organization ; consentements associés. | Voir § 002 : méthodes exactes, code et routes dans la traçabilité. | Organisation : création/lecture seulement ; absence de tests dédiés au rattachement et à la création/modification complète Client. Message lié aux acteurs et indirectement au Client. | Compléter le périmètre CRUD et sa recette. |
| [003 — Définitions versionnées](#feature-003) | **PARTIAL** | AssessmentDefinition : famille/version/questions JSON/engine_version ; Assessment référence la ligne versionnée. | Voir § 003 : méthodes exactes, code et routes dans la traçabilité. | Immutabilité garantie par le parcours HTTP de création, pas par un verrou modèle/base ; règles et dimensions embarquées, pas d’entités séparées. | Décider et tester une garantie d’immutabilité couvrant aussi les écritures internes. |
| [004 — Passation et verrouillage](#feature-004) | **PARTIAL** | AssessmentController::assign/answers ; transaction et lockForUpdate ; autosave JS ; état en_cours/termine/publie. | Voir § 004 : méthodes exactes, code et routes dans la traçabilité. | Reprise après rechargement et concurrence réelle non testées ; transitions réparties dans AssessmentController plutôt que service proposé. | Tester reprise navigateur et soumissions concurrentes sur MySQL isolé. |
| [005 — Consentement et privacy](#feature-005) | **PARTIAL** | Consent conserve texte/version/dates ; Client::hasConsent ; gate serveur ; PrivacyRequest et Retention. | Voir § 005 : méthodes exactes, code et routes dans la traçabilité. | Validation métier du retrait absente ; renouvellement de version et audit non testés explicitement. | Valider les effets métier puis tester changement de version. |
| [006 — Scoring Gordon](#feature-006) | **PARTIAL** | Scoring::calculate central, 60 booléens, 15 par dimension, engine gordon-v1 conservé ; aucune IA dans le calcul. | Voir § 006 : méthodes exactes, code et routes dans la traçabilité. | Test de reproductibilité dédié absent ; grille réelle autorisée non fournie. | Ajouter les tests de reproductibilité/bornes puis importer un référentiel autorisé. |
| [007 — Ennéagramme](#feature-007) | **PARTIAL** | Neuf pourcentages 0..100 validés ; réponses auto-déclarées conservées ; aucun score clinique calculé. | Voir § 007 : méthodes exactes, code et routes dans la traçabilité. | Aucune migration des deux anciens formats, aucun type dominant ; choix métier du format et compatibilité non confirmés. | Décider format canonique, fournir exemples historiques autorisés et décider le traitement des égalités. |
| [008 — Besoins et tests personnalisés](#feature-008) | **PARTIAL** | Démo besoins : 5 domaines et 22 échelles 0..3 ; questions text/scale/choice ; validation Scoring ; snapshots raw-v1. | Voir § 008 : méthodes exactes, code et routes dans la traçabilité. | Explications/cas spécifiques des situations absents de la démo ; pas de test dédié besoins ni de restitution sans score ; textes officiels absents. | Compléter le format besoins sur une source autorisée, puis les tests par type. |
| [009 — Interprétation IA](#feature-009) | **PARTIAL** | Historique chiffré des sorties IA et métadonnées, distinct du brouillon et de la publication. | AiInterpretationHistoryTest : A → B → publication B → génération C ; données persistées et sécurité. | Fournisseur OpenAI réel BLOCKED / NOT VALIDATED faute de crédits ; assistants spécialisés absents. | Validation fournisseur séparée après disponibilité des crédits, jamais dans la suite automatisée. |
| [010 — Révision humaine et publication](#feature-010) | **DONE** | interpretation/publish/unpublish ; copie de draft vers published_content après reviewed ; Access::publisher et audit. | Voir § 010 : méthodes exactes, code et routes dans la traçabilité. | Historique intégral des publications non conservé (limite, pas exigence ferme de cette spec) ; assertions de contenu AuditLog absentes. | Conserver ce comportement et étendre ultérieurement les tests de traçabilité. |
| [011 — Portail patient](#feature-011) | **PARTIAL** | Dashboard filtré Client ; réponses, résultats publiés, documents partagés, messages, agenda, profil. | Voir § 011 : méthodes exactes, code et routes dans la traçabilité. | Dashboard affiche seulement les 6 dernières passations sans liste complète patient ; isolation agenda/messages non couverte par tests dédiés. | Rendre toutes les passations accessibles au patient et couvrir les parcours croisés. |
| [012 — Portail entreprise](#feature-012) | **PARTIAL** | Dashboard Organization, documents d’organisation sans client_id, messagerie avec professionnels. | Voir § 012 : méthodes exactes, code et routes dans la traçabilité. | Pas de test deux organisations distinctes ni de lecture messagerie entreprise autorisée complète. | Tester deux entreprises du même cabinet et un autre tenant. |
| [013 — Notes cliniques](#feature-013) | **PARTIAL** | ClinicalNote chiffrée ; création/lecture admin et psychologue ; Access::publisher ; audit note.creee/dossier.consulte. | Voir § 013 : méthodes exactes, code et routes dans la traçabilité. | Pas d’édition/suppression individuelle (CRUD demandé), ni tests explicites de toutes les routes pour patient/entreprise ; audit lecture au dossier seulement. | Définir le cycle de vie des notes avant d’ajouter édition/suppression et tests. |
| [014 — Documents privés](#feature-014) | **PARTIAL** | Storage local privé chiffré, Access::document, URL signée 5 minutes, fichiers par cabinet. | Voir § 014 : méthodes exactes, code et routes dans la traçabilité. | Pas de stockage durable Render ; conservation seulement dans purge Client, pas de politique documents entreprise/non rattachés. | Décider le stockage durable et les règles de conservation par périmètre. |
| [015 — Messagerie et agenda](#feature-015) | **PARTIAL** | Message chiffré par acteurs ; Appointment par Client ; portails filtrés et CRUD partiel selon périmètre demandé. | Voir § 015 : méthodes exactes, code et routes dans la traçabilité. | Tests d’isolation lecture des messages/RDV absents ; synchronisation externe non décidée et non implémentée. | Tester isolation des lectures et envois avant toute intégration externe. |
| [016 — Comparateur](#feature-016) | **PARTIAL** | Comparison.snapshot chiffré, IDs passations, même version exigée, graphiques et PDF basés sur copie. | Voir § 016 : méthodes exactes, code et routes dans la traçabilité. | Test de stabilité historique absent ; IA comparaison absente, optionnelle ; pas de publication comparaison patient. | Tester snapshot après évolution des données sources et refus versions incompatibles. |
| [017 — Courriers et PDF](#feature-017) | **PARTIAL** | DomPDF, trois sorties, Charts, Markdown HTML sécurisé, branding, pivot document_letter et ZIP contrôlé. | Voir § 017 : méthodes exactes, code et routes dans la traçabilité. | Tests PDF vérifient surtout HTTP/MIME, pas extraction du contenu prouvant exclusion draft ; rendu PDF mélange filtrage et requêtes dans contrôleurs. | Tester le contenu PDF patient après révision privée et les données autorisées. |
| [018 — Audit, rétention et sécurité](#feature-018) | **PARTIAL** | AuditLog, scopes, Access, Retention, Backups, chiffrement, URLs signées et workflow verrouillé. | Voir § 018 : méthodes exactes, code et routes dans la traçabilité. | Journal incomplet/non testé ; politique métier non validée ; tests croisés non exhaustifs et stockage durable absent ; conservation IA corrigée dans 009, fournisseur réel non validé. | Compléter ultérieurement audit/rétention et recette transverse ; fournisseur 009 réel bloqué. |

**Bilan actualisé : 1 DONE (010), 17 PARTIAL. La contradiction 009 de la baseline e71138c est corrigée pour les nouvelles générations ; 009 reste PARTIAL.** Aucune feature entière n’est absente ; les `MISSING`/`BLOCKED` concernent des sous-tâches détaillées. Ce bilan exigeant n’annule pas les parcours déjà opérationnels.

## Résultats des contrôles exécutés

- `composer show --direct` : Laravel 13.33.0, DomPDF wrapper 3.1.2, PHPUnit 12.5.36.
- `php artisan route:list --except-vendor --no-interaction` : 72 routes applicatives listées ; inventaire effectué, pas un test d’autorisation de chaque route.
- `php artisan test --compact` : **31 tests, 240 assertions réussies, 6,41 s**, SQLite en mémoire. Suite existante inchangée ; pas de tests nouveaux pour masquer une lacune.
- HTTPS public : `/up`, `/connexion`, `/assets/app.css`, `/assets/app.js` répondent **200** le 1 octobre pendant cet audit. Aucune connexion administrateur ni mutation métier en production effectuée.
- Pas de suite exécutée sur MySQL 8.4 isolé : environnement de test distinct non établi ; la base Aiven applicative n’est pas utilisée avec RefreshDatabase. Pas d’E2E navigateur/configuration d’automatisation présente, pas de tests de charge/concurrence. Aucun appel IA réel, email réel ou restauration distante dans cet audit.

## État production et limites de preuve

`Dockerfile`, `render.yaml`, `docker/entrypoint.sh` et `config/database.php` matérialisent Render/MySQL et le chargement du CA TLS. `d277679` ajoute `trustProxies('*')` ; `990f470` conserve TLS, retire le diagnostic et ajoute les tests H. Le login administrateur et le trajet TLS Render → Aiven sont des validations antérieures rapportées par l’utilisateur et documentées, pas rejouées ici. Les tests H simulent le proxy, pas l’intégralité de Render.

L’incident du 1 octobre (500, Aiven affiché `Powered off` par l’utilisateur) démontre que `/up` seul ne garantit pas l’accès base. Les réponses publiques sont à nouveau 200 pendant cet audit ; cela ne prouve pas la disponibilité future ni une restauration des documents. Le cookie Secure a été constaté actif lors du diagnostic précédent après la modification utilisateur. La conservation du disque local sur Render reste une limite ; les contrôles production historiques ne rendent pas toutes les features DONE.

## Écarts prioritaires et principes non négociables

1. **009 — contradiction corrigée, PARTIAL** : chaque génération réussie est ajoutée à `Interpretation.ai_generations` chiffré ; édition humaine et publication ne modifient pas ce champ. Les anciennes sorties perdues restent inconnues. La validation fournisseur OpenAI réel reste **BLOCKED / NOT VALIDATED — crédits API OpenAI indisponibles**.
2. **PARTIAL — 003, immutabilité** : le parcours HTTP crée une nouvelle ligne et préserve la référence des passations ; le modèle n’interdit pas une mise à jour interne de la ligne ancienne. Pas de preuve d’une mutation passée ni d’un accès HTTP permettant cette mutation.
3. **MISSING — 002/013** : édition/suppression d’organisation et de note individuelle absentes, contrairement au CRUD demandé par les tâches. L’archivage Client et l’effacement global existent.
4. **MISSING/BLOCKED — 007** : formats historiques non lus/migrés ; leur sens, fixtures et choix métier manquent. Aucun scoring psychométrique ni compatibilité n’est inventé.
5. **PARTIAL — 008/011** : besoins sans explications spécifiques ; patient limité à six passations visibles depuis le dashboard (URLs existantes toujours autorisées pour son périmètre).
6. **MISSING tests** : répétabilité Gordon, stabilité du snapshot comparaison, matrices messages/RDV/entreprises, extraction de contenu PDF et assertions AuditLog. Les 31 tests verts ne couvrent pas ces critères.
7. **BLOCKED décisions** : conséquences métier du retrait, rétention des différents objets, référentiels officiels, fournisseur IA réel, choix éventuel de synchronisation externe. Ces choix ne sont pas faits par cet audit.

Séparation observée : réponses dans Assessment.answers ; résultat déterministe/raw dans Assessment.results ; brouillon dans Interpretation.draft ; approbation explicite via reviewed ; publication copie vers published_content avec auteur/date. Gordon centralisé et déterministe ; Ennéagramme auto-déclaré ; autorisations serveur et consentement présents. **Constitution III : conservation IA corrigée pour les nouvelles générations ; garantie d’immutabilité des définitions encore partielle (003) ; VIII partiellement satisfaite à cause de la couverture audit.** Aucune conformité absolue n’est revendiquée.

## Suite éventuelle de 009

La conservation interne recommandée par la baseline e71138c est maintenant implémentée et testée avec fake. La validation d’un fournisseur réel appartient à une phase séparée, bloquée faute de crédits ; aucune activation ni clé demandée. Les assistants comparaison/questions/courriers restent absents, hors correction actuelle. Les autres features PARTIAL sont inchangées.

## Détail par feature et par tâche

<a id="feature-001"></a>

### 001 — Identité, rôles et isolation : PARTIAL

Source : `specs/001-identity-roles-isolation/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** User, TenantModel, ActiveAccount, Access ; authentification et liens relationnels explicites.

**Preuves disponibles :** `W::test_guest_and_login_and_disabled_account`; `W::test_cross_tenant_and_cross_patient_access_are_denied`; `W::test_counsellor_cannot_see_notes_or_publish`; `W::test_company_has_only_organization_documents`; `C::test_role_changes_revoke_sessions_and_are_tenant_scoped`; `C::test_non_admin_cannot_manage_access_or_read_local_mail`.

**Limites / écarts au plan :** Couverture inter-organisation incomplète ; audit des accès/invitations incomplet.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Formaliser les rôles observés. | DONE | User::isProfessional/canPublish et validation des rôles dans CabinetController/AccountController. |
| T002 — Définir une matrice de permissions validable. | DONE | Matrice documentée dans ARCHITECTURE.md et appliquée par Access ; validation métier finale non attestée. |
| T003 — Implémenter les contrôles serveur. | DONE | Middleware auth/ActiveAccount, scope TenantModel, contrôles Access et contrôleurs ; tests W/C ci-dessus. |
| T004 — Remplacer les relations d'autorisation fondées uniquement sur email. | DONE | Client.user_id et User.organization_id ; pas d’autorisation déduite du seul e-mail. |
| T005 — Tester l'isolation inter-patient/inter-organisation/inter-tenant. | PARTIAL | Tests inter-patient/inter-tenant présents ; deux entreprises distinctes du même tenant ne sont pas testées. |
| T006 — Auditer les opérations sensibles. | PARTIAL | Access::audit existe ; AuthController et AccountController::accept/resend ne journalisent pas ces opérations ; aucune assertion de ces événements dans les tests ; génération IA couverte séparément en 009. |

**Prochaine action :** Compléter matrice négative et inventaire des actions auditées.

<a id="feature-002"></a>

### 002 — CRM Client / Organization : PARTIAL

Source : `specs/002-client-organization-crm/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** Client/Organization avec clés étrangères ; saveClient, archive/restore, organization ; consentements associés.

**Preuves disponibles :** `W::test_all_professional_pages_render`; `W::test_archiving_disables_patient_and_pages_keep_rendering`; `W::test_modules_mutations_and_pdfs`; `W::test_cross_tenant_and_cross_patient_access_are_denied`.

**Limites / écarts au plan :** Organisation : création/lecture seulement ; absence de tests dédiés au rattachement et à la création/modification complète Client. Message lié aux acteurs et indirectement au Client.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Définir schéma Client/Person cible. | DONE | Client, migration M1 : identité, suivi chiffré, user_id, organization_id, archivage. |
| T002 — Définir schéma Organization cible. | DONE | Organization, M1 : tenant, coordonnées, soft deletes. |
| T003 — Créer relations explicites. | PARTIAL | Clés explicites pour les enfants ; Message ne porte pas client_id, lien indirect par utilisateur. Portée des échanges professionnels sur un dossier à préciser. |
| T004 — Implémenter CRUD autorisé. | PARTIAL | Client : création/modification/archivage/restauration ; Organization : aucune route de modification/suppression. |
| T005 — Tester rattachement et isolation. | PARTIAL | Isolation dossier/passation testée ; pas de scénario CRUD Client avec changement d’organisation et refus de rattachement étranger. |

**Prochaine action :** Compléter le périmètre CRUD et sa recette.

<a id="feature-003"></a>

### 003 — Définitions versionnées : PARTIAL

Source : `specs/003-versioned-assessment-definitions/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** AssessmentDefinition : famille/version/questions JSON/engine_version ; Assessment référence la ligne versionnée.

**Preuves disponibles :** `W::test_questionnaire_new_version_does_not_modify_existing_assessments`; `C::test_official_definitions_require_source_and_authorization_and_export_roundtrips`.

**Limites / écarts au plan :** Immutabilité garantie par le parcours HTTP de création, pas par un verrou modèle/base ; règles et dimensions embarquées, pas d’entités séparées.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Créer les entités de définition/version. | DONE | M1 et DefinitionController::store créent une ligne par version, unicité tenant/family/version ; équivalent au modèle séparé proposé. |
| T002 — Formaliser questions et dimensions. | DONE | Questions typées et dimensions Gordon stockées dans questions JSON et validées. |
| T003 — Formaliser règles de scoring optionnelles. | DONE | Règles Gordon dans Scoring + dimensions JSON ; pas de moteur arbitraire inventé. |
| T004 — Migrer Gordon vers une définition versionnée. | PARTIAL | Import versionné et moteur présents ; grille officielle absente (dépendance référentiel autorisé, BLOCKED). |
| T005 — Prévoir formats sans scoring déterministe. | DONE | raw-v1 et self-report-v1 distinguent formats sans calcul clinique. |
| T006 — Tester immutabilité historique. | PARTIAL | Test HTTP conserve version 1 après création version 2 ; aucune protection contre update direct de la définition, aucune assertion sur les anciennes questions/règles complètes. |

**Prochaine action :** Décider et tester une garantie d’immutabilité couvrant aussi les écritures internes.

<a id="feature-004"></a>

### 004 — Passation et verrouillage : PARTIAL

Source : `specs/004-assessment-workflow/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** AssessmentController::assign/answers ; transaction et lockForUpdate ; autosave JS ; état en_cours/termine/publie.

**Preuves disponibles :** `W::test_patient_consent_submission_lock_and_publication`; `W::test_revocation_blocks_autosave`; `W::test_cross_tenant_and_cross_patient_access_are_denied`.

**Limites / écarts au plan :** Reprise après rechargement et concurrence réelle non testées ; transitions réparties dans AssessmentController plutôt que service proposé.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Définir états et transitions. | DONE | États et transitions présents ; aucune réédition administrative des réponses via cette route. |
| T002 — Implémenter assignation. | DONE | assign vérifie Client et définition dans le tenant ; assigned_by et version conservés. |
| T003 — Implémenter autosave. | DONE | public/assets/app.js et answers enregistrent les réponses ; préremplissage dans evaluations/show ; endpoint couvert. |
| T004 — Implémenter soumission atomique. | PARTIAL | Transaction verrouille Client puis Assessment, audit inclus ; garantie de concurrence non exercée par SQLite en mémoire. |
| T005 — Verrouiller après complétion. | DONE | status en_cours exigé ; test de refus 409 après soumission. |
| T006 — Tester reprise, soumission et refus de réédition. | PARTIAL | Sauvegarde/soumission/refus testés ; reprise exacte après nouvelle session/rechargement et erreurs réseau non testées. |

**Prochaine action :** Tester reprise navigateur et soumissions concurrentes sur MySQL isolé.

<a id="feature-005"></a>

### 005 — Consentement et privacy : PARTIAL

Source : `specs/005-consent-privacy/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** Consent conserve texte/version/dates ; Client::hasConsent ; gate serveur ; PrivacyRequest et Retention.

**Preuves disponibles :** `W::test_patient_consent_submission_lock_and_publication`; `W::test_revocation_blocks_autosave`; `C::test_privacy_requests_and_export_keep_unpublished_scores_private`.

**Limites / écarts au plan :** Validation métier du retrait absente ; renouvellement de version et audit non testés explicitement.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Versionner le texte/objet de consentement. | DONE | config/psycho.php définit texte/version ; copie dans Consent à acceptation, séparée du Client. |
| T002 — Enregistrer acceptation et horodatage. | DONE | CabinetController::consent : client_id, accepted_at ; M1. |
| T003 — Ajouter gate de consentement aux parcours concernés. | DONE | Gate hasConsent avant toute sauvegarde/soumission, tests sans consentement et retrait. |
| T004 — Définir avec le métier les effets du retrait. | BLOCKED | Effet technique explicite : nouvelles réponses bloquées, historique conservé. Pas de décision métier/juridique validée fournie sur tous les effets. |
| T005 — Tester version et traçabilité. | PARTIAL | Acceptation/retrait exercés ; pas de test de nouvelle version, du texte conservé, des horodatages ou des événements audit. |

**Prochaine action :** Valider les effets métier puis tester changement de version.

<a id="feature-006"></a>

### 006 — Scoring Gordon : PARTIAL

Source : `specs/006-gordon-scoring/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** Scoring::calculate central, 60 booléens, 15 par dimension, engine gordon-v1 conservé ; aucune IA dans le calcul.

**Preuves disponibles :** `W::test_gordon_grid_scores_and_boolean_normalization`; `W::test_questionnaire_new_version_does_not_modify_existing_assessments`.

**Limites / écarts au plan :** Test de reproductibilité dédié absent ; grille réelle autorisée non fournie.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Formaliser la grille Gordon versionnée. | PARTIAL | Structure et grille synthétique testées ; contenu officiel/grille réelle non fournis : import métier BLOCKED. |
| T002 — Implémenter service de scoring central. | DONE | Un seul calcul métier dans Scoring ; validation normalise boolean avant calcul. |
| T003 — Enregistrer version du moteur. | DONE | Snapshot results.engine et definition_version persistés à la soumission ; champ engine_version de définition. |
| T004 — Tester bornes 0..15. | PARTIAL | Test couvre A=15, B=1, C=D=0 et false ; pas toutes les catégories à 15 ni toutes les variantes de grille invalide. |
| T005 — Tester reproductibilité. | MISSING | Aucun test compare deux calculs du même jeu/version ou un résultat recalculé à son snapshot conservé. |
| T006 — Supprimer/éviter tout scoring dupliqué. | DONE | Recherche code : aucun autre calcul Gordon dans UI/controllers ; les graphiques affichent les scores. |

**Prochaine action :** Ajouter les tests de reproductibilité/bornes puis importer un référentiel autorisé.

<a id="feature-007"></a>

### 007 — Ennéagramme : PARTIAL

Source : `specs/007-enneagramme/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** Neuf pourcentages 0..100 validés ; réponses auto-déclarées conservées ; aucun score clinique calculé.

**Preuves disponibles :** `C::test_official_definitions_require_source_and_authorization_and_export_roundtrips (import/export seulement)`.

**Limites / écarts au plan :** Aucune migration des deux anciens formats, aucun type dominant ; choix métier du format et compatibilité non confirmés.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Choisir avec le métier la structure canonique. | BLOCKED | type1..type9 utilisé en démo, neuf IDs libres acceptés par import ; choix canonique validé par métier non fourni. |
| T002 — Prévoir migration/lecture des deux structures historiques. | MISSING | Aucun lecteur/convertisseur des deux structures historiques ; fixtures source indisponibles pour terminer leur validation (BLOCKED). |
| T003 — Formaliser les 9 types comme référence versionnée. | PARTIAL | Neuf échelles versionnées et démo présentes ; descriptions/référentiel validés absents ; IDs type1..type9 non imposés à l’import. |
| T004 — Tester le type dominant si cette règle est conservée. | BLOCKED | Aucun calcul/test du maximum dominant ; décision de conserver cette règle et gestion des égalités non fournies. Ne pas inventer. |
| T005 — Documenter explicitement l'absence de scoring déterministe. | DONE | Scoring retourne les pourcentages inchangés avec mention sans score clinique ; documentation et UI explicites. |

**Prochaine action :** Décider format canonique, fournir exemples historiques autorisés et décider le traitement des égalités.

<a id="feature-008"></a>

### 008 — Besoins et tests personnalisés : PARTIAL

Source : `specs/008-needs-custom-tests/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** Démo besoins : 5 domaines et 22 échelles 0..3 ; questions text/scale/choice ; validation Scoring ; snapshots raw-v1.

**Preuves disponibles :** `W::test_patient_consent_submission_lock_and_publication`; `C::test_official_definitions_require_source_and_authorization_and_export_roundtrips (preuve générique de version/export)`.

**Limites / écarts au plan :** Explications/cas spécifiques des situations absents de la démo ; pas de test dédié besoins ni de restitution sans score ; textes officiels absents.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Modéliser questionnaire besoins. | PARTIAL | DemoSeeder modélise 5 textes + 22 valeurs ; aucun champ explicatif par situation, pas de schéma besoins contraignant dans DefinitionController. |
| T002 — Modéliser types de questions personnalisées. | DONE | Questions choice, scale et text représentées ; JSON et constructeur UI. |
| T003 — Implémenter validation des réponses par type. | PARTIAL | Validation par type implémentée ; tests ne couvrent pas tous les choix invalides, bornes, inconnues et cas besoins. |
| T004 — Préserver la version de définition. | DONE | assessment_definition_id et résultat definition_version ; nouvelle version distincte. |
| T005 — Tester restitution sans scoring inventé. | PARTIAL | Aucun score inventé dans branche raw-v1 ; answers stockées et exportables. Aucun test dédié ; PDF évaluation ne restitue pas les réponses brutes besoins/personnalise. |

**Prochaine action :** Compléter le format besoins sur une source autorisée, puis les tests par type.

<a id="feature-009"></a>

### 009 — Interprétation IA : PARTIAL

Source inchangée : `specs/009-ai-interpretation/spec.md`, `plan.md`, `tasks.md` de la copie externe identifiée. Statut avant : CONTRADICTS dans e71138c. La spec ne définit pas une politique métier de régénération détaillée ; le minimum technique retenu est d’ajouter chaque sortie réussie à un historique, sans écraser les précédentes, en conservant le comportement existant de remplacement du brouillon courant. Aucun historique de toutes les révisions humaines n’est inventé et aucune publication automatique ajoutée.

**Architecture :** une colonne nullable `Interpretation.ai_generations`, cast `encrypted:array`, masquée dans la sérialisation générique. Chaque entrée contient UUID, horodatage d’enregistrement, utilisateur demandeur, contenu textuel original intégral, modèle demandé, modèle/identifiant retournés si disponibles, version et messages exacts du prompt, entrée structurée expurgée des réponses libres. Un modèle retourné inconnu reste null, jamais déduit arbitrairement. Le brouillon courant reste `draft` (limite existante 50000 caractères) et la publication reste `published_content`. Le texte original conservé n’est pas tronqué à cette limite.

**Régénération :** append sous le verrou transactionnel Assessment existant, dans l’ordre d’enregistrement des réponses réussies. Les champs de dernière génération existants restent compatibles. La sortie initiale conservée A et ses métadonnées persistent quand C arrive ; le brouillon devient C, la publication B et sa date/son validateur restent inchangés. Il n’y a ni retour automatique à un ancien brouillon ni archive de toutes les éditions humaines. Les tests de concurrence MySQL réels restent non exécutés.

**Migration :** `2026_10_01_160120_add_ai_generations_to_interpretations_table.php` ajoute seulement un LONGTEXT nullable ; aucun backfill, aucune ancienne migration modifiée. Pour les interprétations anciennes, null signifie « aucune sortie originale attestée dans cet historique », et non que le draft est une sortie brute. Le premier enregistrement futur ne prétend pas être la première génération historique. Les anciens champs restent inchangés. Migration testée sur SQLite avec une ligne préexistante ; non appliquée à Aiven, non testée sur MySQL 8.4. Un rollback de schéma supprimerait l’historique : ne pas l’utiliser en production sans sauvegarde et décision explicite.

**Accès :** historique affiché en texte échappé uniquement pour admin/psychologue dans la page d’évaluation déjà autorisée. Aucune nouvelle route. Patient/entreprise/conseiller exclus ; aucun ajout aux restitutions patient ni à l’export de droits. Le cycle d’effacement existant supprime l’historique avec la ligne Interpretation ; aucune copie hors de ce périmètre. Ce stockage applicatif n’est pas un journal inviolable contre une écriture SQL privilégiée.

**Preuves :** `tests/Feature/AiInterpretationHistoryTest.php` (12 cas, 143 assertions) ; tests existants WorkflowTest sur opt-in/publication conservés. A → révision B → publication B → régénération C vérifié pour admin et psychologue, avec données relues depuis la base. Test migration préexistante, original long complet, chiffrement, XSS, inter-tenant/rôles, erreurs fournisseur simulées et audit de génération.

| Tâche source | Statut | Constat / preuve |
|---|---|---|
| T001 — Définir entité Interpretation. | DONE | Modèle existant réutilisé ; historique chiffré ajouté sans nouvelle entité. |
| T002 — Stocker statut draft. | DONE | Brouillon et publication séparés, génération ne publie pas. |
| T003 — Stocker métadonnées de génération disponibles. | DONE | Chaque nouvelle sortie et ses métadonnées persistent après édition/régénération ; réponses inconnues restent null ; anciennes pertes non reconstituées. |
| T004 — Implémenter génération sans publication. | PARTIAL | Workflow interne testé avec fake ; intégration fournisseur OpenAI réel BLOCKED / NOT VALIDATED faute de crédits API. Architecture contrôleur existante conservée, pas de refonte en service. |
| T005 — Tester invisibilité patient avant publication. | DONE | GET patient après génération, puis après publication B/régénération C : originaux privés ; export patient sans historique. |

```yaml
Workflow IA interne : testé avec fake/mock
Persistance/versionnement : testé (historique des générations réussies)
Révision humaine : testée
Publication : testée (workflow 010 inchangé)
Fournisseur OpenAI réel : NON TESTÉ / BLOQUÉ faute de crédits API
```

**Suite complète exécutée :** 43 tests, 383 assertions réussies sur SQLite ; Pint et compilation Blade réussis. Aucune requête réelle à un fournisseur IA payant, aucun crédit consommé. `Http::preventStrayRequests()` et endpoint fake déterministe dans la nouvelle suite. Les succès simulés ne suffisent pas à marquer 009 DONE.

**Restant :** déploiement/migration Aiven et validation MySQL isolée non exécutés ; validation réelle fournisseur bloquée ; aides IA spécialisées non implémentées. Ne pas confondre le blocage externe avec le fonctionnement du workflow interne.

<a id="feature-010"></a>

### 010 — Révision humaine et publication : DONE

Source : `specs/010-review-publication/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** interpretation/publish/unpublish ; copie de draft vers published_content après reviewed ; Access::publisher et audit.

**Preuves disponibles :** `W::test_patient_consent_submission_lock_and_publication`; `W::test_counsellor_cannot_see_notes_or_publish`; `W::test_patient_cannot_inject_html_and_score_stays_hidden_until_publication`.

**Limites / écarts au plan :** Historique intégral des publications non conservé (limite, pas exigence ferme de cette spec) ; assertions de contenu AuditLog absentes.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Implémenter édition professionnelle. | DONE | Édition professionnelle du draft ; contenu publié inchangé lors d’une révision (test W). |
| T002 — Implémenter permission de publication. | DONE | Admin/psychologue seulement ; reviewed exigé ; refus patient et conseiller testés. |
| T003 — Créer événement/audit de publication. | DONE | Access::audit interpretation.publiee, reviewed_by et published_at ; vérification statique, aucune assertion dédiée sur audit. |
| T004 — Filtrer portail patient sur contenu publié. | DONE | Vue/PDF contrôlent publication, brouillon réservé aux professionnels. |
| T005 — Tester draft invisible / publié visible. | DONE | Test complet : draft invisible, publié visible, nouvelle révision invisible, dépublication et PDF interdit. |

**Prochaine action :** Conserver ce comportement et étendre ultérieurement les tests de traçabilité.

<a id="feature-011"></a>

### 011 — Portail patient : PARTIAL

Source : `specs/011-patient-portal/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** Dashboard filtré Client ; réponses, résultats publiés, documents partagés, messages, agenda, profil.

**Preuves disponibles :** `W::test_patient_consent_submission_lock_and_publication`; `W::test_cross_tenant_and_cross_patient_access_are_denied`; `W::test_document_is_encrypted_private_and_signature_does_not_bypass_access`; `C::test_charts_escape_labels_and_history_only_includes_published_assessments`.

**Limites / écarts au plan :** Dashboard affiche seulement les 6 dernières passations sans liste complète patient ; isolation agenda/messages non couverte par tests dédiés.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Dashboard patient. | PARTIAL | Dashboard existe et filtre client ; take(6) dans dashboard.blade.php sans pagination patient, /evaluations réservé aux professionnels. |
| T002 — Passations assignées/en cours. | PARTIAL | Passations accessibles via URL autorisée ; les assignations plus anciennes que les six affichées n’ont pas de navigation complète testée. |
| T003 — Résultats publiés. | DONE | Publication filtrée côté serveur/vue ; tests visibilité et historique publié. |
| T004 — Documents privés. | DONE | Documents partagés uniquement, signature et autorisation ; tests croisés. |
| T005 — Messages et rendez-vous. | PARTIAL | Messages, calendrier et profil implémentés ; pas de scénario patient complet avec deux personnes pour agenda et messagerie. |
| T006 — Tests d'isolation patient. | PARTIAL | Évaluations/dossiers/documents couverts ; pas toutes les surfaces patient. |

**Prochaine action :** Rendre toutes les passations accessibles au patient et couvrir les parcours croisés.

<a id="feature-012"></a>

### 012 — Portail entreprise : PARTIAL

Source : `specs/012-company-portal/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** Dashboard Organization, documents d’organisation sans client_id, messagerie avec professionnels.

**Preuves disponibles :** `W::test_company_has_only_organization_documents`.

**Limites / écarts au plan :** Pas de test deux organisations distinctes ni de lecture messagerie entreprise autorisée complète.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Profil organisation. | DONE | CabinetController::dashboard et modules/company affichent Organization liée explicitement. |
| T002 — Documents autorisés. | DONE | Access::document impose shared, organization_id identique et absence de client_id ; test de refus dossier individuel. |
| T003 — Messagerie. | PARTIAL | ModuleController messages/send limite acteurs ; envoi à patient interdit testé, échanges autorisés et lectures croisées non testés. |
| T004 — Tester absence d'exposition de données patient non autorisées. | PARTIAL | Refus documents patient et évaluation vérifiés ; absence de fuite entre organisations distinctes non couverte. |

**Prochaine action :** Tester deux entreprises du même cabinet et un autre tenant.

<a id="feature-013"></a>

### 013 — Notes cliniques : PARTIAL

Source : `specs/013-clinical-notes/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** ClinicalNote chiffrée ; création/lecture admin et psychologue ; Access::publisher ; audit note.creee/dossier.consulte.

**Preuves disponibles :** `W::test_counsellor_cannot_see_notes_or_publish`; `C::test_privacy_requests_and_export_keep_unpublished_scores_private`.

**Limites / écarts au plan :** Pas d’édition/suppression individuelle (CRUD demandé), ni tests explicites de toutes les routes pour patient/entreprise ; audit lecture au dossier seulement.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Définir permissions notes cliniques. | DONE | Permissions canPublish plus restrictives que CRM ; notes exclues du conseiller et export patient. |
| T002 — Implémenter stockage et CRUD restreint. | PARTIAL | Stockage chiffré, création/lecture ; aucune route PUT/DELETE note ; effacement global via Retention ne remplace pas CRUD individuel. |
| T003 — Ajouter audit. | PARTIAL | Création auditée, consultation via dossier.consulte ; aucun événement propre de lecture note ni test audit. |
| T004 — Tester interdictions patient/entreprise par défaut. | PARTIAL | Conseiller refusé et export patient sans note ; pas de test complet POST notes et GET dossier entreprise/patient avec note. |

**Prochaine action :** Définir le cycle de vie des notes avant d’ajouter édition/suppression et tests.

<a id="feature-014"></a>

### 014 — Documents privés : PARTIAL

Source : `specs/014-private-documents/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** Storage local privé chiffré, Access::document, URL signée 5 minutes, fichiers par cabinet.

**Preuves disponibles :** `W::test_document_is_encrypted_private_and_signature_does_not_bypass_access`; `W::test_company_has_only_organization_documents`; `H::test_signed_document_download_works_behind_the_https_proxy`; `C::test_letters_reject_another_patients_documents_and_produce_a_bundle`.

**Limites / écarts au plan :** Pas de stockage durable Render ; conservation seulement dans purge Client, pas de politique documents entreprise/non rattachés.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Configurer stockage privé. | PARTIAL | Privé chiffré en local ; disque local Render éphémère, abstraction configurable non utilisée par les accès Storage::disk(local). |
| T002 — Implémenter upload autorisé. | DONE | Upload professionnel, contrôles rattachement et incompatibilité client+organization ; chiffrement. |
| T003 — Implémenter téléchargement contrôlé/temporaire. | DONE | Téléchargement signé + contrôle propriétaire/tenant ; expiration, tampering, contenu vérifiés. |
| T004 — Tester accès croisé refusé. | PARTIAL | Patient étranger et entreprise-vers-patient testés ; inter-tenant documentaire et inter-organisation complets non testés. |
| T005 — Définir rétention avec le métier. | BLOCKED | Rétention métier non validée ; Retention ne traite que les documents liés au Client, pas entreprise ni documents sans rattachement. |

**Prochaine action :** Décider le stockage durable et les règles de conservation par périmètre.

<a id="feature-015"></a>

### 015 — Messagerie et agenda : PARTIAL

Source : `specs/015-messaging-calendar/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** Message chiffré par acteurs ; Appointment par Client ; portails filtrés et CRUD partiel selon périmètre demandé.

**Preuves disponibles :** `W::test_modules_mutations_and_pdfs`; `W::test_company_has_only_organization_documents`.

**Limites / écarts au plan :** Tests d’isolation lecture des messages/RDV absents ; synchronisation externe non décidée et non implémentée.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Modèle Message sécurisé. | DONE | Message tenant, sender_id, recipient_id, body chiffré ; send vérifie tenant/acteurs. |
| T002 — Modèle Appointment sécurisé. | DONE | Appointment client_id/tenant ; calendrier filtre patient, refuse entreprise ; création/annulation professionnelles. |
| T003 — UI/portails selon permissions. | DONE | Vues calendar/messages et contrôles serveur ; parcours professionnel testé. |
| T004 — Tester isolation. | PARTIAL | Test interdit entreprise → patient ; pas de tests de lectures croisées ou destinataire autre tenant, ni RDV inter-patient. |
| T005 — Décider séparément de Google/Outlook. | BLOCKED | Décision future Google/Outlook non fournie ; hors périmètre interne initial et aucune intégration prétendue. |

**Prochaine action :** Tester isolation des lectures et envois avant toute intégration externe.

<a id="feature-016"></a>

### 016 — Comparateur : PARTIAL

Source : `specs/016-comparison/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** Comparison.snapshot chiffré, IDs passations, même version exigée, graphiques et PDF basés sur copie.

**Preuves disponibles :** `W::test_comparison_export_and_account_creation`; `C::test_charts_escape_labels_and_history_only_includes_published_assessments (graphiques génériques)`.

**Limites / écarts au plan :** Test de stabilité historique absent ; IA comparaison absente, optionnelle ; pas de publication comparaison patient.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Formaliser entrées de comparaison. | DONE | Deux IDs distincts, passations terminées et même assessment_definition_id exigés. |
| T002 — Utiliser snapshots versionnés. | DONE | Copie des résultats/version/noms ; aucune invocation Scoring lors du rendu comparaison. |
| T003 — Générer vues/graphiques. | DONE | modules/compare, comparison-pdf et Charts ; rendu et export testés. |
| T004 — Intégrer analyse IA comme brouillon si utilisée. | MISSING | Analyse IA non intégrée ; analyse manuelle professionnelle seulement. Tâche conditionnelle « si utilisée », absence sans publication automatique ni contradiction. |
| T005 — Tester stabilité historique. | MISSING | Aucun test modifie les sources après création pour comparer le snapshot ni ne vérifie le rejet des versions différentes. |

**Prochaine action :** Tester snapshot après évolution des données sources et refus versions incompatibles.

<a id="feature-017"></a>

### 017 — Courriers et PDF : PARTIAL

Source : `specs/017-letters-pdf/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** DomPDF, trois sorties, Charts, Markdown HTML sécurisé, branding, pivot document_letter et ZIP contrôlé.

**Preuves disponibles :** `W::test_patient_consent_submission_lock_and_publication`; `W::test_comparison_export_and_account_creation`; `W::test_modules_mutations_and_pdfs`; `C::test_letters_reject_another_patients_documents_and_produce_a_bundle`; `C::test_logo_is_private_and_included_in_pdf`; `C::test_charts_escape_labels_and_history_only_includes_published_assessments`.

**Limites / écarts au plan :** Tests PDF vérifient surtout HTTP/MIME, pas extraction du contenu prouvant exclusion draft ; rendu PDF mélange filtrage et requêtes dans contrôleurs.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — PDF évaluation. | DONE | AssessmentController::pdf et evaluations/pdf ; permission avant rendu, test avant/après publication. |
| T002 — PDF comparaison. | DONE | comparisonPdf limité professionnel, données snapshot ; MIME et refus patient testés. |
| T003 — PDF courrier. | DONE | letterPdf, rendu DomPDF ; ZIP contient un PDF reconnu dans test. |
| T004 — Branding cabinet. | DONE | BrandingController et partial pdf-brand ; logo chiffré ; test upload/PDF, pas de vérification visuelle du logo. |
| T005 — Formaliser pièces jointes si retenues. | DONE | Pivot document_letter, contrôle même client et archive chiffrée au stockage puis ZIP autorisé ; contenu pièce testé. |
| T006 — Tester exclusion des brouillons non publiés. | PARTIAL | Refus PDF non publié testé ; template sélectionne published_content, mais aucun test extrait le PDF après édition privée pour chercher un marqueur secret. |

**Prochaine action :** Tester le contenu PDF patient après révision privée et les données autorisées.

<a id="feature-018"></a>

### 018 — Audit, rétention et sécurité : PARTIAL

Source : `specs/018-audit-retention-security/spec.md`, `plan.md`, `tasks.md` de la copie identifiée.

**Code et exigences :** AuditLog, scopes, Access, Retention, Backups, chiffrement, URLs signées et workflow verrouillé.

**Preuves disponibles :** `W::test_cross_tenant_and_cross_patient_access_are_denied`; `W::test_document_is_encrypted_private_and_signature_does_not_bypass_access`; `W::test_patient_consent_submission_lock_and_publication`; `C::test_anonymization_requires_expired_archived_dossier_and_correct_confirmation`; `C::test_retention_hold_and_recent_appointment_prevent_erasure`; `C::test_backup_authentication_rejects_tampering`.

**Limites / écarts au plan :** Journal incomplet/non testé ; politique métier non validée ; tests croisés non exhaustifs et stockage durable absent ; conservation IA corrigée dans 009, fournisseur réel non validé.

| Tâche source | Statut | Constat et preuve / manque |
|---|---|---|
| T001 — Inventorier actions sensibles. | PARTIAL | Inventaire d’écarts dans cet audit ; journalisation existante couvre dossiers, publications, documents et mutations mais pas toutes les actions sensibles. |
| T002 — Implémenter AuditLog. | DONE | M1, AuditLog, Access::audit, liste administration ; pas de test d’intégrité/contenu du journal. |
| T003 — Définir politique de rétention. | BLOCKED | Mécanisme existe (délai par cabinet, archivage, suspension, anonymisation), mais validation métier de la durée et des objets hors dossier absente. |
| T004 — Vérifier toutes les policies/middlewares. | PARTIAL | Lecture de routes/middleware/Access et contrôleurs réalisée ; pas une preuve exhaustive d’absence de faille, matrice négative incomplète. |
| T005 — Tester isolation inter-tenant/inter-personne. | PARTIAL | Tests inter-tenant/personne existants ; manques messagerie/agenda/entreprises explicités en 001/011/012/015. |
| T006 — Vérifier stockage privé et URLs temporaires. | PARTIAL | Chiffrement et signature testés ; durabilité distante et conservation complète non livrées. |
| T007 — Vérifier verrouillage des passations. | PARTIAL | 409 après soumission couvert ; reprise navigateur et concurrence MySQL non exécutées. |
| T008 — Exécuter revue de convergence globale. | DONE | Présent audit 001–018, matrices tâches/preuves, roadmap et traçabilité ; aucune feature développée. |

**Prochaine action :** Compléter ultérieurement audit/rétention et recette transverse ; fournisseur 009 réel bloqué.

## Annexe — empreintes des 57 documents source lus

Chemins relatifs à la copie externe identifiée ; SHA-256. Ces empreintes identifient les sources sans affirmer qu’elles sont versionnées dans ce dépôt.

```text
7a93032f2cdb3c0225e3797c60a1f8c2e2b4836570c96382bfcb3e51eef525b1  .specify/memory/constitution.md
a5229b9404bdc2686977bb2c80aa79095f1a8006cd91c91e88646e63ec96a950  ROADMAP.md
1e5e34b3026397c86d9dae5729d36475aa367d8936ce80b1fd3d18eafe8a3336  TRACEABILITY.md
bf5a013e6a3b1f1cde11dd914daa181f1e2fe928b4e3bf229437677a9175608c  specs/001-identity-roles-isolation/plan.md
fb473f4cbb9c0a4611286809e2dfb4e7389f335c465232f883b0f36e7f318b0b  specs/001-identity-roles-isolation/spec.md
a30ae84440adcf3788f7844f43ef99a1369af26d963abd478f7d9e491a62de16  specs/001-identity-roles-isolation/tasks.md
139397b6307151bc6dfe3840bde2ba32c6d0048501d9180ac5429dae82825cdb  specs/002-client-organization-crm/plan.md
d2c3b61d147e1433b583ecde9cd0dcda88ee69d131e81aaf1a2f458e1a20e50f  specs/002-client-organization-crm/spec.md
e8a5c0fe3b1c7213517e24e805eb838b81a22e18939449d58ebc4ea7d16f684d  specs/002-client-organization-crm/tasks.md
b12af13fd5f9aa8093364d460f51bbba6bf2d9599cc305b3d43d3218b89190d6  specs/003-versioned-assessment-definitions/plan.md
8f105326086880bd679063f785b3bbf50636780283ffea01a949bee133408179  specs/003-versioned-assessment-definitions/spec.md
f531c62defcc849e7ddd8b1e4b3550a039f1ce635e93d2145cdfb2a6cb0a575f  specs/003-versioned-assessment-definitions/tasks.md
9857ab16a3c5c255be6cf5276d628500104307e69e9ef6fdf42288e72d5f3f07  specs/004-assessment-workflow/plan.md
31af50f60b26389a5469e2488d2fab6b87f36f648467f774c29a98264a404d21  specs/004-assessment-workflow/spec.md
5d99812c318db8bc2262423debca851bd03dbb7712b25fc80c55eca46399c28e  specs/004-assessment-workflow/tasks.md
10eba41515f9fd64b289e2b820dc891461f4fd274517fffa790f84ccc91790df  specs/005-consent-privacy/plan.md
a968dadffb68927f319662d8faf6e2056a1db89c44cf5f8ea4eb9e1fa4b3f397  specs/005-consent-privacy/spec.md
ca57c349bdc1c484b04ac993f64893dce384c4be5fac6a60e24db98949691644  specs/005-consent-privacy/tasks.md
1f4ce1c3a527f8b47f4117c30eda1a6b804b0342bdd4f373ae500a6186fa9188  specs/006-gordon-scoring/plan.md
e20171a1d2b7a86424aef773a0df4f927db9310eaea8a075992d2cb04377bb64  specs/006-gordon-scoring/spec.md
0769e6824497bd0a2e882e2df635a012ebb51c06b83380d66d87f5837d6c02c6  specs/006-gordon-scoring/tasks.md
c0f401410cf87154d79cbfa3da118dd386d9dfc8f424f7d73cce426bcd1ddb50  specs/007-enneagramme/plan.md
7f3a4c0c8857ef0b60a552174924f22afc822fa9e009e6142dcc67e6eb2b6a53  specs/007-enneagramme/spec.md
4cb38cc6413eb18427c4c90af482792b13a4ab070c8beeeda6150b57bc51c59f  specs/007-enneagramme/tasks.md
cde0eeee21e8728f76cbe6a7a9a1671aa56190037e10892ca31928c1bc018484  specs/008-needs-custom-tests/plan.md
c5030db71663d145f2606d438ce5f7c9723ff27c4a022f0a7b13babbb9613111  specs/008-needs-custom-tests/spec.md
5f54577d17b02c713764fb4c5d36afb0a08cf313f5f4902c9e44b74e12f7b3aa  specs/008-needs-custom-tests/tasks.md
a6ca759a8a224d9df6fb3859c4aa9f51a05708660fb767ad8d11062326de0a75  specs/009-ai-interpretation/plan.md
f830577d46dc2c5297cabecaab3bc571008733576cc8cc8991e6d34ce012b172  specs/009-ai-interpretation/spec.md
0b95cac8be656f5592453f8e205ccbda840afdae447b995ba7cdae9780e8e80b  specs/009-ai-interpretation/tasks.md
1e2ee2b2549bc7829a2db4181322435d0d5c11eed293be4f569731e542ac72b6  specs/010-review-publication/plan.md
160674a36ff1d954934651715e61f0f376d0e03668eba286d7e9f18f0ba63617  specs/010-review-publication/spec.md
9d67342c2e16e6b48818f10f05890a6699b0c6031d4298ae8327242f151df644  specs/010-review-publication/tasks.md
b199da56ebe03801dabc43a031d11440b5476e43e208a960f03433c8093d4c3e  specs/011-patient-portal/plan.md
fb611ab6d78c4ef91eea5ce2b1f933011f9b75148121ff2a7044a4151c02bca6  specs/011-patient-portal/spec.md
77399d8494ed9b5ec78f421a31298c959146d187e19d5bc82a64fe4b1f0a1366  specs/011-patient-portal/tasks.md
c2e21834145b1b0569b880c9ed5b6cb61e992d626745faf22bd47e09bdb6b717  specs/012-company-portal/plan.md
90343bb0f25dc7f0f4600bd2aada281683310e81154600af714bf89b3e007384  specs/012-company-portal/spec.md
0288cf3c4a250cdef079f10508a70e144c2a1330a9382e82ad5b721cb2b24b66  specs/012-company-portal/tasks.md
ca0ef69722b04e71bb64381e8206940cd56fde67e6a921d8e7d4abc35fa8df83  specs/013-clinical-notes/plan.md
8b677df7a31c77b780d6bc2251b0a597ab487bd1299eebf6e9b89722b5e03112  specs/013-clinical-notes/spec.md
2097a1a8f830153cd1fe5e914372ad5e6de8ea409ba2a20dbc6b2070c9447c52  specs/013-clinical-notes/tasks.md
97c77092d1ddeaf2177bf650c219b93224812e19a96f2a46e51a0b5d9954a21d  specs/014-private-documents/plan.md
658ee1bb02cdfb991a0d975d9f2573fa29e89e8f1780ced98dccbea750fb7792  specs/014-private-documents/spec.md
e0d60269f39417070539cd4f5e5c9df9177a60397daa0f5e9d2b2a065af98c2d  specs/014-private-documents/tasks.md
0f42578e1ef5af6e7787a40795bc07935cb06286035912c83af2502631fe724f  specs/015-messaging-calendar/plan.md
71479e1991e40945173574184ccc03adda0c314db187e6f8721026860963b287  specs/015-messaging-calendar/spec.md
da3118013817fc3478956eb96c20be8d6d5cc53286a78bf0276d288e9f3514cb  specs/015-messaging-calendar/tasks.md
cb646ec7ca338949877401853246c23e6c00fbd837a6c1215ffb500badce6df6  specs/016-comparison/plan.md
39f8cb5d88d9fbc45b607ed7678203f8b13260b97a2e42be844e7db3b368c83f  specs/016-comparison/spec.md
f5b82e4f12714cc4b0db8c4bd8a08cd1e6e268ad9f2542c26f05480d3f7ae214  specs/016-comparison/tasks.md
5b326eaf695e0a75d82edb440bb1d54d2e310e4b979b5872f66aaa0de75b98e2  specs/017-letters-pdf/plan.md
2879eb103caaf667edd3218b29a19e40fc75a841830bf434e79fc356339d3673  specs/017-letters-pdf/spec.md
9a5b52e715142907562e80c81051770997002af1cc7e08ca597d021e4c33344e  specs/017-letters-pdf/tasks.md
cac49258582638c178e995d6f976123e285f49e7c7337dc7fa250e6da018b648  specs/018-audit-retention-security/plan.md
818ec337fa19012c890d6ecb63a6cf7639c9d9f9eb8529b7622de583ffdef569  specs/018-audit-retention-security/spec.md
260297386c189be3c6807f6f2f83cadc0d67585ccb58e1914bade49161b5c943  specs/018-audit-retention-security/tasks.md
```
