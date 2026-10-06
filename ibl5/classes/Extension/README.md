---
description: Player contract extension offer submission, eligibility validation, and processing.
last_verified: 2026-10-03
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
| `ExtensionView` | Renders the post-redirect result banner (`renderResultBanner`) shown on the Team page |

## Placement

Extension stays a Player sub-action. There is no `ibl5/modules/Extension/` (example) directory and no GET-navigable extension page. The three user-facing pieces live where their flows already run:

- Offer form: `Negotiation\NegotiationOfferView::renderNegotiationForm()`, reached from `modules.php?name=Player&pa=negotiate`. The form posts to `ibl5/modules/Player/extension.php` with a CSRF token from `generateToken('extension')`.
- Submission: `ibl5/modules/Player/extension.php`, a POST-only handler (CSRF check, login check, team-ownership gate, then `ExtensionProcessor`). A GET redirects away. Its URL is pinned by three E2E specs and `ibl5/tests/Extension/ExtensionControllerTest.php`.
- Result banner: `ExtensionView::renderResultBanner()`, called by `Team\TeamView::render()` after the PRG redirect to the Team page with `result=` and `msg=` query parameters.

An own module would add a new route and UI surface with no behavior to put behind it, so the View lands here and the form and handler stay with Player and Negotiation.
