"""Replay the body-only recheck eligibility predicate over the `out/live-*` run corpus.

Offline mode reads artifacts only and prints a report (exit 0):
  python3 replay_body_only_recheck.py --harness-root <dir containing harness/> \
      [--out-dir <harness-root>/out] [--json PATH]

For every `fidelity.rounds[]` entry whose outcome is `body-only` it calls the
production predicate `fidelity.body_recheck_eligibility` on the artifacts the real run
left behind, then reports how many rounds are eligible, why the rest are not, which
eligible rounds the Opus re-review actually held (`NOT READY`), and how the predicate
compares with a content heuristic (`_CODE_SUBJECT_RE`) over the prior review's bullets.

A run dir holds one plan hash, so the `plan-changed` arm is always passed (h, h) here and
never fires. A plan-blind run (no `plan_hash` in result.json) is passed (h, "") instead and
is counted under `plan-hash-missing`.

Live mode adds the real Sonnet recheck call over the eligible rounds (it costs money):
  python3 replay_body_only_recheck.py --harness-root . --live [--limit N] --json PATH

Rounds are taken actual-`NOT READY` first, then by run dir name; `--limit 0` (default)
takes every eligible round. Exit 2 when the recheck would have armed a round the Opus
re-review held (`dangerous`), else 0. Stdlib only.
"""
import argparse
import glob
import json
import os
import re
import sys
from collections import Counter
from types import SimpleNamespace

_CODE_SUBJECT_RE = re.compile(
    r"(?i)(unplanned|scope creep|baseline|-snapshots|\.claude/rules/|\.github/workflows/"
    r"|migration|phpstan|(?:absent|missing) from the diff|not in the diff"
    r"|phase \d+ (?:has no|is missing|was not))")
_TREE_LINE_RE = re.compile(r"^REVIEWED_TREE:\s*([0-9a-f]{40})\s*$", re.M)
_ATTEMPT_RE = re.compile(r"attempt(\d+)\.txt$")
_DIFF_HEADER_RE = re.compile(r"^diff --git a/(\S+) b/(\S+)", re.M)
_LIVE_PURPOSE = "plan-fidelity-body-recheck-replay"
_LIVE_DIRNAME = "replay-body-recheck"
_HELD_VERDICT = "NOT READY"
_READY_VERDICTS = ("READY", "READY WITH NOTES")


def _load(harness_root: str):
    sys.path.insert(0, os.path.abspath(harness_root))
    from harness import fidelity, llm_calls, schemas  # noqa: E402  (tree chosen by --harness-root)
    from harness.state import HarnessError  # noqa: E402
    return SimpleNamespace(fidelity=fidelity, llm_calls=llm_calls, schemas=schemas,
                           HarnessError=HarnessError)


def _read(path: str):
    try:
        with open(path, encoding="utf-8", errors="replace") as fh:
            return fh.read()
    except OSError:
        return None


def _latest_attempt(run_dir: str, stem: str):
    """Path of the highest-numbered `<stem>-attempt<N>.txt`, or None."""
    best, best_n = None, -1
    for path in glob.glob(os.path.join(glob.escape(run_dir), glob.escape(stem) + "-attempt*.txt")):
        m = _ATTEMPT_RE.search(path)
        if m and int(m.group(1)) > best_n:
            best, best_n = path, int(m.group(1))
    return best


def _packet_dir(run_dir: str, n: int) -> str:
    return os.path.join(run_dir, "fidelity-packet" if n == 1 else f"fidelity-packet-{n}")


def _tree_of(context_path: str):
    """(found, tree). found is False when context.md is missing; tree is None when the
    file has no REVIEWED_TREE line."""
    text = _read(context_path)
    if text is None:
        return False, None
    m = _TREE_LINE_RE.search(text)
    return True, (m.group(1) if m else None)


def _round_sources(run_dir: str, n: int):
    """Artifacts for round n, or None when a required one is missing.

    Required for the offline predicate: the prior review text, the prior packet's
    context.md and the re-review packet's context.md. Body and diff paths are returned
    for live mode and read only there.
    """
    stem = ("raw-plan-fidelity-review" if n == 1
            else f"raw-plan-fidelity-re-review-{n}")
    raw = _latest_attempt(run_dir, stem)
    prior_text = _read(raw) if raw else None
    prior = _packet_dir(run_dir, n)
    after = _packet_dir(run_dir, n + 1)
    prior_found, prior_tree = _tree_of(os.path.join(prior, "context.md"))
    after_found, tree_now = _tree_of(os.path.join(after, "context.md"))
    if prior_text is None or not prior_found or not after_found or tree_now is None:
        return None
    return {"prior_text": prior_text, "prior_tree": prior_tree, "tree_now": tree_now,
            "prior_body_path": os.path.join(prior, "pr-body.md"),
            "new_body_path": os.path.join(after, "pr-body.md"),
            "diff_path": os.path.join(after, "diff.patch")}


def _prior_bullets(h, text: str) -> list[str]:
    """Finding bullets of a prior review, independent of whether the predicate accepted it."""
    fid = h.fidelity
    pre = (text or "").split(fid.DIGEST_CUT, 1)[0]
    body = [ln for ln in pre.splitlines() if not fid.VERDICT_RE.match(ln)]
    items = fid._group_findings(fid._findings_section(body) or [])
    return [item for _, item in items]


def replay(harness_root: str, out_dir: str) -> dict:
    """Offline report over every `body-only` round under out_dir/live-*."""
    h = _load(harness_root)
    reasons: Counter = Counter()
    eligible_verdicts: Counter = Counter()
    eligible_rounds: list[dict] = []
    not_ready_eligible: list[dict] = []
    heuristic_pure = 0
    given_up: list[dict] = []
    disagreements: list[dict] = []
    total = 0
    for result_path in sorted(glob.glob(os.path.join(glob.escape(out_dir), "live-*", "result.json"))):
        run_dir = os.path.dirname(result_path)
        name = os.path.basename(run_dir)
        try:
            with open(result_path, encoding="utf-8") as fh:
                fid = (json.load(fh) or {}).get("fidelity") or {}
        except (OSError, ValueError, AttributeError):
            continue
        plan_hash = fid.get("plan_hash") or None
        for rnd in fid.get("rounds") or []:
            if not isinstance(rnd, dict) or rnd.get("outcome") != "body-only":
                continue
            n = rnd.get("round")
            if not isinstance(n, int):
                continue
            total += 1
            actual = rnd.get("verdict")
            src = _round_sources(run_dir, n)
            if src is None:
                reasons["artifact-missing"] += 1
                continue
            findings, reason = h.fidelity.body_recheck_eligibility(
                src["prior_text"], src["prior_tree"], src["tree_now"],
                plan_hash, plan_hash or "")
            bullets = findings if findings is not None else _prior_bullets(h, src["prior_text"])
            matches_code = any(_CODE_SUBJECT_RE.search(b) for b in bullets)
            pure = bool(bullets) and not matches_code
            if pure:
                heuristic_pure += 1
            if findings is None:
                reasons[reason] += 1
                if pure:
                    given_up.append({"run": name, "round": n, "reason": reason,
                                     "actual": actual})
                continue
            eligible_verdicts[str(actual)] += 1
            row = {"run": name, "round": n, "actual": actual, "findings": findings}
            eligible_rounds.append({**row, "sources": {k: v for k, v in src.items()
                                                       if k.endswith("_path")}})
            if actual == _HELD_VERDICT:
                not_ready_eligible.append(row)
            if matches_code:
                disagreements.append(row)
    return {"harness_root": os.path.abspath(harness_root), "out_dir": out_dir,
            "rounds_total": total, "eligible": len(eligible_rounds),
            "ineligible_by_reason": dict(reasons),
            "eligible_verdicts": dict(eligible_verdicts),
            "not_ready_eligible": not_ready_eligible,
            "content_heuristic_pure_body": heuristic_pure,
            "coverage_given_up": len(given_up), "coverage_given_up_rounds": given_up,
            "disagreements": disagreements, "eligible_rounds": eligible_rounds,
            "plan_hash_note": "plan-changed arm passed (h, h): never fires here; plan-blind runs show as plan-hash-missing"}


def make_llm(out_dir: str):
    """The same adapter runner.py builds for a live run; ledger and raw replies under
    <out_dir>/replay-body-recheck/. A module-level factory so tests can monkeypatch it."""
    from harness.adapters.llm import ClaudeCli
    from harness.state import UsageLedger
    ledger_dir = os.path.join(out_dir, _LIVE_DIRNAME)
    os.makedirs(ledger_dir, exist_ok=True)
    return ClaudeCli(UsageLedger(), out_dir=ledger_dir)


def _name_status(diff_text: str) -> str:
    """`M\\t<path>` lines from the `diff --git` headers, first appearance order."""
    seen: list[str] = []
    for m in _DIFF_HEADER_RE.finditer(diff_text or ""):
        if m.group(2) not in seen:
            seen.append(m.group(2))
    return "\n".join(f"M\t{p}" for p in seen)


def _live_order(rows: list[dict]) -> list[dict]:
    return sorted(rows, key=lambda r: (0 if r["actual"] == _HELD_VERDICT else 1,
                                       r["run"], r["round"]))


def replay_live(report: dict, limit: int, out_dir: str) -> dict:
    """Run the real recheck call over the offline report's eligible rounds."""
    h = _load(report["harness_root"])
    chosen = _live_order(report["eligible_rounds"])
    if limit > 0:
        chosen = chosen[:limit]
    llm = make_llm(out_dir)
    rows: list[dict] = []
    skipped: Counter = Counter()
    try:
        for er in chosen:
            src = er["sources"]
            prior_body = _read(src["prior_body_path"])
            new_body = _read(src["new_body_path"])
            diff_text = _read(src["diff_path"])
            if prior_body is None or new_body is None or diff_text is None:
                skipped["artifact-missing"] += 1
                continue
            findings = er["findings"]
            hunks = h.llm_calls.body_hunks(prior_body, new_body)
            name_status = _name_status(diff_text)
            # Same path selection as fidelity.body_recheck: cited by a finding or an added line.
            cited = h.fidelity.cited_diff_paths(name_status, findings, hunks)
            evidence = h.llm_calls.diff_sections_for(diff_text, cited)
            prompt = h.llm_calls.pr_body_recheck_prompt(
                findings, hunks, prior_body, new_body, name_status, evidence)
            fids = [f"F{i}" for i in range(1, len(findings) + 1)]
            hids = [hk["id"] for hk in hunks]
            error_kind = None
            try:
                data = llm.call(_LIVE_PURPOSE, "sonnet", prompt,
                                h.schemas.body_recheck_validator(fids, hids),
                                normalizer=h.schemas.unwrap_body_recheck_envelope)
                would_arm = h.schemas.body_recheck_passed(data)
            except h.HarnessError as exc:
                would_arm, data, error_kind = False, None, exc.kind
            rows.append({"run": er["run"], "round": er["round"], "actual": er["actual"],
                         "decision": "would_arm" if would_arm else "would_hold",
                         "error_kind": error_kind, "findings": findings, "reply": data})
    finally:
        close = getattr(llm, "close", None)
        if close:
            close()
    dangerous = [r for r in rows if r["decision"] == "would_arm" and r["actual"] == _HELD_VERDICT]
    coverage_loss = [r for r in rows if r["decision"] == "would_hold"
                     and r["actual"] in _READY_VERDICTS]
    totals = getattr(getattr(llm, "ledger", None), "totals", None)
    return {"limit": limit, "called": len(rows), "skipped": dict(skipped), "rows": rows,
            "would_arm": sum(r["decision"] == "would_arm" for r in rows),
            "would_hold": sum(r["decision"] == "would_hold" for r in rows),
            "errors_by_kind": dict(Counter(r["error_kind"] for r in rows if r["error_kind"])),
            "dangerous": dangerous, "coverage_loss": coverage_loss,
            "usage": totals() if callable(totals) else None}


def _first_line(bullet: str) -> str:
    """First line of a finding bullet without its list marker, capped for the summary."""
    return re.sub(r"^\s*[-*]\s*", "", bullet.splitlines()[0] if bullet else "")[:200]


def render(report: dict, live: dict | None = None) -> str:
    out = [f"rounds_total: {report['rounds_total']}",
           f"eligible: {report['eligible']}",
           f"ineligible_by_reason: {json.dumps(report['ineligible_by_reason'], sort_keys=True)}",
           f"eligible_verdicts (actual re-review): "
           f"{json.dumps(report['eligible_verdicts'], sort_keys=True)}",
           f"not_ready_eligible: {len(report['not_ready_eligible'])}"]
    for r in report["not_ready_eligible"]:
        out.append(f"  {r['run']} round {r['round']}")
        out.extend(f"    - {_first_line(b)}" for b in r["findings"])
    out.append(f"content_heuristic_pure_body: {report['content_heuristic_pure_body']}")
    out.append(f"coverage_given_up: {report['coverage_given_up']} "
               "(heuristic-pure rounds the predicate declined)")
    for r in report["coverage_given_up_rounds"]:
        out.append(f"  {r['run']} round {r['round']}: {r['reason']} (actual {r['actual']})")
    out.append(f"disagreements: {len(report['disagreements'])} "
               "(eligible rounds with a code-subject bullet)")
    for r in report["disagreements"]:
        out.append(f"  {r['run']} round {r['round']} (actual {r['actual']})")
        out.extend(f"    - {_first_line(b)}" for b in r["findings"])
    out.append(f"note: {report['plan_hash_note']}")
    if live is not None:
        out.append(f"live: called {live['called']} would_arm {live['would_arm']} "
                   f"would_hold {live['would_hold']} skipped "
                   f"{json.dumps(live['skipped'], sort_keys=True)} "
                   f"errors {json.dumps(live['errors_by_kind'], sort_keys=True)}")
        if live.get("usage"):
            out.append(f"live usage: {json.dumps(live['usage'], sort_keys=True)}")
        out.append(f"dangerous (would_arm, actual NOT READY): {len(live['dangerous'])}")
        for r in live["dangerous"]:
            out.append(f"  {r['run']} round {r['round']}")
            out.extend(f"    - {_first_line(b)}" for b in r["findings"])
        out.append(f"coverage_loss (would_hold, actual READY): {len(live['coverage_loss'])}")
        for r in live["coverage_loss"]:
            out.append(f"  {r['run']} round {r['round']} (actual {r['actual']}, "
                       f"{r['error_kind'] or 'unresolved'})")
    return "\n".join(out)


def main(argv=None) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--harness-root")
    ap.add_argument("--out-dir")
    ap.add_argument("--json")
    ap.add_argument("--live", action="store_true",
                    help="call the real Sonnet recheck over the eligible rounds (costs money)")
    ap.add_argument("--limit", type=int, default=0,
                    help="with --live: first N eligible rounds, 0 = all")
    args = ap.parse_args(argv)
    if not args.harness_root:
        ap.error("--harness-root is required")
    harness_root = os.path.abspath(args.harness_root)
    out_dir = args.out_dir or os.path.join(harness_root, "out")
    report = replay(harness_root, out_dir)
    live = replay_live(report, args.limit, out_dir) if args.live else None
    if args.json:
        data = {k: v for k, v in report.items() if k != "eligible_rounds"} if live is None \
            else {**{k: v for k, v in report.items() if k != "eligible_rounds"}, "live": live}
        with open(args.json, "w", encoding="utf-8") as fh:
            json.dump(data, fh, indent=2)
    print(render(report, live))
    return 2 if live is not None and live["dangerous"] else 0


if __name__ == "__main__":
    sys.exit(main())
