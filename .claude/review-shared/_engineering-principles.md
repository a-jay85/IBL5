---
description: Seven named engineering principles that IBL5 planners, implementers and reviewers cite as "<principle>: <the decision it changed>". Read on demand.
last_verified: 2026-11-23
---

# Engineering principles

Planners and implementers name each principle that changed a decision, in the form `<principle>: <the decision it changed>`. A citation with no decision behind it counts as unapplied. Reviewers check those notes against the diff and only report. Read the index, then read only the sections whose trigger matches your task.

PHPStan rules and the `bin/check-*` scripts already enforce raw SQL, superglobals, escaping, orphan CSS, column renames and schema compatibility. These principles cover what no gate checks.

## Index

- **Name the data shape first.** A value is stored, computed or rendered in more than one place.
- **Subtract before you add.** The change adds a method, query, table, setting or file.
- **Smallest change that works.** Any task, at the moment you reach for a flag, option or layer.
- **Attack the premise.** A defect came back after a fix, or the task names a cause nobody measured.
- **Prove it on the real artifact.** You are about to call a fix or feature done.
- **One boundary per surface.** You add an entry point, a sibling file or a new caller of shared services.
- **Migrate callers, then delete the old path.** You rename or move a route, URL, method or file.

## Name the data shape first

**When it applies.** A value is stored, computed or rendered in more than one place.

**Rule.** Before writing code, decide which table, constant or method owns the value. Every other site reads from that owner. Write the owner down in the plan.

**IBL5 example.** The restored box-score team-row SQL drifted from the `COLUMNS` list, and PR #3958 had to resync it. GM display names drifted from `owner_name` until PR #3806. One owner per value would have prevented both fixes.

**Sign you skipped it.** A follow-up titled "keep X in sync with Y".

## Subtract before you add

**When it applies.** The change adds a method, query, table, setting or file.

**Rule.** Search for an existing one that already does the job and reuse it. Delete what your change leaves unused in the same PR.

**IBL5 example.** PR #3965 replaced several season tables in the player module with one shared table. PR #3981 later dropped service getters and a salary summary query that no caller used.

**Sign you skipped it.** A later PR whose whole diff deletes code that your PR added or orphaned.

## Smallest change that works

**When it applies.** Any task, at the moment you reach for a new flag, option, abstraction or config key.

**Rule.** Ship the smallest diff that meets the stated task. Pick a sensible default over an opt-in flag. Add a layer only when a second caller exists today.

**IBL5 example.** The owner prefers a smart default over an opt-in flag. A flag costs every later reader a decision, and most callers never set it.

**Sign you skipped it.** A new option that every call site passes with the same value.

## Attack the premise

**When it applies.** A defect came back after a fix, or the task names a cause that nobody has measured.

**Rule.** Test the stated cause against evidence before you fix it. When the evidence disagrees, fix what the evidence shows and say so in the plan. The root-cause method lives in `.claude/skills/fix-and-prevent/SKILL.md`. This principle adds one question first: "is the premise true?"

**IBL5 example.** The engine foul gap took six fixes (PRs #3867, #3908, #3918, #4008, #4040, #4134). A live JSB trace then showed the gap came from foul rates, and no foul site was missing.

**Sign you skipped it.** Your fix is the third one for the same symptom.

## Prove it on the real artifact

**When it applies.** You are about to call a fix or feature done.

**Rule.** Check the result on what users or the engine actually see: a JSB trace or capture, the rendered page on the worktree stack, a query against the real table. A test written against your own mock proves the mock.

**IBL5 example.** Several foul-gap fixes passed their tests while simulated foul totals still drifted from JSB. The live trace settled the question.

**Sign you skipped it.** Every check in your verification ran on data or code that your own PR created.

## One boundary per surface

**When it applies.** You add an entry point, a `?file=` sibling, or a new caller of shared services.

**Rule.** Route it through the boundary its module already uses, the module factory and `ModuleServices`. Open no second path to the same state. PHPStan bans DB handles in entry points, and it cannot see a parallel path built from allowed parts.

**IBL5 example.** PR #3947 routed every module entry point through a factory. PR #4153 then had to route the `?file=` siblings the same way.

**Sign you skipped it.** A file that builds its own repository or service when its module already hands one out.

## Migrate callers, then delete the old path

**When it applies.** You rename or move a route, URL, method or file.

**Rule.** In one PR, move every caller to the new name, keep a redirect or alias for callers you cannot edit (bookmarks, links from other sites), then delete the old code. `bin/check-old-code-compat` and `bin/check-column-rename-sweep` already cover schema drops and SQL column renames, so this principle covers routes, URLs and method names.

**IBL5 example.** The League_Starters URL moved, and PR #3890 had to add a redirect for links that still used the old one.

**Sign you skipped it.** A 404 or a missing-method error from a caller outside the diff.

## Trial record

Trial window 2026-11-23 to 2026-12-06. Signal: posted review findings labeled `important` per merged PR.
Baseline 2026-11-09 to 2026-11-22: 190 important findings / 343 merged PRs
A T3 one-shot titled `principles-trial-2026-12-07` reruns the same count over the trial window.
