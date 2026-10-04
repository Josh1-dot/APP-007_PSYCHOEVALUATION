# Matrice de tests/recette — Feature 021

Provider fake, base de test locale, réseau interdit. Aucun cas ne prouve de validité psychométrique.

| ID | Scénario | Attendu / assertions |
|---|---|---|
| S01 | Forme DEMO 9 items × 9 dimensions | Définitions/questions/règles chargées depuis le backend ; DEMO visible, avertissement non validé. |
| S02 | Définition APPROVED sans provenance/licence/reviewer | Refus du workflow d’approbation, rien n’est assignable comme APPROVED. |
| S03 | Score map incomplète, item manquant/excédentaire, dimension invalide | Validation échoue sans définition/assignation partielle. |
| S04 | IDs/item_key dupliqués ou version/langue/provenance invalides | Rejet server-side. |
| S05 | Une réponse/forme/config identique évaluée deux fois | Résultat strictement identique, même méthode/version. |
| S06 | Calcul pondéré sur une dimension et points multi-dimension | Scores exacts, normalisés 0..100, neuf dimensions. |
| S07 | Réponse échelle reverse | Inversion déterministe exact 100-point après score map. |
| S08 | Score maximal à égalité 2+ types | Tous les types maximaux dans top_dimensions, tie=true, aucun gagnant choisi. |
| S09 | Une dimension sans contribution ou soumission incomplète | Échec; assessment reste en_cours, résultat inchangé. |
| S10 | Trois formes A/B/C, premier passage | Ordre stable, choisit première forme éligible; snapshot et form/version enregistrés. |
| S11 | Même client, deuxième/troisième passation | Formes non utilisées préférées, aucune répétition avant épuisement. |
| S12 | Toutes les formes déjà utilisées | Réutilise la forme à dernière assignation la plus ancienne, tie-break stable. |
| S13 | Un client du même tenant a utilisé des formes | L’historique de l’autre client ne modifie pas la prochaine forme. |
| S14 | Autre tenant/famille/content-status DEMO vs APPROVED | Aucun candidat cross-scope ou état mélangé. |
| S15 | Réassignation pendant un Assessment actif | Questions, scoring rules et version restent inchangés à tous les autosaves/reload. |
| S16 | Réponses tampered avec assessment_id/definition_id/form_key/item IDs | Paramètres ignorés ou rejetés ; contexte d’Assessment route-bound uniquement. |
| S17 | Soumission normale consentie puis répétée/tardive | Une soumission finalise ; mutation après finalisation renvoie 409. |
| S18 | Ancienne passation après création d’une nouvelle forme version | Ancien questionnaire et scoring reproduisent le même résultat sans rebind. |
| S19 | Patient voit une DEMO active | Avertissement DEMO ; pas de scores techniques avant publication. |
| S20 | Patient/entreprise tente GET/POST d’une définition ou d’un workflow approve | 403/404 ; aucun changement de statut ou contenu. |
| S21 | Brouillon/résultat technique non publié | Patient, endpoint result et PatientAI ne révèlent ni score ni texte. |
| S22 | Publication professionnelle effective | Patient peut voir restitution et outil PatientAI publié pour Assessment autorisé. |
| S23 | PatientAI « J’ai un test à faire ? » | Liste seulement des assessments en cours assignés, UUID/tenant issus de Laravel. |
| S24 | PatientAI « Quel questionnaire dois-je faire ? » | Même allowlist, aucun choix/assignation/écriture. |
| S25 | PatientAI « Je veux refaire mon test. » | Réponse de procédure professionnelle ; aucun nouvel Assessment. |
| S26 | PatientAI « Pourquoi les questions sont différentes cette fois ? » | Explication déterministe de rotation, aucune promesse d’équivalence clinique. |
| S27 | PatientAI help questionnaire + manipulation (« meilleure réponse », « Type X ») | Help authorized read-only ou refusal prioritaire ; aucun answer/scoring tool au provider. |
| S28 | PatientAI demande diagnostic/profil certain | Refus; aucun scoring recalculé. |
| S29 | Autre patient/tenant, identité « admin/Joshua », IDOR | Refus non énumérable, aucun contexte issu du texte. |
| S30 | Prompt/env/API secret, injection directe/traduite/documentaire | Refus avant outil/provider. |
| S31 | ClinicalNote/draft/ai_generations | Aucun accès, même via assessment publié. |
| S32 | Memory v0.8 active/inactive | Mémoire ne modifie ni famille/form/answer/scoring/permissions. |
| S33 | PatientAI 019/020, RAG, rétention, export, effacement | Suites existantes vertes ; aucun champ professionnel/secret dans export PatientAI. |
| S34 | Fake provider/network guard | Réponses exactes allowlistées, aucun HTTP sortant/OpenAI. |
| S35 | Migration up/down et données pre-existantes | Schéma additive, tables/rows existantes conservées, rollback retire uniquement colonnes/tables ajoutées (aucune destructive). |
| S36 | Route/forms/pro exports et audit | Rôles corrects ; audit métadonnées seulement, sans réponses/notes/scoring privé. |

## Tests à exécuter

1. Tests unitaires/Feature scorers et configs, notamment S03–S09.
2. Workflow de rotation multi-formes et isolation, S10–S18.
3. UI et visibilité published-only, S19–S22.
4. Tous les cas PatientAI S23–S34 et suites existantes Feature 019/020.
5. Migration locale S35, routes/auth/audit S36, puis suite complète.
