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

_PR_BODY = ReplaySpec(argv=("--body-file", "{body_file}"))
_LIVE_CI = "reads live CI state through gh"

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
    # PR-body gates: the replay feeds the merged PR's body (as it reads today).
    "bin/check-pr-manual-testing": _PR_BODY,
    "bin/check-post-merge-recipe": ReplaySpec(
        argv=("--body-file", "{body_file}", "--repo-slug", "a-jay85/IBL5"),
    ),
    # Diff-scoped hygiene: --pr diffs against the env base, set to the PR's own base.
    "bin/check-e2e-hygiene": ReplaySpec(
        argv=("--pr",), env=(("E2E_HYGIENE_BASE_REF", "{base}"),),
    ),
    "bin/check-phpunit-hygiene": ReplaySpec(
        argv=("--pr",), env=(("PHPUNIT_HYGIENE_BASE_REF", "{base}"),),
    ),
    # Not reject gates, or read state that only exists live.
    "bin/check-hot-files": "informational lister with inverted exit (0 = a crossing found); no reject verdict",
    "bin/check-pr-collisions": "lists live open PRs through gh and always exits 0",
    "bin/check-master-ci-green": _LIVE_CI,
    "bin/check-pr-checks-green": _LIVE_CI,
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

def is_check_script(path: str) -> bool:
    return fnmatch.fnmatchcase(path, "bin/check-*") and "/" not in path[len("bin/"):]


_DIFF_HEADER_RE = re.compile(r"^diff --git a/(.*) b/(.*)$")


def changes_from_unified_diff(diff_text: str) -> list[tuple[str, str]]:
    """(status, path) pairs from a unified diff's `diff --git` headers.

    `new file mode` gives A, `deleted file mode` gives D, `rename to` gives R, else M. The
    runner already holds this diff, so the detector reads what the PR body describes.
    """
    out: list[tuple[str, str]] = []
    old_path = ""
    settled = True
    for line in diff_text.splitlines():
        m = _DIFF_HEADER_RE.match(line)
        if m:
            old_path = m.group(1)
            out.append(("M", m.group(2)))
            settled = False
            continue
        if settled:
            continue
        if line.startswith("new file mode"):
            out[-1] = ("A", out[-1][1])
        elif line.startswith("deleted file mode"):
            out[-1] = ("D", old_path)
        elif line.startswith("rename to "):
            out[-1] = ("R", out[-1][1])
        else:
            continue
        settled = True
    return out


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
        if is_check_script(path):
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


# --- Verdict ---------------------------------------------------------------------------------
#
# Threshold reasoning. The pipeline review of 2026-10-02 found 20 of 34 fix PRs repairing
# something merged under 2 days earlier, so the 48h repair window captures most real breakage.
# The labels still carry noise: a latent bug fixed after 48h, or a fix that touched other files,
# leaves a truly broken PR labelled clean. A threshold of 1 would hold a gate on one mislabelled
# PR. At 2, two independent clean PRs must be flagged. A window of about 30 PRs leaves 20 to 25
# matured after the 48h rule, so 2 false flags means the gate would have blocked about one good
# PR in ten. About 90% of merged PRs are tooling or meta work that a ship-pipeline gate sees, so
# that rate would mean several spurious blocks a week. MIN_MATURED_REPLAYS keeps a gate skipped
# on almost every PR (a plan-reading gate with no plans on disk) from reading as CLEARED.

GATE_BACKTEST_BEGIN = "<!-- gate-backtest:begin -->"
GATE_BACKTEST_END = "<!-- gate-backtest:end -->"
STATE_LINE_RE = re.compile(
    r"^<!-- gate-backtest-state: (NOT-APPLICABLE|CLEARED|HELD|UNKNOWN) -->$", re.M)
FALSE_FLAG_THRESHOLD = 2
MIN_MATURED_REPLAYS = 5
LIST_CAP = 20
TEXT_CAP = 160


@dataclass
class GateTally:
    replayed: int = 0
    matured: int = 0
    catches: list[int] | None = None
    false_flags: list[int] | None = None
    unsettled_flags: list[int] | None = None
    skipped: int = 0
    errors: int = 0
    first_error: str = ""

    def __post_init__(self) -> None:
        self.catches = self.catches if self.catches is not None else []
        self.false_flags = self.false_flags if self.false_flags is not None else []
        self.unsettled_flags = self.unsettled_flags if self.unsettled_flags is not None else []


@dataclass(frozen=True)
class Verdict:
    state: str
    reason: str
    per_gate: dict


def _tally(gate_path: str, results, truth: dict[int, Truth]) -> GateTally:
    tally = GateTally()
    for r in results:
        if r.gate != gate_path:
            continue
        if r.outcome == "skipped":
            tally.skipped += 1
            continue
        if r.outcome in ("error", "timeout"):
            tally.errors += 1
            if not tally.first_error:
                tally.first_error = f"{r.outcome}: {r.detail}"
            continue
        tally.replayed += 1
        state = truth.get(r.pr, Truth("unsettled")).state
        if state in ("clean", "repaired"):
            tally.matured += 1
        if r.outcome == "flag":
            if state == "repaired":
                tally.catches.append(r.pr)
            elif state == "clean":
                tally.false_flags.append(r.pr)
            else:
                tally.unsettled_flags.append(r.pr)
    return tally


def _clean(text: str) -> str:
    return " ".join(text.replace("`", "").split())[:TEXT_CAP]


def compute_verdict(gates, results, truth: dict[int, Truth]) -> Verdict:
    """First matching row wins; every state the replay cannot vouch for holds."""
    replayable = [g for g in gates if g.state == "replayable"]
    per_gate: dict[str, GateTally] = {}
    if results is not None:
        for g in replayable:
            per_gate[g.path] = _tally(g.path, results, truth)

    if not gates:
        return Verdict("NOT-APPLICABLE", "no gate changed", per_gate)
    if results is None and replayable:
        return Verdict("UNKNOWN", "the backtest runner failed before producing results", per_gate)
    for g in gates:
        if g.state == "unspecified":
            return Verdict("HELD", f"`{_clean(g.path)}` has no replay spec ({_clean(g.reason)})",
                           per_gate)
    for g in replayable:
        t = per_gate[g.path]
        if t.errors > 0:
            return Verdict("UNKNOWN", f"`{_clean(g.path)}` replay failed ({_clean(t.first_error)})",
                           per_gate)
    for g in replayable:
        n = len(per_gate[g.path].false_flags)
        if n >= FALSE_FLAG_THRESHOLD:
            return Verdict("HELD", f"`{_clean(g.path)}` flagged {n} clean PRs "
                                   f"(threshold {FALSE_FLAG_THRESHOLD})", per_gate)
    for g in replayable:
        if per_gate[g.path].matured < MIN_MATURED_REPLAYS:
            return Verdict("UNKNOWN", f"thin sample: `{_clean(g.path)}` replayed on "
                                      f"{per_gate[g.path].matured} matured PRs "
                                      f"(minimum {MIN_MATURED_REPLAYS})", per_gate)
    if not replayable:
        return Verdict("NOT-APPLICABLE", "only non-replayable gates changed", per_gate)
    return Verdict("CLEARED", "no gate crossed the false-flag threshold", per_gate)


# --- PR-body block ---------------------------------------------------------------------------

def _numbers(nums, truth=None) -> str:
    shown = []
    for n in list(nums)[:LIST_CAP]:
        if truth is not None and n in truth and truth[n].repaired_by:
            shown.append(f"#{n} (repaired by " + ", ".join(f"#{x}" for x in truth[n].repaired_by) + ")")
        else:
            shown.append(f"#{n}")
    extra = len(nums) - LIST_CAP
    return ", ".join(shown) + (f" (+{extra} more)" if extra > 0 else "")


def render_gate_backtest(verdict: Verdict, gates, results, truth: dict[int, Truth],
                         window_size: int) -> str:
    """Markdown block for the PR body. Only PR numbers are rendered, never historical titles."""
    if not gates:
        return ""
    lines = [GATE_BACKTEST_BEGIN, f"<!-- gate-backtest-state: {verdict.state} -->",
             "### Gate backtest", "",
             f"**State:** {verdict.state}. {verdict.reason}", "",
             f"Replayed against the newest {window_size} settled merged PRs (older than 48h). "
             "Repaired: a `fix` PR touching an overlapping file merged within 48h. "
             "Clean: older than 48h and not repaired. "
             "Unsettled: younger than 48h, never replayed.", ""]
    catches: list[int] = []
    false_flags: list[int] = []
    unsettled: list[int] = []
    if verdict.per_gate:
        lines += ["| Gate | Replayed | Catches | False flags | Unsettled flags | Skipped | Errors |",
                  "|------|----------|---------|-------------|-----------------|---------|--------|"]
        for path, t in verdict.per_gate.items():
            lines.append(f"| `{_clean(path)}` | {t.replayed} | {len(t.catches)} | "
                         f"{len(t.false_flags)} | {len(t.unsettled_flags)} | {t.skipped} | "
                         f"{t.errors} |")
            catches += t.catches
            false_flags += t.false_flags
            unsettled += t.unsettled_flags
        lines.append("")
    if catches:
        lines.append("- Catches: " + _numbers(catches, truth))
    if false_flags:
        lines.append("- False flags: " + _numbers(false_flags))
    if unsettled:
        lines.append("- Unsettled flags: " + _numbers(unsettled))
    for label, state in (("Not replayable", "not-replayable"), ("Unspecified", "unspecified"),
                         ("Removed", "removed")):
        entries = [f"`{_clean(g.path)}` ({_clean(g.reason)})" for g in gates if g.state == state]
        if entries:
            lines.append(f"- {label}: " + ", ".join(entries))
    lines.append(GATE_BACKTEST_END)
    return "\n".join(lines)


def upsert_gate_backtest(body: str, block: str) -> str:
    """Insert or replace the gate-backtest block in a PR body (same contract as
    classify.upsert_tests_changed): both markers in order replace BEGIN..END inclusive,
    otherwise append a fresh block and leave any orphan marker."""
    body = body or ""
    if not body.strip():
        return block
    begin_idx = body.find(GATE_BACKTEST_BEGIN)
    end_idx = body.find(GATE_BACKTEST_END)
    if begin_idx != -1 and end_idx != -1 and begin_idx < end_idx:
        after_end = end_idx + len(GATE_BACKTEST_END)
        return body[:begin_idx] + block + body[after_end:]
    return body.rstrip() + "\n\n" + block + "\n"


def parse_gate_backtest_state(body: str) -> str:
    """No block: NOT-APPLICABLE. One state line inside a closed block: that token. Else UNKNOWN."""
    body = body or ""
    begin_idx = body.find(GATE_BACKTEST_BEGIN)
    if begin_idx == -1:
        return "NOT-APPLICABLE"
    end_idx = body.find(GATE_BACKTEST_END, begin_idx)
    if end_idx == -1:
        return "UNKNOWN"
    matches = STATE_LINE_RE.findall(body[begin_idx:end_idx])
    return matches[0] if len(matches) == 1 else "UNKNOWN"
