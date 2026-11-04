"""Closer A/B readout: fold, population, human-touch rule, reverts, CLI."""
from __future__ import annotations

import datetime as dt
import os
import subprocess
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from harness import ab_readout

HERE = os.path.dirname(os.path.abspath(__file__))
FIXTURE_DIR = os.path.join(HERE, "fixtures", "ab_readout")
WRAPPER = os.path.join(HERE, "..", "..", "..", "bin", "postplan-ab-readout")
UTC = dt.timezone.utc
NOW = dt.datetime(2026, 12, 1, tzinfo=UTC)

# Merge times chosen so the time-to-merge values are easy to check by hand.
MERGED = {
    101: "2026-10-20T12:00:00Z",  # first_start 08:00 -> 4 h
    102: "2026-10-21T10:00:00Z",  # first_start 10-20 10:00 -> 24 h
    201: "2026-10-20T12:30:00Z",  # first_start 06:00 -> 6.5 h
    202: "2026-10-23T08:30:00Z",  # first_start 00:00 -> 8.5 h
}


def _ts(value: str) -> dt.datetime:
    return dt.datetime.fromisoformat(value.replace("Z", "+00:00"))


def _pr(n, *, commits=(), comments=(), reviews=()):
    if n not in MERGED:
        return {"state": "OPEN", "mergedAt": None, "mergeCommit": None, "title": "t",
                "commits": list(commits), "comments": list(comments),
                "reviews": list(reviews)}
    return {"state": "MERGED", "mergedAt": MERGED[n], "mergeCommit": {"oid": f"oid{n}"},
            "title": "t", "commits": list(commits), "comments": list(comments),
            "reviews": list(reviews)}


def _no_log(*_a):
    return []


def _readout(fetch_pr=None, git_log=_no_log, now=NOW):
    return ab_readout.build_readout(
        FIXTURE_DIR, fetch_pr=fetch_pr or (lambda n: _pr(n)), git_log=git_log, now=now)


def _rec(result, slug):
    return next(r for r in result["records"] if r["slug"] == slug)


def _commit(when, login="a-jay85"):
    return {"committedDate": when, "authors": [{"login": login}]}


def _comment(when, login="a-jay85", body="looks fine"):
    return {"createdAt": when, "author": {"login": login}, "body": body}


# ── fold + population ───────────────────────────────────────────────────────

def test_fold_rows_per_pr():
    rec = _rec(_readout(), "c-two")
    assert rec["runs"] == 3
    assert rec["cost_usd"] == 2.5
    assert rec["first_start"] == _ts("2026-10-20T10:00:00Z")
    assert rec["pr"] == 102
    assert rec["last_terminal"] == "shipped-armed"
    assert len(rec["windows"]) == 3
    assert rec["windows"][0] == (_ts("2026-10-20T10:00:00Z"), _ts("2026-10-20T10:22:00Z"))


def test_population_is_enrollment_file():
    result = _readout()
    slugs = {r["slug"] for r in result["records"]}
    assert slugs == {"c-one", "c-two", "c-three", "h-one", "h-two"}
    assert "stray-slug" not in slugs
    # The foreign-schema row costs 99 and would show up on c-one if it were folded.
    assert _rec(result, "c-one")["cost_usd"] == 1.0
    assert _rec(result, "c-one")["runs"] == 1
    assert result["summary"]["closer"]["n"] == 3
    assert result["summary"]["harness"]["n"] == 2


def test_unparseable_rows_skipped():
    rows = ab_readout.read_outcome_rows(FIXTURE_DIR)
    # 11 lines: 1 blank, 1 corrupt, 1 foreign schema. 8 usable rows remain.
    assert len(rows) == 8
    assert all(r["schema"] == "ab-outcome/1" for r in rows)


# ── per-arm numbers ─────────────────────────────────────────────────────────

def test_medians_per_arm_fixture():
    summary = _readout()["summary"]
    closer, harness = summary["closer"], summary["harness"]
    # closer costs: 1.0, 2.5, 3.0. harness costs: 0.4, 0.6.
    assert closer["cost_median_usd"] == 2.5
    assert closer["cost_total_usd"] == 6.5
    assert harness["cost_median_usd"] == 0.5
    assert harness["cost_total_usd"] == 1.0
    # closer merged: 4 h and 24 h (c-three stays open). harness merged: 6.5 h and 8.5 h.
    assert closer["merged"] == 2 and closer["unmerged"] == 1
    assert closer["time_to_merge_median_h"] == 14.0
    assert harness["merged"] == 2 and harness["unmerged"] == 0
    assert harness["time_to_merge_median_h"] == 7.5


# ── human touches ───────────────────────────────────────────────────────────

def _c_two_inputs():
    rec = _rec(_readout(), "c-two")
    return rec["first_start"], rec["windows"]


def test_human_touch_counts_outside_windows_only():
    first_start, windows = _c_two_inputs()
    pr = _pr(102,
             commits=[
                 _commit("2026-10-20T14:10:00Z"),           # inside the 14:00 run window
                 _commit("2026-10-20T12:00:00Z"),           # between two runs: counts
             ],
             comments=[
                 _comment("2026-10-20T09:00:00Z"),          # before first_start
                 _comment("2026-10-20T12:00:00Z", login="renovate[bot]"),
                 _comment("2026-10-20T12:00:00Z",
                          body="<!-- pr-ready-verdict --> READY"),
             ])
    assert ab_readout.count_human_touches(pr, first_start, windows) == 1


def test_human_touch_near_miss_authors_and_markers():
    first_start, windows = _c_two_inputs()
    pr = _pr(102, comments=[
        _comment("2026-10-20T12:00:00Z", login="botany"),                # human login
        _comment("2026-10-20T12:05:00Z", body="the pr-ready-verdict was READY"),
        _comment("2026-10-20T10:00:00Z"),                                # == first_start
    ])
    assert ab_readout.count_human_touches(pr, first_start, windows) == 2


def test_armed_without_human_requires_last_row_armed():
    def fetch(n):
        return _pr(n)  # merged PRs, no commits/comments/reviews: 0 touches

    result = _readout(fetch_pr=fetch)
    assert _rec(result, "c-two")["human_touches"] == 0
    assert _rec(result, "c-two")["armed_without_human"] is True
    # c-three never merges in the fixture. Give it a merge: last row is "shipped-held".
    MERGED[103] = "2026-10-22T20:00:00Z"
    try:
        held = _readout(fetch_pr=fetch)
    finally:
        del MERGED[103]
    assert _rec(held, "c-three")["human_touches"] == 0
    assert _rec(held, "c-three")["armed_without_human"] is False


# ── reverts ─────────────────────────────────────────────────────────────────

def test_revert_detected_and_pending_window():
    merged = _ts(MERGED[101])
    seen = []

    def hit(since, until, needles):
        seen.append((since, until, tuple(needles)))
        return ["deadbeef"]

    reverted = _readout(git_log=hit, now=merged + dt.timedelta(days=3))
    assert _rec(reverted, "c-one")["revert"] == "reverted"
    since, until, needles = next(s for s in seen if "oid101" in s[2])
    assert _ts(until) - _ts(since) == dt.timedelta(days=14)
    assert "Reverts a-jay85/IBL5#101" in needles

    pending = _readout(git_log=_no_log, now=merged + dt.timedelta(days=3))
    assert _rec(pending, "c-one")["revert"] == "pending"
    assert pending["summary"]["closer"]["pending"] >= 1
    assert pending["summary"]["closer"]["reverted"] == 0

    clean = _readout(git_log=_no_log, now=merged + dt.timedelta(days=15))
    assert _rec(clean, "c-one")["revert"] == "clean"


# ── CLI ─────────────────────────────────────────────────────────────────────

def _run(*args):
    return subprocess.run([WRAPPER, *args], capture_output=True, text=True, timeout=60)


def test_cli_rejects_unknown_flag_and_missing_value():
    bad = _run("--bogus")
    assert bad.returncode == 2
    assert "usage" in bad.stderr
    bare = _run("--state-dir")
    assert bare.returncode == 2
    assert "usage" in bare.stderr


def test_cli_offline_prints_n_per_arm():
    proc = _run("--offline", "--state-dir", FIXTURE_DIR)
    assert proc.returncode == 0, proc.stderr
    assert "closer: n=3" in proc.stdout
    assert "harness: n=2" in proc.stdout
    assert "n/a" in proc.stdout
