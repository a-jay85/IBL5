"""Characterization of the verdict=ABSENT mechanism on a clean re-run.

purge_verdict_artifacts runs at the start of every rebase. It deletes the SHA sidecar
and the verdict .ok files but never the condition-(14) marker. A later run whose rebase
is clean writes no new review, so the marker still holds with no verdict to read, and
conflict_verdict_for returns None, which the sticky shows as verdict=ABSENT.

This passes on master and must keep passing: it pins the display gap the conflict
report explains. The hold itself stays fail-closed on purpose.
"""
import glob
import os
import sys
import uuid

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.conflict import purge_verdict_artifacts, verdict_ok_path
from harness.armable import (
    conflict_flag_path,
    conflict_verdict_for,
    record_conflict_resolution,
)
from runner import _conflict_key


def test_purge_after_clean_rebase_reads_absent():
    branch = f"absent-trace-{uuid.uuid4().hex[:10]}"
    key = _conflict_key(branch)
    sha = "c" * 40
    flag = conflict_flag_path(branch)
    sidecar = f"/tmp/postplan-conflict-sha-{key}.txt"
    verdict_file = verdict_ok_path(key, sha)
    try:
        record_conflict_resolution(branch, sha)
        with open(sidecar, "w") as fh:
            fh.write(sha)
        with open(verdict_file, "w") as fh:
            fh.write("CONFLICT-REVIEW=CLEAN\nreply text\n")
        assert conflict_verdict_for(branch) == "CONFLICT-REVIEW=CLEAN"

        purge_verdict_artifacts(key)

        assert conflict_verdict_for(branch) is None
        with open(flag) as fh:
            assert sha in fh.read()
    finally:
        for path in (flag, sidecar, *glob.glob(f"/tmp/postplan-conflict-verdict-{key}-*.ok")):
            if os.path.exists(path):
                os.unlink(path)
