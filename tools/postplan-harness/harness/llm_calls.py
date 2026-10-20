"""Prompt builders for the retained bounded LLM calls outside Phase 4.

Each builder returns the smallest sufficient packet; the ClaudeCli adapter
byte-caps, single-turns, and validates every call.
"""
from __future__ import annotations

import difflib
import re

from .classify import (FILES_CHANGED_BEGIN, FILES_CHANGED_END, MERGE_DIGEST_BEGIN,
                       MERGE_DIGEST_END)
from .state import ArmDecision, Classification, PlanInfo

# Probe allowlist sentence for manual_recheck_prompt.  Keep `at most 8 elements`
# on its own source line.
PROBE_ALLOWLIST_TEXT = (
    "`pytest`, `grep`, or `bin/(check|test)-<name>` — "
    "no interpreters (bash, python3, sh, env, node), no absolute paths, no `..`, "
    "no flags starting with -c/-e/--eval/--exec/-p/--plugin, "
    "and at most 8 elements."
)


def _gm_visible_rubric(cls: Classification) -> str:
    """One sentence the model can act on. Derived from Classification.has_gm_visible
    (classify._NON_RUNTIME denylist): the prompt states the verdict, the model never
    re-derives path rules. The harness retypes feat -> chore deterministically on a
    no-GM-visible diff (schemas.retype_tooling_feat), so this is copy quality, not the guard."""
    if cls.has_gm_visible:
        return ("GM-VISIBLE FILES: yes (HAS_GM_VISIBLE=True). At least one changed path is app "
                "or sim runtime code under ibl5/ or engine/. Apply the GM test above.\n")
    return ("GM-VISIBLE FILES: none (HAS_GM_VISIBLE=False). Every changed path is dev tooling, "
            "docs, tests, or build config (bin/, tools/, .claude/, .github/, ibl5/tests/, "
            "ibl5/docs/, markdown). The type MUST NOT be feat. Use chore, or fix/refactor/"
            "docs/test/ci when the diff is exactly that. The harness retypes feat to chore "
            "on this diff anyway.\n")


def pr_copy_prompt(slug: str, cls: Classification, plan: PlanInfo, plan_excerpt: str) -> str:
    """Commit/PR title + summary. Judgment retained: the feat-vs-chore GM test
    and a faithful summary of intent. Type rubric inlined from
    .claude/rules/auto-commit.md."""
    retro_block = ""
    if cls.retro_registry_row:
        retro_block = (
            "\nRETROSPECTIVE ROUTING — this branch adds a row to the `## Class registry` "
            "in `ibl5/docs/retrospective-class-registry.md`, so it materializes a "
            "`/post-plan` Phase 9 retrospective routing. The `summary_md` field MUST "
            "contain a `## Why this PR exists` section derivable from the registry row "
            "alone. Four required elements in that section:\n"
            "1. Origin defect — the `#<PR>` the row names and what it actually broke.\n"
            "2. The class — quoted from the row's `class:` field, framed as what got "
            "past planning rather than the single instance.\n"
            "3. Why that rung — the ladder stops at the first rung that can express the "
            "class; name each lower rung and say in one clause why it cannot express "
            "this class.\n"
            "4. What the routing now forces — the obligation placed on future plans.\n"
            "If the row's `prior:` field is not `--`, state explicitly that this is a "
            "recurrence of those PRs' class and that the routing moved one rung more "
            "mechanical.\n\n"
            f"RETROSPECTIVE REGISTRY ROW:\n{cls.retro_registry_row}\n"
        )
    return (
        "Write the commit/PR copy for this branch.\n\n"
        "TYPE RUBRIC — decision test: \"Would a league GM notice a new ability they "
        "didn't have before?\" Yes -> feat. Invisible to a GM (dev tooling, internal "
        "refactor, docs, tests, CI, dep bump) -> chore/fix/refactor/docs/test/perf/ci. "
        "Classify by what the diff IS, never by desired merge outcome.\n"
        + _gm_visible_rubric(cls)
        + f"\nBRANCH: {slug}\n"
        f"CLASSIFICATION:\n{cls.summary()}\n"
        + retro_block
        + (f"\nPLAN EXCERPT (intent):\n{plan_excerpt[:4000]}\n" if plan.found else "")
        + "\nDIFF:\n" + cls.filtered_diff[:60000]
        + '\n\nNEVER write a "## Manual Testing" heading or any manual-testing checklist '
        'inside summary_md. The runner owns that section and appends it after you; '
        'duplicating it corrupts the arming gate.'
        + '\n\nEnd summary_md with exactly one line: **Merge danger:** Door: <one-way|two-way> '
        '· Blast radius: <localized|module|site-wide|external> · Why: <20-200 chars naming '
        'what would be hard to reverse>. The runner derives Door and Blast radius mechanically '
        'and only ever raises them; your contribution is the Why text. Never write a second '
        'Merge danger line.'
        + '\n\nDirectly after the Merge danger line, write exactly one line: **How to revert:** '
        '<12-300 chars on one line: `git revert <merge-sha>` for a normal change, plus the extra '
        'step when the diff touches a migration, the prod sync, or config>. The runner swaps in '
        'a mechanical line when yours is missing or too short, or when the diff touches one of '
        'those paths. Never write a second How to revert line.'
        + '\n\nA bare `#N` links to IBL5 PR/issue N. Cite a backlog issue as '
        '`a-jay85/IBL5-backlog#N`, never as a bare `#N`.'
        + '\n\nReturn ONLY JSON with exactly these four keys: {"type": "chore", '
        '"title": "chore(scope): ...", "commit_subject": "chore(scope): ...", '
        '"summary_md": "## Summary\\n- ..."}.\n'
        "`title` is the PR TITLE: it describes the PURPOSE of the branch -- why the "
        "change exists and what it enables.\n"
        "`commit_subject` is the GIT COMMIT SUBJECT: it describes what the diff "
        "PHYSICALLY CONTAINS, scoped to the files and behaviour actually changed "
        "above. Type it against the diff, not against the intent.\n"
        "Both strings must be <= 72 chars and must each start with their own "
        "conventional-commit type. They are two separate artifacts and must NOT be "
        "identical -- write commit_subject from the diff, then title from the purpose."
    )


def pr_body_check_prompt(body: str, name_status: str) -> str:
    """Body-vs-diff verification prompt. Carries check 4 / 4a verbatim from
    _plan-fidelity-review.md so this call and Phase 6.5 cannot drift apart."""
    return (
        "check the PR body's **hand-written** claims (the Scope prose, tests added, "
        "counts, \"no behavior change\") against the actual diff. A hand-written body "
        "claim the diff contradicts is a **finding**, and it is **blocking**: it must be "
        "corrected before the PR is endorsed as ready. "
        "Never endorse readiness on the strength of the body alone. "
        "**Exclude the machine-generated files-changed block** delimited by "
        "`<!-- files-changed:begin -->` / `<!-- files-changed:end -->`: "
        "`SKILL.md` runtime Phase 5.9 regenerates that block from "
        "`git diff --name-status origin/master...HEAD` immediately before this phase, "
        "so it is generated data reconciled by construction, not a claim — a drift there "
        "is a Phase 5.9 defect, never a body finding, and reporting it is a false "
        "`NOT READY`. "
        "Also exclude the machine-generated merge-digest block delimited by "
        "`<!-- merge-digest:begin -->` / `<!-- merge-digest:end -->`: the runner writes "
        "it from the previous run's sticky verdict, so it is not a claim. "
        "The block has already been removed from the text you are shown, so never "
        "re-emit it.\n\n"
        "**Named-constant volumes.** Within this same check, and **inheriting its "
        "blocking verdict**: if the diff introduces or edits a class carrying a named "
        "`EXPECTED` constant array — or any equivalent named-constant row count, deletion "
        "count, or table list — read those constants out of the code and confirm every "
        "volume, count, and table/suffix name in the PR body Summary agrees with them. "
        "Body prose is authored before implementation and hand-patched during it, so "
        "these are the numbers that silently rot (PR #2001 understated a deletion volume "
        "~23x and named the wrong backup-table suffix; both survived to Phase 6). "
        "Read the constant, do not trust the body's number. A disagreement is a blocking "
        "finding, corrected in the body before the PR is endorsed ready.\n\n"
        'The runner owns the "## Manual Testing" section. It has been removed from the '
        "text you are shown. Never write a \"## Manual Testing\" heading and never emit "
        "a manual-testing checklist in corrected_body.\n\n"
        "The file list below is names-only and may be incomplete. If a file is absent "
        "from the name-status list, do not flag claims about it as unsupported.\n\n"
        "BODY PROSE:\n" + body[:40000]
        + "\n\nNAME-STATUS:\n" + name_status[:20000]
        + '\n\nReturn ONLY JSON with exactly these two keys: '
        '{"corrected_body": "<the full revised body prose>", '
        '"findings": ["<one sentence per disagreement you corrected>"]}. '
        "When the body already agrees with the diff, return corrected_body unchanged "
        "and findings as an empty array."
    )


# --- Body-only fidelity recheck (Phase 5.5 body-only rounds) -----------------

FILES_CHANGED_BLOCK_RE = re.compile(
    re.escape(FILES_CHANGED_BEGIN) + r".*?" + re.escape(FILES_CHANGED_END), re.S)
MERGE_DIGEST_BLOCK_RE = re.compile(
    re.escape(MERGE_DIGEST_BEGIN) + r".*?" + re.escape(MERGE_DIGEST_END), re.S)
_DIFF_SECTION_RE = re.compile(r"^diff --git ", re.M)
_DIFF_PATHS_RE = re.compile(r"^diff --git a/(\S+) b/(\S+)")

# Per-field byte caps for pr_body_recheck_prompt. They sum under MAX_PROMPT_BYTES
# (adapters/llm.py) with room for the fixed prose; ClaudeCli's own cap is a backstop.
_RECHECK_CAPS = {"findings": 12_000, "hunks": 12_000, "old_body": 24_000,
                 "new_body": 24_000, "name_status": 8_000, "evidence": 36_000}


def _cap(s: str, n: int) -> str:
    raw = s.encode()
    if len(raw) <= n:
        return s
    return raw[:n].decode(errors="ignore") + "\n[TRUNCATED]"


def strip_machine_blocks(body: str) -> str:
    """Remove the two runner-generated blocks check 4 excludes; everything else stays."""
    return MERGE_DIGEST_BLOCK_RE.sub("", FILES_CHANGED_BLOCK_RE.sub("", body or ""))


def body_hunks(old_body: str, new_body: str) -> list[dict]:
    """Numbered added/changed hunks, old -> new, after strip_machine_blocks on both.

    One hunk per difflib 'insert' or 'replace' opcode, in document order, ids H1..Hn:
    {"id": "H1", "op": "insert"|"replace", "old": [lines], "new": [lines]}.
    'delete' and 'equal' produce nothing: removing a claim cannot add an unsupported one.
    """
    old_lines = strip_machine_blocks(old_body).splitlines()
    new_lines = strip_machine_blocks(new_body).splitlines()
    sm = difflib.SequenceMatcher(None, old_lines, new_lines, autojunk=False)
    hunks: list[dict] = []
    for op, i1, i2, j1, j2 in sm.get_opcodes():
        if op not in ("insert", "replace"):
            continue
        hunks.append({"id": f"H{len(hunks) + 1}", "op": op,
                      "old": old_lines[i1:i2], "new": new_lines[j1:j2]})
    return hunks


def diff_sections_for(diff: str, paths: set[str], cap: int = 40_000) -> str:
    """Per-file sections of a unified diff whose a/ or b/ path is in `paths`.

    Sections keep diff order and are byte-capped. When no section matches, the first
    `cap` bytes of the whole diff stand in. Pure string work.
    """
    diff = diff or ""
    starts = [m.start() for m in _DIFF_SECTION_RE.finditer(diff)]
    picked: list[str] = []
    for k, start in enumerate(starts):
        end = starts[k + 1] if k + 1 < len(starts) else len(diff)
        section = diff[start:end]
        m = _DIFF_PATHS_RE.match(section)
        if m and (m.group(1) in paths or m.group(2) in paths):
            picked.append(section)
    return _cap("".join(picked) if picked else diff, cap)


def pr_body_recheck_prompt(findings: list[str], hunks: list[dict], old_body: str,
                           new_body: str, name_status: str, evidence: str) -> str:
    """Single-turn, no-tools recheck of a PR body after a body-only remediation round.

    Every prior finding is enumerated F1..Fn and every added or changed body hunk
    H1..Hm; schemas.validate_body_recheck demands each id back exactly once.
    """
    f_block = "\n".join(f"F{i}: {text}" for i, text in enumerate(findings, 1))
    h_parts = []
    for h in hunks:
        lines = [f"- {ln}" for ln in h["old"]] + [f"+ {ln}" for ln in h["new"]]
        h_parts.append(f"{h['id']} ({h['op']}):\n" + "\n".join(lines))
    h_block = "\n\n".join(h_parts) if h_parts else "(none)"
    return (
        "You are rechecking a PR body after a body-only remediation. The code tree is "
        "unchanged since the prior review and every prior finding was about the PR body. "
        "You have no tools; judge only from this packet.\n\n"
        "A hand-written body claim the diff evidence contradicts is unresolved or "
        "unsupported. Counts, file names, test names and \"no behavior change\" claims "
        "must be confirmed against the evidence shown, never assumed.\n\n"
        "A claim about state the diff cannot show (an issue number, a deploy, a sibling "
        "repo, \"all tests pass locally\") is unsupported unless the body line itself "
        "cites evidence that is visible in this packet. You cannot run commands, so a "
        "bare claim of that kind is unsupported.\n\n"
        "When the packet does not let you ground a claim, answer `unresolved` / "
        "`unsupported`. A wrong `resolved` arms a merge; a wrong `unresolved` only costs "
        "a fuller review.\n\n"
        "## PRIOR FINDINGS\n" + _cap(f_block, _RECHECK_CAPS["findings"])
        + "\n\n## ADDED OR CHANGED BODY HUNKS\n" + _cap(h_block, _RECHECK_CAPS["hunks"])
        + "\n\n## OLD BODY\n" + _cap(old_body or "", _RECHECK_CAPS["old_body"])
        + "\n\n## NEW BODY\n" + _cap(new_body or "", _RECHECK_CAPS["new_body"])
        + "\n\n## CHANGED FILES (git diff --name-status)\n"
        + _cap(name_status or "", _RECHECK_CAPS["name_status"])
        + "\n\n## DIFF EVIDENCE\n" + _cap(evidence or "", _RECHECK_CAPS["evidence"])
        + "\n\nReply with ONLY a JSON object: "
        '{"findings": [{"id": "F1", "status": "resolved"|"unresolved", '
        '"evidence": "<one sentence citing the body line and the diff line>"}], '
        '"hunks": [{"id": "H1", "status": "supported"|"unsupported", '
        '"evidence": "<one sentence>"}]}. '
        "Include every F-id and every H-id exactly once. No other keys, no prose. "
        'When the hunk list is (none), "hunks": [] is required.'
    )


def safety_verdict_prompt(cls: Classification, title: str, arm_preview: ArmDecision) -> str:
    """Condition (9) bounded verdict — may only ADD holds, never release one.
    Surface list from _phase-6.5-arm-auto-merge.md condition (9)."""
    other = "\n".join(f"({c.number}) {c.name}: {'BLOCKED — ' + c.reason if c.blocked else 'pass'}"
                      for c in arm_preview.conditions if c.number != 9)
    return (
        "You are the PR-time safety verdict for an auto-merge arming decision. "
        "Deterministic checks already ran (results below). Your ONLY job: name any "
        "ADDITIONAL hold from this closed list of surfaces the deterministic pass "
        "cannot judge —\n"
        "- security surface (SQL construction, POST/form endpoint, auth/authz-gated "
        "route, user-facing output rendering) whose defense looks absent in the diff;\n"
        "- destructive or schema-tightening migration semantics beyond the regex "
        "(e.g. NOT NULL added to a populated column, data-lossy type narrowing);\n"
        "- new or redesigned user-visible UI/UX needing human visual judgment;\n"
        "- a property only subjective human judgment can confirm.\n"
        "Do NOT re-litigate the deterministic conditions. Only hold when the DIFF "
        "ITSELF introduces one of the four surfaces above — never because you cannot "
        "personally verify a factual claim the change makes. Docs-only, backlog-status, "
        "test-only, and internal-tooling changes need NO hold. Most PRs need NO hold; "
        "an empty list is the normal answer.\n\n"
        f"PR TITLE: {title}\nCLASSIFICATION:\n{cls.summary()}\n"
        f"DETERMINISTIC CONDITIONS:\n{other}\n\nDIFF:\n" + cls.filtered_diff[:60000]
        + '\n\nReturn ONLY JSON: {"holds": ["<one-line reason>", ...]} or {"holds": []}.'
    )


def manual_classify_prompt(body_section: str, cls: Classification) -> str:
    """Phase 6 — classify remaining manual-testing steps (plan-blind runs only;
    a found plan's matrix rows are deterministic)."""
    return (
        "Classify each remaining manual-testing step from a PR body into exactly one "
        "category: cli-executable (verifiable now by a shell command), phpunit, "
        "api-test, e2e, visual-regression, or truly-manual (needs subjective human "
        "judgment — e.g. 'does this look right').\n\n"
        f"CLASSIFICATION FLAGS:\n{cls.summary()}\n\n"
        f"MANUAL TESTING SECTION:\n{body_section[:8000]}\n\n"
        'Return ONLY JSON: [{"step": "<verbatim step>", "category": "...", '
        '"rationale": "<one line>"}]. Empty section -> [].'
    )


def manual_recheck_prompt(rows: list, cls: "Classification") -> str:
    """Phase 6 re-check — ask the model whether each truly-manual row can be
    verified by a concrete command, or must remain held for a human reviewer.

    The model may reply with either ``{"n": N, "hold": true}`` (subjective, keep
    the row in the checklist) or ``{"n": N, "probe": ["bin/test-x", "arg"]}`` (an
    argv list, never a shell string).  Argv[0] must match the allowlist.
    """
    numbered = "\n".join(f"{r.number}. {r.text}" for r in rows)
    return (
        "Classify each manual-testing row as either held-for-human or runnable by "
        "a concrete check command.\n\n"
        "A row is held when it requires subjective human judgment (visual appearance, "
        "UI correctness, 'does this look right'). A row is runnable only when a "
        "real command that already exists in the repo can verify it — not a command "
        "you invent.\n\n"
        "When a row is runnable, provide the argv as a JSON list. The first element "
        f"must match the allowlist: {PROBE_ALLOWLIST_TEXT} When in doubt, return hold.\n\n"
        f"CLASSIFICATION:\n{cls.summary()}\n\n"
        f"ROWS:\n{numbered}\n\n"
        'Return ONLY JSON: [{"n": 1, "hold": true}, {"n": 2, "probe": ["bin/test-x"]}].'
        " One entry per row, in order."
    )


def fidelity_notes_prompt(verdict_text: str) -> str:
    """Phase 5.5 — extract non-blocking notes from a READY WITH NOTES verdict for backlog filing.

    Notes are typed so `fidelity.extract_notes` can keep only `followup`. The reviewer
    writes for whoever merges the PR, so most of what it calls a note is consumed at the
    merge button and is dead weight in the backlog afterwards — deviations it already
    blessed, PR-copy nits, and its own bookkeeping. Measured 2026-09-19: of the 156
    issues filed since PR #2275, about two thirds were one of those three.
    """
    return (
        "Extract the non-blocking notes from this plan-fidelity verdict.\n\n"
        "These become backlog issues read weeks after the PR merges, so file a note ONLY "
        "when it names a concrete code change that is still worth doing then.\n\n"
        "Type every note you find with \"kind\":\n"
        "  followup          — names a specific code change still worth making after this "
        "PR merges: a bug, a missing or self-satisfying test, a fragile assertion, an "
        "unhandled case.\n"
        "  plan-deviation-ok — the code differs from the plan and the reviewer already "
        "settled it (\"better\", \"a better route\", \"harmless\", \"cosmetic\", \"fine\", "
        "\"not a finding\", \"correct, not a miss\"). Nothing is left to do.\n"
        "  pr-copy           — about the PR title, body, summary, digest, manual-testing "
        "section or commit message wording. Moot once the PR merges.\n"
        "  process           — the reviewer narrating its own review: which checks it ran "
        "or could not run, queueing another review, a missing plan, restating that "
        "something is consistent or matches, \"verify before merge\", a bump a gate "
        "forced.\n"
        "  done-in-pr        — the verdict says it was already fixed, corrected, "
        "remediated or recorded in this PR (including Phase 6.5). Nothing is left.\n"
        "  plan-adherence    — the only reason to act is that the code does not match the "
        "plan's wording (\"revert because the plan said leave it\", \"plan named a "
        "different test\"), with no defect a user or developer would hit.\n"
        "  nit               — cosmetic only: whitespace, docblock or comment wording, "
        "an unused local in a test, assertion-style variance.\n"
        "  plan-artifact     — the change is to the plan file itself: a Verification Matrix "
        "row, a plan step, the Critical Files list. The plan is discarded when the PR "
        "merges, so nothing is left to do.\n\n"
        "Return a JSON array of objects with:\n"
        "  - \"title\": imperative sentence, under 70 characters\n"
        "  - \"detail\": two or three sentences a reader can act on weeks later without "
        "opening the PR: name the file (and function or test when the verdict gives one), "
        "what is wrong, and the change to make\n"
        "  - \"kind\": one of the eight above\n\n"
        "Only `followup` is filed; the rest are discarded. When torn, do NOT call it "
        "`followup` — a dropped note costs nothing, a wrongly filed one is noise a human "
        "triages by hand. A note that only describes a difference, naming no remaining "
        "work, is never `followup`.\n\n"
        "Cover only the notes, not blocking findings. Return [] if there are none.\n\n"
        f"VERDICT:\n{verdict_text[:8000]}\n\n"
        'Return ONLY JSON: [{"title": "...", "detail": "...", "kind": "followup"}] or [].'
    )


def retrospective_prompt(slug: str, terminal: str, arm: ArmDecision | None,
                         findings_n: int, phase5: str | None, fidelity: dict | None = None) -> str:
    """Phase 9 — one bounded call replaces the open-ended reflection turn."""
    holds = "; ".join(c.reason or c.name for c in (arm.holds if arm else []))
    fid = (f"{fidelity.get('verdict_1') or 'none'}->{fidelity.get('verdict_2') or '-'} "
           f"remediated={bool(fidelity.get('remediation_sha'))}") if fidelity else "none"
    return (
        "Post-plan retrospective. Decide whether this run produced a durable, "
        "non-obvious lesson worth saving as a memory (most runs do NOT — routine "
        "shipping is not a lesson). Save only preventive process knowledge that would "
        "change a FUTURE run before it starts.\n\n"
        f"RUN: branch={slug} terminal={terminal} phase5={phase5} "
        f"surviving_findings={findings_n} holds=[{holds}] fidelity={fid}\n\n"
        'Return ONLY JSON: {"save": false} or {"save": true, "name": '
        '"<kebab-slug>", "body": "<the lesson, <=120 words>"}.'
    )
