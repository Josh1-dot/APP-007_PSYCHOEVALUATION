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


## Feature 021 — correction du workflow multi-formes (4 octobre 2026)

Le POST professionnel `/questionnaires` distingue `creation_mode=questionnaire` (nouvelle famille), `form` (nouvelle clé dans la famille de `previous_id`, version 1), et `version` (même clé, maximum des versions de cette forme + 1). Les anciennes requêtes sans mode gardent leur comportement nouveau questionnaire/nouvelle version selon la présence de `previous_id`.

```text
FAMILY
  ├── A v1 → A v2 …
  ├── B v1 → B v2 …
  └── C v1 → C v2 …
```

La référence est résolue sous scope tenant. Une nouvelle forme exige une référence Ennéagramme `enneagramme-weighted-v1`, une clé encore absente et des questions/règles valides. Les créations sont sérialisées par verrou du Tenant dans la transaction ; aucune définition existante n'est modifiée. Le droit publisher existant est conservé, avec audit `enneagramme.forme_creee`. Les imports DEMO et familles DEMO restent DEMO ; aucun reçu de revue/approbation n'est repris. La provenance du snapshot est utilisée si la source du formulaire est vide.

La migration `2026_10_04_165857_scope_definition_versions_to_enneagram_forms` est nécessaire : l'ancien index unique tenant/family/version interdit A v1 et B v1. Elle ajoute `version_scope` (form_key pour Ennéagramme pondéré, chaîne vide pour les autres), remplace l'index par tenant/family/version_scope/version et conserve tous les champs et versions historiques. Les autres familles gardent leur unicité familiale. Le modèle calcule ce scope à la création et protège son immutabilité Ennéagramme. Le rollback refuse les collisions de l'ancien index sans supprimer de données.

Rotation, scoring et permissions PatientAI 019/020 inchangés : sélection des dernières versions éligibles par forme, unused-first puis LRU ; résultats exclusivement publiés. Le générateur canonique local/testing conserve ses versions historiques A1/B2/C3, sans renumérotation ni contournement de sa garde. L'import professionnel crée chaque nouvelle forme à v1, indépendamment de la version du fichier exporté.

Déploiement de cette correction NON réalisé : appliquer la nouvelle migration lors d'une mission dédiée avant de mettre en service le nouveau code. Aucun accès Aiven ni déploiement Render pendant cette correction. Validation MySQL/production de ce nouvel index et contention réelle restent à faire ; tests locaux SQLite uniquement.
