"""Tests for harness/holdrepeat_replay.py: corpus replay of the repeat-hold detector."""
from __future__ import annotations

import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import holdrepeat_replay as rp

FIXTURE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "fixtures", "holdrepeat_replay.json")


def _runs(slug=None):
    with open(FIXTURE, encoding="utf-8") as fh:
        return [r for r in json.load(fh) if slug is None or r["slug"] == slug]


def test_replay_structural_beats_strict_on_cash_cy():
    report = rp.replay(_runs("cash-cy"))
    assert report["strict_repeats"] == 1          # run 4 only
    assert report["structural_repeats"] == 5      # runs 2-4 and 7-8
    assert report["would_dm"] == 2                # one per lifecycle; the armed run 5 reset it


def test_replay_steady_slug_repeats_twice_dms_once():
    report = rp.replay(_runs("steady"))
    assert report["structural_repeats"] == 2
    assert report["would_dm"] == 1


def test_replay_env_only_slug_never_counts():
    report = rp.replay(_runs("env-only"))
    assert report["structural_repeats"] == 0
    assert report["would_dm"] == 0


def test_replay_since_filter_primes_state():
    report = rp.replay(_runs("cash-cy"), since="20260922")   # run 3's date
    assert report["held_runs"] == 5                           # runs 3, 4, 6, 7, 8
    assert report["structural_repeats"] == 4                  # run 3 still repeats run 2 (out of window)
    assert report["would_dm"] == 1                            # run 2's DM is out of window; run 7's counts


def test_replay_whole_fixture_counts_3plus_slugs():
    report = rp.replay(_runs())
    assert report["repeat_runs_in_slugs_with_3plus"] == 14
    assert report["caught_in_slugs_with_3plus"] == 7


def test_load_runs_parses_dirname_and_skips_corrupt(tmp_path):
    good = tmp_path / "live-a-b-20261001-120000-42"
    good.mkdir()
    (good / "result.json").write_text(json.dumps(
        {"arm": {"armed": False, "conditions": [{"number": 7, "name": "x", "blocked": True, "reason": "r"}]}}))
    bad = tmp_path / "live-c-20261001-120000-43"
    bad.mkdir()
    (bad / "result.json").write_text("{not json")
    no_arm = tmp_path / "live-d-20261001-120000-44"
    no_arm.mkdir()
    (no_arm / "result.json").write_text("{}")
    (tmp_path / "live-e-20261001-120000-45").mkdir()
    runs = rp.load_runs(str(tmp_path))
    assert len(runs) == 1
    assert runs[0]["slug"] == "a-b" and runs[0]["ts"] == "20261001-120000"
    assert runs[0]["armed"] is False and runs[0]["conditions"][0]["number"] == 7
