---
description: Rationale for adding a gitleaks secret-scanning CI gate, scrubbing a rotated DB password, and hardening the demo-login token to fail closed.
last_verified: 2026-05-28
---

# ADR-0034: Secret-Scanning Gate

**Status:** Accepted
**Date:** 2026-05-28

## Context

A holistic audit found the production DB password (already rotated by the maintainer) still committed in the tree at `ibl5/docs/maintenance-backlog.md` and surviving across git history. Separately, `ibl5/demo-login.php` accepted the guessable literal `'demo'` as its `DEMO_LOGIN_TOKEN`, so the public magic-link URL granted an authenticated read-only "Warriors GM" session to anyone who read the source. There was **no** automated secret-scanning gate among the CI workflows, so nothing prevented a future credential from being committed. The password was already rotated, so this is scrub-and-prevent, not incident response.

## Decision

1. **Secrets must never be committed.** A `gitleaks` workflow (`.github/workflows/gitleaks.yml`, `gitleaks/gitleaks-action@v2`) runs on every `pull_request` (diff range) and on `push` to `master` (full history via `fetch-depth: 0`). Branch protection should require the `gitleaks` check. False positives are suppressed only via explicit, commented entries in `.gitleaks.toml` at the repo root.

2. **Rotation is the remediation for any leak — never `.gitignore`.** When the gate fires, the credential is rotated and scrubbed from HEAD. Git-history rewrite is out of scope (disruptive; rotation already mitigates), so the already-leaked-and-rotated literal is allowlisted in `.gitleaks.toml` rather than purged from history.

3. **`config.php` stays untracked, and demo login fails closed.** `Auth\DemoLoginGate` resolves the expected token from the `DEMO_LOGIN_TOKEN` env var first, falling back to the constant. Demo login is disabled (HTTP 403, no session) whenever the resolved token is empty or equals the weak `'demo'` literal — even if a stale `config.php` still defines it. A wrong-but-well-formed token keeps the endpoint's prior 404 obscurity.

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
- `ibl5/config.php.example`, `ibl5/docs/maintenance-backlog.md` (findings 3.1, 3.2)
