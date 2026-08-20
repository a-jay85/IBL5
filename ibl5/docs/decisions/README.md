---
description: Index of IBL5 Architecture Decision Records (ADRs). Source of truth for every load-bearing decision and its rationale.
last_verified: 2026-08-20
---

# IBL5 Architecture Decision Records

Every load-bearing decision in IBL5 is captured here as a numbered ADR so that future contributors (human and agent) can reconstruct *why* we chose X over Y, not just *that* we chose X. Rules in `.claude/rules/` and `ibl5/phpstan-rules/` tell you *what* to do; ADRs tell you *why the rule exists*.

## How to Use

- **Reading:** start with the ADR most relevant to the surface you're touching. Each rule file and each PHPStan custom rule links back to the ADR that justifies it.
- **Writing:** when you make a new significant decision, create an ADR first — the CI gate (`bin/adr-check`, the `adr-check` step in workflow `pr-meta-checks.yml`) blocks PRs that add mechanical-enforcement surfaces without an accompanying ADR. See the "When an ADR is required" section below.
- **Creating one:** run `bin/next-adr "kebab-title"` from a worktree (`bin/wt-new <slug>`); it refuses to run from the main checkout to avoid stranding an empty template on `master`. It copies `0000-template.md` into the next numbered slot and prints the path. The template is Michael Nygard format, adapted for IBL5's frontmatter schema.

## Index

| # | Title | Status | Summary |
|---|-------|--------|---------|
| [0001](0001-interface-driven-architecture.md) | Interface-driven Repository/Service/View architecture | Accepted | Organizing pattern for every module in `ibl5/classes/`; drove the 30-module refactor. |
| [0002](0002-xss-enforcement-via-phpstan.md) | XSS enforcement via PHPStan `RequireEscapedOutputRule` | Accepted | Shift-left XSS prevention: mechanical CI gate, not runtime convention. |
| [0003](0003-statsformatter-mandate.md) | `StatsFormatter` mandate, `number_format()` banned | Accepted | Single centralized stat formatter enforced by `BanNumberFormatRule`. |
| [0004](0004-docker-only-dev-environment.md) | Docker-only development environment | Accepted | MAMP sunset; reproducible dev + CI parity + worktree port isolation. |
| [0005](0005-strict-types-enforcement.md) | Strict types + typed properties enforcement | Accepted | PHPStan level `max` + `strict-rules` as the floor; type coercion bugs banned mechanically. |
| [0012](0012-archive-first-jsb-reading.md) | Archive-first JSB file reading | Accepted | `.lge`/`.sch` read directly from backup archive via `JsbSourceResolver`; disk-fallback for manual uploads. |
| [0016](0016-remove-duckdb-analytics.md) | Remove DuckDB analytics layer | Accepted | JSB source decompiled; DuckDB OLAP layer and write-back tables removed. |
| [0017](0017-dependabot-full-ci-and-auto-merge.md) | Dependabot full CI and auto-merge | Accepted | Force all CI checks on Dependabot PRs; auto-squash-merge on pass. |
| [0023](0023-deploy-rehearsal-pre-flight-gate.md) | Deploy rehearsal pre-flight gate | Accepted | Reusable workflow dry-runs `composer --no-dev` + migrate + validate-schema against disposable MariaDB before prod deploy. |
| [0079](0079-sha-pin-github-actions.md) | SHA-pin all external GitHub Actions + drift-guard | Accepted | Pin every external `uses:` ref to a full commit SHA via pinact; `pinact --check` guard in the `gate` enforces it; local `./` composite refs exempt. |
| [0085](0085-just-in-time-opus-escalation-on-final-automouse-retry.md) | Just-in-time Opus escalation on the final automouse retry | Accepted | Non-Opus plans escalate only their final retry to Opus with the prior attempt's capped failure report; env-stops never escalate. |
| [0092](0092-postplan-harness-in-repo-with-python-ci.md) | Post-plan harness in-repo with dedicated Python CI | Accepted | Harness ships under `tools/postplan-harness/` gated by a path-scoped `python-tests.yml`; real-data dirs gitignored; `bin/post-plan-now` pinned to the main checkout. |
| [0095](0095-retire-ibl6-svelte-frontend.md) | Retire the IBL6 SvelteKit frontend; site stays PHP+HTMX | Accepted | Ported IBL6's one page (boxscore) into IBL5 as a PHP module; deleted the second stack + all its CI/Docker/deploy/smoke surface. |
| [0096](0096-headless-plan-autofire.md) | `/plan-prompt` auto-fires the planning run headlessly | Accepted | `bin/plan-now` runs the drafted prompt as a detached Sonnet 4.6 `/plan`; user-facing forks pre-resolved in-session, `bin/check-plan` verdict logged; the runner auto-queues for automouse by default, `--implement` opts out. |
| [0097](0097-single-retrying-host-notification-transport.md) | A single retrying host-side notification transport | Accepted | All host-side Discord sends go through `bin/discord-dm` — retries past a pm2 restart window, spools + exits non-zero rather than dropping a message silently. |
| [0098](0098-attachment-ingest-trust-boundary.md) | The attachment-ingest trust boundary | Accepted | The pipeline's first attacker-controlled binary ingest path: untrusted bytes/metadata, filenames never form paths, snowflakes stay strings, capped downloads against an https allowlist, cache pruned outside the repo, only a sanitized text reference reaches the model, `--allowedTools ''` untouched. |
| [0099](0099-unattended-ci-failure-autofix.md) | Unattended CI-failure autofix via `bug-pipeline-tick` | Accepted | Settled red PRs get one credential-starved fix agent per tick, capped at 3 attempts per head SHA; the trusted tick side publishes and comments, never merges and never arms auto-merge. |
| [0100](0100-actionlint-workflow-gate.md) | actionlint as the workflow-validation gate | Accepted | Pinned actionlint via `bin/lint-workflows` in the `gate`; its shellcheck pass over `run:` blocks is mandatory, with shell policy single-sourced from the ShellCheck job. |
| [0101](0101-bun-only-lockfile-collapse.md) | bun-only lockfile for ibl5 | Accepted | Collapsed ibl5 to a single `bun.lock`; npm audit gate replaced by `bun audit` + weekly tracking issue. |
| [0102](0102-pre-commit-gate-in-version-control.md) | Version-control the pre-commit gate body | Accepted | Moves the git pre-commit gate body out of the untracked common hooks dir into `bin/pre-commit-hook`, installed via a fail-closed shim. |
| [0103](0103-htmx-transient-dom-state-repair-on-history-restore.md) | Repair transient htmx request-time DOM state on history restore | Accepted | htmx snapshots the DOM between `beforeRequest` and the swap, so pre-request mutations must be undone in `htmx:historyRestore` too, scoped to a `data-*` marker; enforced by rule doc + review, not a gate. |
| [0106](0106-local-worktree-sync-fast-forward.md) | Local worktree sync via fast-forward only | Accepted | `bin/wt-sync-tick` fast-forwards idle local worktrees to their `origin/<branch>` counterparts (900 s launchd poll, HID-idle gate, ahead-of-origin skip, straggler log as evidence base). |

## When an ADR is Required

The CI workflow `pr-meta-checks.yml` runs `bin/adr-check` (the `adr-check` step) on every PR. An ADR is required if the PR adds any of:

1. A new PHPStan custom rule under `ibl5/phpstan-rules/*.php`.
2. A new always-loaded or path-conditional agent rule under `.claude/rules/*.md`.
3. A new CI workflow under `.github/workflows/*.yml`.
4. A destructive schema migration (`DROP TABLE`, `DROP COLUMN`, or `DROP INDEX` in `ibl5/migrations/*.sql`).
5. A new `bin/` helper script of at least 50 lines.
6. A new dependency in `ibl5/composer.json`'s `require` or `require-dev` block.

**Bypass** for changes that genuinely don't need an ADR: add `<!-- no-adr: reason at least 15 chars long -->` as an HTML comment inside the PR body. The reason is logged in CI output for traceability. Silence is not allowed — you must type a reason.

## Template and Tooling

- [`0000-template.md`](0000-template.md) — the Nygard template. Never edit in place.
- [`bin/next-adr`](../../../bin/next-adr) — creates the next ADR file by number, copying the template and slugging the title.
- [`bin/adr-check`](../../../bin/adr-check) — the CI gate (also usable locally with `--staged`).
- [`bin/check-docs`](../../../bin/check-docs) — enforces frontmatter freshness on every ADR and verifies bidirectional `Supersedes` integrity.
- [`.claude/rules/doc-freshness.md`](../../../.claude/rules/doc-freshness.md) — the frontmatter schema every ADR must satisfy.
