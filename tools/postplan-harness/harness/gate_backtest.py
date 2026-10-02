"""Pure decision logic for backtesting a new ship-pipeline gate against merged history.

No subprocess, network, or clock calls live here (ADR: gate-backtest-before-merge). The I/O
shell is `gate_backtest_replay.py`. This module holds the gate detector, the replay-spec
registry, the ground-truth classifier, the exit-code mapping, and (Phase 4) the verdict table
and PR-body block.

Hooks live under `~/.claude/hooks/`, outside the repo tree, so no PR diff path ever matches
one. The detector therefore never reports a hook.
"""
from __future__ import annotations

import fnmatch
import re
import shlex
from dataclasses import dataclass
from datetime import datetime, timedelta

ALLOWED_PLACEHOLDERS = frozenset({"base", "head", "diff_file", "plan_file", "body_file", "tree"})
_PLACEHOLDER_RE = re.compile(r"\{([^{}]*)\}")
HEADER_SCAN_LINES = 40
FIX_TITLE_RE = re.compile(r"^fix(\([^)]*\))?!?:")
TRUTH_WINDOW = timedelta(hours=48)


@dataclass(frozen=True)
class ReplaySpec:
    argv: tuple[str, ...]                 # tokens after the gate path; may hold placeholders
    flag_exits: frozenset[int] = frozenset({1})
    ok_exits: frozenset[int] = frozenset({0})
    flag_stdout_re: str = ""              # non-empty: a matching stdout line is a flag
    env: tuple[tuple[str, str], ...] = ()
    needs_plan: bool = False


@dataclass(frozen=True)
class GateChange:
    path: str
    kind: str        # "check-script" | "lib-gate" | "transitive" | "ci-workflow" | "arming-harness"
    state: str       # "replayable" | "not-replayable" | "unspecified" | "removed"
    spec: ReplaySpec | None
    reason: str


@dataclass(frozen=True)
class HistoricalPR:
    number: int
    title: str
    merge_sha: str
    parent_count: int
    merged_at: datetime        # tz-aware UTC
    head_ref: str
    files: tuple[str, ...]


@dataclass(frozen=True)
class Truth:
    state: str                 # "repaired" | "clean" | "unsettled"
    repaired_by: tuple[int, ...] = ()


@dataclass(frozen=True)
class ReplayResult:
    pr: int
    gate: str
    outcome: str     # "flag" | "pass" | "error" | "timeout" | "skipped"
    detail: str      # first flagged stdout line, rc, or skip reason, max 160 chars


# --- Replay-spec registry --------------------------------------------------------------------

_SINCE = ReplaySpec(argv=("--since={base}",))
_TREE = ReplaySpec(argv=())
_PLAN = ReplaySpec(argv=("{plan_file}",), needs_plan=True)

_PR_STATE = "reads live PR state through gh --pr"

# A string value is a not-replayable reason. Only an entry here can mark a script
# not-replayable, so the choice shows up in a reviewed diff.
REPLAY_SPECS: dict[str, ReplaySpec | str] = {
    # Range-aware: replay over the historical PR's own range.
    "bin/check-docs": _SINCE,
    "bin/check-prose": _SINCE,
    "bin/check-numbering": _SINCE,
    "bin/check-destructive-migrations": _SINCE,
    # Whole-tree scans with no required argument.
    "bin/check-claude-dir-placement": _TREE,
    "bin/check-composite-contracts": _TREE,
    "bin/check-digest-prose": _TREE,
    "bin/check-e2e-fa-offers-owner": _TREE,
    "bin/check-e2e-mutator-isolation": _TREE,
    "bin/check-playwright-pinning": _TREE,
    "bin/check-registry-trigger-rows": _TREE,
    "bin/check-rules-byte-budget": _TREE,
    "bin/check-skill-arguments": _TREE,
    "bin/check-workflow-checkout": _TREE,
    # Plan-file gates: the plan resolves from the historical PR's branch name.
    "bin/check-plan": _PLAN,
    "bin/check-plan-staleness": _PLAN,
    # PR-number-aware: today's PR state is not the state at merge time.
    "bin/check-hot-files": _PR_STATE,
    "bin/check-e2e-hygiene": _PR_STATE,
    "bin/check-phpunit-hygiene": _PR_STATE,
    "bin/check-pr-collisions": _PR_STATE,
    "bin/check-pr-manual-testing": _PR_STATE,
    "bin/check-post-merge-recipe": _PR_STATE,
    "bin/check-master-ci-green": "reads live CI state through gh",
    "bin/check-pr-checks-green": "reads live CI state through gh",
    # Required arguments or services that fit no placeholder.
    "bin/check-boxscore-schedule": "needs a live database",
    "bin/check-column-rename-sweep": "needs a columns file or live database credentials",
    "bin/check-e2e-fixture-drift": "needs a snapshot outfile and a live database",
    "bin/check-old-code-compat": "needs --old-tree and a live database",
    "bin/check-orphan-css": "crawls a running app",
    # Exit 0 means "ran"; flags come from stdout lines. Exit 2 lands in the error bucket.
    "bin/lib/plan-matrix-assertions": ReplaySpec(
        argv=("{plan_file}", "{diff_file}"),
        flag_exits=frozenset(),
        flag_stdout_re=r"^UNREALISED-ASSERTION:",
        env=(("MATRIX_ASSERT_ROOT", "{tree}"),),
        needs_plan=True,
    ),
}

_ARMING_PATHS = (
    "tools/postplan-harness/harness/armable.py",
    "bin/lib/pr-armable.sh",
    ".claude/skills/post-plan/_phase-6.5-arm-auto-merge.md",
)


# --- Spec resolution -------------------------------------------------------------------------

_HEADER_ARGV_RE = re.compile(r"^\s*#\s*gate-backtest-argv:(.*)$")
_HEADER_FLAG_RE = re.compile(r"^\s*#\s*gate-backtest-flag:\s*(.*)$")
_HEADER_OPT_OUT_RE = re.compile(r"^\s*#\s*gate-backtest:\s*not-replayable\b\s*(.*)$")


def _bad_placeholders(tokens: list[str]) -> list[str]:
    bad: list[str] = []
    for tok in tokens:
        for name in _PLACEHOLDER_RE.findall(tok):
            if name not in ALLOWED_PLACEHOLDERS and "{" + name + "}" not in bad:
                bad.append("{" + name + "}")
    return bad


def _parse_header(text: str) -> tuple[str, ReplaySpec | None, str] | None:
    """Return a resolution when the candidate file carries a header, else None."""
    argv_tokens: list[str] | None = None
    flag_exits = frozenset({1})
    flag_re = ""
    for line in text.splitlines()[:HEADER_SCAN_LINES]:
        m = _HEADER_OPT_OUT_RE.match(line)
        if m:
            return ("unspecified", None,
                    "self-declared not replayable: " + m.group(1).strip())
        m = _HEADER_ARGV_RE.match(line)
        if m and argv_tokens is None:
            try:
                argv_tokens = shlex.split(m.group(1))
            except ValueError as exc:
                return ("unspecified", None, f"unparseable gate-backtest-argv header: {exc}")
            continue
        m = _HEADER_FLAG_RE.match(line)
        if m:
            spec_text = m.group(1).strip()
            if spec_text.startswith("exit="):
                try:
                    flag_exits = frozenset(int(x) for x in spec_text[5:].split(",") if x.strip())
                except ValueError:
                    return ("unspecified", None, "bad gate-backtest-flag exit list")
            elif spec_text.startswith("stdout="):
                flag_re = spec_text[7:]
                try:
                    re.compile(flag_re)
                except re.error:
                    return ("unspecified", None, "bad gate-backtest-flag stdout regex")
                flag_exits = frozenset()
    if argv_tokens is None:
        return None
    bad = _bad_placeholders(argv_tokens)
    if bad:
        return ("unspecified", None, "unknown placeholder in header argv: " + ", ".join(bad))
    spec = ReplaySpec(argv=tuple(argv_tokens), flag_exits=flag_exits, flag_stdout_re=flag_re,
                      needs_plan=any("{plan_file}" in t for t in argv_tokens))
    return ("replayable", spec, "header")


def resolve_spec(path: str, text: str) -> tuple[str, ReplaySpec | None, str]:
    """Header in the candidate file wins, then the built-in registry, then unspecified."""
    header = _parse_header(text)
    if header is not None:
        return header
    entry = REPLAY_SPECS.get(path)
    if isinstance(entry, str):
        return ("not-replayable", None, entry)
    if entry is not None:
        return ("replayable", entry, "registry")
    return ("unspecified", None,
            "no replay spec: add a '# gate-backtest-argv:' header")


# --- Detector --------------------------------------------------------------------------------

def _is_check_script(path: str) -> bool:
    return fnmatch.fnmatchcase(path, "bin/check-*") and "/" not in path[len("bin/"):]


def detect_gate_changes(changed, read_candidate, check_sources) -> list[GateChange]:
    """Classify a diff's changed paths into gate changes. `[]` means no gate was touched.

    changed: (status, path) pairs from `git diff --name-status`; a rename carries its new path.
    read_candidate(path) -> text of the candidate branch's file, or None.
    check_sources: every `bin/check-*` path on the candidate tree mapped to its text.
    """
    out: dict[str, GateChange] = {}

    def add(change: GateChange) -> None:
        out.setdefault(change.path, change)

    def resolved(path: str, kind: str, text: str | None) -> GateChange:
        state, spec, reason = resolve_spec(path, text or "")
        return GateChange(path, kind, state, spec, reason)

    for status, path in changed:
        removed = status.startswith("D")
        if _is_check_script(path):
            if removed:
                add(GateChange(path, "check-script", "removed", None, "gate deleted"))
            else:
                add(resolved(path, "check-script", read_candidate(path)))
        elif path in REPLAY_SPECS and path.startswith("bin/lib/"):
            if removed:
                add(GateChange(path, "lib-gate", "removed", None, "gate deleted"))
            else:
                add(resolved(path, "lib-gate", read_candidate(path)))
        elif fnmatch.fnmatchcase(path, ".github/workflows/*.yml"):
            state = "removed" if removed else "not-replayable"
            reason = "gate deleted" if removed else "CI workflow: needs a GitHub runner event"
            add(GateChange(path, "ci-workflow", state, None, reason))
        elif path in _ARMING_PATHS:
            state = "removed" if removed else "not-replayable"
            reason = "gate deleted" if removed else "arming condition: reads live run state"
            add(GateChange(path, "arming-harness", state, None, reason))
        elif path.startswith("bin/lib/") and not removed:
            needle = path[len("bin/"):]
            for check_path in sorted(check_sources):
                if needle in check_sources[check_path]:
                    add(resolved(check_path, "transitive", check_sources[check_path]))
    return list(out.values())


# --- Placeholder expansion and exit mapping --------------------------------------------------

def expand_argv(spec: ReplaySpec, ctx: dict[str, str]) -> list[str]:
    """Per-token placeholder replacement. The result is an argv list and is never shell-parsed."""
    out: list[str] = []
    for token in spec.argv:
        for name in ALLOWED_PLACEHOLDERS:
            if name in ctx:
                token = token.replace("{" + name + "}", ctx[name])
        out.append(token)
    return out


def expand_env(spec: ReplaySpec, ctx: dict[str, str]) -> dict[str, str]:
    env: dict[str, str] = {}
    for key, value in spec.env:
        for name in ALLOWED_PLACEHOLDERS:
            if name in ctx:
                value = value.replace("{" + name + "}", ctx[name])
        env[key] = value
    return env


def classify_exit(spec: ReplaySpec, rc: int, stdout: str) -> tuple[str, str]:
    """Map a replay's exit code and stdout to (outcome, detail). Exit 2 is an error, never a pass."""
    if spec.flag_stdout_re and rc in spec.ok_exits:
        pattern = re.compile(spec.flag_stdout_re, re.M)
        for line in stdout.splitlines():
            if pattern.search(line):
                return ("flag", line.strip()[:160])
    if rc in spec.flag_exits:
        first = next((ln.strip() for ln in stdout.splitlines() if ln.strip()), "")
        return ("flag", (first or f"rc={rc}")[:160])
    if rc in spec.ok_exits:
        return ("pass", f"rc={rc}")
    return ("error", f"rc={rc}")


# --- Ground truth ----------------------------------------------------------------------------

def classify_truth(prs, now: datetime, window: timedelta = TRUTH_WINDOW) -> dict[int, Truth]:
    """repaired: a later `fix` PR on an overlapping file merged within the window.
    clean: older than the window and not repaired. unsettled: younger and not repaired."""
    fixes = [f for f in prs if FIX_TITLE_RE.match(f.title)]
    result: dict[int, Truth] = {}
    for p in prs:
        pfiles = set(p.files)
        repairers = sorted(
            f.number for f in fixes
            if f.number != p.number
            and p.merged_at < f.merged_at <= p.merged_at + window
            and pfiles & set(f.files)
        )
        if repairers:
            result[p.number] = Truth("repaired", tuple(repairers))
        elif now - p.merged_at >= window:
            result[p.number] = Truth("clean")
        else:
            result[p.number] = Truth("unsettled")
    return result
