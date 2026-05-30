---
description: Replace the JSB jumpshot.exe binary round-trip with a native Go sim engine as a pure stdin/stdout transform.
last_verified: 2026-05-30
---

# ADR-0035: Native Go Simulation Engine

**Status:** Accepted
**Date:** 2026-05-30

## Context

Game simulation has always been performed by JSB (`jumpshot.exe`), a closed Windows binary run manually by a community member, who returns binary output files (`.sco`, `.plr`, `.car`, etc.) that IBL parses byte-by-byte through a large parser/importer/exporter layer. This couples the league to outdated Windows tooling, gives us no control over the engine, and forces a brittle binary-file translation layer between sim output and the website. JSB also seeds its RNG from system time with no `srand()`, so runs are non-reproducible. We have fully reverse-engineered the parts of the engine IBL uses, so a native reimplementation is now feasible.

## Decision

We will build a native simulation engine in **Go**, living in the monorepo at `engine/`, structured as a **pure stdin/stdout transform**: it reads a JSON input bundle (rosters, ratings, depth charts, schedule) and writes a JSON result (a per-possession event stream plus box-score rows), touching no database, network, or files. The PHP side builds the bundle and loads the result; the existing derivation steps (standings, power rankings, snapshots, history) are unchanged. The engine uses a **seedable PRNG** (`math/rand/v2` PCG) with the seed recorded per run, making every simulation reproducible. The Go module and its tests are enforced in CI by `.github/workflows/engine.yml` (build, vet, test).

## Alternatives Considered

- **Keep running the JSB binary** — status quo. Rejected because: no control over the engine, depends on manual Windows execution, and forces the brittle binary-file translation layer we want to delete.
- **Reimplement in PHP, inline with the app** — one language, no new toolchain. Rejected because: a full-season possession sim is CPU-bound and PHP would be too slow and awkward to make deterministic/testable as an isolated unit.
- **Reimplement in Rust** — comparable performance and safety. Rejected because: Go has a gentler concurrency model for batch-simming a season and produces cleaner, more maintainable code for this team's needs; performance is more than adequate.

## Consequences

- Positive: full control over and extensibility of the engine; no dependency on Windows or `jumpshot.exe`.
- Positive: reproducible sims (seed recorded per run) enable deterministic golden-master tests, replay, and audit — capabilities JSB never had.
- Positive: the binary-file round-trip and JSB parser/importer/exporter classes can be retired; sim output flows directly to the database.
- Negative: adds a second language and its toolchain to the repo, and shifts the maintenance burden of the simulation rules onto the team.

## References

- `engine/go.mod` — the Go module (monorepo root).
- `engine/cmd/jsbsim/main.go` — the pure stdin/stdout CLI entrypoint.
- `engine/internal/rng/rng.go` — the seedable PCG PRNG.
- `.github/workflows/engine.yml` — CI enforcement (build, vet, test).
- `ibl5/docs/JSB_FILE_FORMATS.md` — the binary file formats this engine's contract replaces.
