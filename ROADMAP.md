# Roadmap — convergence Spec Kit 001–018

Mise à jour : 1 octobre 2026. Code de référence : `990f470`.

Cette roadmap est créée dans le dépôt Laravel : la roadmap initiale était dans la copie externe du Spec Kit. Provenance, critères et preuves dans [SPEC-CONVERGENCE.md](docs/SPEC-CONVERGENCE.md). Les anciens `planned` sont remplacés par les constats réels ; un parcours utilisable ne suffit pas à clôturer une feature dont les critères importants restent incomplets.

Statuts : `DONE`, `PARTIAL`, `MISSING`, `BLOCKED`, `CONTRADICTS`. Les statuts des sous-tâches peuvent différer de celui de leur feature. Aucune nouvelle fonctionnalité développée par cette mise à jour.

| ID | Feature | Dépendances source | Statut | Travail restant prioritaire |
|---|---|---|---|---|
| 001 / R01 | Identité, rôles et isolation | — | PARTIAL | Compléter matrice négative et inventaire des actions auditées. |
| 002 / R02 | CRM Client / Organization | 001 | PARTIAL | Compléter le périmètre CRUD et sa recette. |
| 003 / R03 | Définitions versionnées | 001 | PARTIAL | Décider et tester une garantie d’immutabilité couvrant aussi les écritures internes. |
| 004 / R04 | Passation et verrouillage | 002, 003 | PARTIAL | Tester reprise navigateur et soumissions concurrentes sur MySQL isolé. |
| 005 / R05 | Consentement et privacy | 001, 002 | PARTIAL | Valider les effets métier puis tester changement de version. |
| 006 / R06 | Scoring Gordon | 003, 004 | PARTIAL | Ajouter les tests de reproductibilité/bornes puis importer un référentiel autorisé. |
| 007 / R07 | Ennéagramme | 003, 004 | PARTIAL | Décider format canonique, fournir exemples historiques autorisés et décider le traitement des égalités. |
| 008 / R08 | Besoins et tests personnalisés | 003, 004 | PARTIAL | Compléter le format besoins sur une source autorisée, puis les tests par type. |
| 009 / R09 | Interprétation IA | 004, 006/007/008 | CONTRADICTS | Conserver séparément sortie IA originale, révisions humaines et métadonnées, sans activer de fournisseur. |
| 010 / R10 | Révision humaine et publication | 009 (brouillon manuel aussi disponible) | DONE | Conserver ce comportement et étendre ultérieurement les tests de traçabilité. |
| 011 / R11 | Portail patient | 001, 004, 010 | PARTIAL | Rendre toutes les passations accessibles au patient et couvrir les parcours croisés. |
| 012 / R12 | Portail entreprise | 001, 002 | PARTIAL | Tester deux entreprises du même cabinet et un autre tenant. |
| 013 / R13 | Notes cliniques | 001, 002 | PARTIAL | Définir le cycle de vie des notes avant d’ajouter édition/suppression et tests. |
| 014 / R14 | Documents privés | 001, 002 | PARTIAL | Décider le stockage durable et les règles de conservation par périmètre. |
| 015 / R15 | Messagerie et agenda | 001, 002 | PARTIAL | Tester isolation des lectures et envois avant toute intégration externe. |
| 016 / R16 | Comparateur | 006/007, 010 | PARTIAL | Tester snapshot après évolution des données sources et refus versions incompatibles. |
| 017 / R17 | Courriers et PDF | 010, 016 | PARTIAL | Tester le contenu PDF patient après révision privée et les données autorisées. |
| 018 / R18 | Audit, rétention et sécurité | 001–017 | PARTIAL | Traiter en priorité 009 conservation IA, puis audit/rétention et recette transverse. |

## Prochain travail technique

009 — Conservation indépendante de la sortie IA originale et des générations/révisions, avec tests. **Recommandation seulement : aucun développement réalisé dans cet audit.** Puis combler les tests de sécurité/historique manquants et décider les périmètres CRUD incomplets.

## Dépendances et reports

- 005/014/018 : validation métier du retrait et des durées/règles de rétention non fournie ; mécanismes techniques existants.
- 006/007/008 : textes/grilles autorisés manquants ; 007 requiert aussi fixtures anciennes et choix canonique. Pas d’invention psychométrique.
- IA réelle et assistants spécialisés : non activés/non validés ; comparaison IA conditionnelle absente. Une correction de conservation peut être testée sans fournisseur.
- Google/Outlook : décision séparée, intégration non implémentée ; non bloquante pour le domaine interne 015.
- E-mail externe, stockage durable Render et sauvegarde/restauration distante : exploitation encore incomplète.
- Production déjà déployée ; incident Aiven Powered off signalé puis endpoints publics à nouveau 200 pendant l’audit. Le déploiement ne vaut pas clôture de 001–018.
- Les sources Spec Kit sont externes au dépôt ; organiser leur versionnement avant de prétendre à une traçabilité intégralement autonome sur GitHub.

## Principes conservés

Réponse, scoring, interprétation IA, validation humaine et publication distincts ; IA jamais auto-publiée ; Gordon déterministe ; Ennéagramme sans scoring clinique inventé ; versions et snapshots ; isolation, consentement et audit. Les écarts à ces principes sont consignés, pas masqués par un statut `DONE`.
