# CI Speedup Handoff

**Created:** 2026-07-03 · **Repo:** IBL5 · **Author:** prior Claude session (branch `ci-e2e-throughput`)

Menu of CI speedups to `/plan` + queue. Ranked by payoff×frequency ÷ risk.
All file:line refs are relative to the IBL5 repo root. Verify line numbers before editing — they drift.

> **STATUS 2026-07-03 (update):** A, B, C, D, E, I all shipped (#1308–#1312). G shipping
> (buildx `type=gha` layer cache on the cron build — branch `ci-buildx-mariadb-share`).
> **H dropped** — GHA service containers are job-scoped, so "share one MariaDB" means *merging*
> the 3 migration-safety jobs into 1, serializing parallel jobs on a safety-critical workflow for
> only a runner-slot win (like #1300), not a speedup. **F closed — not worth it (measured).**
> Post-A+E, the only shareable setup slice is migrate+seed = **~13s** (147 migrations apply in
> 10.5s; run 28691375601). The dominant per-job setup cost (docker image pulls + `compose up
> --wait`, ~50s) is unshareable — every E2E job needs its own live Apache stack. Dump-artifact
> adds a ~40s+ serial `needs:` prefix to save ~13s → net wall-clock loss; a pre-seeded mariadb
> image saves ~13s/job but needs a GHCR build + staleness gate + schema guard + blast radius on
> the E2E critical path. Not justified for ~13s. **Menu effectively complete.**

> **Live context when written:** an in-flight PR on branch `ci-e2e-throughput` collapsed the
> E2E shard matrix 4→1 (`e2e-tests.yml`) and added `^engine/` to `bin/website-affecting`'s
> deny-set. That PR **owns `e2e-tests.yml`** — do not stack edits onto that file until it merges.

---

## The fat target

The dominant CI cost is the E2E stack: **5–7 heavyweight jobs each independently re-pay the full
setup** — vendor cache restore + `bun install` + `composer install` + docker pull/build +
`docker compose up` (which **rebuilds ibl6 from source**) + migrations + seed. Every lever below
chips at that repetition.

E2E jobs (all in `e2e-tests.yml`): `e2e` "Visual Regression" (:100), `api-e2e` (:329),
`e2e-shards` (:380), `e2e-mutators` (:497), `ibl6-visual` (:599), `ibl6-e2e` (:705).

---

## Tier 1 — cheap wins, ad-hoc-safe (known blast radius, existing pattern, no design fork)

Each is independently shippable. B/C/D do **not** touch `e2e-tests.yml`, so they carry zero
collision with the in-flight shard PR and can ship immediately.

- **B. Persist the PHPStan result cache** — `tests.yml:266`. `composer run analyse` +
  `analyse:tests` recompute cold every PR. Add `actions/cache` on PHPStan's result-cache dir
  (`--memory-limit=1G` run; cache dir is `tmp/` by default unless configured in `phpstan.neon`).
  Isolated, no behavior change. **Confidence: high.**
- **C. Cache `npx playwright install chromium --with-deps`** — `tests.yml:214` (`ibl6-checks`
  job). Uncached browser download every run. Either cache `~/.cache/ms-playwright` keyed on the
  Playwright version, or reuse the already-pulled Playwright image. **Confidence: high.**
- **D. Bump `actions/cache@v5` → `@v6`** — `.github/actions/lighthouse-setup/action.yml`. Version
  drift vs `@v6` everywhere else. Trivial. **Confidence: high.**
- **A. Skip `bun install` / `composer install` on cache-hit** — every e2e job in `e2e-tests.yml`.
  Both run **unconditionally** despite restored vendor/node caches. Copy the skip-on-cache-hit
  pattern already in `cache-dependencies.yml:78,105`. Payoff multiplied across 5–7 jobs/PR.
  **⚠️ Touches `e2e-tests.yml` — must wait for the shard PR to merge first.** **Confidence: high.**

## Tier 2 — structural, needs `/plan` (bigger blast radius / new infra)

- **E. Pin ibl6 to a prebuilt image — SINGLE BIGGEST LEVER.** `docker-compose.ci.yml:44` uses
  `build:` not `image:`, so **every** stack bring-up rebuilds ibl6 from source with no layer cache
  — even ibl5-only jobs that never exercise ibl6. Build + push an ibl6 image in
  `cache-dependencies.yml` alongside `ghcr.io/a-jay85/ibl5/php-apache:latest`, then switch the
  compose service to `image:`. Spans `cache-dependencies.yml` + `docker-compose.ci.yml` + new GHCR
  image + the setup-docker-e2e pull path. Hits every E2E job. **Plan this first.**
- **F. Consolidate E2E setup: one build/setup job → artifacts → fan-out**, replacing 5–7 jobs each
  repeating setup. Biggest architectural payoff, biggest blast radius. Depends conceptually on the
  install/caching shape from A + E. Plan-sized.
- **G. buildx layer cache on the base docker build** — `cache-dependencies.yml:129` has no
  buildx/`type=gha` cache; cold base rebuild. Lower priority — it's the **daily cron**, not
  per-PR, so it doesn't sit on the PR critical path.

## Tier 3 — lower frequency / hygiene

- **H. Share one MariaDB across `migration-safety.yml`** — it spins **3 independent** mariadb:10.11
  services (idempotency-check, schema-parity-check, schema-completeness). Bounded: only fires on
  `ibl5/migrations/**.sql`.
- **I. Add `timeout-minutes`** to `tests.yml`, `migration-safety.yml`, `deploy-rehearsal.yml` jobs
  — currently none; a hung job burns the **6h default**. Cost-cap, not a speedup, but cheap.

---

## Suggested sequencing / queue plan

1. **Now (separate branch, no `e2e-tests.yml`):** B + C + D. Optionally I as a hygiene rider.
2. **After the shard PR merges:** A (on `e2e-tests.yml`).
3. **`/plan` E** (ibl6 prebuilt image) — highest payoff; land before F.
4. **`/plan` F** (consolidated setup job) — builds on A + E.
5. **Opportunistic:** G, H alongside whichever plan touches those files.

## Verification notes for whoever picks this up
- Re-run the inventory: line numbers above will have drifted. Grep for the anchors
  (`build:` in `docker-compose.ci.yml`, `playwright install` in `tests.yml`, `analyse` in the
  phpstan job) rather than trusting the `:NNN`.
- Every caching change must prove a **cache-hit path AND a cold path** both still pass — a broken
  key silently disables the cache (slow but green) or, worse, restores stale deps.
- E2E changes: `/post-plan` rebuilds the Docker stack for verification; expect a heavy local run.
- Workflow constraints in effect: work in a worktree (never edit master checkout directly);
  leave the tree dirty for `/ship` or `/post-plan` to commit; PHPStan only via composer scripts.
