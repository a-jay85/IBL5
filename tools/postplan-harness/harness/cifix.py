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
    """Classify CI failures into green / rollup-only / actionable.

    Drops SIGNOFF_CHECKS names, then splits the rest into rollups and actionable
    names, preserving input order and de-duplicating. Returns:
    - ("green", []) when nothing remains after dropping signoff (includes failed == []).
    - ("rollup-only", [<rollup names>]) when only rollups remain.
    - ("actionable", [<non-rollup names>]) otherwise. Rollups are never in this list.
    """
    seen: set[str] = set()
    remaining: list[str] = []
    for name in failed:
        if name in SIGNOFF_CHECKS:
            continue
        if name not in seen:
            seen.add(name)
            remaining.append(name)

    if not remaining:
        return ("green", [])

    rollups = [n for n in remaining if n in ROLLUP_CHECKS]
    actionable = [n for n in remaining if n not in ROLLUP_CHECKS]

    if not actionable:
        return ("rollup-only", rollups)

    return ("actionable", actionable)


def failed_job_refs(checks: list[dict], names: list[str]) -> dict[str, tuple[str, str]]:
    """Map actionable check names to (run_id, job_id) from `gh pr checks` JSON.

    Input is the parsed `gh pr checks <pr> --json name,state,link` array. For each
    entry whose `name` is in `names`, apply _RUN_LINK to `link` and map
    name -> (run_id, job_id). An entry whose link does not match is omitted (a
    third-party status check has no Actions run).
    """
    name_set = set(names)
    result: dict[str, tuple[str, str]] = {}
    for entry in checks:
        name = entry.get("name", "")
        if name not in name_set:
            continue
        link = entry.get("link", "") or ""
        m = _RUN_LINK.search(link)
        if m:
            result[name] = (m.group(1), m.group(2))
    return result


def ci_fix_prompt(pr_number, attempt: int, names: list[str], log_paths: dict[str, str],
                  diff_path: str, prior: list[str]) -> str:
    """Build the Opus prompt for a CI fix attempt."""
    lines = [
        f"Fix the failing CI checks for PR #{pr_number}.",
        "",
        "Failing checks and their log files:",
    ]
    for name in names:
        if name in log_paths:
            lines.append(f"  - {name}: {log_paths[name]}")
        else:
            lines.append(f"  - {name}: (no Actions log: third-party check)")

    lines += [
        "",
        f"Diff path (origin/master...HEAD patch): {diff_path}",
        "",
        "Master is green. This PR's diff caused every failure listed. Find the root cause in the diff and fix it.",
        "",
        "Do NOT change a test's expected value, snapshot, baseline, fixture count or hardcoded total just to make it match new output. Change one only when the PR intentionally changed the thing it counts, and name the diff line that did so in your reply.",
        "",
        "If the failure is not caused by this diff (runner outage, network timeout, cancelled job), make NO edits and reply FLAKY.",
        "",
        GATE_EDIT_DENY_TEXT,
        "",
        "The following Bash commands are denied: git push, git commit, gh pr merge, gh pr review, gh api are denied; the harness commits and pushes.",
        "",
        f"This is attempt {attempt} of {MAX_CI_FIX_ATTEMPTS}.",
    ]

    if prior:
        lines.append("")
        lines.append("Previous attempts:")
        for p in prior:
            lines.append(f"  - {p}")

    return "\n".join(lines) + "\n"


def survivor_comment(survivors: list[str], attempts: list[str], flaky: bool) -> str:
    """Markdown PR comment body for surviving CI failures.

    No heading — post_review_summary(pr, title, body) prepends '## <title>'.
    Raises ValueError when survivors is empty.
    """
    if not survivors:
        raise ValueError("survivor_comment called with empty survivors list")

    lines = []
    for name in survivors:
        lines.append(f"- {name}")
    for audit in attempts:
        lines.append(f"- {audit}")
    lines.append("A human needs to look at these before merging.")
    return "\n".join(lines) + "\n"


def flaky_comment(names: list[str]) -> str:
    """Body only for a flaky-failure cleared by re-run.

    Export title: FLAKY_TITLE = "Phase 7 CI: flaky failure cleared by re-run".
    One bullet per name, and the line about no code change.
    """
    lines = []
    for name in names:
        lines.append(f"- {name}")
    lines.append(r"No code change was made. The failed jobs passed on a single `gh run rerun --failed`.")
    return "\n".join(lines) + "\n"
