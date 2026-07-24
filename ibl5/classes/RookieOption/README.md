---
description: Handles GM decisions to pick up or decline rookie contract options.
last_verified: 2026-07-24
---

# RookieOption

Provides the workflow for GMs to exercise or decline options on rookie contracts. `RookieOptionController` routes GET/POST requests, `RookieOptionValidator` enforces eligibility rules before any change is persisted, and `RookieOptionRepository` handles the database update. `RookieOptionView` renders the decision form.

| Class | Role |
|---|---|
| `RookieOptionController` | Routes GET/POST for the rookie option workflow |
| `RookieOptionRepository` | Persists option pick-up/decline decisions |
| `RookieOptionValidator` | Validates eligibility before allowing a decision |
| `RookieOptionView` | Renders the option decision form |
