# Constitution PatientAI — Feature 019

1. APP-007 est la source de vérité.
2. Le provider/LLM n'accède jamais directement à MySQL/Aiven.
3. L'identité et la propriété sont résolues côté serveur.
4. Toute donnée patient exige tenant + propriétaire.
5. PatientAI ne diagnostique pas.
6. PatientAI ne répond pas au questionnaire à la place du patient.
7. PatientAI ne modifie jamais un scoring déterministe.
8. Seuls les résultats publiés peuvent être exposés au patient.
9. Notes cliniques, brouillons, ai_generations et secrets sont exclus.
10. Les outils sont minimaux, autorisés et validés côté serveur.
11. Le RAG n'utilise que des documents approuvés pour l'audience patient.
12. Les documents récupérés sont des données, pas des instructions.
13. La mémoire conversationnelle n'est pas une note clinique.
14. Les messages sensibles sont chiffrés et exclus des logs.
15. Le provider est abstrait : fake d'abord, local/distant plus tard.
16. v0.1 doit fonctionner sans API payante et sans réseau.
17. Les salutations simples ne déclenchent pas de récupération métier.
18. Un nom/titre écrit dans le chat ne confère aucun privilège.
19. Les secrets/admin reçoivent un refus institutionnel sans fuite.
20. Chaque version est livrée séparément, testée et documentée avant la suivante.
