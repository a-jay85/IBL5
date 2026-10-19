"""Bounded replay of the 4D verifier votes against archived post-plan runs.

`bin/gate-backtest` replays `bin/check-*` gates and cannot measure a change to the 4D
scoring path, so this module supplies the flip count: how many historical review holds
(a code finding scored >= 80 or a security finding scored >= 75) the verifier votes would
have dropped, and how many dropped findings they would now keep.

    python3 -m harness.verifier_replay inventory [--out-root PATH]
    python3 -m harness.verifier_replay replay --limit N [--include-dropped M]
                                              [--dry-run] [--out DIR] [--out-root PATH]

`inventory` makes no LLM call. `replay` checks out each sampled PR head with
`git worktree add --detach`, runs the production `ReviewPhase.verify_findings` against it
and writes `<out>/<run_id>/verifier-votes.json`. Every verifier call costs money, so the
replay is a bounded sample. Run by hand only; nothing in the pipeline calls it.
"""
from __future__ import annotations

import concurrent.futures
import dataclasses
import glob
import json
import os
import re
import subprocess
import sys
import tempfile
import threading

from .review import (CODE_THRESHOLD, SEC_THRESHOLD, VERIFIER_COUNT, VERIFIER_MAX_BUDGET_USD,
                     ReviewPhase)
from .state import Finding, HarnessError, UsageLedger

USAGE = ("usage: python3 -m harness.verifier_replay inventory [--out-root PATH]\n"
         "       python3 -m harness.verifier_replay replay --limit N [--include-dropped M]\n"
         "                                                  [--dry-run] [--out DIR] [--out-root PATH]")
HARNESS_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DEFAULT_OUT_ROOT = os.path.join(HARNESS_ROOT, "out")
REPLAY_WORKERS = 6
_GIT_LOCK = threading.Lock()       # fetch and worktree add/remove take repo-wide locks
_CI_RE = re.compile(r"^ci-([0-9a-f]{7,40})\.json$")
_TS_RE = re.compile(r"-(\d{8})-(\d{6})-\d+$")


@dataclasses.dataclass
class RunRecord:
    run_id: str
    source: str                    # "result" (a live-* run dir) or "reviewcache"
    pr_number: int = 0
    head_sha: str = ""
    findings: list = dataclasses.field(default_factory=list)
    scored: list = dataclasses.field(default_factory=list)
    duplicate: bool = False        # a reviewcache arm whose run_id a run dir already covers


def _threshold(source: str) -> int:
    return SEC_THRESHOLD if source == "security-audit" else CODE_THRESHOLD


def _is_held(row: dict) -> bool:
    score = row.get("score")
    return isinstance(score, int) and not isinstance(score, bool) and score >= _threshold(row.get("source", ""))


def _row_key(row: dict) -> tuple:
    return (row.get("source"), row.get("agent"), row.get("path"), row.get("line"))


def _load_json(path: str):
    try:
        with open(path) as fh:
            return json.load(fh)
    except (OSError, ValueError):
        return None


def _head_sha(run_dir: str, ci_head) -> str:
    shas = []
    for name in os.listdir(run_dir):
        m = _CI_RE.match(name)
        if m:
            shas.append((os.path.getmtime(os.path.join(run_dir, name)), m.group(1)))
    if not shas:
        return ""
    if isinstance(ci_head, str) and any(s == ci_head for _, s in shas):
        return ci_head
    return max(shas)[1]


def load_runs(out_root: str) -> list[RunRecord]:
    """Every live-* run dir with a readable result.json, then every reviewcache arm.
    A reviewcache arm whose run_id names a run dir already loaded is marked duplicate."""
    runs: list[RunRecord] = []
    seen: set[str] = set()
    for path in sorted(glob.glob(os.path.join(out_root, "live-*", "result.json"))):
        data = _load_json(path)
        if not isinstance(data, dict):
            continue
        run_dir = os.path.dirname(path)
        run_id = os.path.basename(run_dir)
        pr = data.get("pr_number")
        runs.append(RunRecord(run_id=run_id, source="result",
                              pr_number=pr if isinstance(pr, int) else 0,
                              head_sha=_head_sha(run_dir, data.get("ci_head")),
                              findings=[f for f in data.get("findings") or [] if isinstance(f, dict)],
                              scored=[r for r in data.get("scored_findings") or [] if isinstance(r, dict)]))
        seen.add(run_id)
    for path in sorted(glob.glob(os.path.join(out_root, "state", "*.reviewcache.json"))):
        data = _load_json(path)
        if not isinstance(data, dict) or not isinstance(data.get("review"), dict):
            continue
        arm = data["review"]
        run_id = arm.get("run_id") or data.get("run_id") or os.path.basename(path)
        pr = data.get("pr_number")
        runs.append(RunRecord(run_id=run_id, source="reviewcache",
                              pr_number=pr if isinstance(pr, int) else 0,
                              findings=[f for f in arm.get("findings") or [] if isinstance(f, dict)],
                              scored=[r for r in arm.get("scored_findings") or [] if isinstance(r, dict)],
                              duplicate=run_id in seen))
        seen.add(run_id)
    return runs


def _replayable(run: RunRecord) -> bool:
    return (run.source == "result" and not run.duplicate and bool(run.head_sha)
            and run.pr_number > 0)


def eligible(run: RunRecord) -> bool:
    """A held finding, a recorded head sha and a PR number: the runs a verifier drop could flip."""
    return _replayable(run) and any(_is_held(r) for r in run.scored)


def classify_flip(before_rows: list[dict], after_records: list[dict]) -> dict:
    """Compare a run's archived scored rows with fresh vote records, paired by
    (source, agent, path, line). A run flips only when it held before and no finding
    clears its threshold now, which is when conditions (2) and (11) would release."""
    after = {_row_key(r): r for r in after_records}
    held = [r for r in before_rows if _is_held(r)]
    kept_now = [r for r in after_records if r.get("kept") and _is_held(r)]
    finding_flips = sum(1 for r in held if _row_key(r) in after and not _is_held(after[_row_key(r)]))
    newly_held = sum(1 for r in before_rows
                     if not _is_held(r) and _row_key(r) in after and _is_held(after[_row_key(r)]))
    return {"held_before": len(held), "kept_now": len(kept_now),
            "run_flip": bool(held) and all(not _is_held(r) for r in after_records),
            "finding_flips": finding_flips, "newly_held": newly_held}


def _inventory_block(runs: list[RunRecord], ref_exists) -> str:
    primary = [r for r in runs if not r.duplicate]
    rows = [row for r in primary for row in r.scored]
    code = sum(1 for row in rows if row.get("source") != "security-audit" and _is_held(row))
    sec = sum(1 for row in rows if row.get("source") == "security-audit" and _is_held(row))
    held_runs = sum(1 for r in primary if any(_is_held(row) for row in r.scored))
    elig = [r for r in primary if eligible(r)]
    unfetchable = sum(1 for r in elig if not ref_exists(r.pr_number))
    n_result = sum(1 for r in runs if r.source == "result")
    n_cache = sum(1 for r in runs if r.source == "reviewcache")
    n_dup = sum(1 for r in runs if r.duplicate)
    return "\n".join([
        "<!-- verifier-replay-inventory -->",
        f"runs scanned: {n_result} result.json + {n_cache} reviewcache (dedup {n_dup})",
        f"scored findings: {len(rows)}; code >= 80: {code}; security >= 75: {sec}; "
        f"runs with >= 1 held finding: {held_runs}",
        f"eligible for replay (held finding + head sha + pr number): {len(elig)}; "
        f"head ref unfetchable: {unfetchable}",
        "<!-- /verifier-replay-inventory -->",
    ])


def _git(args: list[str], cwd: str) -> str:
    proc = subprocess.run(["git", *args], cwd=cwd, capture_output=True, text=True, timeout=300)
    if proc.returncode != 0:
        raise HarnessError("verifier-replay-git", f"git {' '.join(args)}: {proc.stderr.strip()[:200]}")
    return proc.stdout


def _repo_root() -> str:
    return _git(["rev-parse", "--show-toplevel"], HARNESS_ROOT).strip()


def _live_ref_exists(pr: int) -> bool:
    try:
        return bool(_git(["ls-remote", "origin", f"refs/pull/{pr}/head"], HARNESS_ROOT).strip())
    except (HarnessError, subprocess.TimeoutExpired):
        return False


def _findings_for(run: RunRecord) -> list[Finding]:
    """One Finding per archived scored row; the full body comes from result.json's findings
    when a row matches one, else the 160-char body_head the row kept."""
    bodies = {_row_key(f): f.get("body") or "" for f in run.findings}
    out = []
    for row in run.scored:
        out.append(Finding(source=row.get("source", ""), agent=row.get("agent", ""),
                           path=row.get("path", ""), line=row.get("line") or 0,
                           body=bodies.get(_row_key(row)) or row.get("body_head") or ""))
    return out


class _CountingLlm:
    """Forwards to the live adapter and counts call_tooled invocations. out_dir is per
    thread so concurrent runs each write their own verifier-votes.json."""

    def __init__(self, inner):
        self._inner = inner
        self._local = threading.local()
        self._lock = threading.Lock()
        self.tooled = 0

    @property
    def out_dir(self):
        return getattr(self._local, "out_dir", None)

    @out_dir.setter
    def out_dir(self, value):
        self._local.out_dir = value

    def call_tooled(self, *a, **kw):
        with self._lock:
            self.tooled += 1
        return self._inner.call_tooled(*a, **kw)


def _sample(runs: list[RunRecord], limit: int, include_dropped: int) -> tuple[list, list]:
    def _ts(r):
        m = _TS_RE.search(r.run_id)
        return m.group(1) + m.group(2) if m else ""
    newest = sorted((r for r in runs if _replayable(r) and r.scored), key=_ts, reverse=True)
    held = [r for r in newest if eligible(r)][:limit]
    dropped = [r for r in newest if not eligible(r)][:include_dropped]
    return held, dropped


def _replay_one(run: RunRecord, llm: _CountingLlm, out: str, tmp_root: str, repo: str) -> dict:
    checkout = os.path.join(tmp_root, run.head_sha)
    with _GIT_LOCK:
        _git(["fetch", "-q", "origin", f"refs/pull/{run.pr_number}/head"], repo)
        _git(["worktree", "add", "-q", "--detach", checkout, run.head_sha], repo)
    try:
        base = _git(["merge-base", "origin/master", run.head_sha], repo).strip()
        diff = _git(["diff", base, run.head_sha], repo)
        llm.out_dir = os.path.join(out, run.run_id)
        phase = ReviewPhase(llm, None, log=print, worktree=checkout)
        records = phase.verify_findings(_findings_for(run), diff)
        phase._audit_votes(records)
    finally:
        try:
            with _GIT_LOCK:
                _git(["worktree", "remove", "--force", checkout], repo)
        except HarnessError as e:
            print(f"verifier-replay: could not remove {checkout}: {e}", file=sys.stderr)
    return classify_flip(run.scored, records)


def replay(runs: list[RunRecord], limit: int, include_dropped: int, dry_run: bool, out: str,
           ref_exists, llm_factory) -> str:
    held, dropped = _sample(runs, limit, include_dropped)
    n_elig = sum(1 for r in runs if eligible(r))
    if dry_run:
        for r in held + dropped:
            print(f"pr {r.pr_number} {r.head_sha[:7]} "
                  f"held-before={sum(1 for row in r.scored if _is_held(row))} "
                  f"fetchable={'yes' if ref_exists(r.pr_number) else 'no'}")
        return "\n".join([
            "<!-- verifier-replay-result -->",
            f"sample: {len(held)} held runs of {n_elig} eligible, {len(dropped)} dropped-only runs; "
            "verifier calls: 0",
            "finding flips (held -> dropped): n/a (dry run); run flips (held -> armable): n/a (dry run)",
            "reverse (dropped -> held): n/a (dry run)",
            "bounded sample: yes; cost ceiling: 0 x VERIFIER_MAX_BUDGET_USD",
            "<!-- /verifier-replay-result -->",
        ])
    llm = _CountingLlm(llm_factory())
    repo = _repo_root()
    results: dict[str, dict] = {}
    with tempfile.TemporaryDirectory(prefix="verifier-replay-") as tmp_root:
        with concurrent.futures.ThreadPoolExecutor(max_workers=REPLAY_WORKERS) as ex:
            futs = {r.run_id: ex.submit(_replay_one, r, llm, out, tmp_root, repo) for r in held + dropped}
            for r in held + dropped:
                try:
                    results[r.run_id] = futs[r.run_id].result()
                except (HarnessError, subprocess.TimeoutExpired) as e:
                    print(f"pr {r.pr_number} {r.head_sha[:7]} skipped: {e}")
                    continue
                c = results[r.run_id]
                print(f"pr {r.pr_number} {r.head_sha[:7]} held-before={c['held_before']} "
                      f"kept-now={c['kept_now']} run-flip={'yes' if c['run_flip'] else 'no'} "
                      f"newly-held={c['newly_held']}")
    done_held = [results[r.run_id] for r in held if r.run_id in results]
    done_drop = [results[r.run_id] for r in dropped if r.run_id in results]
    return "\n".join([
        "<!-- verifier-replay-result -->",
        f"sample: {len(done_held)} held runs of {n_elig} eligible, {len(done_drop)} dropped-only runs; "
        f"verifier calls: {llm.tooled}",
        f"finding flips (held -> dropped): {sum(c['finding_flips'] for c in done_held)} of "
        f"{sum(c['held_before'] for c in done_held)}; run flips (held -> armable): "
        f"{sum(1 for c in done_held if c['run_flip'])} of {len(done_held)}",
        f"reverse (dropped -> held): {sum(c['newly_held'] for c in done_drop)} findings in "
        f"{sum(1 for c in done_drop if c['newly_held'])} of {len(done_drop)} runs",
        f"bounded sample: yes; cost ceiling: {llm.tooled} x VERIFIER_MAX_BUDGET_USD "
        f"({llm.tooled * VERIFIER_MAX_BUDGET_USD:.0f} USD, {VERIFIER_COUNT} seats per chunk)",
        "<!-- /verifier-replay-result -->",
    ])


def _usage_error(msg: str) -> int:
    print(f"verifier_replay: {msg}\n{USAGE}", file=sys.stderr)
    return 2


def _live_llm():
    from .adapters.llm import ClaudeCli
    return ClaudeCli(UsageLedger())


def main(argv: list[str] | None = None, *, ref_exists=_live_ref_exists, llm_factory=_live_llm) -> int:
    args = list(sys.argv[1:] if argv is None else argv)
    if not args or args[0] not in ("inventory", "replay"):
        return _usage_error("expected a subcommand")
    cmd, rest = args[0], args[1:]
    opts = {"--out-root": DEFAULT_OUT_ROOT, "--out": os.path.join(tempfile.gettempdir(), "verifier-replay"),
            "--limit": None, "--include-dropped": "0"}
    allowed = {"--out-root"} if cmd == "inventory" else {"--out-root", "--out", "--limit", "--include-dropped"}
    dry_run = False
    i = 0
    while i < len(rest):
        a = rest[i]
        if a == "--dry-run" and cmd == "replay":
            dry_run = True
            i += 1
        elif a in allowed and i + 1 < len(rest):
            opts[a] = rest[i + 1]
            i += 2
        else:
            return _usage_error(f"unknown or incomplete argument {a!r}")
    if cmd == "inventory":
        print(_inventory_block(load_runs(opts["--out-root"]), ref_exists))
        return 0
    if opts["--limit"] is None:
        return _usage_error("replay needs --limit N")
    try:
        limit, include_dropped = int(opts["--limit"]), int(opts["--include-dropped"])
    except ValueError:
        return _usage_error("--limit and --include-dropped take integers")
    if limit < 0 or include_dropped < 0:
        return _usage_error("--limit and --include-dropped must be >= 0")
    print(replay(load_runs(opts["--out-root"]), limit, include_dropped, dry_run, opts["--out"],
                 ref_exists, llm_factory))
    return 0


if __name__ == "__main__":
    sys.exit(main())
