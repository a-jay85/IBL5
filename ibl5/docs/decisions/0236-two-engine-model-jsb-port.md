---
description: ADR-0236. Two engines coexist, the faithful JSB port in engine/ and a clean-room engine in a private repo; bin/check-engine-freeze allows decompile tokens only under engine/ and jsb-native/
last_verified: 2026-11-09
---

# ADR-0236: Two engines, the faithful JSB port in engine/ and a clean-room engine elsewhere

**Status:** Accepted
**Date:** 2026-11-09
**Supersedes:** [ADR-0194](0194-engine-freeze-gates.md)

## Context

ADR-0194 froze `engine/` until an independently built engine could replace it. On 2026-11-08 the owner resumed `engine/` as a faithful Go port of JSB. The port has three aims. It drops the dependency on the Windows JSB binary. It documents the engine the league runs on. It drives further website automation.

## Decision

Two engines coexist, and `engine/` has no deletion date. The clean-room engine lives in the private repo `a-jay85/ibl-engine`. It may become commercial and is free to diverge from JSB. It replaces the port only once it plays better than JSB. The repo boundary is the contamination wall, so this repo guards no clean-room folder.

`bin/check-engine-freeze` loses its freeze gate. A PR may add, rename, and edit files under `engine/` again. The decompile-token gate stays, with a fixed prefix allowlist of `engine/` and `jsb-native/`. An added line or added file name that carries a decompile token passes only under those prefixes. Website code under `ibl5/` and every other path stay banned. The gate reads only added lines, so existing citations elsewhere stay where they are. CI runs the gate on every PR from `.github/workflows/pr-meta-checks.yml`, and `bin/test-check-engine-freeze` pins each allowlist entry and each near-miss path.

## Allowlist entries

- `engine/` holds the port.
- `jsb-native/` is the port's research directory for reverse engineering. It is untracked today, because the main checkout excludes it locally and `.gitignore` does not list it. The entry is unused until the directory becomes tracked, and it costs nothing until then.
- `bin/jsb-ref` stays off the list. Its token mentions are usage examples, and it reads the decompile directory from `JSB_DECOMPILE_DIR`, outside the repo. A future edit there can use a placeholder.
- `ibl5/docs/decisions/` stays off the list. The owner banned tokens everywhere in `ibl5/`. A future port ADR cites symbols from a doc under `engine/` and links to that doc.

## Name

`bin/check-engine-freeze` keeps its historical name. `bin/check-docs` scans ADR bodies for dead path references. ADR-0194 and its index row are append-only, so a rename would leave a dead reference that no later PR could fix.

## Clean-room hygiene

Decompile material and JSB port work stay in this repo, and port work here reads that material. `a-jay85/ibl-engine` never receives a decompile token or port code. The owner and AI agents build the clean-room engine in that repo. Commercial use needs a legal review of this separation. This ADR states the principle and makes no legal claim.

## Alternatives Considered

- **Rename the script.** Rejected because the append-only ADR-0194 body and index row cite the old name, and `bin/check-docs` would flag the dead reference forever.
- **Delete the token gate too.** Rejected because website code and tooling must stay free of new decompile citations.
- **An exemption marker.** An exemption directory or an inline ignore marker was rejected for the reason ADR-0194 gave. A general escape spreads, and a fixed prefix list covers every future port file.
- **Wider allowlist.** Adding `ibl5/docs/decisions/` or `bin/jsb-ref` was rejected because the owner's ban covers `ibl5/`, and `bin/jsb-ref` can use placeholders.

## Consequences

- Positive: port work under `engine/` can cite the symbols it ports, next to the code that ports them.
- Positive: `bin/check-docs` drops its engine doc filter, so stale engine docs return to the staleness report and the nightly audit can fix them.
- Negative: the dependabot ignore on the golangci-lint action is gone. A bump PR must restate the pin in `engine/.claude/rules/engine-context.md`, and the lint-pin drift check catches a miss.
- Negative: the struct-offset pattern can match hex arithmetic in website code. The failure message names the way out. Move the citation under an allowlisted path or reword the added line.

## Supersedes

ADR-0194. Its freeze gate is removed. Its decompile-token gate stays under the same script name and gains the path allowlist above.

## References

- `bin/check-engine-freeze`
- `bin/test-check-engine-freeze`
- `bin/check-docs`
- `.claude/rules/engine-go.md`
- `.github/workflows/pr-meta-checks.yml`
- `.github/dependabot.yml`
- [ADR-0194](0194-engine-freeze-gates.md)
