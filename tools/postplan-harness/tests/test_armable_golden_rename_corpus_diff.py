"""Corpus diff: the rename-aware golden flags flip nothing on the arming replay corpus."""
import json

from harness import armable
from harness.arming_replay import CORPUS_PATH
from harness.arming_replay_driver import decode
from harness.state import Classification, Finding


def test_replay_corpus_golden_flags_match_classification():
    records = json.loads(CORPUS_PATH.read_text(encoding="utf-8"))
    assert records, "arming replay corpus is empty"
    flips = []
    for record in records:
        inp = decode(record, armable.ArmInputs, Classification, Finding)
        cls = inp.classification
        if armable._golden_flags(inp) != (cls.golden_changed, cls.jsb_capture_changed):
            flips.append(record["id"])
    assert flips == []
