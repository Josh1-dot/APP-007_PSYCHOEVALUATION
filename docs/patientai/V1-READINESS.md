# PatientAI v1.0 — checklist de readiness

| Contrôle | Statut | Preuve / limite |
|---|---|---|
| SafetyPolicy | PASS | Refus serveur avant provider validé par les tests locales PatientAI et d’anti-usurpation. |
| rate limiting | PASS | Rate limiting serveur configuré et validé dans la suite PatientAI. |
| audit | PASS | Audit local sans contenu sensible vérifié dans les parcours PatientAI existants. |
| observabilité | PASS | Observabilité locale existante sans payload sensible, conformes aux règles de confidentialité. |
| rétention | PASS | Cycle de vie rétention / purge / export / effacement validé localement. |
| export/effacement | PASS | Contrôles de confidentialité et suppression validés par les tests locaux. |
| kill switch | PASS | `PATIENT_AI_ENABLED` est vérifié côté serveur et reste OFF. |
| provider policy | PASS | Provider forced `fake` / fail-closed, sans réseau ni fournisseur externe. |
| provider privacy | PASS | Seuls DTO minimaux sont envoyés au provider ; aucun modèle Eloquent ou données privées ne quitte Laravel. |
| adversarial tests | PASS | Suite locale de prompt injection, extraction, usurpation, IDOR, provider tampering et RAG attaques documentaires. |
| PatientAI regression | PASS | Non-régression v0.1 → v0.9 validée par la suite PatientAI et la suite complète. |
| full regression | PASS | 327 tests passés / 3 437 assertions. |
| MySQL | PARTIAL | Validation SQLite uniquement ; MySQL/Aiven réel non exécuté. |
| concurrence réelle | PARTIAL | Protections applicatives présentes, mais pas de validation MySQL réelle. |
| browser E2E | NOT EXECUTED | Aucun navigateur réel exécuté dans cet environnement. |
| Aiven/Render | NOT EXECUTED | Aucun déploiement ni migration Aiven/Render effectués. |
| real provider | NOT EXECUTED | Aucun fournisseur LLM réel activé ni validé. |
| production scheduler | NOT EXECUTED | Aucun scheduler de production vérifié. |
| feature flag | PASS | Flag PatientAI conservé OFF par défaut. |

## Verdict documenté

- PatientAI v1.0 code-complete : PASS
- PatientAI v1.0 local-test-complete : PARTIAL
- PatientAI v1.0 production-ready : NON

> Aucune validation non exécutée n’a été transformée en PASS. Les validations concrètes restantes avant production sont documentées comme nécessaires, sans prétendre qu’elles ont été faites.
