# Validation effectuée

Mise à jour : 28 septembre 2026.

## Exécution

- Laravel 13.33.0 et PHP 8.3.6, dépendances installées et verrouillées.
- MySQL 8.0.46 permanent via `/run/mysqld/mysqld.sock` ; base applicative et base de tests distinctes.
- Migrations exécutées sur MySQL et SQLite.
- **28 tests, 227 assertions réussies sur SQLite le 28 septembre.**
- La suite MySQL a réussi le 25 septembre : **28 tests, 226 assertions**. Le dernier contrôle ajouté (restauration interdite après anonymisation) est validé sur SQLite. La base MySQL temporaire de tests n’est plus disponible le 28 septembre ; aucun test destructif n’a été lancé sur la base applicative.
- Formatage PHP : Laravel Pint, vérification réussie.
- Syntaxe PHP : application, migrations, routes et vues compilées vérifiées sans erreur.
- Vues Blade : compilation réussie.
- JavaScript : `node --check public/assets/app.js` réussi.

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

Plusieurs scénarios sont réunis dans un même test fonctionnel.

## Sauvegardes et exploitation locale

- Services MySQL, psychoevaluation et cron actifs le 28 septembre ; page de connexion HTTP 200.
- Planificateur Laravel présent dans la crontab utilisateur ; sauvegarde quotidienne à 2 h, heure de Kampala, lorsque la machine est allumée.
- Sauvegarde chiffrée créée et vérifiée le 28 septembre : `psycho-20260928-094447-i5lfxo.psyenc`.
- Restauration réelle testée le 25 septembre dans une nouvelle base isolée : 28 tables importées, déchiffrement des contenus applicatifs réussi, copie de contrôle supprimée ensuite.
- Conservation des archives locales trente jours. Aucune copie hors machine configurée.

## Vérification HTTP réelle

Connexion au serveur PHP avec le compte fictif administrateur, redirection vers le tableau de bord, pages patients/évaluations/questionnaires/agenda/messagerie/documents/administration, consultation de passations et téléchargement d’un fichier PDF réel : réponses HTTP 200. Une mutation sans jeton CSRF reçoit HTTP 419.

## Limites de validation

- Aucun navigateur n’est connecté à l’outil de contrôle visuel de cette session : pas de capture d’écran ni de vérification graphique sur téléphone. Les media queries et le HTML sont présents, mais le rendu visuel responsive reste à contrôler dans un navigateur.
- Le comportement JavaScript d’auto-sauvegarde est vérifié syntaxiquement ; les routes et verrouillages sont testés côté serveur. Pas de test E2E navigateur ni de test de charge/concurrence multi-utilisateur.
- La configuration Docker / MySQL 8.4 n’a pas été exécutée, Docker étant absent. MySQL 8.0 a été utilisé directement pour les tests.
- Aucun appel vers un fournisseur IA réel, aucun e-mail externe ni aucune synchronisation calendrier.
- Tests techniques uniquement : pas de validation des instruments psychométriques, ni audit de conformité ou de sécurité externe.
