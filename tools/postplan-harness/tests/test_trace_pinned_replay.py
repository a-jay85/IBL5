"""Replay the five engine golden-snapshot PRs against the `trace-pinned` token.

PRs #3908, #3918, #3949, #4008 and #4040 ported JSB foul and outcome routines and the owner
merged each one by hand, because condition (5) holds every golden change in a headless run
and condition (7) held their `golden-new` plan token. As merged they carry no trace evidence,
so they stay held. Each `engine-golden-*` corpus entry holds the PR's file list, pasted from
`gh pr diff <N> --name-only -R a-jay85/IBL5`."""
import json

import pytest

from harness import arming_replay
from harness.arming_replay_driver import decode
from harness.armable import ArmInputs, evaluate, second_review_candidates
from harness.state import Classification, Finding

ENGINE_PRS = ("3908", "3918", "3949", "4008", "4040")


def _corpus():
    return json.loads(arming_replay.CORPUS_PATH.read_text(encoding="utf-8"))


def _engine_records():
    records = {r["id"]: r for r in _corpus() if r["id"].startswith("engine-golden-")}
    assert sorted(records) == [f"engine-golden-{n}" for n in ENGINE_PRS]
    return records


def _decode(record) -> ArmInputs:
    return decode(record, ArmInputs, Classification, Finding)


def _blocked(decision):
    return {c.number for c in decision.conditions if c.blocked}


@pytest.mark.parametrize("pr", ENGINE_PRS)
def test_as_merged_engine_prs_stay_held(pr):
    inp = _decode(_engine_records()[f"engine-golden-{pr}"])
    decision = evaluate(inp)
    blocked = _blocked(decision)
    assert not decision.armed
    assert {5, 7} <= blocked
    by_number = {c.number: c for c in decision.conditions}
    assert by_number[5].name == "golden-snapshot-headless"
    assert by_number[7].name == "plan-auto-merge-hold"
    assert second_review_candidates(inp, decision.conditions) == {}
