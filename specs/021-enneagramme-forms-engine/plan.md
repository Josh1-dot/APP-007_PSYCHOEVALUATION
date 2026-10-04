# Plan — Feature 021 Ennéagramme Forms Engine

**État initial vérifié :** une définition DEMO `self-report-v1`, neuf échelles 0..100 recopiées sans calcul, pas de formes/rotation, pas de source psychométrique approuvée constatée. Les assertions de validation finale seront datées dans `docs/VALIDATION.md`.

## A. Décisions d’architecture

- Feature 021 séparée des Features 019/020 et Feature 003/004 historiques.
- Réutiliser `AssessmentDefinition` versionnée comme snapshot backend d’une forme ; questions JSON = items de la forme versionnée. Réutiliser `Assessment` comme assignation/passation immuable et son lien existant `assessment_definition_id` comme provenance/reproductibilité.
- Migration additive minimale pour form key, règles de scoring et reçus auteur/review/approbation/content status. Ne pas créer de tables banque/forms redondantes.
- `Scoring` gagne une branche `enneagramme-weighted-v1`; `self-report-v1` et toutes les branches Gordon/raw-v1 restent compatibles.
- Les formes DEMO rédigées pour tests restent `DEMO`, jamais `APPROVED`. Une forme officielle nécessite source/licence et revue explicite.

## B. Lots d’implémentation

1. **Schema/data model** : migration additive, casts/immutabilité Ennéagramme, backfill déterministe des définitions existantes vers `DEMO`/`DRAFT` selon `is_demo` ; aucun backfill de réponse/résultat.
2. **Validation/versioning** : valider forme, form_key, stable item keys, langue/provenance, neuf dimensions, rules map exacte et statut. Création suivante produit nouvelle version ; une forme utilisée ne se modifie pas.
3. **Approval workflow/UI pro** : visibilité du contenu/status/source/méthode, review puis approve traçables, seuls DEMO explicitement DEMO ou APPROVED éligibles à l’assignation.
4. **Scoring** : scorer configurable, normalisé `[0,100]`, pondérations, reverse explicite, version de méthode, tops/ties, réponses insuffisantes rejetées ; scorer pur et reproductible.
5. **Rotation d’assignation** : pool latest-per-form_key, tenant/family/content status stricts, historique Assessment, non-répétition, réutilisation least-recently-assigned quand épuisé, transaction/locking Client.
6. **Patient UI/pro** : réutiliser formulaire/pagination existants ; afficher forme/version/content status et disclaimer DEMO ; résultat technique au professionnel ; réponses et scores privés demeurent cachés au patient avant publication.
7. **PatientAI** : intents/alias déterministes sur le chemin Feature 020, réutiliser outils statut/liste/questionnaire/resultat publié ; retake/help statiques read-only ; SafetyPolicy avant outils.
8. **Validation/docs** : tests scoring/forms/rotation/soumission/publishing/IDOR/PatientAI ; toutes suites requises, Pint, route/diff/secret checks ; mettre à jour docs sans recette distante imaginée.

## C. Fixtures DEMO locales

Fournir trois formes déterministes A/B/C de neuf items (un par dimension) rédigés spécifiquement comme exemples synthétiques et clairement `DEMO`. Chaque forme a des item keys/version/provenance déclarés et une table de points/poids simple permettant de vérifier calculs, neuf scores, reverse optionnel et égalité. Aucun item ne provient d’un instrument propriétaire ou d’une source web. La démo enseigne le pipeline, pas une théorie clinique.

## D. Validation

- Unit/Feature : tous les calculs et erreurs de configuration ; forms/rotation déterministes ; no repeat, exhaustion/reuse, deux patients/tenants ; snapshots historiques et compatibilité ancien self-report.
- Workflow : consent, autosave/reprise, verrou final, réponses privées, résultat non publié vs publié, review/approve seulement par rôle professionnel.
- PatientAI : formulations requises, tools read-only, refus du questionnaire manipulation et non-publié, isolations tenant/user/client, mémoire v1.1 inchangée ; exécuter toutes les suites existantes 019/020.
- `RefreshDatabase` seulement pour migration locale ; ne pas lancer `migrate` avec un profil distant.

## E. Non-réalisation distante

Aucun accès Aiven/Render, aucune migration distante, aucun push/déploiement. La recette réelle, la disponibilité d’une source psychométrique licenciée et tout statut APPROVED restent des décisions/preuves séparées.

## F. Livraison locale — 4 octobre 2026

Architecture prévue conservée. Trois formes DEMO A/B/C de neuf items, scoring strict, interfaces/catalogue/actions, import/export professionnel des snapshots, reprise/soumission et publication existantes, alias/doc PatientAI et published-only implémentés. Pas de contenu APPROVED opérationnel ; seuls des fixtures SQLite testent les transitions avec références fictives clairement réservées aux tests.

Voir docs/VALIDATION.md pour les commandes/résultats et les limites de Pint global et de concurrence SQLite. Aucun migrate/seeder sur la base applicative ; up/down uniquement dans SQLite :memory: des tests. Aucun appel externe, Aiven, Render ou push.
