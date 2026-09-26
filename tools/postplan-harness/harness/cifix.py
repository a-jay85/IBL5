from __future__ import annotations
import re
from .adapters.llm import MODEL_MAP
from .fidelity import GATE_EDIT_DENY_TEXT, REMEDIATION_ALLOWED_TOOLS, REMEDIATION_DENIED_TOOLS

CI_FIX_MODEL = "opus"                       # alias; call_tooled allowlists it
CI_FIX_MODEL_ID = MODEL_MAP[CI_FIX_MODEL]   # "claude-opus-5-5", used in the audit line
MAX_CI_FIX_ATTEMPTS = 3
CI_FIX_COMMIT_MSG = "fix: address Phase 7 CI failures (attempt {n})"
SIGNOFF_CHECKS = frozenset({"human-signoff"})
# Jobs whose only failure mode is "an upstream job failed" (tests.yml `gate`:
# if: always(), needs: [...], exit 1 on any upstream failure). Never a fix target.
ROLLUP_CHECKS = frozenset({"Tests and Analysis"})
CI_FIX_ALLOWED_TOOLS = REMEDIATION_ALLOWED_TOOLS
CI_FIX_DENIED_TOOLS = REMEDIATION_DENIED_TOOLS
_RUN_LINK = re.compile(r"/actions/runs/(\d+)/job/(\d+)")

SURVIVOR_TITLE = "Phase 7 CI fix loop: failures remain"
FLAKY_TITLE = "Phase 7 CI: flaky failure cleared by re-run"


def triage(failed: list[str]) -> tuple[str, list[str]]:
    """Classify a list of failed check names into an outcome and an action list.

    Drops SIGNOFF_CHECKS names, then splits the rest into rollups and actionable
    names, preserving input order and de-duplicating.

    Returns:
        ("green", [])           — nothing remains after dropping signoff checks.
        ("rollup-only", [...])  — only rollup checks remain.
        ("actionable", [...])   — at least one non-rollup, non-signoff check.
                                  Rollups are never in this list.
    """
    seen: set[str] = set()
    deduped: list[str] = []
    for name in failed:
        if name not in seen:
            seen.add(name)
            deduped.append(name)

    # Drop signoff names entirely.
    remaining = [n for n in deduped if n not in SIGNOFF_CHECKS]

    if not remaining:
        return ("green", [])

    rollups = [n for n in remaining if n in ROLLUP_CHECKS]
    actionable = [n for n in remaining if n not in ROLLUP_CHECKS]

    if not actionable:
        return ("rollup-only", rollups)

    return ("actionable", actionable)


def failed_job_refs(checks: list[dict], names: list[str]) -> dict[str, tuple[str, str]]:
    """Map each named failing check to its (run_id, job_id) pair.

    Input is the parsed ``gh pr checks <pr> --json name,state,link`` array.
    For each entry whose ``name`` is in ``names``, apply ``_RUN_LINK`` to ``link``
    and map ``name -> (run_id, job_id)``.  An entry whose link does not match is
    omitted (a third-party status check has no Actions run).
    """
    result: dict[str, tuple[str, str]] = {}
    for entry in checks:
        name = entry.get("name", "")
        if name not in names:
            continue
        link = entry.get("link", "") or ""
        m = _RUN_LINK.search(link)
        if m is None:
            continue
        result[name] = (m.group(1), m.group(2))
    return result


def ci_fix_prompt(
    pr_number,
    attempt: int,
    names: list[str],
    log_paths: dict[str, str],
    diff_path: str,
    prior: list[str],
) -> str:
    """Build the Opus prompt for a CI fix attempt.

    The prompt includes:
    - failing check names with log file paths (never log text)
    - the diff path (origin/master...HEAD patch)
    - a root-cause instruction
    - anti-pattern clause forbidding blind expectation edits
    - FLAKY reply instruction
    - gate-edit deny text and denied-Bash sentence
    - attempt counter and prior-attempt list when non-empty
    """
    lines: list[str] = []

    lines.append(f"CI fix pass — PR #{pr_number}")
    lines.append(f"This is attempt {attempt} of {MAX_CI_FIX_ATTEMPTS}.")
    lines.append("")
    lines.append(
        "Master is green. This PR's diff caused every failure listed. "
        "Find the root cause in the diff and fix it."
    )
    lines.append("")
    lines.append("Failing checks and their log files (Read the file for details):")
    for name in names:
        if name in log_paths:
            lines.append(f"  - {name}: {log_paths[name]}")
        else:
            lines.append(f"  - {name}: (no Actions log: third-party check)")
    lines.append("")
    lines.append(f"Diff path (origin/master...HEAD patch): {diff_path}")
    lines.append("")
    lines.append(
        "Do NOT change a test's expected value, snapshot, baseline, fixture count or "
        "hardcoded total just to make it match new output. Change one only when the PR "
        "intentionally changed the thing it counts, and name the diff line that did so "
        "in your reply."
    )
    lines.append("")
    lines.append(
        "If the failure is not caused by this diff (runner outage, network timeout, "
        "cancelled job), make NO edits and reply FLAKY."
    )
    lines.append("")
    lines.append(GATE_EDIT_DENY_TEXT)
    lines.append(
        "The following Bash commands are DENIED and must not be attempted: "
        "`git push`, `git commit`, `gh pr merge`, `gh pr review`, `gh api`. "
        "The harness commits and pushes."
    )
    lines.append("")

    if prior:
        lines.append("Previous attempts:")
        for p in prior:
            lines.append(f"  - {p}")
        lines.append("")

    return "\n".join(lines)


def survivor_comment(survivors: list[str], attempts: list[str], flaky: bool) -> str:
    """Markdown PR comment body for a CI fix loop that still has failing checks.

    The caller prepends a heading via ``post_review_summary(pr, SURVIVOR_TITLE, body)``.
    Raises ``ValueError`` when ``survivors`` is empty — the caller only posts on survivors.
    """
    if not survivors:
        raise ValueError("survivor_comment called with empty survivors list")

    lines: list[str] = []

    lines.append("Checks still failing after all fix attempts:")
    for name in survivors:
        lines.append(f"- {name}")

    if attempts:
        lines.append("")
        lines.append("Fix attempt audit:")
        for audit in attempts:
            lines.append(f"- {audit}")

    lines.append("")
    lines.append("A human needs to look at these before merging.")

    return "\n".join(lines)


def flaky_comment(names: list[str]) -> str:
    """Markdown PR comment body for a flaky-failure that cleared on re-run.

    The caller prepends a heading via ``post_review_summary(pr, FLAKY_TITLE, body)``.
    """
    lines: list[str] = []

    for name in names:
        lines.append(f"- {name}")

    lines.append("")
    lines.append(
        "No code change was made. "
        "The failed jobs passed on a single `gh run rerun --failed`."
    )

    return "\n".join(lines)
