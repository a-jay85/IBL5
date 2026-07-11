---
name: backlog-housekeep
description: "Ship backlog housekeeping with an implemented backlog item: flip its status, stamp discovered items with provenance, sweep sibling and cross-backlog redundancy, archive done items behind a dated pointer, and reconcile the README index — then self-verify via bin/check-docs."
last_verified: 2026-07-10
---

# Backlog Housekeeping

Run this when a unit of work (a PR or a plan) just **implemented, resolved, or surfaced** a backlog item under `ibl5/docs/backlog/`. It performs the full housekeeping pass so the bookkeeping ships **with** the change, then self-verifies via the `bin/check-docs` backstop.

It is both a slash command (`/backlog-housekeep`) and a procedure another skill can **Read and follow inline** — `/post-plan` Phase 2.5 cannot call the Skill tool, so it Reads this file and executes the checklist directly. Write nothing here that depends on `$ARGUMENTS`-only wiring; the steps below are self-contained.

## Inputs

`$ARGUMENTS` optionally carries the provenance ref (`<PR-number|plan-slug>`) and the diff base. When absent, derive:

- **Base:** `git merge-base HEAD master` (the PR's `baseRefName` for a stacked PR).
- **Provenance `<ref>`:** the open PR number (`gh pr view --json number --jq .number`), else the branch slug (`basename "$(git rev-parse --show-toplevel)"`).

The base drives both the `--since=<base>` self-verify and, for a PR, the `#<PR>` used in provenance/supersession stamps.

## Caller-tier branch (the token-efficiency core)

**Detect your own tier from your system prompt** — the model executing this skill *is* the caller (there is no runtime model-id API; the orchestrator self-identifies, as `work-triage.md` § Execution routing and `feedback_default_sonnet_execution` assume). If genuinely unsure, default to **inline** — a wrong-way delegation costs more than a same-tier inline pass.

- **Caller is Opus or Fable → delegate; do NOT Read backlog files.** Reason from resident context about *what the work did*: which item it resolved, which items it newly surfaced, which siblings shifted scope. Seed those known effects into the semantic delegation packet (below), then spawn **ONE** Sonnet subagent (`subagent_type: general-purpose`, `model: sonnet`) that Reads the backlog files, runs the cross-backlog redundancy grep sweep (the expensive multi-file read), applies every edit / archive move / index update, self-verifies via `bin/check-docs --since=<base>`, and returns a thin one-line report. The backlog bodies enter the *subagent's* context, never the orchestrator's — that is the entire point of the branch.
- **Caller is Sonnet or Haiku (e.g. `/post-plan` runs Sonnet 4.6) → execute inline, no subagent.** Read the backlog files, run the sweep, apply the edits, self-verify — all in the current context. Spawning a same-tier subagent is pure overhead (`feedback_default_sonnet_execution`: if the orchestrator IS Sonnet, edit inline).

Both branches run the identical checklist below; only *who reads the files* differs.

## Housekeeping checklist (7 operations)

1. **Source-backlog status flip.** In the backlog that owns the implemented item, flip its status glyph to the resolved state (`✅ Implemented` / `🚫 Declined`) with today's date on its `**Status (YYYY-MM-DD):**` line.
2. **Provenance-stamped discoveries.** For each item surfaced *during* this work, add a new backlog entry stamped `(discovered YYYY-MM-DD during <ref>)` so its origin is greppable.
3. **Sibling-scope edits.** Edit sibling items in the same backlog whose scope shifted because of this change (narrowed, split, or now-blocked), noting the shift in the entry.
4. **Cross-backlog redundancy sweep.** Grep the OTHER backlogs for items this change makes redundant; on each, add the greppable field `**Superseded by:** <ref> — <reason>` (verbatim format — `<ref>` = this PR/plan, `<reason>` = one clause). Do NOT delete the superseded item; the stamp is the audit trail.
5. **Archive move + dated pointer.** For every item now done or declined in a **body-status** backlog: move its body into the sibling archive file `archive/<x>-backlog-archive.md` (create it if absent — the gate's (b) invariant requires the canonical sibling), and replace the LIVE entry with a one-line dated pointer into that archive. Table-status backlogs (`maintenance-backlog.md`) keep their glyphs in-place per row — no archive move.
6. **README index/count reconciliation.** Update `ibl5/docs/backlog/README.md` counts and any index rows affected by the flips/additions/archives, and bump its `last_verified` to today (the convention this step follows is codified in that README).
7. **Self-verify (mandatory gate).** Run `bin/check-docs --since=<base>` — the backstop that catches an unresolved pointer, a missing sibling, or an inline-done item. Fix every diagnostic before returning. This is the machine check that the housekeeping is internally consistent.

If nothing qualifies (no flip, no discovery, no archive), **no-op** and say so in one line — never fabricate edits.

## Delegation-packet shape (Opus/Fable branch)

Author the packet in the format at `.claude/skills/plan/_architect-contract.md` **§ Delegation packets for verbose phases** (the canonical heading). Fill each field **semantically** (domain facts, not file line-edits — the subagent resolves those by Reading):

- **Scope:** "housekeep the backlog(s) for `<item>` resolved by `<ref>`."
- **Recipe:** the seeded known-effects from resident context — the item to flip, discovered items to stamp, siblings that shifted scope, backlogs to sweep for redundancy, items to archive.
- **Self-verify the subagent MUST run before returning:** `bin/check-docs --since=<base>` green (plus full `composer test` only if it touched anything under `ibl5/` — backlog docs do not, so the gate alone suffices).
- **Report-back (thin):** one line — `flipped <n>, discovered <n>, superseded <n>, archived <n>; check-docs green`.
