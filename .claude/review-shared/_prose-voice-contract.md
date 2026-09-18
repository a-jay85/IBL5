---
description: The written-voice contract for machine-authored merge-digest lines and PR-body prose — five rules, the sentence-tokenizing definition bin/check-digest-prose implements, and two annotated failure samples.
last_verified: 2026-09-18
---

# Prose Voice Contract

## Scope

This contract governs **machine-authored prose** in two places:

- The five `### Merge digest` label lines authored by `.claude/agents/pr-ready-phase6.md`.
- The PR-body prose the `/post-plan` orchestrator composes per `.claude/skills/post-plan/SKILL.md`: the `## Scope` why-paragraph, `**Scope expansion:**`, `## Why this PR exists`, and `## Reviewer verification`.

It is **read on demand**, never auto-attached. Both callers carry an explicit `Read` instruction pointing here.

It **adds nothing to the digest's structural contract**. The digest stays exactly five lines, one per label, labels unchanged, no blank lines between them. That structure is owned by `.claude/agents/pr-ready-phase6.md` and `.claude/skills/pr-ready/scripts/digest.sh`; this file governs only how the sentence reads, never how many lines there are.

**Why sentence length matters here.** `bin/digest-dm-build` truncates `**Watch:**` at `WATCH_MAX=700` characters and `**What changed:**` at `WHAT_MAX=1000` characters for the Discord DM. A long sentence's tail can be the part that vanishes. <!-- slop-ok -->

## The five rules

### Rule 1 — Max 25 words per sentence <!-- slop-ok -->

**Max 25 words per sentence. Break at the clause boundary.** <!-- slop-ok -->

The break is at a clause boundary, so two short sentences replace one long one. Two unrelated things to watch get one sentence each. This restates, and must not contradict, the existing agent-def clause "never join them with a semicolon". <!-- slop-ok -->

Rule 1 is the primary constraint. Rules 2–5 are the most reliable ways to satisfy it. <!-- slop-ok -->

### Rule 2 — Name the component <!-- slop-ok -->

**Name the component.** Use codebase names (`Phase 6.5`, `bin/digest-dm-build`) over relative clauses describing the thing. <!-- slop-ok -->

A relative clause ("the follow-up session that decides whether a PR can merge unattended") costs ~9 words and is ambiguous; the codebase name (`Phase 6.5`) costs 2 and is greppable. Rule 2 is therefore the cheapest way to satisfy rule 1. <!-- slop-ok -->

Rule 2 is author-side guidance. No regex can decide whether a noun phrase has a codebase name. <!-- slop-ok -->

### Rule 3 — No define-by-negation trailing clause <!-- slop-ok -->

**No trailing `instead of` / `rather than` define-by-negation clause.** <!-- slop-ok -->

A define-by-negation tail states what did *not* happen. The reader wants the positive claim. Delete the clause; if the contrast is load-bearing, make it its own sentence with its own subject.

### Rule 4 — At most one em-dash per line <!-- slop-ok -->

**At most one em-dash per line, and never to append a new subject.** <!-- slop-ok -->

One em-dash sets off an aside. A second one is a comma splice wearing a costume. Using an em-dash to append a *new subject* starts a second sentence without ending the first.

### Rule 5 — Active voice with a concrete actor <!-- slop-ok -->

**Active voice with a concrete actor.** Not "the X path cannot execute." <!-- slop-ok -->

Name the actor that acts. "`bin/pr-ready-now` pins the main-checkout copy" not "the launcher pins"; "no result is written" not "the first real-world confirmation is".

### Non-goal

These rules govern sentence construction only. They never authorize changing a label name, adding a sixth line, wrapping a label onto a second line, or softening the terminal verdict word.

## What counts as a sentence

This is the normative definition `bin/check-digest-prose` implements. The linter and this prose must agree; if a future edit changes one, it changes both.

1. **Strip backtick spans first.** Remove every `` `...` `` span (and its delimiters) before any boundary detection. Periods inside a path (`bin/check-digest-prose`, `ibl5/docs/decisions/0128-path-scoped-rules-over-memory.md`) or inside an inline code sample are not sentence boundaries. Stripping before splitting also means a backticked span contributes **zero** words to the 25-word count. A naive `wc -w` and the linter's count therefore differ when backtick spans are present.

2. **A sentence boundary is a period followed by whitespace or end-of-line.** No `?`/`!` handling is required; digest and PR-body prose are declarative.

3. **Exclusions** — a period matching any of these is not a boundary: <!-- slop-ok -->
   - a period immediately followed by a **digit** — version numbers (`v2.12.2`, `1.2.3`, `Phase 6.5`) and decimal figures; <!-- slop-ok -->
   - the abbreviations `e.g.`, `i.e.`, `etc.`, `vs.` (case-insensitive), matched as whole tokens.

4. **Word count** is whitespace-separated tokens of the backtick-stripped sentence.

Every one of these exclusions has a must-pass fixture in `bin/test-pr-ready-now`; a change to this definition that drops an exclusion turns a green fixture red.

## Two failure samples

### Sample 1 — `**What changed:**` line <!-- slop-ok -->

> The automated post-plan pipeline now hands its own already-computed 'final checks passed' result across to the follow-up session that decides whether a PR can merge unattended, instead of leaving that session to look for a result nobody wrote.

Fails:

- **Rule 1** — 38 words in one sentence. <!-- slop-ok -->
- **Rule 2** — "the follow-up session that decides whether a PR can merge unattended" is periphrasis for `Phase 6.5`. <!-- slop-ok -->
- **Rule 3** — trailing `instead of` define-by-negation clause. <!-- slop-ok -->

Rewrite (passes all five): `/post-plan` now writes its final-checks result to the file Phase 6.5 reads. Phase 6.5 previously found no result there and held the PR.

### Sample 2 — `**Watch:**` line <!-- slop-ok -->

> The joined write-then-read path cannot execute on this branch because the launcher pins the main-checkout copy of the tooling, so the first real-world confirmation is the next automated run after this lands — and the fail-closed direction (no result written means still blocked) is the property to re-confirm there. <!-- slop-ok -->

Fails:

- **Rule 1** — 50 words in one sentence. <!-- slop-ok -->
- **Rule 4** — the em-dash appends a new subject ("the fail-closed direction ... is"). <!-- slop-ok -->
- **Rule 5** — nominalized subject, "the first real-world confirmation is". <!-- slop-ok -->

Rewrite: `bin/pr-ready-now` pins the main-checkout copy, so this branch never runs the write-then-read path. The next automated run confirms it. Re-check the fail-closed direction there: no result written must still hold the PR.

## Enforcement

1. **`bin/check-digest-prose` reports; it never blocks.** It exits `0` on every input, including inputs with violations. A reported violation never changes a run's exit status.

2. **Rewrite once, then ship.** The author (agent def or orchestrator) runs the linter on the prose it just wrote. On violations it rewrites **once**, using the reported rule names, and ships whatever that rewrite produces. There is no second loop and no abort. A second rewrite risks churning the prose worse than the first pass.

3. **Out of bounds by design.** This linter must never become a Phase 6.5 arming condition, a required status check, or a `bin/check-*` blocking gate. It is advisory-only. A future reader must not "fix" it by wiring it in.
