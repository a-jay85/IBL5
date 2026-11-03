"""Review-finding outcome log: taxonomy, mapping, path resolution, append.

Pure standard library. Shared by the harness (in-process) and the skill-prose
path (through ``bin/review-outcomes``) so the two pipelines cannot drift on the
verdict-to-outcome mapping.
"""
from __future__ import annotations

import datetime
import json
import os
import statistics
import subprocess
import sys

from .labels import REVIEW_AGENTS

SCHEMA_VERSION = 1
LOG_BASENAME = "review-outcomes.jsonl"
ENV_OVERRIDE = "IBL_REVIEW_OUTCOMES_LOG"
REASON_CAP = 300  # chars; keeps every line far below PIPE_BUF (4096)
OUTCOMES = ("fixed", "declined-wrong", "intentional", "didnt-matter")
DECLINE_CATEGORIES = {
    "wrong": "declined-wrong",  # not a defect / the finding is mistaken
    "intentional": "intentional",  # the flagged behavior is deliberate
    "already-fixed": "fixed",  # a prior commit already addressed it
    "not-worth-it": "didnt-matter",  # real but out of scope / not worth changing
}


class OutcomeLogError(Exception):
    """The log path could not be resolved."""


def map_outcome(verdict, category):
    """Pure function of verdict + category. ``None`` means log nothing."""
    if verdict == "FIX":
        return "fixed"
    if verdict == "DECLINE":
        if isinstance(category, str):
            return DECLINE_CATEGORIES.get(category, "didnt-matter")
        return "didnt-matter"
    return None


def _clean_reason(reason) -> str:
    text = str(reason if reason is not None else "")
    for ch in ("\r", "\n", "\t"):
        text = text.replace(ch, " ")
    return text[:REASON_CAP]


def build_event(*, pr, finding_id, agent, score, outcome, source, basis, pipeline,
                reason="", sha=None, origin=None, now=None) -> dict:
    ts_dt = now or datetime.datetime.now(datetime.timezone.utc)
    ts = ts_dt.strftime("%Y-%m-%dT%H:%M:%SZ")
    return {
        "v": SCHEMA_VERSION,
        "ts": ts,
        "pr": int(pr),
        "findingId": int(finding_id),
        "agent": agent or "unknown",
        "score": int(score) if score is not None else None,
        "outcome": outcome,
        "source": source,
        "basis": basis,
        "pipeline": pipeline,
        "reason": _clean_reason(reason),
        "sha": sha,
        "origin": origin,
    }


ORIGINS = ("pre-existing", "same-run")


def event_from_verdict(*, pr, thread, verdict, category, reason, sha, pipeline, origin="pre-existing"):
    """One Phase 4.5 event from a verdict and its thread row, or ``None`` to log nothing."""
    if origin not in ORIGINS:
        raise ValueError(f"unknown origin {origin!r}")
    mapped = map_outcome(verdict, category)
    if mapped is None:
        return None
    if verdict == "FIX":
        basis = "fix-verdict"
    elif isinstance(category, str) and category in DECLINE_CATEGORIES:
        basis = category
    else:
        basis = "category-missing"
    return build_event(pr=pr, finding_id=thread.get("commentId"), agent=thread.get("agent"),
                       score=thread.get("score"), outcome=mapped, source="phase4.5",
                       basis=basis, pipeline=pipeline, reason=reason, sha=sha, origin=origin)


def resolve_log_path(repo_dir, env=None) -> str:
    env = os.environ if env is None else env
    override = env.get(ENV_OVERRIDE)
    if override:
        return override
    try:
        proc = subprocess.run(
            ["git", "-C", repo_dir, "rev-parse", "--path-format=absolute", "--git-common-dir"],
            capture_output=True, text=True, check=False,
        )
    except OSError as e:
        raise OutcomeLogError(f"git unavailable: {e}") from e
    common = proc.stdout.strip()
    if proc.returncode != 0 or not common:
        raise OutcomeLogError(f"not a git repository: {repo_dir}")
    main = os.path.dirname(common.rstrip("/"))
    return os.path.join(main, "tools", "postplan-harness", "out", "state", LOG_BASENAME)


def append_events(path, events, log) -> int:
    """Append one JSON line per event. Never raises; returns events written."""
    written = 0
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_APPEND, 0o644)
        try:
            for event in events:
                line = json.dumps(event, ensure_ascii=True, sort_keys=True) + "\n"
                os.write(fd, line.encode())
                written += 1
        finally:
            os.close(fd)
    except Exception as e:  # noqa: BLE001 - logging must never change a run
        log(f"outcome-log: append failed ({e!r}); continuing")
    return written


def read_events(path) -> list:
    """Parse the log; skip blank, corrupt, or foreign-version lines."""
    if not os.path.exists(path):
        return []
    events = []
    with open(path, encoding="utf-8", errors="replace") as fh:
        for raw in fh:
            raw = raw.strip()
            if not raw:
                continue
            try:
                obj = json.loads(raw)
            except ValueError:
                continue
            if isinstance(obj, dict) and obj.get("v") == SCHEMA_VERSION:
                events.append(obj)
    return events


def seen_finding_ids(path, pr) -> set:
    return {e["findingId"] for e in read_events(path) if e.get("pr") == int(pr)}


def sweep_events(threads, pr, seen, *, pipeline, now=None) -> list:
    """Outcomes for findings Phase 4.5 never answered, from GitHub thread facts only."""
    from .thread_ingestion import _is_trusted  # function-local: thread_ingestion imports this module
    out = []
    for t in threads or []:
        cid = t.get("commentId")
        if cid is None or t.get("score") is None:
            continue  # no <!-- score --> marker: not a posted finding
        if not _is_trusted(t.get("authorLogin") or "", t.get("authorType") or ""):
            continue  # forged marker from an untrusted commenter
        if int(cid) in seen:
            continue  # already logged by 4.5 or an earlier sweep
        if t.get("isOutdated"):
            outcome, basis = "fixed", "outdated"
        elif t.get("isResolved"):
            outcome, basis = "didnt-matter", "resolved-no-reply"
        else:
            continue  # still open: no outcome yet
        out.append(build_event(pr=pr, finding_id=cid, agent=t.get("agent"), score=t.get("score"),
                               outcome=outcome, source="sweep", basis=basis,
                               pipeline=pipeline, now=now))
        seen.add(int(cid))
    return out


def run_sweep(threads, pr, path, log, *, pipeline) -> int:
    """Log sweep events for unanswered findings. Never raises; returns events written."""
    try:
        seen = seen_finding_ids(path, pr)
        return append_events(path, sweep_events(threads, pr, seen, pipeline=pipeline), log)
    except Exception as e:  # noqa: BLE001 - logging must never change a run
        log(f"outcome-log: sweep failed ({e!r}); continuing")
        return 0


# ---------------------------------------------------------------------------
# CLI (bin/review-outcomes wraps `python3 -m harness.outcome_log`)
# ---------------------------------------------------------------------------

USAGE = (
    "usage: review-outcomes record --pr=N --verdicts=PATH --threads=PATH [--pipeline=skill|harness] [--log=PATH]\n"
    "       review-outcomes sweep --pr=N [--pipeline=skill|harness] [--log=PATH]  (thread JSONL on stdin)\n"
    "       review-outcomes report [--since=YYYY-MM-DD] [--until=YYYY-MM-DD] [--log=PATH] [--json]\n"
    "flags take the --flag=value form only"
)
_FLAGS = {
    "record": {"pr", "verdicts", "threads", "pipeline", "log"},
    "sweep": {"pr", "pipeline", "log"},
}


def _parse_flags(subcommand, args):
    """``--flag=value`` only. Returns a dict, or ``None`` on any usage error."""
    allowed = _FLAGS[subcommand]
    flags = {}
    for arg in args:
        if not arg.startswith("--") or "=" not in arg:
            return None
        name, _, value = arg[2:].partition("=")
        if name not in allowed:
            return None
        flags[name] = value
    if not flags.get("pr", "").isdigit():
        return None
    if flags.get("pipeline", "skill") not in ("skill", "harness"):
        return None
    if subcommand == "record" and not (flags.get("verdicts") and flags.get("threads")):
        return None
    return flags


def _read_jsonl_text(text) -> list:
    rows = []
    for raw in text.splitlines():
        raw = raw.strip()
        if not raw:
            continue
        try:
            obj = json.loads(raw)
        except ValueError:
            continue
        if isinstance(obj, dict):
            rows.append(obj)
    return rows


def _cli_record(flags, log) -> None:
    pr = int(flags["pr"])
    pipeline = flags.get("pipeline", "skill")
    path = flags.get("log") or resolve_log_path(os.getcwd())
    with open(flags["verdicts"], encoding="utf-8", errors="replace") as fh:
        verdicts = _read_jsonl_text(fh.read())
    with open(flags["threads"], encoding="utf-8", errors="replace") as fh:
        threads = {t.get("commentId"): t for t in _read_jsonl_text(fh.read())}
    events = []
    for v in verdicts:
        thread = threads.get(v.get("commentId"))
        if thread is None:
            continue  # a verdict for an id the thread file never listed is not logged
        origin = v.get("origin")
        if origin not in ORIGINS:
            origin = "pre-existing"  # a typo in the verdict line must not drop the batch
        event = event_from_verdict(pr=pr, thread=thread, verdict=v.get("verdict"),
                                   category=v.get("category"), reason=v.get("reason"),
                                   sha=v.get("sha"), pipeline=pipeline,
                                   origin=origin)
        if event is not None:
            events.append(event)
    if events:
        append_events(path, events, log)


def _cli_sweep(flags, log) -> None:
    pr = int(flags["pr"])
    path = flags.get("log") or resolve_log_path(os.getcwd())
    rows = _read_jsonl_text(sys.stdin.read())
    run_sweep(rows, pr, path, log, pipeline=flags.get("pipeline", "skill"))


_REPORT_KEYS = ("total",) + OUTCOMES + ("wrong_or_didnt_matter_share",)
_REPORT_HEADER = "agent    total  fixed  wrong  intentional  didnt-matter  wrong+dm%"
_CYCLE_GAP_SECONDS = 300
_CYCLE_BUCKETS = ("1", "2", "3", "4+")
_REVERT_PREFIX = 'Revert "'
_GIT_TIMEOUT_SECONDS = 20
_REPO_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.realpath(__file__)))))


def _parse_report_flags(args):
    """``--flag=value`` plus a bare ``--json``. ``None`` on any usage error."""
    flags = {"json": False}
    for arg in args:
        if arg == "--json":
            flags["json"] = True
            continue
        if not arg.startswith("--") or "=" not in arg:
            return None
        name, _, value = arg[2:].partition("=")
        if name not in ("since", "until", "log"):
            return None
        if name in ("since", "until"):
            try:
                datetime.date.fromisoformat(value)
            except ValueError:
                return None
        flags[name] = value
    return flags


def _agent_row(counts) -> dict:
    row = {"total": sum(counts.get(o, 0) for o in OUTCOMES)}
    for o in OUTCOMES:
        row[o] = counts.get(o, 0)
    bad = row["declined-wrong"] + row["didnt-matter"]
    row["wrong_or_didnt_matter_share"] = round(bad / row["total"], 3) if row["total"] else None
    return row


def _parse_ts(ts):
    try:
        return datetime.datetime.strptime(str(ts), "%Y-%m-%dT%H:%M:%SZ")
    except ValueError:
        return None


def build_cycles(events, since=None, until=None) -> dict:
    """Review cycles per PR, derived from the raw phase4.5 events (never the last-wins dict).

    A cycle is a batch of one PR's phase4.5 events; a new batch starts where two consecutive
    events sit more than _CYCLE_GAP_SECONDS apart. A PR is in range when the UTC day of its
    last phase4.5 event is. Blind spot: a review run that logged zero finding outcomes writes
    no event, so a clean first pass on a PR with no findings is invisible.
    """
    per_pr = {}
    for e in events:
        if e.get("source") != "phase4.5" or e.get("pr") is None:
            continue
        t = _parse_ts(e.get("ts"))
        if t is not None:
            per_pr.setdefault(e.get("pr"), []).append(t)
    counts = []
    for times in per_pr.values():
        times.sort()
        day = times[-1].strftime("%Y-%m-%d")
        if (since and day < since) or (until and day > until):
            continue
        counts.append(1 + sum(1 for a, b in zip(times, times[1:])
                              if (b - a).total_seconds() > _CYCLE_GAP_SECONDS))
    hist = {k: 0 for k in _CYCLE_BUCKETS}
    for n in counts:
        hist[str(n) if n < 4 else "4+"] += 1
    return {"prs": len(counts),
            "mean": round(sum(counts) / len(counts), 2) if counts else None,
            "median": statistics.median(counts) if counts else None,
            "max": max(counts) if counts else None,
            "histogram": hist, "gap_seconds": _CYCLE_GAP_SECONDS}


def build_report(events, since=None, until=None) -> dict:
    """Last event wins per (pr, findingId); the date filter applies to the winning event."""
    latest = {}
    for e in events:
        latest[(e.get("pr"), e.get("findingId"))] = e
    per_agent = {}
    total = {}
    for e in latest.values():
        day = str(e.get("ts", ""))[:10]
        if (since and day < since) or (until and day > until):
            continue
        outcome = e.get("outcome")
        if outcome not in OUTCOMES:
            continue
        agent = e.get("agent") or "unknown"
        counts = per_agent.setdefault(agent, {})
        counts[outcome] = counts.get(outcome, 0) + 1
        total[outcome] = total.get(outcome, 0) + 1
    return {"since": since, "until": until,
            "agents": {a: _agent_row(c) for a, c in per_agent.items()},
            "total": _agent_row(total),
            "cycles": build_cycles(events, since, until)}


def read_master_subjects(repo_dir=_REPO_ROOT) -> list:
    """The injectable git seam: (UTC commit day, subject) per origin/master commit.

    Every failure is raised as RuntimeError so the caller can degrade loudly.
    """
    try:
        proc = subprocess.run(
            ["git", "-C", repo_dir, "log", "origin/master", "--date=format-local:%Y-%m-%d",
             "--format=%cd%x09%s"],
            capture_output=True, text=True, timeout=_GIT_TIMEOUT_SECONDS,
            env=dict(os.environ, TZ="UTC"))
    except (OSError, subprocess.TimeoutExpired) as e:
        raise RuntimeError(f"git log failed ({e!r})") from e
    if proc.returncode != 0:
        raise RuntimeError(f"git log exit {proc.returncode}: {proc.stderr.strip()[:200]}")
    rows = []
    for line in proc.stdout.splitlines():
        day, sep, subject = line.partition("\t")
        if sep:
            rows.append((day, subject))
    return rows


def build_reverts(subjects, since=None, until=None) -> dict:
    """Count master commits whose subject starts with 'Revert "', bucketed by UTC month."""
    months = {}
    for day, subject in subjects:
        if (since and day < since) or (until and day > until):
            continue
        m = months.setdefault(day[:7], {"commits": 0, "reverts": 0})
        m["commits"] += 1
        if subject.startswith(_REVERT_PREFIX):
            m["reverts"] += 1
    ordered = {k: months[k] for k in sorted(months)}
    return {"months": ordered,
            "commits": sum(m["commits"] for m in ordered.values()),
            "total": sum(m["reverts"] for m in ordered.values())}


def _format_reverts(r) -> list:
    if r is None:
        return ["reverts: revert count unavailable (git log origin/master failed, see stderr)"]
    lines = [f"reverts on origin/master (subject starts 'Revert \"'): {r['total']} of {r['commits']} commits"]
    for month, m in r["months"].items():
        pct = f"{100 * m['reverts'] / m['commits']:.1f}"
        lines.append(f"  {month}  {m['reverts']}/{m['commits']}  {pct}%")
    return lines


def _format_cycles(c) -> list:
    mean = "-" if c["mean"] is None else f"{c['mean']:.2f}"
    median = "-" if c["median"] is None else f"{c['median']:g}"
    mx = "-" if c["max"] is None else f"{c['max']:g}"
    hist = "  ".join(f"{k}={c['histogram'][k]}" for k in _CYCLE_BUCKETS)
    return [f"cycles-to-pass: {c['prs']} PRs  mean {mean}  median {median}  max {mx}",
            f"cycles histogram: {hist}",
            f"note: cycles = phase4.5 outcome batches per PR split at gaps over "
            f"{c['gap_seconds']}s; review runs that logged zero findings are not counted"]


def format_report(report) -> str:
    def line(name, row):
        share = row["wrong_or_didnt_matter_share"]
        pct = "-" if share is None else f"{share * 100:.1f}"
        return (f"{name:<9}{row['total']:>5}  {row['fixed']:>5}  {row['declined-wrong']:>5}  "
                f"{row['intentional']:>12}  {row['didnt-matter']:>12}  {pct:>9}")

    agents = dict(report["agents"])
    for a in REVIEW_AGENTS:
        agents.setdefault(a, _agent_row({}))
    live = list(REVIEW_AGENTS)
    legacy = sorted(a for a in agents if a not in REVIEW_AGENTS and a != "unknown")
    names = live + legacy + (["unknown"] if "unknown" in agents else [])
    lines = [_REPORT_HEADER]
    lines += [line(a, agents[a]) for a in names]
    lines.append(line("TOTAL", report["total"]))
    if "cycles" in report:
        lines += [""] + _format_cycles(report["cycles"])
    if "reverts" in report:
        lines += [""] + _format_reverts(report["reverts"])
    return "\n".join(lines)


def _cli_report(flags) -> int:
    try:
        path = flags.get("log") or resolve_log_path(os.getcwd())
        report = build_report(read_events(path), flags.get("since"), flags.get("until"))
    except Exception as e:  # noqa: BLE001 - report is a human command; say what broke
        print(f"review-outcomes: report failed ({e!r})", file=sys.stderr)
        return 1
    try:
        report["reverts"] = build_reverts(read_master_subjects(), flags.get("since"),
                                          flags.get("until"))
    except RuntimeError as e:
        report["reverts"] = None
        print(f"review-outcomes: revert count unavailable ({e})", file=sys.stderr)
    if flags["json"]:
        print(json.dumps(report, sort_keys=True))
    else:
        print(format_report(report))
    unknown = report["agents"].get("unknown")
    if unknown:
        print(f'note: {unknown["total"]} outcome(s) under "unknown" were logged before harness '
              "review comments carried an agent marker; their agent cannot be recovered "
              "from the comment or the cache.", file=sys.stderr)
    return 0


def main(argv=None) -> int:
    argv = list(sys.argv[1:] if argv is None else argv)
    if argv and argv[0] == "report":
        flags = _parse_report_flags(argv[1:])
        if flags is None:
            print(USAGE, file=sys.stderr)
            return 2
        return _cli_report(flags)
    handlers = {"record": _cli_record, "sweep": _cli_sweep}
    if not argv or argv[0] not in handlers:
        print(USAGE, file=sys.stderr)
        return 2
    subcommand = argv[0]
    flags = _parse_flags(subcommand, argv[1:])
    if flags is None:
        print(USAGE, file=sys.stderr)
        return 2

    def log(msg):
        print(msg, file=sys.stderr)

    try:
        handlers[subcommand](flags, log)
    except Exception as e:  # noqa: BLE001 - logging must never fail the caller
        print(f"review-outcomes: {subcommand} failed ({e!r})", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())
