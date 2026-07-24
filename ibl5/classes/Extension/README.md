---
description: Player contract extension offer submission, eligibility validation, and processing.
last_verified: 2026-07-24
---

# Extension

Handles the full lifecycle of IBL player contract extensions. `ExtensionService` orchestrates the workflow; `ExtensionOfferEvaluator` determines eligibility and computes offer terms; `ExtensionValidator` enforces IBL CBA constraints; and `ExtensionProcessor` commits accepted extensions to the database. CBA rules (max salary, raise percentages, bird rights) live in the shared `ContractRules` class rather than here.

| Class | Role |
|---|---|
| `ExtensionService` | Workflow orchestrator |
| `ExtensionOfferEvaluator` | Eligibility and offer-terms evaluation |
| `ExtensionValidator` | CBA constraint validation |
| `ExtensionProcessor` | Commits accepted extensions |
| `ExtensionRepository` | Database read/write for extension records |
