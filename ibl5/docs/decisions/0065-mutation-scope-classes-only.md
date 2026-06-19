---
description: Why mutation testing (Infection) is scoped to classes/ only — scripts/, modules/, and root .php are intentionally out of scope.
last_verified: 2026-06-18
---

# ADR-0065: Mutation testing scoped to classes/ only

**Status:** Accepted
**Date:** 2026-06-18

## Context

Infection's `infection.json5` sets `source.directories: ["classes"]`. Everything outside `classes/` — `scripts/`, `modules/`, and root-level `.php` entry points — is therefore never mutated. This was never written down, so a reader auditing mutation scope cannot tell whether the omission is deliberate or an oversight. A fourth PR in the mutation-hardening effort was originally scoped to extend mutation into `scripts/`; investigation showed that work is not worthwhile, and the effort dropped it. This ADR records *why* the boundary sits at `classes/` so the decision is discoverable next to its lineage (ADR-0019, ADR-0020), rather than buried in a JSON5 comment.

## Decision

Keep Infection's mutate-scope at `source.directories: ["classes"]`. Do not extend it to `scripts/`, `modules/`, or root `.php`. The rationale, from the dropped-PR-4 investigation:

- `scripts/updateAllTheThings.php` — its logic was already extracted into the tested `classes/Updater/` namespace (UpdaterService + the per-step classes, covered by `tests/UpdateAllTheThings`). The script is a thin driver around already-mutated code.
- `scripts/reconstructPlr.php` — delegates to the tested `classes/PlrParser/`. Mutating the script would re-test parsing logic that already lives under `classes/`.
- The remaining scripts (e.g. `scripts/patch_2007_asg_sco.php` and the archival/import one-offs) are single-use patch and migration tools with no ongoing behavior worth a mutation budget.
- `modules/` and root `.php` are thin entry/glue layers that delegate into `classes/`; their logic is exercised through the `classes/` code Infection already mutates and through E2E.

Therefore `classes/` is the correct, intentional mutate-scope. Within `classes/`, a small set of paths is further excluded (see `infection.json5` and `phpunit-mutation.xml`): low-level infra (`classes/Database`, `classes/JSB.php`), interface-only `Contracts`, the `OneOnOneGame` engine, and the Group-B View files that lack output-assertion tests. Those per-file exclusions are documented inline and enforced by `ibl5/bin/check-infection-excludes`.

## Consequences

- Positive: the `classes/`-only boundary is documented where future mutation-scope audits look (`ibl5/docs/decisions/`), not in a config comment.
- Positive: no mutation budget is spent on thin script/glue layers whose real logic already lives under `classes/` and is mutated there.
- Negative: genuinely script-only logic (if any is ever added directly to `scripts/` rather than extracted into `classes/`) would escape mutation testing. Mitigated by the existing convention of extracting logic into `classes/` (the `updateAllTheThings`/`reconstructPlr` precedent).

## Lineage

- ADR-0019 (mutation-unlock-repositories) and ADR-0020 (mutation-unlock-controllers-handlers) progressively widened the mutate-scope *within* `classes/`. This ADR records the outer boundary of that effort: `classes/` is where mutation stops. It does not supersede either; it complements them by documenting why the scope is not widened beyond `classes/`.
