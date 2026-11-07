"""The owner's reviewer bar: a pure verdict over eval counts, plus a record check.

The fix round lets the fixer touch a security-audit finding only when `check` passes.
`check` fails closed on every state except a fresh, self-consistent, passing record:
too few confirmed cases, a missing or unreadable record, a record scored against another
reviewer surface (`v`) or corpus (`c`), or a score line that its own cache entries do not
reproduce. See ibl5/docs/reviewer-eval.md § Reviewer bar.

`reviewer_eval` is imported lazily, so `fix_round` can import `Verdict` without loading
the eval module.
"""
from __future__ import annotations

import json
import os
from dataclasses import dataclass
from pathlib import Path
from typing import Callable, Optional

MIN_CASES = 30
MIN_RECALL_PCT = 60
MAX_CLEAN_FLAGGED_PCT = 20
MAX_DEGRADED = 0
RECORD_BASENAME = "reviewer-bar.json"
RECORD_SCHEMA = 1


@dataclass(frozen=True)
class Verdict:
    passed: bool
    reason: str


def evaluate(*, hits, scored, clean_flagged, clean_total, degraded, confirmed_total) -> Verdict:
    if scored < MIN_CASES:
        return Verdict(False, f"too-few-cases: {scored} < {MIN_CASES}")
    if scored != confirmed_total:
        return Verdict(False, f"scored-mismatch: {scored} scored vs {confirmed_total} confirmed")
    if degraded > MAX_DEGRADED:
        return Verdict(False, f"degraded: {degraded}")
    if hits * 100 < MIN_RECALL_PCT * scored:
        return Verdict(False, f"recall: {hits}/{scored} < {MIN_RECALL_PCT}%")
    if clean_total <= 0:
        return Verdict(False, "no-clean-cases")
    if clean_flagged * 100 > MAX_CLEAN_FLAGGED_PCT * clean_total:
        return Verdict(False, f"clean-flagged: {clean_flagged}/{clean_total} > {MAX_CLEAN_FLAGGED_PCT}%")
    return Verdict(True, f"pass: recall {hits}/{scored}, clean flagged {clean_flagged}/{clean_total}")


def counts(score) -> dict:
    """The `evaluate` keyword ints for a `reviewer_eval.Score`, minus `confirmed_total`."""
    return {"hits": int(score.hits), "scored": int(score.scored),
            "clean_flagged": int(score.clean_flagged), "clean_total": int(score.clean_total),
            "degraded": int(score.degraded)}


def harness_root() -> str:
    """The checkout holding this harness copy (the main checkout at run time, ADR-0092)."""
    return str(Path(__file__).resolve().parents[3])


def default_record_path() -> str:
    """The main checkout's harness state dir, so every worktree reads one record."""
    from . import outcome_log
    return os.path.join(os.path.dirname(outcome_log.resolve_log_path(os.getcwd())),
                        RECORD_BASENAME)


def confirmed_count(corpus: dict) -> int:
    """Confirmed bug cases, picked with the predicate `run_eval` scores by."""
    return sum(1 for c in corpus["cases"] if c["status"] == "confirmed")


def check(root, record_path, *,
          surface_hash: Optional[Callable[[Path], str]] = None,
          load_corpus: Optional[Callable[[Path], dict]] = None,
          rescore: Optional[Callable[[dict, dict], object]] = None) -> Verdict:
    """Pass only on a fresh, self-consistent record whose re-scored entries meet the bar."""
    try:
        from . import reviewer_eval
        root = Path(root)
        if load_corpus is None:
            def load_corpus(r: Path) -> dict:
                return reviewer_eval.load_corpus(Path(r) / reviewer_eval.DEFAULT_CORPUS)
        if surface_hash is None:
            surface_hash = reviewer_eval.surface_hash_worktree
        corpus = load_corpus(root)
        confirmed = confirmed_count(corpus)
        if confirmed < MIN_CASES:
            return Verdict(False, f"too-few-cases: {confirmed} < {MIN_CASES}")
        if not os.path.exists(record_path):
            return Verdict(False, f"missing: {record_path}")
        try:
            with open(record_path, encoding="utf-8") as fh:
                record = json.load(fh)
        except (OSError, ValueError) as e:
            return Verdict(False, f"unreadable: {e!r}"[:200])
        if not isinstance(record, dict) or record.get("schema") != RECORD_SCHEMA:
            return Verdict(False, "unreadable: not a schema-1 record")
        current_v = surface_hash(root)
        if record.get("v") != current_v:
            return Verdict(False, f"stale-v: record {record.get('v')} vs checkout {current_v}")
        current_c = reviewer_eval.corpus_digest(corpus)
        if record.get("c") != current_c:
            return Verdict(False, f"stale-c: record {record.get('c')} vs corpus {current_c}")
        if rescore is None:
            def rescore(corp: dict, entries: dict) -> object:
                return reviewer_eval.score_from_entries(corp, entries, v=current_v)
        score = rescore(corpus, record.get("entries") or {})
        if reviewer_eval.format_score_line(score) != record.get("line"):
            return Verdict(False, "record-inconsistent: entries do not reproduce the score line")
        return evaluate(**counts(score), confirmed_total=confirmed)
    except Exception as e:  # noqa: BLE001 - the bar fails closed on any error
        return Verdict(False, f"error: {e!r}"[:200])
