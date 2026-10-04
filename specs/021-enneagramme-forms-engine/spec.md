# Feature 021 — Ennéagramme : formes versionnées et moteur déterministe

**Statut :** implémentation locale livrée et testée ; contenu synthétique DEMO uniquement, source clinique approuvée absente.
**Dépendances :** Features 003/004/010 (définitions, passations, publication), Feature 019/020 (PatientAI et ses frontières).
**Code historique constaté :** `self-report-v1`, neuf échelles 0..100 recopiées en résultats ; une seule forme DEMO dans `DemoSeeder`.

## 1. Objectif et preuve de validité

Construire un moteur technique configurable et versionné pour assigner plusieurs formes, enregistrer leurs snapshots et produire un résultat reproductible à neuf dimensions. Le dépôt ne contient pas de source psychométrique Ennéagramme autorisée ou validée. Aucun contenu nouveau rédigé pour cette feature ne sera `APPROVED` ni présenté comme instrument validé.

Les formes livrées pour recette locale sont explicitement `DEMO`, synthétiques et non diagnostiques. La livraison du moteur ne valide ni le construit ni les items ni le scoring. Le remplacement ultérieur par une source fournie/licenciée doit créer une nouvelle version approuvée et ne doit pas réécrire les passations historiques.

## 2. Architecture retenue

Réutiliser `AssessmentDefinition` comme définition immuable/versionnée et `Assessment` comme passation liée à une définition précise. La liste `questions` existante est le snapshot backend de la forme : aucun second référentiel de questions ni aucune banque parallèle à synchroniser. Étendre la définition par métadonnées de forme, scoring et revue ; les réponses/résultats restent dans les champs chiffrés existants de `Assessment`.

- `family` identifie le groupe de rotation sélectionnable par le professionnel.
- `form_key` identifie la série (A, B, C…). Chaque publication de forme est une nouvelle `version` de `AssessmentDefinition`, unique au sein de la famille.
- Chaque question snapshot porte un `id` de passation et un `item_key` stable de banque, `item_version`, `language`, `provenance` et contenu/type/rèponse.
- Les règles de scoring versionnées sont attachées à la définition/form-version exacte. Le moteur consomme uniquement cette configuration Laravel.
- `Assessment.assessment_definition_id` fixe la forme, version, items et méthode pour toute la durée de la passation ; questions jamais remplacées en cours de route.
- Pas de nouvelle table items/forms : les snapshots JSON existants sont le magasin versionné et sont exportés avec la définition. Une migration additive ajoute seulement les champs de métadonnées réellement absents.

## 3. États, provenance et immutabilité

Statuts de contenu Ennéagramme :

- `DRAFT` : incomplet ou en attente ; non assignable.
- `DEMO` : contenu fictif de démonstration ; assignable uniquement comme DEMO et présenté comme non validé.
- `REVIEWED` : lecture documentée par un professionnel ; pas encore assignable.
- `APPROVED` : source exacte documentée/licenciée, scoring/règles revus et approbation professionnelle explicite ; assignable comme APPROVED.

Tracer auteur, reviewer/date et approver/date si le statut s’applique. Une source sans consentement/licence confirmé ne passe pas `APPROVED`. L’approbation ne constitue pas une validation scientifique. Une définition Ennéagramme persistée ne peut pas changer ses items, règles, `family`, `form_key`, version ou provenance ; créer une nouvelle version. Seule la transition de statut et les reçus de revue/approbation autorisés sont modifiables.

Les anciennes définitions `self-report-v1` sont traitées en compatibilité historique comme non rotatives et ne sont jamais converties silencieusement en scoring configurable. Les données existantes ne sont ni remplacées ni rétro-scored.

## 4. Contrat de forme/item

Pour le moteur `enneagramme-weighted-v1`, chaque forme contient au plus 250 items, un `item_key` stable et `item_version` explicite, langue/provenance, question non ambiguë et type de réponse supporté (`scale`, `choice`, `boolean`). Item keys et IDs sont uniques dans leur portée ; un ID stable identifie le snapshot précis et `item_key` relie les révisions autorisées d’un même item.

Chaque règle référencée par question fournit :

- `score_map` : valeur/option permise → score numérique configuré dans `[0,100]` ; couverture exacte des réponses autorisées ;
- `dimension_weights` : une ou plusieurs dimensions `type1`…`type9` avec poids fini strictement positif ;
- `reverse` facultatif, booléen, permis uniquement pour `scale` et appliqué par `100 - score_configuré` ;
- la version de méthode au niveau de la définition (`method_version`).

La configuration manquante, supplémentaire, mal typée, incohérente avec les items ou réponse, ou ne couvrant pas les neuf dimensions fait échouer la création/assignation, sans fallback implicite.

## 5. Calcul déterministe

Pour chaque réponse validée, lire le score défini par la règle de l’item, appliquer l’éventuel reverse, multiplier par le poids de chaque dimension contributrice. Pour chaque dimension, le résultat est la moyenne pondérée de ses contributions, arrondie de manière fixée à deux décimales, sur un maximum de 100. Le moteur exige les réponses complètes et une contribution de configuration pour chacune des neuf dimensions.

Résultat technique versionné : `kind`, `engine`, `method_version`, `definition_version`, `form_key`, `content_status`, `scores` des neuf dimensions, `maximum=100`, `top_dimensions` triées et `is_tie`. En cas d’égalité, conserver toutes les dimensions maximales ; n’en choisir aucune arbitrairement. Aucun diagnostic, type définitif, trait clinique, interprétation ou recommandation n’est déduit par le moteur.

Même AssessmentDefinition/règles + mêmes réponses normalisées => résultat exactement identique. Le résultat et les réponses restent chiffrés selon le modèle existant. Aucune génération de score n’est faite par un contrôleur, une vue ou PatientAI.

## 6. Formes et rotation

L’assignation professionnelle choisit une famille Ennéagramme ; le patient ne choisit pas la forme ni une combinaison d’items. Laravel :

1. vérifie le tenant, le patient/client et le statut de contenu choisi (`DEMO` ou `APPROVED`) ;
2. prend, pour chaque `form_key`, sa version la plus récente éligible du même `family` et du même état, sans mélanger DEMO et APPROVED ;
3. identifie les form_key déjà utilisées par ce client, dans n’importe quel état de passation et version ; une nouvelle version ne remet pas à zéro cet historique ;
4. choisit parmi les formes non utilisées dans l’ordre déterministe `form_key` ;
5. si toutes sont utilisées, réutilise celle dont la dernière assignation de cette famille est la plus ancienne, tie-break `form_key` puis version.

L’Assessment réellement créé pointe vers la forme/version sélectionnée et sert d’historique de rotation. Un retry concurrent est sérialisé par verrou de Client ; jamais de choix LLM/aléatoire. Si aucune forme éligible n’existe, l’assignation échoue avec validation explicite et ne crée pas d’Assessment.

## 7. Workflow patient/professionnel

Le professionnel garde les écrans de création/export/questionnaires et d’assignation existants. Ils montrent forme, version, statut DEMO/APPROVED, provenance et version méthode ; le workflow propose review puis approve pour une source professionnelle, et ne rend assignable que les états DEMO/APPROVED explicitement choisis. Le code serveur revalide état, même tenant, rôle et règles.

À l’assignation, les formulaires patient existants reprennent le snapshot lié. La soumission reste sous consentement, verrouille l’Assessment, enregistre les réponses, calcule dans `Scoring`, finalise et devient immuable. L’historique garde chaque passation/form/version/résultat. Le patient ne voit aucun score item-par-item, poids, règle interne, réponse passée ni résultat technique non publié. DEMO reste identifié avant, pendant et après passation comme non validé.

L’interprétation, validation professionnelle, publication/dépublication gardent le workflow existant sans auto-publication. PatientAI ne présente jamais un résultat Ennéagramme non publié.

## 8. PatientAI

Réutiliser intents/outils read-only de Feature 020 : disponibilité/statut des évaluations, questionnaire help depuis définition assignée, résultat publié via `PatientPublishedResultTool`. Ajouter les formulations de Feature 021 par alias déterministes, sans autoriser l’écriture depuis le chat.

- « J’ai un test à faire ? » / « Quel questionnaire dois-je faire ? » : lister uniquement les évaluations actuellement assignées/en cours et leur lien patient.
- « Je veux refaire mon test. » : expliquer que seul le professionnel peut assigner une nouvelle passation ; aucun POST métier depuis PatientAI.
- « Pourquoi les questions sont différentes cette fois ? » : explication neutre de la politique de formes/version ; aucune affirmation de validité psychométrique.
- « Explique-moi ce questionnaire » : aide déterministe/provenance approuvée ou mention DEMO, pas de scoring interne.
- « Quel est mon résultat ? » : seulement une interprétation déjà publiée et un Assessment autorisé ; jamais un résultat technique privé.

SafetyPolicy v1.1, PatientContextFactory et les refus Feature 019/020 restent avant les outils. Jamais de choix/réponse, conseil d’optimisation, estimation de type, recalcul, choix de forme, accès aux brouillons/notes/autres patients ou publication par LLM.

## 9. Non-objectifs

- Toute prétention de validation clinique/scientifique de la démo ou du moteur.
- Copier ou générer à partir d’un questionnaire propriétaire non fourni/licencié.
- Ennéagramme définitif, diagnostic, psychologie clinique ou conseil thérapeutique.
- Scoring par IA/LLM, mutation par PatientAI, sélection adaptative en cours de passation, réponse au questionnaire pour le patient.
- Migration destructrice, écriture directe dans Aiven, déploiement Render, provider externe/OpenAI.
- Refonte générale de `Assessment`, workflow Interpretation ou permission PatientAI.

## 10. Statuts de fin

Distinguer obligatoirement : `SPEC-COMPLETE`, `CODE-COMPLETE`, `LOCAL-TEST-COMPLETE`, `AIVEN-PENDING`, `RENDER-PENDING`, `REAL-RECIPE-PENDING`, et `PSYCHOMETRIC-CONTENT-APPROVAL-PENDING` pour tout dataset DEMO sans source officielle.
