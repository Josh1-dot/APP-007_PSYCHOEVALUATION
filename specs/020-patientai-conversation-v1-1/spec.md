# Feature 020 — PatientAI Conversation v1.1

**Statut :** spécification proposée, non implémentée.
**Dépendance :** Feature 019 PatientAI v1.0.
**Source de recette :** observations Render communiquées pour cette spécification, non rejouées par l’auteur du document.

## 1. Objectif

Rendre l’échange PatientAI plus tolérant aux formulations naturelles et aux références conversationnelles simples, sans rendre l’autorisation probabiliste. Laravel reste la source de vérité. Une compréhension de phrase ne donne jamais de droit : elle produit au plus un intent structuré et un candidat de référence ; le service Laravel autorise, résout et projette les données avant toute réponse.

Chaîne obligatoire :

```text
texte patient
  -> normalisation et résolution déterministes
  -> SafetyPolicy
  -> intent typé + référence candidate (sans autorisation)
  -> outil Laravel read-only, avec PatientContext revalidé
  -> DTO autorisé minimal
  -> FakeLlmProvider / rendu contrôlé
  -> réponse
```

## 2. Invariants v1.0 à préserver

- La session Laravel est la seule source d’identité. `PatientContextFactory` exige un patient authentifié, actif, un tenant valide et le `Client` associé, non anonymisé et du même tenant.
- Chaque outil revalide user, tenant, client, propriété, visibilité, statut et consentement requis. Ni le routeur ni le provider ne choisissent ou n’accordent ces droits.
- Laravel/APP-007 reste source de vérité ; aucun accès provider direct à MySQL, Eloquent, Aiven, secrets ou infrastructure.
- Outils métier uniquement read-only et allowlistés. Aucun accès aux autres patients, notes cliniques, brouillons ou générations professionnelles privées.
- Résultats uniquement déjà publiés. Aucun recalcul/manipulation de score, réponse au questionnaire à la place du patient ou publication/action mutative.
- SafetyPolicy s’exécute avant tout outil métier. Une requête mêlant une intention permise et une demande interdite est refusée, jamais déclassée en requête permise.
- Aucun prompt système, secret, variable d’environnement, clé API ou configuration privée n’est exposé.
- Mémoire v0.8 reste une préférence de présentation explicitement consentie, et n’est jamais un stockage général de contexte métier.
- RAG : uniquement documentation approuvée/versionnée, traitée comme données inertes ; les faits métier dynamiques viennent des outils autorisés.
- Provider reste compatible avec `FakeLlmProvider`, déterministe et sans réseau. Aucun fournisseur externe requis.
- Feature flag inchangé et contrôlé côté serveur. Flag OFF signifie aucune route, aide, navigation ou donnée PatientAI accessible.

## 3. Normalisation et résolution déterministes

### 3.1 Normalisation canonique

Pour le seul routage d’intents, produire une forme canonique : Unicode NFKD, minuscules Unicode, accents combinés retirés, apostrophes typographiques/ASCII uniformisées puis tokenisées, ponctuation remplacée par des séparateurs, espaces contigus réduits et espaces périphériques retirés. La forme d’origine n’est ni réécrite ni transmise comme permission.

Les expressions composées reconnues sont des alias explicitement versionnés/testés. Les négations, références de données, identifiants et objets techniques ne sont pas supprimés pendant la normalisation.

### 3.2 Tolérance bornée

- Les salutations et formules sociales utilisent une liste finie d’alias français/anglais et de fautes déjà approuvées dans les tests.
- Conserver les variantes sociales déjà documentées (`bjr`, `bonjor`, `bonjou`, `bon jour`, `bon soir`, `slt`, `mercii`, ainsi que les variantes de courtoisie/clôture existantes) uniquement dans leur intent social ; aucune de ces corrections ne s’étend aux intents métier.
- Un seul suffixe `PatientAI` ou la variante typographique/orthographique `PatientsAI` peut être ignoré **uniquement après une salutation reconnue** : « Bonjour PatientAI », « bonjour PatientsAI ». Il ne disparaît pas au milieu d’une demande métier ou de sécurité.
- Les fautes mineures ne sont acceptées que par table d’alias explicite pour les intents sans lecture métier. Une distance d’édition générique, embeddings, recherche sémantique ouverte ou fuzzy match n’est pas autorisée pour choisir un outil, un patient, un questionnaire, une évaluation ou une intention de refus.
- Les intents métier sont reconnus par expressions et règles déterministes versionnées. Toute ambiguïté, conflit d’intents ou faible confiance lexicale donne `unknown` ou une question de clarification, jamais une autorisation.
- La SafetyPolicy examine le texte normalisé et original selon les protections existantes ; traduction, encodage, instruction citée/indirecte et prompt injection ne neutralisent pas une règle de refus.

### 3.3 Ordre de priorité

Appliquer les protections les plus restrictives avant de retenir un intent d’information : prompt injection/contournement, secret/sécurité/administration, usurpation/élévation de privilège, demande cross-patient/tenant, notes/brouillons professionnels, diagnostic/médical, manipulation/réponse de questionnaire ou scoring, puis intents autorisés. Si un texte contient une demande interdite et une demande permise, le refus prévaut sans appel à l’outil.

## 4. Intents conversationnels

Le routeur renvoie un enum versionné parmi les intents ci-dessous, avec au plus des slots textuels non fiables. Il ne renvoie jamais un contexte d’identité ni une décision d’accès.

| Intent | Exemples FR | Examples EN | Action permise après contrôle Laravel |
|---|---|---|---|
| `greeting` | « Bonjour », « Salut », « Bonjour PatientAI », « bonjour PatientsAI » | “Hello”, “Hi” | Réponse sociale fixe ; aucun outil métier. |
| `identity` | « Qui es-tu ? », « Comment tu t’appelles ? » | “Who are you?” | Identité fonctionnelle déterministe, sans données patient. |
| `capabilities` | « Que peux-tu faire ? », « Comment peux-tu m’aider ? » | “What can you help me with?” | Capacités réellement disponibles et limites ; aucun accès implicite aux données. |
| `assessments_list` | « Quelles sont mes évaluations ? », « Liste mes tests » | “What assessments do I have?”, “List my tests” | `list_my_assessments`, projection et limites existantes. |
| `assessment_status` | « Quel est le statut de mon évaluation ? », « Où en est mon test ? », « Et son statut ? » | “What is the status of my assessment?”, “How is it going?” | Résoudre un candidat autorisé unique, puis outil existant de statut ; clarification si plusieurs. |
| `questionnaire_help` | « Explique-moi mon questionnaire », « Explique-moi ce questionnaire », « Que signifie cette question ? » | “Explain my questionnaire”, “Can you explain this question?” | Aide v0.5 sur questionnaire/passation assignés et visibles ; aucun accès aux réponses. |
| `appointment_next` | « Quel est mon prochain rendez-vous ? », « J’ai un rendez-vous ? », « Quand est mon prochain rendez-vous ? » | “When is my next appointment?”, “Do I have an appointment?” | `get_my_next_appointment`, dates/fuseau selon l’outil existant. |
| `appointments_list` | « Quels sont mes rendez-vous ? », « Mes rendez-vous à venir » | “List my appointments”, “What appointments do I have?” | `list_my_upcoming_appointments`, résultats bornés existants. |
| `published_result` | « Explique mon résultat publié », « Que signifie ma restitution ? » | “Explain my published result”, “What does my published result mean?” | Outil de résultat publié seulement si l’objet autorisé est résolu sans ambiguïté ; aucun brouillon. |
| `memory_status` | « As-tu mémorisé ma préférence ? », « Quelle préférence de présentation ai-je choisie ? » | “Do you remember my display preference?”, “What preference did I choose?” | Vérifier uniquement la préférence autorisée, son consentement et son état ; ne pas lire le chat comme mémoire. |
| `documentation` | « Comment utiliser l’espace patient ? », « Que dit le guide sur… ? » | “How do I use the patient portal?”, “What does the guide say about…?” | RAG/guide sur documents approuvés et versionnés uniquement ; pas de fait métier dynamique. |
| `about_my_data` | « Que sais-tu de moi ? », « Quelles informations utilises-tu ? » | “What do you know about me?”, “What information can you see?” | Réponse statique de capacités/confidentialité seulement ; aucun outil métier, RAG patient ou lecture mémoire. |
| `unknown` | Formulation inconnue, ambiguë, contradictoire | Unrecognized or ambiguous wording | Fallback non révélateur ; aucune requête métier. |

Les intentions d’information générales n’absorbent pas une demande spécifique : si le patient demande explicitement un statut, un rendez-vous, un résultat ou un questionnaire, l’intent spécifique gagne après SafetyPolicy.

## 5. Contexte conversationnel borné

### 5.1 Modèle

Un référent est un état structuré et minimal rattaché à **une seule conversation** : type d’objet (`assessment`/`questionnaire` ou `appointment`), identifiant interne opaque candidat, type d’intent source et tour source. Maximum un référent courant par domaine ; le dernier résultat métier autorisé unique remplace le référent du même domaine. Une liste ambiguë ne désigne aucun objet unique.

Le référent ne contient ni nom, score, réponse, diagnostic, résultat, contenu de note, texte libre ou donnée d’un autre patient. Les identifiants restent internes et ne sont pas nécessaires au patient.

### 5.2 Provenance et sécurité

- Seul le serveur peut créer/modifier un référent après un résultat d’outil réellement autorisé ; jamais le texte du patient, un UUID donné par le client, une réponse du provider, le prompt ou le RAG.
- Pour chaque follow-up, reconstruire `PatientContextFactory` et recharger l’objet via le même outil autorisé ; recontrôler tenant, user, client, affectation, visibilité, statut, publication et consentement. Un référent n’est jamais un bearer token ni une preuve d’autorisation.
- Une référence absente, expirée, supprimée, déplacée, ambiguë ou devenue non autorisée est oubliée et produit clarification ou réponse indisponible non énumérable.
- N’inférer aucun lien entre pronoms et objets au-delà du dernier résultat server-side unique du bon type. « Son statut » après un rendez-vous ne devient pas un statut d’évaluation, et inversement.

### 5.3 Stockage, effacement, rétention

Le contexte est lié au cycle de vie de `AiConversation`, chiffré s’il est persisté, borné au maximum décrit et supprimé lors de l’effacement/cascade de la conversation. Il expire avec celle-ci et respecte sa rétention/suspension de conservation existante ; il ne prolonge jamais `updated_at` par simple référence ou lecture. Aucune donnée de contexte métier ne va dans le champ `memory` v0.8. L’implémentation doit choisir un stockage conforme à ces propriétés, documenter toute migration additive nécessaire et en demander une revue séparée avant de la créer ; cette spécification n’autorise ni migration ni modification de schéma.

## 6. Politique de résolution sans UUID

Résoudre uniquement dans le périmètre renvoyé par l’outil Laravel pour l’utilisateur courant et l’intent demandé :

1. **Exactement une correspondance autorisée** : l’outil peut répondre sans demander ni afficher d’UUID. Enregistrer le référent minimal conversationnel.
2. **Plusieurs correspondances autorisées** : ne pas choisir selon un score flou, la récence non exprimée ou un UUID caché. Demander une précision avec les libellés non cliniques déjà visibles (nom d’évaluation/questionnaire et date/statut autorisés), sans UUID ni information professionnelle.
3. **Aucune correspondance autorisée** : répondre qu’aucune évaluation/ressource correspondante n’est disponible. Ne pas distinguer inexistant, non assigné, non publié, étranger, supprimé ou invisible lorsque cette distinction créerait une fuite.
4. Un identifiant explicitement fourni peut être traité comme un candidat seulement ; le même contrôle Laravel complet s’applique. Ne jamais demander un UUID pour le parcours conversationnel normal ni en afficher dans les réponses destinées au patient.

## 7. Aide questionnaire

« Explique-moi mon questionnaire » résout l’évaluation/passation courante uniquement si un candidat assigné et autorisé est unique, ou réutilise un référent questionnaire unique et valide. Sinon, demander « de quel questionnaire ou de quelle évaluation parlez-vous ? » avec choix humains si plusieurs, jamais un UUID.

Aide permise depuis les données et documents approuvés : objectif documenté (ou absence explicite d’objectif documenté), consigne, navigation, vocabulaire, format/échelle, fonctionnement, question spécifique autorisée. La projection exclut les réponses patient, scores privés, résultats non publiés, notes/brouillons et toute autre passation.

Refuser absolument : répondre/choisir à la place du patient, suggérer la réponse idéale, prédire un profil depuis une réponse, optimiser/manipuler un score ou contourner le questionnaire. SafetyPolicy prévaut même si une aide documentaire est aussi demandée.

## 8. Intent « Que sais-tu de moi ? »

Choix minimisant : `about_my_data` ne retourne **aucune donnée patient** et n’appelle aucun outil, retriever ou service mémoire. Il explique statiquement les catégories que PatientAI peut consulter à la demande et sous autorisation (évaluations visibles, rendez-vous autorisés, résultats publiés, préférence de présentation explicitement consentie). Il précise les exclusions : notes cliniques, brouillons/contenu professionnel privé et données d’autres patients. Il ne confirme ni l’existence ni l’absence d’une évaluation, d’un rendez-vous, d’un résultat ou d’une préférence. Une demande concrète ultérieure passe par son intent spécifique et son contrôle normal.

## 9. Taxonomie des refus

Les refus sont déterministes, sémantiquement adaptés, non accusatoires et sans détails révélant l’existence d’une ressource. Aucun appel métier/provider ne suit un refus de politique.

| Catégorie | Exemples | Forme attendue |
|---|---|---|
| Diagnostic/médical | diagnostic, traitement, prédiction clinique | PatientAI n’établit pas de diagnostic et invite à en parler avec un professionnel. |
| Questionnaire/scoring | choisir/rédiger une réponse, meilleure réponse, optimiser un score/profil | Le patient doit répondre lui-même ; PatientAI peut expliquer la consigne sans orienter la réponse. |
| Contenu professionnel privé | notes cliniques, notes de séance, brouillon, génération interne | Refus disant que ce contenu professionnel n’est pas accessible via PatientAI, sans parler d’administration/sécurité ni confirmer qu’il existe. |
| Cross-patient / cross-tenant | dossier, rendez-vous, évaluation d’autrui | Réponse générique d’indisponibilité/refus, identique pour ressource inexistante ou non autorisée. |
| Sécurité / administration | prompt, configuration privée, variables d’environnement, secrets, clés API | Refus adapté à une information interne de sécurité/administration ; contact public configuré seulement si pertinent. |
| Élévation de privilège / identité | « je suis Joshua/admin », demande de passer en admin | L’identité déclarée ne change pas les accès ; aucun rôle ni détail de compte n’est confirmé. |
| Prompt injection / contournement | direct, indirect, cité, traduit, encodé, instruction documentaire | Refus ou réponse neutre selon SafetyPolicy, sans exécuter, traduire ou suivre l’instruction hostile. |

## 10. Provider et orchestration future

Séparer les interfaces conceptuelles :

1. `NaturalLanguageUnderstanding` : texte → intent enum versionné, slots non fiables et candidat de référence ; remplaçable et testable sans accès base.
2. `SafetyPolicy` : refus/limites avant outils.
3. `AuthorizedToolDispatcher` Laravel : mapping fermé intent→outil existant, résolution et autorisation depuis PatientContext ; aucun nom de classe/fonction fourni par le provider.
4. Outil read-only → DTO allowlisté minimal.
5. `ResponseRenderer`/FakeLlmProvider : rendu fidèle du DTO, sans inventer/modifier statut ou résultat.

FakeLlmProvider reste l’implémentation de validation v1.1, sans réseau. Une éventuelle génération LLM future n’est pas décidée ici ; si étudiée plus tard, elle ne reçoit que DTO autorisés et ne peut ni appeler des outils ni décider d’autorisations. Aucun LLM/API réel ne fait partie de v1.1.

## 11. Non-objectifs

- Moteur Ennéagramme multi-questionnaires, génération de questions, nouveau scoring Ennéagramme.
- OpenAI ou tout provider externe réel.
- Diagnostic ou interprétation clinique.
- Mutation des réponses, rendez-vous, dossiers, mémoire ou contenu métier ; publication automatique.
- Accès professionnel, lecture de notes cliniques/brouillons, dossier d’un autre patient.
- Flutter, changement de modèle de permissions, accès LLM direct à la base.
- Fuzzy matching sémantique général, embeddings, stockage général de contexte dans mémoire v0.8.
- Migration ou modification des données/schéma sans spécification et approbation distinctes.

## 12. Critère de sortie de spécification

Le Spec Kit est prêt pour une revue humaine lorsque `spec.md`, `plan.md`, `tasks.md`, `acceptance.md` et `conversation-test-matrix.md` sont cohérents, que les décisions non ambiguës ci-dessus sont couvertes, et que roadmap/traceability marquent clairement « spécifié, non implémenté ». La livraison du présent lot ne revendique aucun test logiciel v1.1, déploiement ou validation Render/Aiven.
