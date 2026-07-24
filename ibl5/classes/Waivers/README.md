---
description: Waiver claim submission and processing with eligibility validation.
last_verified: 2026-07-24
---

# Waivers

Handles the full waiver claim workflow: GMs browse available players, submit claims, and the system validates eligibility before processing. `WaiversController` routes GET/POST for the module. `WaiversValidator` enforces claim eligibility rules and `WaiversProcessor` executes approved claims. `WaiversService` and `WaiversRepository` handle data assembly and persistence. Entry point: `ibl5/modules/Waivers/index.php`.

| Class | Role |
|---|---|
| `WaiversController` | Routes GET/POST for the waivers module |
| `WaiversService` | Assembles waiver page data |
| `WaiversRepository` | Database access for waiver data |
| `WaiversProcessor` | Executes approved waiver claims |
| `WaiversValidator` | Validates claim eligibility |
| `WaiversView` | Renders the waivers page |
