# Traçabilité — Spec Kit → Laravel → tests

Audit du 1 octobre 2026 sur `990f470`, baseline e71138c ; actualisation ciblée de la feature 009. La référence est la copie externe `Downloads/SPEC KIT/APP-007-SPEC-KIT-EXERCISE` : les dossiers `specs/` et `.specify/` ne sont pas présents dans le dépôt Laravel. Chemins et empreintes des 57 sources dans [SPEC-CONVERGENCE.md](docs/SPEC-CONVERGENCE.md). Les liens ci-dessous pointent vers du code réellement présent ; ils ne remplacent pas les limites de validation détaillées dans l’audit.

## Schéma et routes communs

- M0 : [création users/sessions](database/migrations/0001_01_01_000000_create_users_table.php).
- M1 : [tables cabinet](database/migrations/2026_09_25_000001_create_cabinet_tables.php).
- M2 : [compléments cabinet](database/migrations/2026_09_25_203858_complete_cabinet_features.php).
- [routes/web.php](routes/web.php) : 72 routes applicatives listées par Artisan ; [bootstrap/app.php](bootstrap/app.php) ajoute ActiveAccount et trustProxies. Aucun répertoire policies dédié : autorisations réparties entre Access, scopes, middleware et contrôleurs.
- Le schéma SQL utilise des clés étrangères simples ; l’égalité de tenant entre objets est contrôlée par l’application, pas par des contraintes tenant composites ou une RLS MySQL.

## Matrice des implémentations

| Feature / dossier source | Statut | Modèles | Contrôleurs, services et vues principales | Migrations | Routes principales |
|---|---|---|---|---|---|
| 001 — `001-identity-roles-isolation` | PARTIAL | [app/Models/User.php](app/Models/User.php)<br>[app/Models/Tenant.php](app/Models/Tenant.php)<br>[app/Models/Client.php](app/Models/Client.php)<br>[app/Models/Organization.php](app/Models/Organization.php)<br>[app/Models/TenantModel.php](app/Models/TenantModel.php) | [app/Http/Controllers/AuthController.php](app/Http/Controllers/AuthController.php)<br>[app/Http/Controllers/AccountController.php](app/Http/Controllers/AccountController.php)<br>[app/Services/Access.php](app/Services/Access.php)<br>[app/Http/Middleware/ActiveAccount.php](app/Http/Middleware/ActiveAccount.php) | M0, M1, M2 | POST /connexion ; PUT /administration/utilisateurs/{user}/role ; routes auth |
| 002 — `002-client-organization-crm` | PARTIAL | [app/Models/Client.php](app/Models/Client.php)<br>[app/Models/Organization.php](app/Models/Organization.php) | [app/Http/Controllers/CabinetController.php](app/Http/Controllers/CabinetController.php)<br>[app/Services/Access.php](app/Services/Access.php) | M1, M2 | GET/POST /clients ; GET/PUT/DELETE /clients/{client} ; GET/POST /organisations ; POST /corbeille/{id}/restaurer |
| 003 — `003-versioned-assessment-definitions` | PARTIAL | [app/Models/AssessmentDefinition.php](app/Models/AssessmentDefinition.php)<br>[app/Models/Assessment.php](app/Models/Assessment.php) | [app/Http/Controllers/DefinitionController.php](app/Http/Controllers/DefinitionController.php)<br>[app/Services/Scoring.php](app/Services/Scoring.php) | M1, M2 | GET/POST /questionnaires ; GET /questionnaires/{definition}/export |
| 004 — `004-assessment-workflow` | PARTIAL | [app/Models/Assessment.php](app/Models/Assessment.php) | [app/Http/Controllers/AssessmentController.php](app/Http/Controllers/AssessmentController.php)<br>[app/Services/Scoring.php](app/Services/Scoring.php)<br>[public/assets/app.js](public/assets/app.js)<br>[resources/views/evaluations/show.blade.php](resources/views/evaluations/show.blade.php) | M1 | POST /evaluations ; GET /evaluations/{assessment} ; PUT /evaluations/{assessment}/reponses |
| 005 — `005-consent-privacy` | PARTIAL | [app/Models/Consent.php](app/Models/Consent.php)<br>[app/Models/Client.php](app/Models/Client.php)<br>[app/Models/PrivacyRequest.php](app/Models/PrivacyRequest.php) | [app/Http/Controllers/CabinetController.php](app/Http/Controllers/CabinetController.php)<br>[app/Http/Controllers/PrivacyController.php](app/Http/Controllers/PrivacyController.php)<br>[config/psycho.php](config/psycho.php) | M1, M2 | POST /consentement ; POST /consentement/retrait ; POST /profil/demandes |
| 006 — `006-gordon-scoring` | PARTIAL | [app/Models/AssessmentDefinition.php](app/Models/AssessmentDefinition.php)<br>[app/Models/Assessment.php](app/Models/Assessment.php) | [app/Services/Scoring.php](app/Services/Scoring.php)<br>[app/Http/Controllers/DefinitionController.php](app/Http/Controllers/DefinitionController.php)<br>[app/Http/Controllers/AssessmentController.php](app/Http/Controllers/AssessmentController.php) | M1 | POST /questionnaires ; PUT /evaluations/{assessment}/reponses |
| 007 — `007-enneagramme` | PARTIAL | [app/Models/AssessmentDefinition.php](app/Models/AssessmentDefinition.php)<br>[app/Models/Assessment.php](app/Models/Assessment.php) | [app/Services/Scoring.php](app/Services/Scoring.php)<br>[app/Http/Controllers/DefinitionController.php](app/Http/Controllers/DefinitionController.php)<br>[database/seeders/DemoSeeder.php](database/seeders/DemoSeeder.php) | M1 | POST /questionnaires ; PUT /evaluations/{assessment}/reponses ; aucun endpoint migration historique |
| 008 — `008-needs-custom-tests` | PARTIAL | [app/Models/AssessmentDefinition.php](app/Models/AssessmentDefinition.php)<br>[app/Models/Assessment.php](app/Models/Assessment.php) | [app/Services/Scoring.php](app/Services/Scoring.php)<br>[app/Http/Controllers/DefinitionController.php](app/Http/Controllers/DefinitionController.php)<br>[database/seeders/DemoSeeder.php](database/seeders/DemoSeeder.php)<br>[resources/views/definitions/builder.blade.php](resources/views/definitions/builder.blade.php) | M1 | POST /questionnaires ; PUT /evaluations/{assessment}/reponses |
| 009 — `009-ai-interpretation` | PARTIAL | [app/Models/Interpretation.php](app/Models/Interpretation.php)<br>[app/Models/Assessment.php](app/Models/Assessment.php) | [app/Http/Controllers/AssessmentController.php](app/Http/Controllers/AssessmentController.php)<br>[config/psycho.php](config/psycho.php) | M1 + migration ai_generations (ci-dessous) | POST /evaluations/{assessment}/ia ; POST /evaluations/{assessment}/interpretation |
| 010 — `010-review-publication` | DONE | [app/Models/Interpretation.php](app/Models/Interpretation.php)<br>[app/Models/Assessment.php](app/Models/Assessment.php)<br>[app/Models/AuditLog.php](app/Models/AuditLog.php) | [app/Http/Controllers/AssessmentController.php](app/Http/Controllers/AssessmentController.php)<br>[app/Services/Access.php](app/Services/Access.php)<br>[resources/views/evaluations/show.blade.php](resources/views/evaluations/show.blade.php) | M1 | POST /evaluations/{assessment}/interpretation ; /publier ; /depublier |
| 011 — `011-patient-portal` | PARTIAL | [app/Models/User.php](app/Models/User.php)<br>[app/Models/Client.php](app/Models/Client.php)<br>[app/Models/Assessment.php](app/Models/Assessment.php)<br>[app/Models/Document.php](app/Models/Document.php)<br>[app/Models/Message.php](app/Models/Message.php)<br>[app/Models/Appointment.php](app/Models/Appointment.php) | [app/Http/Controllers/CabinetController.php](app/Http/Controllers/CabinetController.php)<br>[app/Http/Controllers/AssessmentController.php](app/Http/Controllers/AssessmentController.php)<br>[app/Http/Controllers/ModuleController.php](app/Http/Controllers/ModuleController.php)<br>[resources/views/dashboard.blade.php](resources/views/dashboard.blade.php)<br>[resources/views/modules/profile.blade.php](resources/views/modules/profile.blade.php) | M1, M2 | GET / ; /profil ; /documents ; /messagerie ; /calendrier ; /evaluations/{assessment} |
| 012 — `012-company-portal` | PARTIAL | [app/Models/User.php](app/Models/User.php)<br>[app/Models/Organization.php](app/Models/Organization.php)<br>[app/Models/Document.php](app/Models/Document.php)<br>[app/Models/Message.php](app/Models/Message.php) | [app/Http/Controllers/CabinetController.php](app/Http/Controllers/CabinetController.php)<br>[app/Http/Controllers/ModuleController.php](app/Http/Controllers/ModuleController.php)<br>[app/Services/Access.php](app/Services/Access.php)<br>[resources/views/modules/company.blade.php](resources/views/modules/company.blade.php) | M1 | GET / ; /documents ; GET/POST /messagerie |
| 013 — `013-clinical-notes` | PARTIAL | [app/Models/ClinicalNote.php](app/Models/ClinicalNote.php) | [app/Http/Controllers/CabinetController.php](app/Http/Controllers/CabinetController.php)<br>[app/Http/Controllers/PrivacyController.php](app/Http/Controllers/PrivacyController.php)<br>[app/Services/Access.php](app/Services/Access.php)<br>[resources/views/clients/show.blade.php](resources/views/clients/show.blade.php) | M1 | GET /clients/{client} ; POST /clients/{client}/notes ; GET /droits/clients/{id}/export |
| 014 — `014-private-documents` | PARTIAL | [app/Models/Document.php](app/Models/Document.php) | [app/Http/Controllers/ModuleController.php](app/Http/Controllers/ModuleController.php)<br>[app/Services/Access.php](app/Services/Access.php)<br>[app/Services/Retention.php](app/Services/Retention.php)<br>[config/filesystems.php](config/filesystems.php) | M1, M2 | GET/POST /documents ; GET /documents/{document}/lien ; GET /documents/{document}/telecharger (signed) |
| 015 — `015-messaging-calendar` | PARTIAL | [app/Models/Message.php](app/Models/Message.php)<br>[app/Models/Appointment.php](app/Models/Appointment.php) | [app/Http/Controllers/ModuleController.php](app/Http/Controllers/ModuleController.php)<br>[resources/views/modules/messages.blade.php](resources/views/modules/messages.blade.php)<br>[resources/views/modules/calendar.blade.php](resources/views/modules/calendar.blade.php) | M1 | GET/POST /messagerie ; GET/POST /calendrier ; POST /calendrier/{appointment}/annuler |
| 016 — `016-comparison` | PARTIAL | [app/Models/Comparison.php](app/Models/Comparison.php)<br>[app/Models/Assessment.php](app/Models/Assessment.php) | [app/Http/Controllers/ModuleController.php](app/Http/Controllers/ModuleController.php)<br>[app/Services/Charts.php](app/Services/Charts.php)<br>[resources/views/modules/compare.blade.php](resources/views/modules/compare.blade.php)<br>[resources/views/modules/comparison-pdf.blade.php](resources/views/modules/comparison-pdf.blade.php) | M1 | GET/POST /comparateur ; GET /comparateur/{comparison}/pdf |
| 017 — `017-letters-pdf` | PARTIAL | [app/Models/Letter.php](app/Models/Letter.php)<br>[app/Models/Document.php](app/Models/Document.php)<br>[app/Models/Tenant.php](app/Models/Tenant.php)<br>[app/Models/Interpretation.php](app/Models/Interpretation.php) | [app/Http/Controllers/AssessmentController.php](app/Http/Controllers/AssessmentController.php)<br>[app/Http/Controllers/ModuleController.php](app/Http/Controllers/ModuleController.php)<br>[app/Http/Controllers/BrandingController.php](app/Http/Controllers/BrandingController.php)<br>[app/Services/Charts.php](app/Services/Charts.php)<br>[resources/views/evaluations/pdf.blade.php](resources/views/evaluations/pdf.blade.php)<br>[resources/views/modules/letter-pdf.blade.php](resources/views/modules/letter-pdf.blade.php)<br>[resources/views/partials/pdf-brand.blade.php](resources/views/partials/pdf-brand.blade.php) | M1, M2 (document_letter) | GET /evaluations/{assessment}/pdf ; /comparateur/{comparison}/pdf ; /courriers/{letter}/pdf ; /courriers/{letter}/archive ; POST/DELETE /administration/logo |
| 018 — `018-audit-retention-security` | PARTIAL | [app/Models/AuditLog.php](app/Models/AuditLog.php)<br>[app/Models/Client.php](app/Models/Client.php)<br>[app/Models/PrivacyRequest.php](app/Models/PrivacyRequest.php)<br>[app/Models/TenantModel.php](app/Models/TenantModel.php) | [app/Services/Access.php](app/Services/Access.php)<br>[app/Services/Retention.php](app/Services/Retention.php)<br>[app/Services/Backups.php](app/Services/Backups.php)<br>[app/Http/Controllers/PrivacyController.php](app/Http/Controllers/PrivacyController.php)<br>[app/Http/Middleware/ActiveAccount.php](app/Http/Middleware/ActiveAccount.php)<br>[routes/console.php](routes/console.php) | M1, M2 | GET /administration ; GET /droits ; POST /droits/clients/{id}/conservation ; /effacer ; commandes cabinet:backup et cabinet:restore |

## Matrice des tests existants

Un test cité peut ne couvrir qu’une partie de la feature. Les limites sont indiquées ; aucune couverture intégrale n’est inférée de son nom.

| Feature | Méthodes existantes | Portée restant à couvrir |
|---|---|---|
| 001 | [W::test_guest_and_login_and_disabled_account](tests/Feature/WorkflowTest.php)<br>[W::test_cross_tenant_and_cross_patient_access_are_denied](tests/Feature/WorkflowTest.php)<br>[W::test_counsellor_cannot_see_notes_or_publish](tests/Feature/WorkflowTest.php)<br>[W::test_company_has_only_organization_documents](tests/Feature/WorkflowTest.php)<br>[C::test_role_changes_revoke_sessions_and_are_tenant_scoped](tests/Feature/CompletionTest.php)<br>[C::test_non_admin_cannot_manage_access_or_read_local_mail](tests/Feature/CompletionTest.php) | Couverture inter-organisation incomplète ; audit des accès/invitations incomplet. |
| 002 | [W::test_all_professional_pages_render](tests/Feature/WorkflowTest.php)<br>[W::test_archiving_disables_patient_and_pages_keep_rendering](tests/Feature/WorkflowTest.php)<br>[W::test_modules_mutations_and_pdfs](tests/Feature/WorkflowTest.php)<br>[W::test_cross_tenant_and_cross_patient_access_are_denied](tests/Feature/WorkflowTest.php) | Organisation : création/lecture seulement ; absence de tests dédiés au rattachement et à la création/modification complète Client. Message lié aux acteurs et indirectement au Client. |
| 003 | [W::test_questionnaire_new_version_does_not_modify_existing_assessments](tests/Feature/WorkflowTest.php)<br>[C::test_official_definitions_require_source_and_authorization_and_export_roundtrips](tests/Feature/CompletionTest.php) | Immutabilité garantie par le parcours HTTP de création, pas par un verrou modèle/base ; règles et dimensions embarquées, pas d’entités séparées. |
| 004 | [W::test_patient_consent_submission_lock_and_publication](tests/Feature/WorkflowTest.php)<br>[W::test_revocation_blocks_autosave](tests/Feature/WorkflowTest.php)<br>[W::test_cross_tenant_and_cross_patient_access_are_denied](tests/Feature/WorkflowTest.php) | Reprise après rechargement et concurrence réelle non testées ; transitions réparties dans AssessmentController plutôt que service proposé. |
| 005 | [W::test_patient_consent_submission_lock_and_publication](tests/Feature/WorkflowTest.php)<br>[W::test_revocation_blocks_autosave](tests/Feature/WorkflowTest.php)<br>[C::test_privacy_requests_and_export_keep_unpublished_scores_private](tests/Feature/CompletionTest.php) | Validation métier du retrait absente ; renouvellement de version et audit non testés explicitement. |
| 006 | [W::test_gordon_grid_scores_and_boolean_normalization](tests/Feature/WorkflowTest.php)<br>[W::test_questionnaire_new_version_does_not_modify_existing_assessments](tests/Feature/WorkflowTest.php) | Test de reproductibilité dédié absent ; grille réelle autorisée non fournie. |
| 007 | [C::test_official_definitions_require_source_and_authorization_and_export_roundtrips](tests/Feature/CompletionTest.php) | Aucune migration des deux anciens formats, aucun type dominant ; choix métier du format et compatibilité non confirmés. |
| 008 | [W::test_patient_consent_submission_lock_and_publication](tests/Feature/WorkflowTest.php)<br>[C::test_official_definitions_require_source_and_authorization_and_export_roundtrips](tests/Feature/CompletionTest.php) | Explications/cas spécifiques des situations absents de la démo ; pas de test dédié besoins ni de restitution sans score ; textes officiels absents. |
| 009 | [AiInterpretationHistoryTest](tests/Feature/AiInterpretationHistoryTest.php) : original_survives_review_publication_and_regeneration (admin/psychologue), full_original_is_kept_even_when_draft_is_limited_and_history_is_escaped, failed_generation_preserves_all_existing_content, unauthorized_roles_cannot_generate_or_read_originals, connection_failure_does_not_create_an_interpretation, other_tenant_cannot_access_history_or_generate, additive_migration_does_not_invent_a_legacy_original ; [WorkflowTest](tests/Feature/WorkflowTest.php) : opt-in et publication existants | Fournisseur réel BLOCKED faute de crédits ; MySQL 8.4/concurrence et déploiement non testés. |
| 010 | [W::test_patient_consent_submission_lock_and_publication](tests/Feature/WorkflowTest.php)<br>[W::test_counsellor_cannot_see_notes_or_publish](tests/Feature/WorkflowTest.php)<br>[W::test_patient_cannot_inject_html_and_score_stays_hidden_until_publication](tests/Feature/WorkflowTest.php) | Historique intégral des publications non conservé (limite, pas exigence ferme de cette spec) ; assertions de contenu AuditLog absentes. |
| 011 | [W::test_patient_consent_submission_lock_and_publication](tests/Feature/WorkflowTest.php)<br>[W::test_cross_tenant_and_cross_patient_access_are_denied](tests/Feature/WorkflowTest.php)<br>[W::test_document_is_encrypted_private_and_signature_does_not_bypass_access](tests/Feature/WorkflowTest.php)<br>[C::test_charts_escape_labels_and_history_only_includes_published_assessments](tests/Feature/CompletionTest.php) | Dashboard affiche seulement les 6 dernières passations sans liste complète patient ; isolation agenda/messages non couverte par tests dédiés. |
| 012 | [W::test_company_has_only_organization_documents](tests/Feature/WorkflowTest.php) | Pas de test deux organisations distinctes ni de lecture messagerie entreprise autorisée complète. |
| 013 | [W::test_counsellor_cannot_see_notes_or_publish](tests/Feature/WorkflowTest.php)<br>[C::test_privacy_requests_and_export_keep_unpublished_scores_private](tests/Feature/CompletionTest.php) | Pas d’édition/suppression individuelle (CRUD demandé), ni tests explicites de toutes les routes pour patient/entreprise ; audit lecture au dossier seulement. |
| 014 | [W::test_document_is_encrypted_private_and_signature_does_not_bypass_access](tests/Feature/WorkflowTest.php)<br>[W::test_company_has_only_organization_documents](tests/Feature/WorkflowTest.php)<br>[H::test_signed_document_download_works_behind_the_https_proxy](tests/Feature/RenderHttpsTest.php)<br>[C::test_letters_reject_another_patients_documents_and_produce_a_bundle](tests/Feature/CompletionTest.php) | Pas de stockage durable Render ; conservation seulement dans purge Client, pas de politique documents entreprise/non rattachés. |
| 015 | [W::test_modules_mutations_and_pdfs](tests/Feature/WorkflowTest.php)<br>[W::test_company_has_only_organization_documents](tests/Feature/WorkflowTest.php) | Tests d’isolation lecture des messages/RDV absents ; synchronisation externe non décidée et non implémentée. |
| 016 | [W::test_comparison_export_and_account_creation](tests/Feature/WorkflowTest.php)<br>[C::test_charts_escape_labels_and_history_only_includes_published_assessments](tests/Feature/CompletionTest.php) | Test de stabilité historique absent ; IA comparaison absente, optionnelle ; pas de publication comparaison patient. |
| 017 | [W::test_patient_consent_submission_lock_and_publication](tests/Feature/WorkflowTest.php)<br>[W::test_comparison_export_and_account_creation](tests/Feature/WorkflowTest.php)<br>[W::test_modules_mutations_and_pdfs](tests/Feature/WorkflowTest.php)<br>[C::test_letters_reject_another_patients_documents_and_produce_a_bundle](tests/Feature/CompletionTest.php)<br>[C::test_logo_is_private_and_included_in_pdf](tests/Feature/CompletionTest.php)<br>[C::test_charts_escape_labels_and_history_only_includes_published_assessments](tests/Feature/CompletionTest.php) | Tests PDF vérifient surtout HTTP/MIME, pas extraction du contenu prouvant exclusion draft ; rendu PDF mélange filtrage et requêtes dans contrôleurs. |
| 018 | [W::test_cross_tenant_and_cross_patient_access_are_denied](tests/Feature/WorkflowTest.php)<br>[W::test_document_is_encrypted_private_and_signature_does_not_bypass_access](tests/Feature/WorkflowTest.php)<br>[W::test_patient_consent_submission_lock_and_publication](tests/Feature/WorkflowTest.php)<br>[C::test_anonymization_requires_expired_archived_dossier_and_correct_confirmation](tests/Feature/CompletionTest.php)<br>[C::test_retention_hold_and_recent_appointment_prevent_erasure](tests/Feature/CompletionTest.php)<br>[C::test_backup_authentication_rejects_tampering](tests/Feature/CompletionTest.php) | Journal incomplet/non testé ; politique métier non validée ; tests croisés non exhaustifs et stockage durable absent ; reproduction IA contredite (009). |

## Équivalences et écarts de conception

- 001 : rôle dans User, pas d’entités Identity/Profile/Role/Permission séparées ; contrôles serveur présents.
- 003 : une ligne AssessmentDefinition constitue une version ; questions, dimensions et règles sont embarquées plutôt que réparties en tables. L’absence de classes portant les noms du plan n’est pas à elle seule une non-conformité. L’absence de verrou d’immutabilité interne reste un écart distinct.
- 004 : answers/results chiffrés remplacent les entités Response/Score séparées ; transitions centralisées dans AssessmentController, pas dans un service de workflow.
- 005 : configuration texte/version + copies Consent équivalent à ConsentDefinition/Record, sans éditeur dédié.
- 009 : génération conservée dans AssessmentController ; historique chiffré dans Interpretation.ai_generations, distinct de draft et published_content. Pas de refonte du service ni de publication automatique.
- 010 : approved/published matérialisés par reviewed requis, reviewed_by/published_at et published_content ; pas d’archive complète des publications successives.
- 017 : DomPDF assure le rendu, mais orchestration/export restent dans les contrôleurs ; les templates ne sont pas un service métier de scoring.
- 015 : Google/Outlook séparés du domaine interne, non implémentés et non déclarés fonctionnels.

## Traçabilité du déploiement (transverse, hors nouvelle feature)

| Élément | Preuve dans le dépôt | Portée |
|---|---|---|
| Render Docker | [Dockerfile](Dockerfile), [render.yaml](render.yaml) | Infrastructure décrite, pas preuve des paramètres privés effectivement déployés |
| CA Aiven / TLS | [entrypoint](docker/entrypoint.sh), [database.php](config/database.php) | Décodage CA et option PDO ; secrets exclus du dépôt |
| HTTPS proxy | [bootstrap/app.php](bootstrap/app.php), commit d277679 | trustProxies conservé |
| Tests proxy | [RenderHttpsTest](tests/Feature/RenderHttpsTest.php), commit 990f470 | Assets absolus HTTPS et liens signés simulés, HTTP local |
| État observé | [VALIDATION.md](docs/VALIDATION.md) | Authentification/TLS historiques rapportés ; endpoints publics vérifiés pendant cet audit |

## Hors preuves et décisions manquantes

Aucune validation métier officielle de la matrice de permissions, du format Ennéagramme ou des règles juridiques de rétention n’est déduite des tests. Aucun scoring Ennéagramme, seuil clinique, fournisseur IA ou synchronisation externe n’est inventé. Les références autorisées et les fixtures de migration historique restent nécessaires.

## Ajouts 009 — persistance et confidentialité

- Migration additive : [2026_10_01_160120_add_ai_generations_to_interpretations_table.php](database/migrations/2026_10_01_160120_add_ai_generations_to_interpretations_table.php), colonne nullable ai_generations sur interpretations, aucun backfill.
- [Interpretation](app/Models/Interpretation.php) : cast encrypted:array et hidden pour l’historique ; relations existantes inchangées.
- [AssessmentController::ai](app/Http/Controllers/AssessmentController.php) : append atomique avec copie exacte des messages et contenu original intégral ; événement interpretation.generee conservé. Édition et publication inchangées.
- [Historique professionnel](resources/views/evaluations/ai-history.blade.php), inclus par [show](resources/views/evaluations/show.blade.php) : texte échappé, admin/psychologue seulement.
- [AiInterpretationHistoryTest](tests/Feature/AiInterpretationHistoryTest.php) : 12 cas / 143 assertions, requêtes IA simulées et réseau inattendu interdit ; aucune validation OpenAI réelle.


## 019 — PatientAI P0 + v0.1

| Tâches | Réalisation / preuve |
|---|---|
| PAI-001 à 008 | Décisions P0 : docs/ARCHITECTURE.md ; handoff corrigé vers PATIENTAI-CONSTITUTION.md |
| PAI-010 à 018 | config/patientai.php ; app/Services/{LlmProvider,FakeLlmProvider,ConversationIntentRouter,PatientAiChat}.php ; modèles AiConversation/AiMessage ; migration 2026_10_02_073257_create_patientai_tables.php |
| PAI-019 à 022 | PatientAiController, routes nommées, vue modules/patientai, layout et CSS ; audit sans texte |
| PAI-023 à 030 | tests/Feature/PatientAiTest.php : auth/rôles/flag, propriété, chiffrement, CSRF/XSS, erreurs, intentions, limites, réseau HTTP absent |
| PAI-031 | PatientAiLifecycle, PrivacyController::export, Retention::erase, commande/schedule patientai:purge ; tests export/effacement/cascade/suspension/expiration/isolation purge |
| PAI-032 à 034 | docs/VALIDATION.md : suite dédiée 33/237, complète 76/620, Pint et contrôles ; commit de livraison contenant ce suivi |
| v0.2 à v1.0 | Non implémentées. Aucun provider réel, contexte métier, outil, RAG ou mémoire résumée |

Preuves locales SQLite uniquement ; migration applicative/Aiven, déploiement, scheduler distant, concurrence MySQL et recette navigateur non validés.


## 019 — PatientAI v0.2

- PAI-040 : app/Services/PromptRegistry.php, version explicite patientai-v0.2, instructions/réponses/motifs centralisés et version inconnue refusée.
- PAI-041 : app/Services/SafetyPolicy.php ; PatientAiChat applique les refus avant provider ; FakeLlmProvider utilise le registre, contrat LlmProvider inchangé ; normalisation partagée avec ConversationIntentRouter.
- PAI-042 : tests/Feature/PatientAiPolicyTest.php, 35 nouveaux cas ; tests PatientAI 68/1100, suite 111/1484 ; refus sans provider, absence de SQL clinique/HTTP, identité, usurpation, fallback et salutations v0.1.
- Aucun contexte métier v0.3 ni version ultérieure ; tâches v1.0 non clôturées. Version active via registre/Git, sans historique de version persisté par message.


## 019 — PatientAI v0.3

- PAI-050 : app/Services/PatientContextFactory.php, résolution depuis auth()->id(), User::tenant()/client(), lectures limitées et identité revalidée dans les transactions.
- PAI-051 : app/Services/PatientContext.php, DTO final readonly avec exactement userId/tenantId/clientId ; PatientAiController/PatientAiChat autorisent et filtrent les conversations depuis ce contexte, jamais transmis au provider.
- PAI-052 : tests/Feature/PatientAiContextTest.php, 29 cas/302 assertions : identité invalide, relations obsolètes, isolation bidirectionnelle, paramètres IDOR, propriété falsifiée, déclarations dans le texte, absence de SQL clinique et HTTP, fallback v0.2.
- PatientAI 97/1530 ; suite complète 140/1910. Aucun outil métier v0.4, migration, provider distant ou déploiement. Limites détaillées dans docs/VALIDATION.md.


## 019 — PatientAI v0.4
- PAI-060 : PatientAssessmentTools::listMyAssessments, filtres bornés et DTO PatientAssessmentData/Result.
- PAI-061 : getMyAssessmentStatus, UUID validé, tenant/propriétaire/visibilité, erreurs non énumérables ; migration UUID et Assessment existants.
- PAI-062 : PatientAiAssessmentTest ; intégration router/chat/fake/formatter, tests isolation, statuts/liens, projection minimale et absence HTTP. Preuves et limites : docs/VALIDATION.md.


## 019 — PatientAI v0.5
- PAI-070 : knowledge/06-app007-functional-guide-patient.json et résumé Markdown, PatientGuideRegistry, audit des 14 rubriques contre code réel, métadonnées audience/approbation/version/provenance et liens Laravel.
- PAI-071 : QuestionnaireHelpTool, QuestionnaireHelpData/QuestionHelpData, définition/version réelle et isolation PatientContext ; requête autorisée v0.4 partagée.
- PAI-072 : PromptRegistry patientai-v0.5/SafetyPolicy prioritaire, tests anti-réponse/anti-profil et contenu documentaire inerte ; PatientHelpFormatter rend seulement les DTO autorisés.
- PAI-073 : PatientAiHelpTest, tests liens patient réels et exclusions professionnelles/futures, sans réseau. Résultats et limites dans docs/VALIDATION.md. Aucune v0.6+.


## 019 — PatientAI v0.6
- PAI-080 : PatientAppointmentTools, PatientAppointmentData/Result, PatientAppointmentFormatter ; Appointment existant, source commune liste/prochain, projection minimale, calendrier Laravel et contrôle de rendu PatientAiChat/FakeLlmProvider. Prompt actif patientai-v0.6 et correction factuelle guide patient-guide-v0.6.1.
- PAI-081 : PatientAiAppointmentTest, 22 cas / 178 assertions ; bornes temporelles/offsets/jour, isolation, paramètres falsifiés, provider minimal, absence HTTP et mutations refusées. PatientAI 176/2111 ; suite complète 219/2494.
- Aucune route/Blade/migration nouvelle, aucun v0.7+. Règles exactes et limites dans docs/ARCHITECTURE.md et docs/VALIDATION.md.


## 019 — PatientAI v0.7
- PAI-090 : PatientPublishedResultTool/Data/Formatter, Assessment/AssessmentDefinition/Interpretation et autorisation v0.4 réutilisées ; publication réelle verrouillée et projection fidèle des scores/texte publics.
- PAI-091 : sélection explicite publiée sans draft/ai_generations/input_snapshot/notes/answers ; conteneur results filtré côté serveur. FakeLlmProvider/PatientAiChat rendent et vérifient un DTO sans Eloquent ni permissions provider.
- PAI-092 : PatientAiPublishedResultTest, 26 cas / 157 assertions ; workflow publier/dépublier, isolation, incohérences, DTO, fidélité/absence d’invention, distinction faits/explication et absence HTTP. PatientAI 202/2245 ; suite 245/2628.
- Prompt actif patientai-v0.7, guide corrigé patient-guide-v0.7.1. Aucune v0.8+, route/Blade/migration nouvelle ; décisions/limites dans docs/ARCHITECTURE.md et docs/VALIDATION.md.


## 019 — PatientAI v0.8
- PAI-100 : PatientMemoryService/Data/Formatter, AiConversation encrypted:array/hidden, config bornée et migration add_controlled_memory_to_ai_conversations ; accord mémoire explicite et seule enum standard/concise.
- PAI-101 : PatientAiController/Blade modes avec/sans mémoire et route memory.clear ; PatientAiLifecycle export enrichi, rétention/suppression/anonymisation/hold réutilisés.
- PatientAiChat/SafetyPolicy/PromptRegistry patientai-v0.8 : outil métier prioritaire, mémoire minimale comme donnée et rendu provider contrôlé ; versions antérieures conservées.
- PatientAiMemoryTest : 39 cas / 402 assertions ; PatientAI 241/2647 ; suite 284/3030. Preuves et limites dans docs/VALIDATION.md. Aucun v0.9/RAG, provider distant ni migration appliquée ; flag OFF.


## 019 — PatientAI v0.9
- PAI-110 : PatientRagDocument/Chunk, casts encrypted/hidden, factories/seeder de brouillons, migration create_patient_rag_tables (non appliquée en base applicative).
- PAI-111 : PatientRagWorkflow draft/submit/review/reject/approve/index/retire, acteurs/dates/empreintes et commande locale PatientRagManage avec attestations explicites ; aucun import automatiquement approuvé.
- PAI-112/113 : exactement cinq audiences, PatientRagRetriever depuis PatientContext ; PATIENT_CONTEXTUAL lié à une définition/version assignée visible, trois audiences privées exclues avant chunks.
- PAI-114 : PatientRagResult/ChunkData/Provenance readonly, source publique/version/section observables sans source interne ni métadonnée professionnelle.
- PAI-115 : PromptRegistry patientai-v0.9, documents comme données distinctes, formatter/fake/rendu exact et priorité SafetyPolicy/outils/guide avant retrieval, mémoire sans influence sur les droits.
- PAI-116 : PatientAiRagTest 43 cas / 406 assertions ; PatientAI 284/3053, suite 327/3436. Procédure/bornes/limites : docs/ARCHITECTURE.md, docs/VALIDATION.md et knowledge/09-rag-workflow.md. Aucun v1.0, source clinique automatique ou provider distant ; flag OFF.

## 019 — PatientAI v1.0 readiness (documenté)
- Code-complete : **PASS**
- Local-test-complete : **PARTIAL**
- Production-ready : **NON**
- Preuves : PatientAI 284/3056 ; suite complète 327/3437 ; feature flag OFF ; aucun OpenAI / API externe / déploiement Aiven / rendus navigateur E2E exécutés.
- Blocages production documentés : MySQL réel, navigateur E2E, Aiven/Render, provider réel, scheduler de production.

## 020 — PatientAI Conversation v1.1
- Sources : [spec](specs/020-patientai-conversation-v1-1/spec.md), [plan](specs/020-patientai-conversation-v1-1/plan.md), [tasks](specs/020-patientai-conversation-v1-1/tasks.md), [acceptance](specs/020-patientai-conversation-v1-1/acceptance.md), [test matrix](specs/020-patientai-conversation-v1-1/conversation-test-matrix.md).
- Routage/résolution : [ConversationIntentRouter](app/Services/ConversationIntentRouter.php), [ConversationIntentResolution](app/Services/ConversationIntentResolution.php), [PatientAiChat](app/Services/PatientAiChat.php). SafetyPolicy v1.1 reste avant tout dispatch ; `PatientContextFactory` et les autorisations des outils Laravel sont conservés.
- Références : [PatientConversationReferences](app/Services/PatientConversationReferences.php), [AiConversation](app/Models/AiConversation.php), migration additive [2026_10_02_120000_add_conversation_context_to_ai_conversations.php](database/migrations/2026_10_02_120000_add_conversation_context_to_ai_conversations.php). Références chiffrées et masquées ; export uniquement métadonnées expurgées via [PatientAiLifecycle](app/Services/PatientAiLifecycle.php).
- Rendu : DTO assessment sans UUID pour le dialogue ([PatientAssessmentConversationResult](app/Services/PatientAssessmentConversationResult.php)); sélection de rendez-vous séparée du DTO provider ([PatientAppointmentSelection](app/Services/PatientAppointmentSelection.php)); FakeLlmProvider seulement.
- Tests : [PatientAiConversationTest](tests/Feature/PatientAiConversationTest.php) : 14 tests / 115 assertions couvrant intent, résolution, ambiguïté, follow-up, expurgation, rétention, migration locale et contexte ; suites Feature 019 couvrent les frontières des outils, refus, mémoire et RAG. 300 tests PatientAI / 3 251 assertions et 345 tests complets / 3 660 assertions.
- Statut : code-complete PASS ; local-test-complete PASS ; Render recipe PENDING, non exécutée. Migration non appliquée à Aiven ; aucun provider externe, déploiement ou push.

## Feature 021 — traçabilité locale (4 octobre 2026)

| Tâches | Implémentation / preuve |
|---|---|
| REV-001, 001–004, 013, 015 | AssessmentDefinition, migration additive 2026_10_02_233312, EnneagramScoring/Scoring ; EnneagramAssessmentTest, EnneagramWorkflowTest, EnneagramMigrationTest |
| 005–007, 014 | EnneagramDemoForms + DemoSeeder, EnneagramFormRotation, AssessmentController::assign/answers, protection du lien Assessment ; routes réelles A/B/C/A, transaction, snapshot, reprise, verrou |
| 008–010, 016 | DefinitionController review/approve/export/store ; vues definitions et evaluations index/show/results/pdf ; rôles, consentement, chiffrement, publication/dépublication |
| 011–012, 017 | Alias ConversationIntentRouter, guide patient-guide-v021.1, refus PromptRegistry, whitelist pondérée PatientPublishedResultTool ; DTO existants inchangés ; tests adversariaux et toutes suites PatientAI |
| 018–020 | docs/VALIDATION.md, architecture/roadmap/specs/tasks ; tests, Pint du périmètre, routes/Blade/diff/scan secrets ; livraison Git locale uniquement |
| REV-002 — contenu externe | Garde-fou testé ; aucune source officielle fournie ni forme opérationnelle APPROVED. Approbation de contenu/licence professionnelle réelle reste PENDING. |

68 tests dédiés / 361 assertions ; PatientAI 312 / 3 360 ; suite complète 413 / 4 026. Code/local tests PASS, contenu psychométrique et déploiement PENDING. Concurrence MySQL non validée ; deux écarts Pint globaux présents dans HEAD et non modifiés. Aucun changement aux permissions/classifications PatientAI 019/020, aucun résultat privé ou non publié autorisé au provider.


Feature 021 — correction multi-formes : DefinitionController (`creation_mode`), catalogue Questionnaires, AssessmentDefinition (`version_scope`) et migration `2026_10_04_165857` ; preuves dans EnneagramWorkflowTest et EnneagramMigrationTest. A/B/C même famille à v1, versions par forme, tenant/publisher/DEMO/audit et rotation inchangée ; aucune migration distante ni déploiement de cette correction. E021-REV-002 toujours en attente.
