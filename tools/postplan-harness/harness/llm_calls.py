"""Prompt builders for the retained bounded LLM calls outside Phase 4.

Each builder returns the smallest sufficient packet; the ClaudeCli adapter
byte-caps, single-turns, and validates every call.
"""
from __future__ import annotations

from .state import ArmDecision, Classification, PlanInfo


def pr_copy_prompt(slug: str, cls: Classification, plan: PlanInfo, plan_excerpt: str) -> str:
    """Commit/PR title + summary. Judgment retained: the feat-vs-chore GM test
    and a faithful summary of intent. Type rubric inlined from
    .claude/rules/auto-commit.md."""
    retro_block = ""
    if cls.retro_registry_row:
        retro_block = (
            "\nRETROSPECTIVE ROUTING — this branch adds a row to the `## Class registry` "
            "in `ibl5/docs/backlog/loop-engineering-backlog.md`, so it materializes a "
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
        "Classify by what the diff IS, never by desired merge outcome.\n\n"
        f"BRANCH: {slug}\n"
        f"CLASSIFICATION:\n{cls.summary()}\n"
        + retro_block
        + (f"\nPLAN EXCERPT (intent):\n{plan_excerpt[:4000]}\n" if plan.found else "")
        + "\nDIFF:\n" + cls.filtered_diff[:60000]
        + '\n\nNEVER write a "## Manual Testing" heading or any manual-testing checklist '
        'inside summary_md. The runner owns that section and appends it after you; '
        'duplicating it corrupts the arming gate.'
        + '\n\nReturn ONLY JSON: {"type": "chore", "title": "chore(scope): ...", '
        '"summary_md": "## Summary\\n- ..."}. Title <= 72 chars, starts with its type.'
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
        "must match the allowlist: `pytest`, `grep`, or `bin/(check|test)-<name>` — "
        "no interpreters (bash, python3, sh, env, node), no absolute paths, no `..`, "
        "no flags starting with -c/-e/--eval/--exec/-p/--plugin, and at most 8 "
        "elements. When in doubt, return hold.\n\n"
        f"CLASSIFICATION:\n{cls.summary()}\n\n"
        f"ROWS:\n{numbered}\n\n"
        'Return ONLY JSON: [{"n": 1, "hold": true}, {"n": 2, "probe": ["bin/test-x"]}].'
        " One entry per row, in order."
    )


def retrospective_prompt(slug: str, terminal: str, arm: ArmDecision | None,
                         findings_n: int, phase5: str | None) -> str:
    """Phase 9 — one bounded call replaces the open-ended reflection turn."""
    holds = "; ".join(c.reason or c.name for c in (arm.holds if arm else []))
    return (
        "Post-plan retrospective. Decide whether this run produced a durable, "
        "non-obvious lesson worth saving as a memory (most runs do NOT — routine "
        "shipping is not a lesson). Save only preventive process knowledge that would "
        "change a FUTURE run before it starts.\n\n"
        f"RUN: branch={slug} terminal={terminal} phase5={phase5} "
        f"surviving_findings={findings_n} holds=[{holds}]\n\n"
        'Return ONLY JSON: {"save": false} or {"save": true, "name": '
        '"<kebab-slug>", "body": "<the lesson, <=120 words>"}.'
    )
