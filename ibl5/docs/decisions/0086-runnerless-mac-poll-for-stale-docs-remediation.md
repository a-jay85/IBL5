---
description: Replace ADR-0079's self-hosted macOS runner with a launchd-scheduled Mac poll — the repo is public, so a runner label is addressable by any workflow including a fork's; the Mac pulls work from GitHub instead of GitHub pushing work onto the Mac.
last_verified: 2026-07-15
---

# ADR-0086: Runnerless Mac poll for stale-docs remediation

**Status:** Accepted
**Date:** 2026-07-15
**Supersedes:** ADR-0079 *Autonomous stale-docs remediation via a self-hosted macOS runner*
(`0079-stale-docs-auto-remediation.md`) — transport only; its human-merge posture is carried forward
deliberately.

> **Numbering note.** The number 0079 is **duplicated on master**: this ADR supersedes
> `0079-stale-docs-auto-remediation.md` (2026-07-04), **not** `0079-sha-pin-github-actions.md`
> (2026-07-05), which is untouched and still in force. Every unqualified "ADR-0079" below means the
> stale-docs one. The collision is pre-existing and repo-wide (0032, 0062, 0065, 0069, 0079, 0081, and
> 0085 are each used twice); fixing it is out of scope here — renumbering would break inbound links
> from rules and PHPStan rules that cite these numbers.

## Context

ADR-0079 (*stale-docs auto-remediation*) designed a pipeline: on a new or changed stale set, a `docfix`
job running on a self-hosted macOS runner invokes `bin/docfix-run`, which launches a detached headless
Claude run that refreshes the flagged docs and opens a PR held for human merge.

**The pipeline has never fired.** No runner was ever registered — `gh api
repos/a-jay85/IBL5/actions/runners` returns `total_count: 0` — so every `docfix` job queues until
GitHub cancels it (runs `29334100558` and `29255223423` cancelled; `29417207057` queued). ADR-0079's
"Operational setup" section was never executed. Zero doc-fix PRs have ever been produced; the owner
receives a "N docs stale" DM instead of a refresh. The pipeline's *code* merged (PR #1329,
2026-07-06); its *transport* never existed.

Completing the missing install step was the obvious fix, and it is the wrong one.

### ADR-0079's containment premise is false

ADR-0079 states its own precondition plainly: a self-hosted runner "is only acceptable on a
**private, single-owner repo** where all workflow code is trusted." **This repo is public**
(`isPrivate: false`, `forkCount: 1`). The premise was false when 0079 was written, so its security
conclusion never held.

Its containment argument does not survive the correction, and it fails for a reason worth naming
precisely, because the reasoning error is easy to repeat. ADR-0079 argued the `docfix` job is
reachable ONLY via the `audit` job, which triggers on `schedule`/`workflow_dispatch` and never on
`pull_request` — "so no untrusted fork PR code ever reaches the runner." That reasoning is about the
reachability of *one job*. It is not the property that matters.

**A registered runner is addressable by its labels, and labels are a repo-wide resource.** Once a
runner is registered with `[self-hosted, macOS]`, *any* workflow in the repo can target it — including
a workflow that does not exist yet. On a public repo, a fork PR can add a workflow declaring
`on: pull_request` and `runs-on: [self-hosted, macOS]`, and that workflow executes arbitrary code on
the owner's Mac. Nothing in the `audit` → `docfix` dependency constrains this; the attacker never
touches `docfix` at all. GitHub's default fork protection requires approval only for **first-time**
contributors — a single merged typo fix promotes a contributor permanently, after which their fork
PRs run without further approval.

The mitigations one would reach for are unavailable, verified 2026-07-15:

- `actions/permissions/access` → HTTP 422: *"Access policy only applies to internal and private
  repositories."* The very control that would scope runner access is unavailable **because** the repo
  is public — the API refusing the request is itself confirmation of the premise error.
- Repo-level runner groups, which could restrict which workflows may target a runner, are an
  organization feature; this repo is owned by a personal account (`a-jay85`).

Deliberately NOT cited as aggravating, though each is factually true of this repo: `allowed_actions:
"all"`, `sha_pinning_required: false`, and `default_workflow_permissions: "write"`. None of them bear
on this threat. A fork's workflow needs no marketplace action to run `curl … | sh` on the runner, so an
allowlist is irrelevant; SHA-pinning **is** in fact enforced on PRs by the `pinact-check` drift-guard
in the `gate` aggregator (ADR-0079, *SHA-pin all external GitHub Actions* — the other ADR numbered
0079), and in any case a self-hosted runner executes a fork's job *before* any gate verdict exists, so
a CI lint cannot prevent code execution; and a fork PR receives a read-only `GITHUB_TOKEN` regardless
of the default-permissions setting. The vulnerability rests on label addressability alone. Listing
unrelated settings beside it would inflate the case and invite a reader to reject the whole argument
when one decoration is shown not to matter.

## Decision

**1. Transport = a launchd-scheduled poll on the Mac. The Mac pulls; GitHub never pushes.**

`bin/docfix-poll` runs daily from a launchd `StartCalendarInterval` agent, derives the stale set
locally via `bin/check-docs --staleness-report`, reads the open `stale-docs` tracker issue, and
invokes `bin/docfix-run` itself. The `docfix` job is deleted from
`.github/workflows/doc-freshness-audit.yml`.

This **eliminates the vulnerability class rather than mitigating it**: with no runner registered,
there is no label to target, so no workflow — fork-authored or otherwise — can schedule anything onto
the Mac. There is no allowlist to maintain, no approval setting to get right, and no first-time-
contributor promotion to reason about. The Mac's outbound `gh` calls are the only coupling.

This is a **third option ADR-0079 never considered.** It weighed exactly two transports — self-hosted
runner versus Tailscale-SSH — and chose the runner because it is pull-based: no inbound networking, no
SSH key to rotate, no listening service. That reasoning was sound and is preserved here. A launchd
poll is *also* pull-based and shares every one of those properties, while additionally requiring no
GitHub-side execution grant at all. Against Tailscale-SSH, 0079's rejection stands unchanged (a
tailnet, a rotating auth key, and an ACL granting CI SSH into the dev machine remain strictly more
surface for the same effect).

**2. The human-merge posture is PRESERVED — a deliberate carry-forward, not an oversight.**

ADR-0079 decision (4) holds in full: `bin/docfix-run` is unchanged, still seeding line-1
`auto_merge: false` into the runtime hold plan, and `/post-plan` Phase 6.5 condition (7) still reads
that frontmatter and refuses to arm auto-merge. A machine-authored doc edit still gets a human read
before it lands.

Auto-merging doc-refresh PRs was considered and rejected in favour of this posture. Two reasons.
First, `bin/lib/pr-armable.sh` condition (10) establishes an unconditional floor — a PR opened by an
autonomous pipeline "ALWAYS holds for a human merge," with no override label — and doc-refresh PRs are
squarely that class; they evade the label today only because nothing applies it, which is an unclosed
hole rather than a grant. Second, the human review **is** the bound on what an unattended
`--dangerously-skip-permissions` run can land. Preserving it is what keeps this change small: no
mechanical docs-only gate is needed, so this ADR adds no new gate and leaves condition (10) and the
shared predicate untouched.

**3. Only genuine failures alert; the pipeline self-heals.**

The alert discriminates on PR state and CI status rather than on staleness alone. A leftover branch
from a merged PR is reaped automatically. An open PR with green CI is the **expected steady state** —
it means the pipeline did its job and the owner has already been told to review it — and is silent;
alerting on it would nag the owner for not having merged yet. A declined PR goes quiet without any
manual cleanup. Only a run that died before opening a PR, a PR whose CI is red, or a poll that never
fired will DM. `bin/cleanup` is never surfaced as an owner remedy.

This preserves the constraint that motivated the redesign — a broken pipeline must still ping, never
fail silently — while removing every DM that merely asks the owner to do maintenance.

**4. Nothing to revoke.**

Because no runner was ever registered, superseding 0079's transport requires no decommissioning: no
token to revoke, no runner to unregister, no `actions/runner` install to remove. ADR-0079's
"Operational setup" section is void, never having been performed.

## Consequences

- **Positive.** The fork-PR code-execution class is eliminated by construction, not mitigated by
  configuration. The pipeline gains an owner-visible schedule it never had, and it now actually runs.
  The reaping step fixes a latent wedge in which a single leftover `docs-stale-refresh-*` branch would
  have stopped the pipeline forever after its first PR (`bin/docfix-run:36-39`).
- **Cost.** Remediation latency is now bounded by the poll interval rather than firing immediately
  after the audit. Scheduling moves from a GitHub-visible workflow to a machine-local launchd agent,
  so a failure of the *poller itself* is not visible in the Actions UI — which is precisely why the
  never-fired alert exists.
- **Cost.** The launchd agent must be installed once on the Mac, after this change merges, via
  `bin/docfix-poll --install-schedule`. It cannot ship pre-installed: the agent's `Program` must point
  at the main checkout's copy of the script, which does not exist until merge. If the owner skips the
  install, the alert fires — the install step is self-enforcing rather than reliant on memory.
- **Security posture.** No inbound network surface, consistent with 0079's goal. No GitHub-side
  execution grant of any kind. The detached run continues to use the Mac user's own persistent
  `gh auth login` credential (not the ephemeral Actions `GITHUB_TOKEN`) — which is also what makes the
  pipeline function at all, since a PR opened with the default token does not trigger `pull_request`
  workflows. Every PR it opens remains held for human merge.

## What ADR-0079 got right

Carried forward unchanged: the GitHub issue remains the idempotent tracker, marker-diffed on the
stale-set signature, with the doc-fix PR carrying `Closes #<issue-num>` (0079 decision 2). The fix
still runs headless via the `bin/post-plan-now` launchd-detach idiom, because a `nohup … &` shares its
parent's process group and dies with it (0079 decision 3). The produced PR is still held for human
merge (0079 decision 4). Only decision 1 — the transport — is superseded, and only because its stated
precondition was not true of this repo.
