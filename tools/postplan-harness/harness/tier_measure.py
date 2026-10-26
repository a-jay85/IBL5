"""Tier-measurement driver: run the post-plan reviewer's agent A prompt on the
reviewer-eval corpus across (model, effort) cells and report hit rate, cost and time.

One tool-less `claude -p` call per (run, case, cell). Every call is budget-checked
before it starts, every record is flushed to disk as soon as the call returns, and a
re-run resumes from the JSONL it left behind. Reuses reviewer_eval's corpus, hit rule
and score line; reuses review.agent_a_prompt for the prompt and the llm adapter's
extract_json / schemas for the reply, in the order ClaudeCli.call applies them.
"""
from __future__ import annotations

import argparse
import json
import math
import os
import subprocess
import sys
import tempfile
import time
from pathlib import Path
from typing import Callable, Optional, Sequence

from . import reviewer_eval as re_
from . import schemas
from .adapters.llm import MAX_PROMPT_BYTES, extract_json
from .review import agent_a_prompt
from .state import Finding, HarnessError, PlanInfo

CELLS = (("sonnet-high", "claude-sonnet-5-5", "high"),
         ("opus-high", "claude-opus-5-5", "high"),
         ("opus-max", "claude-opus-5-5", "max"),
         ("fable-high", "claude-fable-5-1", "high"))
BUG_CASES = ("depth-chart-log-after-commit", "playoff-series-invalid-group-by",
             "saved-dc-sum-is-int", "table-dropdown-unencoded-param",
             "team-page-missing-pick-team", "trade-preview-unbounded-cash-years")
CLEAN_CASES = ("clean-navigation-voting-link", "clean-perf-logger-injectable")
RUNS_BUG = 2
RUNS_CLEAN = 1
PER_CALL_CAP_USD = 1.50
BUDGET_CEILING_USD = 25.00
CALL_TIMEOUT_S = 280
EFFORT_LEVELS = ("low", "medium", "high", "xhigh", "max")
EXIT_OK = 0
EXIT_USAGE = 2
EXIT_TRUNCATED = 3

MIN_BUG_RUNS = 8
MAX_ERROR_RATE = 0.25
MECHANISMS = ("flag", "settings")

# Agent A's identity in review.py's `tasks` tuple.
AGENT_A_SOURCE = "code-review"
AGENT_A = "A"

Call = tuple  # (run_idx, case_id, cell_name)


def plan_calls(cells: Sequence[tuple]) -> list[Call]:
    calls: list[Call] = []
    for run_idx in range(1, RUNS_BUG + 1):
        for case_id in BUG_CASES + CLEAN_CASES:
            if case_id in CLEAN_CASES and run_idx > RUNS_CLEAN:
                continue
            for name, _model, _effort in cells:
                calls.append((run_idx, case_id, name))
    return calls


def build_argv(model: str, effort: str, mechanism: str) -> list[str]:
    if effort not in EFFORT_LEVELS:
        raise ValueError(f"unknown effort level {effort!r}; expected one of {EFFORT_LEVELS}")
    if mechanism not in MECHANISMS:
        raise ValueError(f"unknown effort mechanism {mechanism!r}; expected one of {MECHANISMS}")
    if mechanism == "flag":
        effort_args = ["--effort", effort]
    else:
        effort_args = ["--settings", json.dumps({"effortLevel": effort})]
    return ["claude", "-p", "--model", model, *effort_args, "--tools", "",
            "--max-turns", "1", "--output-format", "json",
            "--max-budget-usd", f"{PER_CALL_CAP_USD:.2f}"]


def must_stop(spent: float, cap: float = PER_CALL_CAP_USD,
              ceiling: float = BUDGET_CEILING_USD) -> bool:
    return spent + cap > ceiling + 1e-9


def charged_cost(envelope: Optional[dict]) -> float:
    if not isinstance(envelope, dict) or envelope.get("is_error"):
        return PER_CALL_CAP_USD
    cost = envelope.get("total_cost_usd")
    if isinstance(cost, bool) or not isinstance(cost, (int, float)):
        return PER_CALL_CAP_USD
    if not math.isfinite(cost) or cost < 0:
        return PER_CALL_CAP_USD
    return float(cost)


def parse_findings(envelope: Optional[dict]) -> Optional[list[Finding]]:
    """Agent A's findings from a CLI envelope, or None on any parse or schema failure.
    None, never [], because an empty list would score as a clean pass."""
    if not isinstance(envelope, dict):
        return None
    text = envelope.get("result")
    if not isinstance(text, str):
        return None
    try:
        data = extract_json(text)
        data = schemas.unwrap_findings_envelope(data)
        schemas.validate_findings(data)
        return [Finding(source=AGENT_A_SOURCE, agent=AGENT_A, path=f["path"],
                        line=f["line"], body=f["body"],
                        severity_hint=f.get("severity", "important"),
                        preexisting_hint=bool(f.get("preexisting", False)),
                        bug_class=f.get("bug_class", ""),
                        other_instances=list(f.get("other_instances", [])))
                for f in data]
    except (ValueError, TypeError, KeyError, AttributeError, HarnessError):
        return None


# ---------------------------------------------------------------------------
# Runners
# ---------------------------------------------------------------------------

def subprocess_runner(argv: list[str], prompt: str, timeout: int):
    """Mirror ClaudeCli.call's isolation: neutral temp cwd (no repo CLAUDE.md or rules),
    CLAUDE_HEADLESS=1, and the same input-size cap."""
    if len(prompt.encode()) > MAX_PROMPT_BYTES:
        prompt = prompt.encode()[:MAX_PROMPT_BYTES].decode(errors="ignore") + "\n[TRUNCATED]"
    env = {**os.environ, "CLAUDE_HEADLESS": "1"}
    env.pop("IBL5_USAGE_GATE", None)
    with tempfile.TemporaryDirectory(prefix="tier-measure-") as cwd:
        return subprocess.run(argv, input=prompt, capture_output=True, text=True,
                              timeout=timeout, cwd=cwd, env=env)


class CannedRunner:
    """Replays DIR/<cell>__<case_id>__<run_idx>.json instead of spawning claude. run()
    calls begin() with the call identity first, because the runner itself only sees argv."""

    def __init__(self, directory: Path):
        self.directory = Path(directory)
        self._current: Optional[Call] = None

    def begin(self, call: Call) -> None:
        self._current = call

    def __call__(self, argv: list[str], prompt: str, timeout: int):
        run_idx, case_id, cell = self._current  # type: ignore[misc]
        path = self.directory / f"{cell}__{case_id}__{run_idx}.json"
        try:
            text = path.read_text(encoding="utf-8")
        except OSError:
            return subprocess.CompletedProcess(argv, 1, stdout="", stderr=f"missing {path.name}")
        return subprocess.CompletedProcess(argv, 0, stdout=text, stderr="")


def default_prompt_for(root: Path) -> Callable[[dict], str]:
    cache: dict[str, str] = {}

    def prompt_for(case: dict) -> str:
        cid = case["id"]
        if cid not in cache:
            meta, cls = re_.case_input(root, case["commit"])
            cache[cid] = agent_a_prompt(meta, cls, PlanInfo())
        return cache[cid]

    return prompt_for


# ---------------------------------------------------------------------------
# Driver
# ---------------------------------------------------------------------------

def _read_records(path: Path) -> list[dict]:
    if not path.exists():
        return []
    out = []
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line:
            continue
        try:
            rec = json.loads(line)
        except json.JSONDecodeError:
            continue
        if isinstance(rec, dict):
            out.append(rec)
    return out


def _identity(rec: dict) -> Optional[Call]:
    if "cell" not in rec or "case_id" not in rec or "run_idx" not in rec:
        return None
    return (rec["run_idx"], rec["case_id"], rec["cell"])


def _append(fh, rec: dict) -> None:
    fh.write(json.dumps(rec, sort_keys=True) + "\n")
    fh.flush()


def _execute(call: Call, model: str, effort: str, mechanism: str, case: dict,
             prompt: str, runner) -> dict:
    run_idx, case_id, cell = call
    argv = build_argv(model, effort, mechanism)
    if hasattr(runner, "begin"):
        runner.begin(call)
    t0 = time.monotonic()
    envelope: Optional[dict] = None
    status = "call-error"
    try:
        proc = runner(argv, prompt, CALL_TIMEOUT_S)
    except subprocess.TimeoutExpired:
        status = "timeout"
        proc = None
    wall = time.monotonic() - t0
    if proc is not None and proc.returncode == 0:
        try:
            parsed = json.loads(proc.stdout)
            envelope = parsed if isinstance(parsed, dict) else None
        except (json.JSONDecodeError, TypeError):
            envelope = None
    if envelope is not None and envelope.get("is_error"):
        envelope_ok = False
    else:
        envelope_ok = envelope is not None
    cost = charged_cost(envelope)
    hit: Optional[bool] = None
    n_findings: Optional[int] = None
    if status != "timeout" and envelope_ok:
        findings = parse_findings(envelope)
        if findings is None:
            status = "parse-error"
        else:
            status = "ok"
            n_findings = len(findings)
            if case_id in BUG_CASES:
                hit = re_.case_hit(findings, re_.accepted_ranges(case))
    usage = (envelope or {}).get("usage")
    out_tokens = usage.get("output_tokens") if isinstance(usage, dict) else None
    return {"run_idx": run_idx, "case_id": case_id, "cell": cell, "model": model,
            "effort": effort, "status": status, "hit": hit, "n_findings": n_findings,
            "cost_charged": cost, "wall_s": round(wall, 3), "output_tokens": out_tokens}


def run(root: Path, corpus: dict, out_jsonl: Path, *, cells: Sequence[tuple],
        runner, max_calls: Optional[int], already_spent: float, dry_run: bool,
        mechanism: str = "flag",
        prompt_for: Optional[Callable[[dict], str]] = None) -> int:
    for _name, model, effort in cells:
        build_argv(model, effort, mechanism)          # ValueError before any call
    plan = plan_calls(cells)
    by_cell = {name: (model, effort) for name, model, effort in cells}
    cases = {c["id"]: c for c in [*corpus["cases"], *corpus["clean"]]}
    missing = sorted({cid for _r, cid, _c in plan if cid not in cases})
    if missing:
        raise re_.EvalUsageError(f"corpus lacks case id(s): {', '.join(missing)}")
    if prompt_for is None:
        prompt_for = default_prompt_for(root)

    if dry_run:
        # Build every prompt now so a bad case commit fails at $0.
        for case_id in dict.fromkeys(cid for _r, cid, _c in plan):
            try:
                prompt_for(cases[case_id])
            except Exception as e:
                raise re_.EvalUsageError(f"cannot build prompt for case {case_id}: {e}") from e
        for run_idx, case_id, cell in plan:
            model, effort = by_cell[cell]
            print(f"call run={run_idx} case={case_id} cell={cell} model={model} effort={effort}")
        print(f"planned_calls={len(plan)}")
        print(f"ceiling={BUDGET_CEILING_USD:.2f} per_call_cap={PER_CALL_CAP_USD:.2f}")
        return EXIT_OK

    out_jsonl = Path(out_jsonl)
    existing = _read_records(out_jsonl)
    done = {i for i in map(_identity, existing) if i is not None}
    spent = already_spent + sum(r.get("cost_charged", 0.0) or 0.0 for r in existing
                                if "cell" in r)
    made = 0
    out_jsonl.parent.mkdir(parents=True, exist_ok=True)
    with open(out_jsonl, "a", encoding="utf-8") as fh:
        for call in plan:
            if call in done:
                continue
            if max_calls is not None and made >= max_calls:
                return EXIT_OK
            if must_stop(spent):
                _append(fh, {"budget_truncated": True, "spent": spent})
                return EXIT_TRUNCATED
            _run_idx, case_id, cell = call
            model, effort = by_cell[cell]
            case = cases[case_id]
            rec = _execute(call, model, effort, mechanism, case, prompt_for(case), runner)
            _append(fh, rec)
            spent += rec["cost_charged"]
            made += 1
    return EXIT_OK


# ---------------------------------------------------------------------------
# Summary / report
# ---------------------------------------------------------------------------

def _is_error(rec: dict) -> bool:
    return rec.get("status") != "ok" or (rec.get("cost_charged") or 0.0) >= PER_CALL_CAP_USD


def summarize(records: Sequence[dict]) -> dict:
    by_cell: dict[str, list[dict]] = {}
    for rec in records:
        if "cell" not in rec:
            continue
        by_cell.setdefault(rec["cell"], []).append(rec)
    ordered = [c[0] for c in CELLS if c[0] in by_cell] + [c for c in by_cell
                                                          if c not in {x[0] for x in CELLS}]
    result: dict[str, dict] = {}
    for cell in ordered:
        recs = by_cell[cell]
        bug_ok = [r for r in recs if r.get("case_id") in BUG_CASES and r.get("status") == "ok"]
        clean_ok = [r for r in recs if r.get("case_id") in CLEAN_CASES and r.get("status") == "ok"]
        hits = sum(1 for r in bug_ok if r.get("hit"))
        bug_outcomes = []
        for cid in BUG_CASES:
            runs = [r for r in bug_ok if r["case_id"] == cid]
            bug_outcomes.append(re_.CaseOutcome(
                id=cid, kind="bug", findings=(), degraded_agents=(),
                hit=bool(runs) and all(r.get("hit") for r in runs)))
        clean_outcomes = []
        for cid in CLEAN_CASES:
            n = sum(r.get("n_findings") or 0 for r in clean_ok if r["case_id"] == cid)
            clean_outcomes.append(re_.CaseOutcome(
                id=cid, kind="clean", degraded_agents=(), hit=None,
                findings=tuple(Finding(source=AGENT_A_SOURCE, agent=AGENT_A, path="",
                                       line=0, body="") for _ in range(n))))
        score = re_.compute_score(bug_outcomes, clean_outcomes, v="tier-measure", c=cell)
        rates = []
        for run_idx in range(1, RUNS_BUG + 1):
            run_recs = [r for r in bug_ok if r.get("run_idx") == run_idx]
            rates.append(sum(1 for r in run_recs if r.get("hit")) / len(run_recs)
                         if run_recs else None)
        spread = abs(rates[0] - rates[1]) if None not in rates else None
        completed = [r for r in recs if r.get("status") == "ok"]
        errors = sum(1 for r in recs if _is_error(r))
        error_rate = errors / len(recs)
        reasons = []
        if len(bug_ok) < MIN_BUG_RUNS:
            reasons.append(f"{len(bug_ok)} completed bug runs < {MIN_BUG_RUNS}")
        if error_rate > MAX_ERROR_RATE:
            reasons.append(f"error rate {error_rate:.0%} > {MAX_ERROR_RATE:.0%}")
        result[cell] = {
            "model": recs[0].get("model", ""), "effort": recs[0].get("effort", ""),
            "H": hits / len(bug_ok) if bug_ok else None,
            "H_hits": hits, "H_n": len(bug_ok),
            "S": score, "spread": spread,
            "FP": sum(r.get("n_findings") or 0 for r in clean_ok),
            "C": (sum(r.get("cost_charged") or 0.0 for r in completed) / len(completed)
                  if completed else None),
            "T": sum(r.get("wall_s") or 0.0 for r in recs) / len(recs),
            "completed_bug_runs": len(bug_ok),
            "error_rate": error_rate,
            "inconclusive": bool(reasons),
            "reason": "; ".join(reasons),
        }
    return result


def _fmt(v, spec: str) -> str:
    return "n/a" if v is None else format(v, spec)


def render_markdown(summary: dict, spent: float, truncated: bool) -> str:
    lines = ["| cell | model | effort | bug runs | H | S | spread | FP | C (USD/call) "
             "| T (s/call) | verdict |",
             "|---|---|---|---|---|---|---|---|---|---|---|"]
    for cell, m in summary.items():
        verdict = f"inconclusive: {m['reason']}" if m["inconclusive"] else "ok"
        h = "n/a" if m["H"] is None else f"{m['H']:.2f} ({m['H_hits']}/{m['H_n']})"
        s = m["S"]
        lines.append(f"| {cell} | {m['model']} | {m['effort']} | {m['completed_bug_runs']} | {h} "
                     f"| {s.hits}/{s.scored} | {_fmt(m['spread'], '.2f')} | {m['FP']} "
                     f"| {_fmt(m['C'], '.3f')} | {_fmt(m['T'], '.1f')} | {verdict} |")
    lines.append("")
    lines.append(f"total spent: ${spent:.2f}" + (" (budget truncated)" if truncated else ""))
    lines.append("")
    for m in summary.values():
        lines.append(re_.format_score_line(m["S"]))
    return "\n".join(lines) + "\n"


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def _build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(prog="tier_measure")
    sub = p.add_subparsers(dest="cmd", required=True)
    r = sub.add_parser("run")
    r.add_argument("--repo-root", required=True)
    r.add_argument("--out", required=True)
    r.add_argument("--dry-run", action="store_true")
    r.add_argument("--canned", metavar="DIR", default=None)
    r.add_argument("--max-calls", type=int, default=None)
    r.add_argument("--already-spent", type=float, default=0.0)
    r.add_argument("--cell-model", action="append", default=[], metavar="CELL=ID")
    r.add_argument("--drop-cell", action="append", default=[], metavar="CELL")
    r.add_argument("--effort-mechanism", choices=MECHANISMS, default="flag")
    rep = sub.add_parser("report")
    rep.add_argument("--in", dest="in_path", required=True)
    rep.add_argument("--md", required=True)
    return p


def _apply_cell_overrides(parser: argparse.ArgumentParser, args) -> list[tuple]:
    names = {c[0] for c in CELLS}
    models = {}
    for item in args.cell_model:
        cell, sep, model = item.partition("=")
        if not sep or cell not in names or not model:
            parser.error(f"--cell-model expects CELL=ID with CELL in {sorted(names)}: {item!r}")
        models[cell] = model
    for cell in args.drop_cell:
        if cell not in names:
            parser.error(f"--drop-cell: unknown cell {cell!r}")
    return [(n, models.get(n, m), e) for n, m, e in CELLS if n not in args.drop_cell]


def main(argv: Optional[Sequence[str]] = None) -> int:
    parser = _build_parser()
    args = parser.parse_args(argv)
    if args.cmd == "report":
        records = _read_records(Path(args.in_path))
        spent = sum(r.get("cost_charged", 0.0) or 0.0 for r in records if "cell" in r)
        truncated = any(r.get("budget_truncated") for r in records)
        md = render_markdown(summarize(records), spent, truncated)
        Path(args.md).write_text(md, encoding="utf-8")
        return EXIT_OK
    cells = _apply_cell_overrides(parser, args)
    root = Path(args.repo_root).resolve()
    runner = CannedRunner(Path(args.canned)) if args.canned else subprocess_runner
    try:
        corpus = re_.load_corpus(root / re_.DEFAULT_CORPUS)
        return run(root, corpus, Path(args.out), cells=cells, runner=runner,
                   max_calls=args.max_calls, already_spent=args.already_spent,
                   dry_run=args.dry_run, mechanism=args.effort_mechanism)
    except (re_.EvalUsageError, ValueError) as e:
        print(f"tier_measure: {e}", file=sys.stderr)
        return EXIT_USAGE


if __name__ == "__main__":
    sys.exit(main())
