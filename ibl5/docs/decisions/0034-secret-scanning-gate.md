---
description: Rationale for adding a gitleaks secret-scanning CI gate, scrubbing a rotated DB password, and hardening the demo-login token to fail closed.
last_verified: 2026-09-23
---

# ADR-0034: Secret-Scanning Gate

**Status:** Accepted
**Date:** 2026-05-28

## Context

A holistic audit found the production DB password (already rotated by the maintainer) still committed in the tree at `ibl5/docs/backlog/maintenance-backlog.md` (example) and surviving across git history. Separately, `ibl5/demo-login.php` accepted the guessable literal `'demo'` as its `DEMO_LOGIN_TOKEN`, so the public magic-link URL granted an authenticated read-only "Warriors GM" session to anyone who read the source. There was **no** automated secret-scanning gate among the CI workflows, so nothing prevented a future credential from being committed. The password was already rotated, so this is scrub-and-prevent, not incident response.

## Decision

1. **Secrets must never be committed.** A `gitleaks` workflow (`.github/workflows/gitleaks.yml`, `gitleaks/gitleaks-action@v2`) runs on every `pull_request` (diff range) and on `push` to `master` (full history via `fetch-depth: 0`). Branch protection should require the `gitleaks` check. False positives are suppressed only via explicit, commented entries in `.gitleaks.toml` at the repo root.

2. **Rotation is the remediation for any leak — never `.gitignore`.** When the gate fires, the credential is rotated and scrubbed from HEAD. Git-history rewrite is out of scope (disruptive; rotation already mitigates), so the already-leaked-and-rotated literal is allowlisted in `.gitleaks.toml` rather than purged from history.

3. **`config.php` stays untracked, and demo login fails closed.** `Auth\DemoLoginGate` resolves the expected token from the `DEMO_LOGIN_TOKEN` env var first, falling back to the constant. Demo login is disabled (HTTP 403, no session) whenever the resolved token is empty or equals the weak `'demo'` literal — even if a stale `config.php` still defines it. A wrong-but-well-formed token keeps the endpoint's prior 404 obscurity.

## Addendum — action version (2026-09-02)

The Decision above records `gitleaks/gitleaks-action@v2`, which is what was adopted on
2026-05-28. The workflow has since been upgraded to v3 and is now pinned by digest
(`gitleaks/gitleaks-action@e0c47f4f8be36e29cdc102c57e68cb5cbf0e8d1e # v3.0.0` in
`.github/workflows/gitleaks.yml`). The decision itself — that a gitleaks gate runs on every PR
and on pushes to `master`, with suppressions only via commented `.gitleaks.toml` entries — is
unchanged; only the action major version moved. The v2 reference is left in place deliberately
as the historical record of what was decided.

## Alternatives Considered

- **Rewrite git history to purge the secret** — surgically remove the literal from all commits. Rejected because: it is disruptive (invalidates every clone/fork), and rotation already neutralizes the exposed value.
- **`.gitignore` the doc / leave demo token as-is** — Rejected because: hiding a file does not rotate a leaked secret, and a guessable default token is an open auth bypass regardless of where it is defined.
- **TruffleHog instead of gitleaks** — equivalent capability. Rejected because: gitleaks has a maintained first-party GitHub Action, a simple TOML allowlist, and no license requirement for personal-account repos.

## Consequences

- Positive: any newly committed credential fails CI before merge; the demo endpoint can no longer be entered with a guessable token.
- Positive: the token-resolution logic is a small, unit-tested pure class (`Auth\DemoLoginGate`), decoupled from the legacy `config.php`.
- Negative: `.gitleaks.toml` must be maintained — each genuine false positive (test fixture, public-by-design key, deleted-legacy history) needs an explicit allowlist entry, or the gate blocks the PR.
- Negative: demo login now requires deployments to set a strong `DEMO_LOGIN_TOKEN`; until they do, the feature is off (intended fail-closed behavior).

## References

- `ibl5/demo-login.php`, `ibl5/classes/Auth/DemoLoginGate.php`, `ibl5/tests/Auth/DemoLoginGateTest.php`
- `ibl5/tests/e2e/security/demo-login-weak-token.spec.ts`
- `ibl5/config.php.example`, `ibl5/docs/backlog/maintenance-backlog.md` (example) (findings 3.1, 3.2)

## Addendum (2026-09-22): DB credentials moved to config.local.php

This ADR left `config.php` untracked with the four DB credential variables
read through `getenv()` fallbacks in `ibl5/config.php.example`. IBL5-backlog
#179 closes the remaining gap: the production `config.php` still carried the
credentials inline, and the `getenv()` fallback in the example meant a missing
env var quietly produced a default password.

Decision:

- The four variables (`$dbhost`, `$dbuname`, `$dbpass`, `$dbname`) live only in
  `config.local.php`, a gitignored file next to `config.php` (rule added to
  `ibl5/.gitignore`). The tracked template is `ibl5/config.local.php.example`,
  whose values are the docker-compose stack defaults.
- `ibl5/config.php.example` no longer reads DB credentials from the environment.
  It checks that `config.local.php` exists and dies with a message naming the
  template when it does not. There is no fallback password.
- `ibl5/bin/check-config-example` pins the contract in CI: the template's four
  literals are non-empty, the example has no `getenv('DB_*')` credential read,
  and a copy of the example without `config.local.php` exits non-zero.
- CI writes `config.local.php` from each job's `DB_*` env in
  `.github/actions/setup-php-env/action.yml` and at the two raw
  `cp config.php.example config.php` sites in `.github/workflows/migration-safety.yml`
  and `.github/workflows/deploy-rehearsal.yml`. Worktrees receive a copy from
  `materialize_worktree_config_local()` in `bin/lib/git-helpers.sh`.
- `config.php` stays untracked on production and in the main checkout. The
  operator places `config.local.php` by hand and replaces the credential lines
  in the production `config.php` with the require block from the example. The
  production password is not rotated by this change.

Consequence for the scanning gate: gitleaks now has one fewer plausible leak
path, since the only tracked file that names `$dbpass` is the template with a
Docker-default value. The allowlist in `.gitleaks.toml` is unchanged.

## Addendum (2026-09-23): exit(1) correction in config.php.example

The 2026-09-22 addendum states that "a copy of the example without
`config.local.php` exits non-zero." The initial implementation used
`die('string')`, which PHP exits with status 0 when passed a string. The code
was corrected in the same PR: `config.php.example` now echoes the message and
calls `exit(1)`, so CLI callers receive a non-zero status and the
`ibl5/bin/check-config-example` gate passes. `echo` rather than `fwrite(STDERR, ...)`
because the `STDERR` constant is undefined under web SAPIs.
