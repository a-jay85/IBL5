---
description: Class registry for /post-plan Phase 9 retrospective routing — one row per defect class, written by /post-plan, never edited by hand.
last_verified: 2026-09-08
---

# Retrospective Class Registry

Written by `/post-plan` Phase 9 only. Never edit or delete a row — the value is in the history.

## Class registry

Append-only. One line per **class of defect**, written by `/post-plan` Phase 9 when a run routes a
learning up the escalation ladder. Never edit or delete a line — the value is in the history.

The `prior:` field is the anti-recurrence lever: when a new line's class matches an existing one,
record the earlier PR numbers there. A non-empty `prior:` means the class has recurred, and recurrence
is the signal that the previously chosen rung was too weak — escalate one rung rather than re-routing
to the same place. This is a **prompted** loop, not an automated one: nothing scans this table on a
schedule, and no gate fails on it.

The table is fenced and every path inside it is written bare — no backticks, no link syntax. The
bare paths are what keep a row naming a destination that does not exist yet from failing the
dead-reference check; the fence alone would not, since the checker does not strip fenced blocks. Do
not add backticks or markdown links to a row.

```
| Date       | PR   | Class                                                                 | Routed to rung                                                                                          | Prior          |
| ---------- | ---- | --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- | -------------- |
| 2026-07-25 | #1633 | class: env/supervisor precondition unverified before a long-running job | routed to: Rung 3 - forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger) | prior: -- |
| 2026-07-25 | --   | class: shared-context wire contract between two scripts is unasserted   | routed to: Rung 3 - forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger) | prior: -- |
| 2026-07-25 | #1654 | class: CLI entrypoint accepts an unknown flag silently instead of erroring | routed to: Rung 1 - PHPStan rule over argv option parsing, queued as L33 in this backlog, not yet built; interim Rung 3 backstop shipped in #1668 (section: Forced integration-verification trigger). A fourth occurrence forces the Rung 1 rule. | prior: #1354, #1496 |
| 2026-08-10 | #1834 | class: app-generated file read by an updater is force-tracked via .gitignore negation, so deploy git-reset clobbers live data with stale committed content | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): updater/importer that reads a generated file from a repo-relative path must assert git ls-files exits nonzero for that path | prior: -- |
| 2026-08-14 | #1880 | class: gate escape path conditioned on a git-range query silently blocks when the range is empty (first-branch-commit), with no null fallback | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying an escape path in a CI check gate that calls a git-range helper must test the empty-range (no-prior-commits-on-branch) scenario | prior: -- |
| 2026-08-16 | #1897 | class: integer from $_GET used as a for-loop upper bound without a domain ceiling guard, enabling DoS via unbounded iteration until a dedicated fix closed it | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): plan introducing a loop with user-input bounds must assert over-horizon input is rejected before the loop begins | prior: -- |
| 2026-08-16 | #1901 | class: htmx request-lifecycle handlers (beforeRequest/afterRequest) that mutate DOM state leave that mutation serialized in the history cache; browser Back restores request-time snapshot, making the mutation permanent until a historyRestore handler repairs it | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced E2E triggers): plan adding/modifying htmx beforeRequest/afterRequest DOM mutations must require E2E coverage of browser Back behavior | prior: -- |
| 2026-08-19 | #1925 | class: queue enqueue operation inherits mtime from the queued file rather than stamping the ordering key at insertion time, silently misordering entries with old authoring dates | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying an enqueue or requeue path in bin/automouse/queue must test back-of-queue placement with an ancient-mtime plan | prior: -- |
| 2026-08-19 | #1930 | class: a plan that stops ongoing data corruption in an import or upsert path ships without a compensating backfill migration for rows already corrupted before the fix | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan removing or modifying an importer or upsert path that was writing an incorrect value must verify a compensating backfill ships in the same PR or explicitly scope out already-corrupted rows in the plan | prior: -- |
| 2026-08-17 | #1900 | class: shell scripts use BSD-specific stat/date invocations without GNU/Linux fallbacks, failing on Linux CI but passing macOS dev — not caught by shellcheck | routed to: Rung 4 - new rule doc .claude/rules/shell-portability.md guiding dual-form stat/date patterns (stat -c %Y ... || stat -f %m ...) for cross-platform shell scripts | prior: -- |
| 2026-08-19 | #1923 | class: worktree batch-sync script uses git rebase instead of git merge on already-published branches, rewriting shared commit history | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying a worktree batch-sync script must assert that a branch with already-published commits is handled without rewriting local commit history | prior: -- |
| 2026-08-21 | #1953 | class: cap-validation or salary-comparison logic selects a salary-basis column (current vs. next-year) without consulting the league phase, producing incorrect hard-cap outcomes during offseason | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying salary-comparison or cap-enforcement logic must carry verification rows for both the in-season path (advancesContractYears()=false, current_salary basis) and the offseason path (advancesContractYears()=true, next_year_salary basis) | prior: -- |
| 2026-08-22 | #1963 | class: a fail-closed validation gate on a store/import path is relaxed to warn-only before the compensating resolution path that makes relaxation safe is shipped, allowing uncorrectable orphaned rows to accumulate undetected | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan that relaxes a fail-closed guard on a store or import path (error → warn or removed) must verify that the compensating resolution path ships in the same PR or is already in prod, OR explicitly scope out orphan accumulation with a follow-up | prior: -- |
| 2026-08-23 | #1969 | class: an importer writes to a secondary tracking table but omits the corresponding write to the canonical flag column in the primary table — the secondary write satisfies the importer's narrow contract while the flag silently stays at its default | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying an importer that writes to a secondary/audit table must carry an integration test verifying the canonical flag column in the primary table is also updated after a full import cycle | prior: -- |
| 2026-08-26 | #1996 | class: SQL table names in BaseMysqliRepository subclasses inserted without backtick quoting, silently bypassing the rewriteTableNames() invariant that all repository SQL be rewrite-eligible | routed to: Rung 1 - PHPStan rule over SQL string literals in BaseMysqliRepository subclasses, asserting all bare table-name identifiers are backtick-quoted in INSERT/UPDATE/DELETE statements | prior: -- |
| 2026-08-27 | #2002 | class: Phase 6 conflict-audit runtime dependency (/tmp/pr-ready-diff-pre-<N>.patch) deletable by bin/pr-ready-now:246 (rm -f /tmp/pr-ready-*-"${PR}".*), leaving the audit unable to verify conflict resolution without a reconstruction workaround | routed to: Rung 3 - forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan modifying the /pr-ready skill's tmp-file cleanup in bin/pr-ready-now must verify the Phase 6 conflict-audit path (/tmp/pr-ready-diff-pre-<N>.patch) is excluded from the cleanup glob | prior: -- |
| 2026-08-29 | #2023 | class: an unconditional detection check in an audit class is nested inside a fail-open guard conditioned on data availability, causing the check to silently skip when the guard condition is false instead of running independently | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying a detection check in an audit class that has a fail-open guard must verify the check fires even when the guard-controlling condition is false (e.g., ScheduleReconciliationAudit with empty schedule index) | prior: -- |
| 2026-08-31 | #2039 | class: an awk filter in a skill file uses a reset pattern that fires after the set pattern on the same diff-header line, making the exclusion a no-op and silently passing all lines through | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying an awk filter in a skill or bin/ file must carry a CLI-executable smoke test that verifies the negative path (excluded content absent from output) and the positive path (non-excluded content present) | prior: -- |
| 2026-08-31 | #2043 | class: a skill or generator asserts a fact about its own output environment as a template constant — test types present, a tool exempt from a gate — with nothing checking the assertion holds after the diff that generates the output changes | routed to: Rung 2 - extended pr_manual_testing_clearance() in bin/lib/pr-armable.sh to fail closed when the PR body's tail clause names a test type absent from the changed-file list | prior: -- |
| 2026-08-31 | #2042 | class: CLI entrypoint accepts an unknown flag silently instead of erroring — Rung 1 rule delivered | routed to: Rung 1 complete — BanUnknownCliOptionRule added in ibl5/phpstan-rules/; fires on all getopt() calls; 2 existing callers (scripts/build-engine-bundle.php, scripts/runEngineShadow.php) baselined as temporary; closes class registered 2026-07-25 row (#1654 trigger, interim Rung 3 in #1668) | prior: #1354, #1496, #1654 |
| 2026-09-01 | #2054 | class: a two-phase CLI tool that collects human judgment for a set of items does not short-circuit when the set is empty, forcing an unnecessary second invocation and opening a failure window in the inter-invocation gap | routed to: Rung 4 - rule doc in .claude/rules/ stating that two-invocation CLI scripts must implement the trivial bypass when invocation 1 produces an empty judgment set | prior: -- |
| 2026-09-04 | #2087 | class: Shell script wrapper that cd's to its module root before invoking Python invalidates caller-provided relative path arguments, silently breaking callers that pass repo-relative paths | routed to: Rung 4 - .claude/rules/shell-wrapper-path-resolution.md | prior: -- |
| 2026-08-31 | #2040 | class: a backlog entry archived in the live file receives a table-row status flip but its section-body Status: line is left at the old open status — the two representations diverge until a follow-up fix commit corrects the section body | routed to: Rung 3 - forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan that archives a backlog entry (moves section body to archive/) must carry a verification step confirming the archived entry's Status: line is updated to ✅ Implemented | prior: -- |
| 2026-09-04 | #2092 | class: a plan-level portability claim for a shell script uses find -regex with \{n\} interval notation, verified only on macOS BSD find (where BRE supports \{n\}), not on Ubuntu GNU find (which uses emacs regex type by default and does not treat \{n\} as an interval) — the regex silently matches nothing in CI, causing the script to find no directories and skip its entire body without error | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan introducing a find -regex pattern claiming cross-platform portability between macOS and Ubuntu must carry a CI-run verification row demonstrating the regex matches on the Ubuntu runner, OR must use bash-level character-class and length filtering instead of find interval expressions | prior: -- |
| 2026-09-05 | #2117 | class: proc_open subprocess contract violations (unchecked proc_close exit, undrained stderr, NUL-unsafe delimiter) shipped undetected when a plan adds or modifies a proc_open call site without requiring subprocess contract verification | routed to: Rung 1 (partial, shipped in #2117) - BanProcOpenUncheckedExitRule in ibl5/phpstan-rules/ enforces checked proc_close exit; broader contract (stderr drain, NUL-delimiter correctness) routed to Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger) | prior: -- |
| 2026-09-05 | #2121 | class: new always-loaded rule doc committed to wrong directory tree during implementation — bin/check-rules-byte-budget scans only the correct $RULES_DIR, so the misplaced file passes the gate silently until manually relocated | routed to: Rung 4 - note in .claude/rules/doc-freshness.md clarifying always-loaded .claude/rules/*.md files must be created at the exact repo-root path, not inside any subdirectory (e.g. not ibl5/.claude/rules/) | prior: -- |
| 2026-09-05 | #2140 | class: a plan phase prescribes a specific numeric expected value for a phase-sensitive salary boundary case (e.g., cy=0) without tracing the resolver chain under each phase condition, producing an incorrect assertion that a later plan phase must overwrite | routed to: Rung 4 - new path-scoped rule doc .claude/rules/plan-phase-sensitive-expected-values.md: when a plan phase specifies an expected value for a characterization test involving resolveCurrentContractYear() or Season::advancesContractYears(), trace the resolver path under each phase condition to derive the value — domain intuition is insufficient for boundary cases where the dispatch chain collapses apparent differences | prior: -- |
| 2026-08-16 | #1899 | class: shell-function-as-timeout-argument — timeout(1) execvp()s its argument; wrapping a shell function name exits 127 at exec time, undetectable at plan-authoring time | routed to: Rung 4 - verification test at the execution site (row 9 of bin/test-bug-pipeline-hunt exercises run_under_starved_env+timeout under the credential-starved env); the exit-127 failure surfaced and fixed the argument order inline during implementation | prior: -- |
| 2026-08-21 | #1950 | class: a shell port-guard that pipes lsof output to grep -qv without stripping the column header always fires the "occupied by other process" branch regardless of actual port state, because the lsof header line never matches the process name | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying a port-guard or process-detection guard that pipes lsof to a pattern filter must assert the port-free case exits with the expected free-port verdict (no false positive from the header line) | prior: -- |
| 2026-09-08 | #2174 | class: a shared plan-architect context file accumulates rationale content over successive plans with no byte-cap enforcement, silently growing the architect's cold-read token load beyond what it needs | routed to: Rung 2 - bin/test-architect-contract-split CI gate (wired in tests.yml) asserts both plan-architect context files stay within hard byte caps and that all operative content survived the split | prior: -- |
| 2026-09-08 | #2174 | class: a shell test harness under set -o pipefail uses printf-pipe-grep-q, causing grep's early exit (match found) to SIGPIPE printf and make pipefail report failure — passes on macOS, fails on Linux CI | routed to: Rung 4 - new lazy rule doc .claude/rules/shell-pipefail-grep.md (path-scoped to bin/ shell scripts, not resident) explaining the herestring fix | prior: -- |
```

---


---

### L42 Autonomous-loop PR ships stale line citations, undeclared plan substitution, unmentioned diff file, and duplicate backlog ID

*(discovered 2026-09-01 during #1966)*

**class:** an autonomous-loop run authors or squash-rebases a PR body containing hand-written line citations, plan-substitution declarations, and a scope file list — none of which are re-validated after the commit history changes; and a sequential backlog ID is assigned without checking the preceding entry for duplication.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #1966 body — `bin/plan-now:449` (should be `:453`) and `bin/plan-now:583` (should be `:587`) post-rebase | yes | yes | fixed this pass (via `gh pr edit`) |
| 2 | PR #1966 body — plan-item 5 substituted but PR body carries no substitution declaration | yes | yes | fixed this pass (via `gh pr edit`) |
| 3 | PR #1966 body — `ibl5/docs/backlog/loop-engineering-backlog.md` (example) modified in diff but absent from Scope section | yes | yes | fixed this pass (via `gh pr edit`) |
| 4 | `ibl5/docs/backlog/loop-engineering-backlog.md` (example) — second `L39` entry created without checking that `L39` already existed | yes | fixed this pass | fixed this pass |
| 5 | `ibl5/docs/backlog/loop-engineering-backlog.md` (example) — iterative heading rename (L51→L53→L68) left stale L51 and L53 rows in the summary table; same-ID assertion would have caught this | yes | fixed this pass | fixed this pass (PR #2043) |

`prevention_ladder:`

- **rung 0 — already covered by an existing gate?** No gate re-validates hand-authored PR body line citations against post-rebase line numbers, and no gate checks sequential backlog ID uniqueness.
- **rung 1 — extend an existing gate?** **Yes — landing rung for the duplicate ID.** `bin/check-docs` already parses backlog tables; extending it to assert that each ID appears exactly once in its file's table is structurally the right host. Backlog-entry authoring (`_remediation.md`) should also instruct the author to grep for the proposed ID before writing. The PR-body citation gap is a harder problem (line numbers drift after rebase) and warrants a separate tracking note.
- **rung 2 — a rule doc?** Augment `ibl5/docs/backlog/loop-engineering-backlog.md` (example)'s authoring notes (or a shared backlog-authoring companion) to include "grep for the ID first" before assigning.
- **rungs 3–5** — N/A. Line-number citations in free-form PR prose cannot be mechanically validated without reparsing the referenced file at the PR's HEAD.

`artifact destination:` `bin/check-docs` unique-ID extension (in-repo; ship-pipeline surface — wants a `/plan`). Authoring note: wherever `_remediation.md` instructs backlog-entry creation.


### L43 Autonomous-loop doc-fix PR body contains stale claims and inconsistent ADR authoring format after post-review commit
➜ L43 Autonomous-loop doc-fix PR body contains stale claims and inconsistent ADR authoring format after post-review commit — ✅ Implemented (2026-09-05, #2131): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L44 Upstream overlap silently drops a plan phase; Phase 2a pre-rebase artifact captures post-rebase state, making the drop undetectable

*(discovered 2026-09-02 during #1789)*

**class:** A git rebase that silently drops a plan-phase's implementation (via upstream overlap) leaves the branch with stale test assertions for the dropped code, and the Phase 2a pre-rebase artifact captures post-rebase state if a prior rebase already ran, making the drop undetectable.

**Occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/docfix-run` (dropped `bin/docfix-run:38-42` guard, superseded by PR #1861) + `bin/test-docfix-run` case 23 (stale assertion for 'API unreachable') | yes | yes | fixed this pass (test assertion corrected; PR body and ADR corrected) |

**prevention_ladder:**

- rung 0 — not covered by existing gate. No gate detects when a plan-phase's implementation is absent from the branch due to upstream overlap, and no gate checks whether the Phase 2a pre-rebase artifact predates the branch's latest reflog entry.
- rung 1 — extend Phase 2a to check whether `/tmp/pr-ready-diff-pre-<N>.patch` predates the branch's latest reflog entry; if so, recapture. This is the landing rung. Cheaper rungs are insufficient because the timing check is mechanical and can be automated.
- rung 2 — a rule doc noting that Phase 2a artifacts should be re-captured after any rebase. Insufficient alone: the artifact timestamp issue is not visible to the author.

Landing rung: 1 (extend Phase 2a capture to detect and correct post-rebase stale artifacts).

**artifact destination:** `.claude/skills/pr-ready/scripts/` (Phase 2a capture logic, in-repo)

**provenance:** (discovered 2026-09-02 during #1789)

**Status (2026-09-02):** ✅ fixed this pass (test assertion fixed; PR body and ADR corrected) — 🟦.

---

### L45 `/pr-ready` Phase 2 squashes load-bearing commit boundaries when `auto_merge: false`; PR body SHAs go stale after force-push

**class (Check 2 + Check 4):** A `/pr-ready` Phase 2 rebase delegate that applies a generic squash-is-cosmetic rule to a plan whose `auto_merge: false` flag signals a load-bearing commit boundary, silently voiding the merge gate (V-2c/V-4a/V-7a) and leaving PR body SHA citations pointing at pre-squash history that is no longer reachable from the pushed head.

**Check 3 — class: n/a:** three plan-undeclared docs (`codebase-map.md`, backlog archive, maintenance backlog) landed in the commit stack; informational finding, surfaced by Phase 5.9 files-changed comparison; no additional gate warranted beyond the existing Phase 5.9 diff.

**Check 5 — class: n/a:** the plan's V-4c/V-4d verification matrix contained a literal count that became stale after master advanced past the plan's authoring point; correctness of plan literals is the plan author's responsibility; no harness gate can distinguish intentional from accidental staleness in a plan literal.

**Occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `.claude/skills/pr-ready/_rebase-and-conflicts.md` — Phase 2 delegate squash rule fires unconditionally regardless of `auto_merge` plan flag | yes (Check 2) | yes | fixed this pass (restored pre-squash stack; 11 commits rebased `--onto` new master; V-2c/V-4a verified) |
| 2 | PR body of #1797 — SHA citations pointed at `5bd71bc12` / `14b363829`, both unreachable from pushed head | yes (Check 4) | yes | fixed this pass (updated 3 SHA citations to `acbfff148a` / `09ee61e054`) |

**prevention_ladder:**

- rung 0 — not covered by an existing gate.
- rung 1 — extend `_rebase-and-conflicts.md` Phase 2 delegate to read `auto_merge:` from the plan file before squashing; if `false`, preserve individual commits. This is the landing rung for Check 2. Check 4 is self-corrected by Phase 6.5 PR body reconciliation (already implemented); no new rung needed.
- rung 2 — a rule doc noting that `auto_merge: false` signals a load-bearing commit boundary; Phase 2 must not squash. Insufficient alone: rule docs are not loaded during Sonnet delegation.
- rung 3 — not applicable (PHPStan cannot gate plan-file parsing).
- rung 4 — not applicable (CI cannot verify delegate behavior mid-run).
- rung 5 — not warranted (a push hook cannot recover a squash already committed).

Landing rung: 1 for Check 2 (extend `_rebase-and-conflicts.md`); rung 0 for Check 4 (Phase 6.5 already handles it). Check 3 and Check 5: `prevention_ladder: no gate warranted`.

**artifact destination:**
- Check 2: `.claude/skills/pr-ready/_rebase-and-conflicts.md` (in-repo)
- Check 4: `.claude/skills/pr-ready/SKILL.md` Phase 6.5 (already present; no new artifact)
- Check 3/5: `n/a — no gate`

**provenance:** (discovered 2026-09-02 during #1797)

---

➜ L46 Queued matrix-less plan with non-canonical `impl_model:` alias slips all pre-queue gates; runner disposes on first nightly run — ✅ Implemented (2026-09-05): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

---

➜ L47 `/pr-ready` hook rejection resilience — ✅ Implemented (2026-09-04): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

---

### L48 Planning pipeline prose coverage gap: code-block path expressions in `SKILL.md` are invisible to `bin/check-docs`
➜ L48 Planning pipeline prose coverage gap: code-block path expressions in `SKILL.md` are invisible to `bin/check-docs`, so they can diverge from `bin/plan-now`'s runtime slug derivation silently — ✅ Implemented (2026-09-04): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L49 `/pr-ready` Phase 6.5 files backlog rows with non-canonical status glyphs and automouse values

**class:** A `/pr-ready` Phase 6.5 remediation filing using non-canonical status glyphs (`🔵 filed`) and automouse values (`✗`) outside the documented five-glyph set, causing filed rows to be invisible to open-work filters and readers relying on the canonical taxonomy.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `ibl5/docs/backlog/maintenance-backlog.md:682` — row 15.31, Status and Automouse columns | yes | was live; fixed this pass | fixed this pass |
| 2 | `ibl5/docs/backlog/e2e-backlog.md:233` — row E15, Status and Automouse columns | yes | was live; fixed this pass | fixed this pass |
| 3 | `ibl5/docs/backlog/maintenance-backlog.md:28` — roll-up total not updated when row 15.31 was added | yes (related filing defect — stale count) | was live; fixed this pass | fixed this pass |

**prevention_ladder:**

- rung 0 — no existing gate validates status glyph values of new backlog rows.
- rung 1 — `bin/check-docs` could be extended to grep new `| <ID> |` rows added by the diff and validate Status and Automouse column values against the canonical set in `ibl5/docs/backlog/README.md` (example). Effort: S.
- rung 2 — add an explicit note to `.claude/skills/fix-and-prevent/_remediation.md` step 4 specifying the five canonical status glyphs (`⬜ Open`, `◑ Partial`, `📋 Planned`, `✅ Done`, `🚫 Declined`) and canonical automouse values (`🟩`/`🟦`/`🟨`/`🟥`/`—`). Cheaper than a CI gate and catches the defect at write time. Effort: XS.
- rung 3 — not applicable (markdown surface; PHPStan does not parse `.md` files).
- rung 4 — CI gate via extended `bin/check-docs`: possible but rung 2 is cheaper and faster.
- rung 5 — a new hook: not warranted per `meta-tooling-bar.md` (no distinct trigger event; rung 2 is the natural landing).

Landing rung: **2** — add an explicit note to `.claude/skills/fix-and-prevent/_remediation.md` step 4 before the "Bump that file's `last_verified:`" instruction, specifying canonical status glyphs and automouse values.

**artifact destination:** `.claude/skills/fix-and-prevent/_remediation.md` step 4 (in-repo)

**provenance:** (discovered 2026-09-04 during #1956)

---

### L50 `bin/pr-cycle` logs gate nominees as "excluded this run" but then orders and readies them

**class:** A log line that states a disposition the code does not apply — the worker prints `excluded this run (gate nominee, unjudged)` for every `### #N` nominee in `bin/pr-attack --gate-candidates` output, then calls `bin/pr-attack --work <WORK> --gate-edges /dev/null`, which is the *judged-empty* form: every nominee is re-admitted as orderable with no gate edges. The first live run (2026-09-05, `/tmp/pr-cycle-20260905-023625-80966.log`) printed seven "excluded" lines and then readied #2108, the first one on that list.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/pr-cycle` — the `excluded this run (gate nominee, unjudged)` echo inside the nominee loop, followed by the `--gate-edges /dev/null` re-run | yes | live — fires on every run with gate nominees | ⬜ Open |

**Why it matters:** The plan (`~/claude-plans/pr-cycle-driver.md`) said nominees are excluded for the run; the implementation orders them unjudged. Either is a defensible overnight policy — arming stays fail-closed in `bin/pr-triage`, and a gate PR merged out of order lands the affected PR in BLOCKED-CHECK for the human rather than merging it wrong. But the log must not lie: a reader debugging a surprising merge order will trust "excluded" and look elsewhere.

**Fix (pick one, S):**
- Reword to `ordered with no gate edges (gate nominee, unjudged)` and say so in the usage header — matches what the code does today; or
- Actually exclude: pass each nominee to `bin/pr-attack` as excluded (or filter them from `tried`/pick) so the log and behavior agree, at the cost of fewer merges per night.

The static-guard case in `bin/test-pr-cycle` should pin whichever wording lands, so the two cannot drift again.

**provenance:** (discovered 2026-09-05 during the first live `bin/pr-cycle --go` run, right after #2081 merged)

---

### L51 Plan Phase 5 dry-run count propagated to archive only, not PR body; reviewer blast-radius instruction stale by ~23%

*(discovered 2026-09-05 during #2108)*

**class:** A plan Phase 5 stated deliverable — recording the dry-run-measured blast-radius count in the PR body — propagated to the archive entry but not the PR body, leaving a reviewer-facing instruction citing the planning-time estimate (~772) rather than the measured figure (~626), a ~23% overstatement.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2108 body § "What this PR does to `gh-pages`" — both `~772` occurrences and the reviewer check instruction; plan `~/claude-plans/gh-pages-count-prune.md` Phase 5 states "record 626 in the archive" but does not explicitly say "update the PR body" | yes | was live; fixed this pass | fixed this pass (both occurrences updated to ~626 with measurement note; reviewer instruction now uses `<N>` placeholder) |

**prevention_ladder:**

- rung 0 — not covered. Phase 6 check 4 (`_plan-fidelity-review.md` 6d.4) catches this at review time, as it did here, but not at authoring time.
- rung 1 — extend Phase 5 of `/pr-ready` or the plan template to add an explicit instruction: "after recording the dry-run measurement, update the PR body in the same phase." No new gate required; the existing Phase 6 check 4a already flags a disagreement as blocking.
- rung 2 — a rule doc (or an addition to the plan template's Phase 5 dry-run section) stating that any plan phase that measures and records a value must propagate that value to the PR body before proceeding. Cheaper than rung 1 but advisory only.

Landing rung: **2** — add a sentence to the plan template's Phase 5 dry-run section stating that the measured count must be reflected in the PR body in the same phase, before CI is re-watched.

**artifact destination:** plan template or `.claude/skills/pr-ready/SKILL.md` Phase 5 prose (in-repo)

**provenance:** (discovered 2026-09-05 during #2108)

**Status (2026-09-05):** ⬜ Open — 🟦.

---

### L52 Test harness case comment over-claims assertion scope; adjacent cases leave `run_block` exit codes unchecked

*(discovered 2026-09-05 during #2108)*

**class:** A test harness case comment asserts a behavioral property ("1 tracked removed") that the case's assertions do not verify; adjacent cases also capture `run_block` exit codes into a variable but do not assert them, so a non-zero exit silently masks the real cause.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | test-vr-pages-prune:134 — Case 4 comment said "1 tracked removed" but assertions only check rc=0, stray/ presence, and no fatal in stderr | yes | was live at discovery | moot — test-vr-pages-prune has since been retired on master; prune logic moved to `bin/prune-vr-galleries` / `bin/test-prune-eligibility` |
| 2 | test-vr-pages-prune:142 — `rc4=$?` captured but only asserted as `[ "$rc4" -eq 0 ]`; Cases 1, 2, 3 call `run_block` bare with no explicit exit-code capture | near-miss | was live at discovery | moot — same retired harness |

**prevention_ladder:**

- rung 0 — not covered. `shellcheck` does not validate assertion accuracy vs comment claims.
- rung 1 — a dedicated per-harness comment-vs-assertion lint: not warranted for a single 7-case harness.
- rung 2 — no rule doc warranted; the fix is inline and the occurrence is isolated.

Landing rung: **no gate warranted** — neither occurrence exists in the tree after test-vr-pages-prune was retired; the class is real but disproportionate to a standing gate, and Phase 4B structured code review (Agent E) already surfaces this class at review time.

**artifact destination:** n/a

**provenance:** (discovered 2026-09-05 during #2108)

**Status (2026-09-05):** ✅ moot — harness retired this PR.
### L54 Archive closure Status line can assert full sweep completion while named candidate sites lack documented dispositions

*(discovered 2026-09-05 via PR #2040 Phase 6 plan-intent fidelity review)*

**Location:** Any `loop-engineering-backlog-archive.md` entry whose body enumerates candidate sites for a multi-site sweep.

**Problem:** A Status line stamped ✅ Implemented after converting one site — while the body still names other sites as candidates requiring assessment — produces a permanently false closure. The archive is read-only after merge; a false closure there is uncorrectable without a follow-up PR.

**Suggested direction:** When archiving a multi-site sweep entry, the Status line must name every site listed in the body and state its disposition (converted, assessed/out-of-scope, or waived with reason). Add a check to `.claude/skills/fix-and-prevent/_remediation.md` step 4 noting this requirement before "Bump that file's `last_verified`".

**provenance:** (discovered 2026-09-05; PR #2040 L22 Status line claimed full sweep when gate-14, post-plan/SKILL.md, and bin/plan-now candidate dispositions were undocumented; corrected in same PR)

---

### L53 Phase 2 test code lost in branch rebuild — invisible because CI passed without the tests

*(discovered 2026-09-06 during #2141)*

**class:** A plan phase's test code that was implemented and committed was lost in a manual branch rebuild; the loss was invisible because CI passed — the missing tests had no footprint left to catch their own absence.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/test-automouse-queue` (Phase 2 rows 21-26 + row-15 tightening) | yes | yes | fixed this pass |

**prevention_ladder:**

- rung 0 — already covered: `/pr-ready` Phase 6 check 5 (Verification Matrix realisation) catches absent declared automated test paths, as demonstrated by finding F3 in this very run. Landing rung is **0 — already covered by existing gate.**
- rungs 1-5 — superseded by rung 0.

**artifact destination:** `.claude/skills/pr-ready/SKILL.md` Phase 6 (the gate that caught this)

**provenance:** (discovered 2026-09-06 during #2141)

**Status (2026-09-06):** ✅ fixed this pass — 🟦.

### L63 PR #1899 Phase 6 notes — `fixed`+`terminal` skip and undeclared backlog addition

**class:** two notes from Phase 6 review of #1899, both non-blocking.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/bug-pipeline-tick` — `terminal:true` check fires before a Gate-1 reject exits the tier-1 loop, sending the hunt to `give_up_needs_human` without climbing further tiers; the plan described rejection at tier 1 as never reaching a human directly (F3) | yes | live | 📝 Note — fail-safe direction; warrants its own plan |
| 2 | `ibl5/docs/backlog/loop-engineering-backlog.md` (example) — PR #1899 added a shell-function-as-timeout-argument row but did not mention the backlog change in the body Scope prose (F6) | near-miss | resolved | 📝 Note — additive and doc-only |

**F3 detail:** A result carrying `verdict="fixed"` AND `terminal:true` that fails Gate 1 (`observed_before != reported`) triggers the `terminal` branch and calls `give_up_needs_human` immediately. The plan stated tier-1 rejections climb to tier 2 before giving up. The observed behavior is fail-safe — the hunter never ships a bad fix; a human receives the findings — but the escalation path is bypassed. Fixing it requires either clearing `terminal` before the reject exits, or adding a dedicated test row for this combination. Neither is a quick tweak; file as its own plan.

**F6 detail:** `ibl5/docs/backlog/loop-engineering-backlog.md` (example) is listed in the `files-changed` block, but the Scope prose does not mention it. The change is additive and doc-only. Phase 5.9 already surfaces it via the files-changed block.

**prevention_ladder:**
- F3: no gate warranted — the combination of `fixed`+`terminal:true` being rejected by Gate 1 is not exercised in rows 50–55; fixing correctly requires a dedicated plan.
- F6: no gate warranted — additive backlog additions are already surfaced by Phase 5.9 files-changed; a rule requiring Scope prose for every backlog touch would be low-value.

`prevention_ladder: no gate warranted for either finding`

`artifact destination: n/a — no gate`

*(discovered 2026-09-05 during Phase 6 review of #1899)*

### L64 `fixed`+`terminal:true` in Gate-1 reject skips tier-climbing

**class:** a `verdict:"fixed"` result also carrying `terminal:true` that is rejected by Gate 1 (`observed_before != reported`) falls through to the `terminal` check at line 956 of `bin/bug-pipeline-tick`, skips the remaining model tiers, and hands directly to `give_up_needs_human` — contrary to the plan's stated intent that every Gate-1 reject climbs the full ladder before reaching a human.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/bug-pipeline-tick:943-958` | yes | yes | not fixed — filed |

**prevention_ladder:**
- rung 0 — no existing gate covers this; the test harness does not exercise `fixed`+`terminal:true` together.
- rung 1 — extend `bin/test-bug-pipeline-hunt`: a dedicated row asserting the combination climbs to sonnet/opus before landing on `give_up_needs_human`. Machine-verifiable.
- rung 2 — n/a (no rule doc needed; the existing plan prose already states the intent).
- rung 3–5 — n/a; a harness row (rung 1) is sufficient.

Landing rung: **rung 1** — a test row plus the matching `bin/bug-pipeline-tick` fix; warrants its own `/plan` to design the assertion and the guard change correctly. Rungs 2–5 not needed for this class.

**artifact destination:** `bin/test-bug-pipeline-hunt` (in-repo) + `bin/bug-pipeline-tick` (in-repo)

**provenance:** (discovered 2026-09-06 during Phase 6 review of #1899, surfaced by Phase 4B on 2026-08-16 at 75/100 sub-threshold)

---

### L68 `pr_manual_testing_clearance` callers in the ship pipeline pass only one argument; the keyword–file AND gate never fires at runtime

**class:** A gate predicate in `bin/lib/pr-armable.sh` extended with a second `changed_files` parameter and AND-semantics keyword enforcement, where every production caller in the ship pipeline still passes only the body argument, so the keyword–file gate is structurally unreachable at runtime.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/lib/pr-armable.sh` — OR-semantics accumulation in `pr_manual_testing_clearance` | yes | was live; fixed this pass (PR #2043) | fixed this pass |
| 2 | `.claude/skills/post-plan/_phase-6.5-arm-auto-merge.md` — all `pr_manual_testing_clearance` call sites pass only `$BODY` | yes | live | not fixed — filed |
| 3 | `.claude/skills/pr-ready/scripts/holds.sh:37` — `run_predicate "manual-testing-clearance" pr_manual_testing_clearance "$BODY"` passes only one arg | yes | live | not fixed — filed |
| 4 | `bin/pr-triage:288` — `pr_manual_testing_clearance "$BODY"` passes only one arg | yes | live | not fixed — filed |

**prevention_ladder:**

- rung 0 — no existing gate checks argument arity of sourced-shell-function calls.
- rung 1 — `bin/check-docs` or a dedicated `bin/test-pr-armable-wiring` (example) could grep each caller site and assert it passes two arguments; extend-before-add bar favors extending an existing test script if one already covers these callers. Effort: S.
- rung 2 — adding a note to `_phase-6.5-arm-auto-merge.md` naming the required second argument is cheaper but prose-only, so insufficient on its own for a headless caller that cannot read rules mid-run.
- rung 3 — not applicable (shell surface; PHPStan does not parse shell scripts).
- rung 4 — a CI gate that greps caller sites for the single-arg pattern after the function signature changes. Possible; rung 1 is the natural form for this repo.
- rung 5 — not warranted per `meta-tooling-bar.md`; rung 1 is the correct host.

Landing rung: **1** — extend the existing `ibl5/tests/Cli/PrArmableLibCliTest.php` or a shell-level wiring test to assert that each caller in `_phase-6.5-arm-auto-merge.md` and `scripts/holds.sh` passes the `changed_files` argument.

**artifact destination:** `ibl5/tests/Cli/PrArmableLibCliTest.php` (in-repo, extends existing test class) or a new `bin/test-pr-armable-wiring` (example) (in-repo shell test).

**provenance:** (discovered 2026-09-05 during #2043)

**Status (2026-09-05):** ⬜ Open — 🟦.

---

### L57 `bin/pr-ready-now:434` claims both `STOP:` and `PUSH FAILED` are matched as line prefixes, but only `STOP:` is anchored

`STOP:` is checked with a line-anchored pattern; `PUSH FAILED` uses unanchored `grep -qF`, so a log line containing the substring anywhere (e.g. in quoted prose or a commented-out rule) would be classified as a hard-stop marker. Decide whether to anchor `PUSH FAILED` to the line start or correct the comment to reflect the current behaviour — this is a gate change needing its own verification, and was deliberately left out of scope for L47's implementation. See also the L47 archive body for context.

**Status (2026-09-04):** ⬜ Open — 🟥.

**provenance:** (discovered 2026-09-04 during L47 implementation)

---

### L58 Reconcile `~/claude-plans/pr-ready-dm-and-push-retry.md` — §6.1 scoped, Phase 6.6/8.3 `push.sh` shasum stale

**class:** A plan-doc (`~/claude-plans/pr-ready-dm-and-push-retry.md`) whose §6.1 prose claim (`PUSH FAILED` is genuinely non-retriable) and Phase 6.6/8.3 `shasum` pin for `.claude/skills/pr-ready/scripts/push.sh` both become stale when `push.sh` is edited in a sibling PR — non-discoverable at diff time because the plan file lives outside the repo.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `~/claude-plans/pr-ready-dm-and-push-retry.md` — §6.1 claim and Phase 6.6/8.3 `push.sh` shasum pin | yes | live | not fixed — filed (this entry) |

**prevention_ladder:** no gate warranted — the stale shasum pin fails closed (loud mismatch at run time, not silent loss); plan files live outside the repo at `~/claude-plans/` and are unreachable by CI or `bin/check-docs`. The durable fix is documented here: re-record the `.claude/skills/pr-ready/scripts/push.sh` digest before executing `pr-ready-dm-and-push-retry.md` Phase 6.6/8.3.

**artifact destination:** n/a — no gate

**provenance:** (discovered 2026-09-04 during #2083; row dropped by the 2026-09-04 18:12:52 rebase onto `0e71e6f4b`, restored 2026-09-05)

**Status (2026-09-06):** ⬜ Open — 🟦.

---

### L55 PR body mislabels Phase 6.5 remediation artifacts; new backlog entry inserted contextually collides with master's concurrent sequence advance

*(discovered 2026-09-06 during #2040)*

**class:** A Phase 6.5 remediation that (a) inserts a new backlog entry adjacent to a thematically related entry rather than at the end of the numeric sequence, producing an ID collision when master concurrently assigns the same number; and (b) describes the new entry in the PR body Scope prose with the wrong artifact form and wrong defect class, making the actual artifacts invisible to a reviewer reading the body.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `ibl5/docs/backlog/loop-engineering-backlog.md:52` (L51 contextual insert, renumbered L54) | yes | yes | fixed this pass |
| 2 | `#2040 PR body Scope prose` (L51 labeled as class-registry row with wrong defect class) | yes | yes | fixed this pass |

**prevention_ladder:**
- rung 0 — `bin/check-numbering` (existing CI gate) already caught the ID collision (finding a). Phase 6 check 4 (existing gate) already caught the body misdescription (finding b). Landing rung is **0 — already covered by existing gates.**
- rungs 1-5 — superseded by rung 0. Note: `_remediation.md` step 4 already says "read the last row's ID and increment"; a more explicit position instruction (end of sequence, not mid-table) would reinforce it without a new gate.

**artifact destination:** n/a — no gate

**provenance:** (discovered 2026-09-06 during #2040)

**Status (2026-09-06):** ✅ fixed this pass — 🟥.

---

### L59 PR body coordinate citations go stale after commits that renumber backlog rows or shift source-file lines

**class:** A PR body prose claim that cites a coordinate (backlog row ID or source-file line number) that a post-body commit changes — no gate recomputes these citations, and no rule names them as a re-read trigger alongside the negative-claim list.

**occurrence table:**

| # | Finding | Same class? | Live? | Status |
|---|---------|-------------|-------|--------|
| 1 | F1 — `#2083` body notes (c)/(d)/(e) cited `L51`/`L52` after rows were renumbered to `L53`/`L54`; archive cross-ref `(see L51)` was also stale | yes | fixed | fixed this pass (`gh pr edit` + archive file) |
| 2 | F2 — `#2083` body cited `push.sh:55`/`:41`/`:59-63` after hunk B shifted them to `:56`/`:42`/`:60-62` | yes | fixed | fixed this pass (`gh pr edit`) |

**prevention_ladder:**

- rung 0 — `.claude/rules/pr-body-negative-claim-recheck.md` exists and fires on negative-scope claims after every commit. It does not cover positive citations (row IDs, line numbers). Not covered for this class.
- rung 1 — extend `pr-body-negative-claim-recheck.md`: add a parallel "positive-cite re-check" trigger that re-reads any `(see L<N>)` or `:NN` line citation in the body after any commit that renumbers rows or touches the cited file. Landing rung for the F1 sub-class.
- rung 2 — a companion rule doc reminding authors to re-verify prose line citations after any commit that adds or removes lines in the cited region. Landing rung for the F2 sub-class (automated verification would require parsing arbitrary prose numbers, which is higher cost than a rule).
- rung 3 — PHPStan rule: not applicable (shell and markdown, no PHP).
- rung 4 — CI gate: a gate could scan PR body for `:<N>` patterns and cross-check against live file line counts, but false-positive risk on prose text is high and the cost exceeds the benefit for an infrequent class.
- rung 5 — new hook: not warranted; `pr-body-negative-claim-recheck.md` is the natural host for extension.

Landing rung: **1** for backlog ID citations (extend `pr-body-negative-claim-recheck.md`); **2** for line-number citations (rule doc).

**artifact destination:** `.claude/rules/pr-body-negative-claim-recheck.md` (extension); optionally a companion rule for line citations.

**provenance:** (discovered 2026-09-06 during /pr-ready Phase 6 review of #2083; second recurrence of F1 class on this same PR)

**Status (2026-09-06):** ⬜ Open — 🟦.

---

### L70 `pr-body-negative-claim-recheck.md` covers negative-claim-list re-reads but not Summary-prose re-reads when a post-review commit modifies a Summary-mentioned file

*(discovered 2026-09-05 during #2131)*

**class:** A post-review commit that modifies a file already named in the PR body's `## Summary` section silently invalidates Summary-section prose without triggering a targeted re-read. The existing rule (`pr-body-negative-claim-recheck.md`) already re-reads the negative-claim list on every commit; the uncovered gap is Summary-section prose — nothing cross-references the Summary-mentioned file set to trigger a re-read there.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2131 — a post-review commit modified `ibl5/docs/backlog/loop-engineering-backlog.md` (example) (named in `## Summary`); no mechanism triggered a re-read of Summary-section prose, leaving the requirement (b) bullet inaccurate until caught by Phase 6 plan-fidelity review | yes | yes — not prevented by existing rule | ⬜ Open |

**prevention_ladder:**

- rung 0 — `.claude/rules/pr-body-negative-claim-recheck.md` exists and fires on every commit, but it is a behavioral prompt only — it carries no parse of the Summary to derive a file-set trigger. Phase 6 catches this class at review time (as it did here), but not at commit-push time.
- rung 1 — extend `pr-body-negative-claim-recheck.md` to add requirement (b): when a post-review commit modifies a file named in `## Summary`, the rule fires a targeted re-read of **Summary-section prose** (the negative-claim list is already covered by the existing rule). Landing rung.
- rung 2 — a separate rule doc is not warranted; requirement (b) belongs as a named clause in the existing rule.

Landing rung: **1** (extend `pr-body-negative-claim-recheck.md` to add the Summary-file cross-reference trigger for Summary-prose re-reads as requirement (b)).

**artifact destination:** `.claude/rules/pr-body-negative-claim-recheck.md` (in-repo)

**provenance:** (discovered 2026-09-05 during #2131)

**Status (2026-09-05):** ⬜ Open — 🟦.

---

### L71 Autonomous-loop impl deviated from plan "exact content" recipe without declaring the deviation

*(discovered 2026-09-06 during #2131)*

**class:** An autonomous-loop implementation that delivers a rule doc at significant compression relative to the plan's stated "exact content" recipe without recording the deviation or its rationale in the PR body, leaving reviewers unable to assess whether the compression was intentional.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2131 — `.claude/rules/pr-body-claims.md` shipped at ~1.2 KB against a plan recipe of ~4.5 KB; the `## What triggered this rule` section (both PR #2059 failure narratives) was dropped without declaration in the PR body | yes | yes | not fixed — filed |

**prevention_ladder:**
- rung 0 — no existing gate compares shipped rule doc content against plan recipe content.
- rung 1 — extending an existing gate: not feasible; plan recipes are free-form Markdown with no canonical diff surface.
- rung 2 — a rule doc requiring that any deviation from a plan's "exact content" recipe be declared in the PR body Scope with a one-line rationale. Landing rung.
- rungs 3–5 — not applicable; the surface is PR body authoring, not PHP code or CI.

Landing rung: **2** — a rule doc under `.claude/rules/` requiring declared deviations from "exact content" plan recipes. Zero gate overhead; surfaced at the authoring step where the cost of a miss is lowest.

**artifact destination:** `.claude/rules/` (new file, in-repo)

**provenance:** (discovered 2026-09-06 during #2131)

**Status (2026-09-06):** 📝 Note — not fixed; deviation is non-regression (enforcement norm intact); prevention filed.

---

### L72 Loop-authored backlog entry cited unreachable squash-artifact SHA; archive entry missing blank line before GFM table

*(discovered 2026-09-06 during #2131)*

**class:** An autonomous-loop-authored backlog entry that cites a commit SHA that is a squash artifact and will be unreachable on `master` after merge; and a companion archive entry whose `**occurrence table:**` heading is not followed by a blank line, causing GFM to render the table as prose.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `ibl5/docs/backlog/loop-engineering-backlog.md` (example) L70 — cited `0ca67f8b4` (squash artifact; unreachable post-merge) | yes | fixed this pass | fixed this pass |
| 2 | `ibl5/docs/backlog/archive/loop-engineering-backlog-archive.md` (example) L43 entry — `**occurrence table:**` not followed by blank line; GFM table renders as prose | yes | fixed this pass | fixed this pass |

**prevention_ladder:**
- rung 0 — no existing gate validates that SHAs cited in backlog entries resolve to reachable ancestors of HEAD.
- rung 1 — extending `bin/check-docs`: could warn on commit-SHA tokens in backlog prose whose SHA is not a reachable ancestor of HEAD. Feasible; low false-positive risk.
- rung 2 — a rule doc: "when filing a backlog entry, cite the PR number rather than a commit SHA; if a SHA is essential, confirm it is a reachable ancestor of HEAD at filing time." Cheaper and sufficient. Landing rung.
- rungs 3–5 — not applicable; the surface is backlog authoring, not PHP code or CI.

Landing rung: **2** — rule doc under `.claude/rules/` (or addendum to `.claude/rules/pr-body-claims.md` since both govern PR/backlog authoring quality).

**artifact destination:** `.claude/rules/` (addendum or new file, in-repo)

**provenance:** (discovered 2026-09-06 during #2131)

**Status (2026-09-06):** 📝 Note — fixed this pass (SHA replaced, blank line added); prevention filed.

---
