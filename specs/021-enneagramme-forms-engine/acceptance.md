# Critères d’acceptation — Feature 021 Ennéagramme Forms Engine

**Règle d’interprétation :** aucune réussite technique ne constitue une validation psychométrique. Les formes synthétiques sont affichées DEMO/non validées.

## AC-01 — Versionnement, banque, provenance

- Un form est rattaché à un tenant/family/form_key/version, content status, méthode/version de scoring et reçus d’auteur/revue/approbation.
- Items ont un item_key stable, item ID de snapshot unique, item version, langue, texte/type/options et provenance.
- Une nouvelle version ne modifie jamais `questions`, scoring rules, form key ou source d’une définition utilisée ; la passation historique reste liée à son AssessmentDefinition d’origine.
- APPROVED exige source_reference et licence attestée, review puis approbation explicites ; pas d’approbation automatique de DemoSeeder, présence ou ancien contenu.

## AC-02 — Cohérence des règles

- Les dimensions autorisées correspondent aux neuf identifiants canoniques `type1`…`type9`.
- La configuration couvre exactement les items définis et toutes leurs réponses possibles ; weights finis > 0, points configurés bornés, reverse booléen supporté uniquement pour l’échelle.
- Configuration manquante, invalide, dimension orpheline, poids zéro/négatif/non numérique ou item non scoré fait échouer validation avant sauvegarde/assignation.
- Tout form assignable contient des contributions à chacune des neuf dimensions.

## AC-03 — Scoring

- `enneagramme-weighted-v1` utilise la règle versionnée Laravel seule, accepte réponses normalisées validées et produit les neuf scores normalisés 0..100, max, moteur/méthode/form/version.
- Mêmes definition/rules/answers => résultat strictement identique.
- Les réponses/config ne sont pas recalculées dans Blade ou PatientAI.
- Reverse, pondérations et points multi-dimension sont testés avec calculs attendus exacts.
- Une égalité est représentée explicitement par toutes les dimensions maximales, ordre stable et indicateur tie ; aucun type n’est arbitrairement choisi.
- Une réponse manquante à soumission, extra key, mauvais type ou échelle hors bornes est rejetée sans finaliser l’Assessment.
- L’ancien `self-report-v1` reste compatible et n’est jamais interprété rétroactivement par le nouveau moteur.

## AC-04 — Formes et rotation

- Trois formes DEMO ou plus par famille peuvent coexister, chaque forme ayant items/règles distincts versionnés.
- L’assignation sélectionne le pool tenant/family/status actif uniquement ; pas de mélange DEMO/APPROVED.
- Une forme non encore utilisée par ce client est choisie avant répétition, ordre stable; aucun aléatoire ou LLM.
- Après épuisement, la plus ancienne dernière utilisation est réemployée avec tie-break déterministe form_key/version.
- Deux clients peuvent avoir des formes prochaines différentes selon leur historique, sans lecture cross-client.
- Double assignation/concurrence locale ne crée pas un doublon incorrect et le verrou client sérialise la décision.
- Une passation active conserve les mêmes questions/version/règles du début à la fin.

## AC-05 — Soumission/historique

- Professional assign crée un Assessment qui pointe vers la forme/version réellement choisie ; aucune ID de forme soumise par le patient n’est acceptée.
- Formulaire patient autorisé re-prend la même version et garde autosave/reprise existants.
- Consentement requis, tenant/user/client vérifiés, soumission transactionnelle et verrou final (409) restent actifs.
- La réponse enregistrée et le résultat sont chiffrés ; historique Assessment/form/result reste reproductible après publication d’une nouvelle version.

## AC-06 — Résultat/visibilité

- Résultat technique affiche scores/dimensions et tie au professionnel habilité, avec forme/version/content-status/scoring-version.
- DEMO porte un avertissement visible dans interfaces pro/patient et dans la restitution ; aucun texte ne prétend à un test validé.
- Patient n’obtient ni résultats techniques ni brouillon tant que la publication existante n’est pas effectivement présente.
- Résultat publié reste soumis aux mêmes protections Assessment/Interpretation v1.0 et à l’allowlist `PatientPublishedResultTool`.

## AC-07 — PatientAI

- Les formulations disponibles/quel questionnaire, statut et help routent vers les outils Laravel autorisés existants.
- « Je veux refaire mon test » ne crée ni assignation ni Assessment ; indique le recours professionnel.
- Une explication de différences de formes décrit la rotation déterministe sans prétendre à l’équivalence psychométrique.
- PatientAI ne révèle jamais scoring par item/weights/réponse brute, ne propose aucune option ni forme stratégique et ne calcule ni modifie ni publie de résultat.
- Les résultats non publiés, notes, drafts, réponses d’un autre patient et tenants restent inaccessibles. SafetyPolicy avant outils.

## AC-08 — Sécurité/régression

- Cross-tenant/client, IDOR, user/role inactif, paramètres falsifiés, forme d’une autre famille, conversation étrangère échouent non-enumerably.
- PatientContextFactory, permissions 019/020, RAG/mémoire, consentement, rétention/effacement et checks published-only restent inchangés en comportement.
- Aucun secret, clé, prompt, réponse privée ou donnée d’autre patient dans provider/audit/log/session.
- Fake provider sans réseau ; aucun provider externe/API nécessaire.

## AC-09 — Migration/release

- Migration uniquement additive, testée up/down sur base de test ; aucun migrate Aiven, pas de donnée distante modifiée.
- Tests Feature/scoring/PatientAI, suite complète, Pint, routes, `git diff --check` et scan ciblé secrets passent.
- Code/local tests documentés séparément d’Aiven, Render, recette réelle et approbation psychométrique.
