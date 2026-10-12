import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.verify import ReplayVerify
from harness.state import Classification


def _shellcheck(text):
    cls = Classification(has_shell=True)
    tracks = ReplayVerify({"verify": {"shellcheck": text}}).run(cls)
    return next(t for t in tracks if t.name == "shellcheck")


@pytest.mark.parametrize("text,status", [
    ("", "pass"),
    ("  \n", "pass"),
    ("Checking 12 shell scripts", "pass"),
    ("In x line 3:\nSC2086 quote", "fail"),
    ("Checking 12 shell scripts\nIn x line 3:", "fail"),
    (None, "unavailable"),
    ("garbage", "unavailable"),
])
def test_shellcheck_replay_judgement(text, status):
    assert _shellcheck(text).status == status
