"""The replay-progress check for jsb-port-* trace-pinned PRs.

The child-process tests run the real `run_child` against a stub jsb-native tree. The verdict
tests inject a fake `run_child`, so none of this needs Go or the private jsb-native repo.
Fixtures use fake addresses only."""
import subprocess
import sys
import time
from pathlib import Path

import pytest

from harness import replay_progress as rp

HARNESS_DIR = Path(__file__).resolve().parents[1]

LOOP_PY = '''
REPLAY_DAYS = 7
def _current_recording(jsbn):
    rec = {rec!r}
    if rec is None:
        return ({{"recordings": {{}}}}, None)
    return ({{"recordings": {{rec: {{"baseline_sha256": "b1"}}}}}}, rec)
'''

DIVERGE_PY = '''
import time
from pathlib import Path

class ReplayError(Exception):
    pass

def verify_recording(jsbn, index, rec_id):
    return None

def run_day(ibl5, jsbn, rec_id, day, env=None):
    with open(Path(__file__).with_name("calls.txt"), "a") as fh:
        fh.write("%d\\n" % day)
    (Path(jsbn) / "parity" / "state" / "replay" / ("day%d" % day)).mkdir(parents=True, exist_ok=True)
    time.sleep({sleep_s})
    if day == {error_day}:
        raise ReplayError("replay-tool-failed:day%d" % day)
    return {{"day": day}}

def first_mismatch(game_entry_address=None, **got):
    if {mismatch_day} is not None and got["day"] >= {mismatch_day}:
        return {{"match": False, "target": "0xFEEDF00D", "jsb_fn": "FUN_FAKE01", "game": 1,
                "global_index": 40, "matched_draws": 40, "total_draws": 900, "kind": "draw"}}
    return {{"match": True}}
'''


def _stub_jsbn(tmp_path, mismatch_day=None, error_day=None, sleep_s=0, recording="rec-a"):
    jsbn = tmp_path / "jsbn"
    loop_dir = jsbn / "tools" / "parity_loop"
    loop_dir.mkdir(parents=True)
    (loop_dir / "loop.py").write_text(LOOP_PY.format(rec=recording))
    (loop_dir / "diverge.py").write_text(DIVERGE_PY.format(
        mismatch_day=mismatch_day, error_day=error_day, sleep_s=sleep_s))
    overlay = tmp_path / "overlay"
    overlay.mkdir()
    return jsbn, loop_dir, overlay


def _args(jsbn, loop_dir, overlay, max_day, mode="replay"):
    return ["--mode", mode, "--loop-dir", str(loop_dir), "--jsbn", str(jsbn),
            "--overlay", str(overlay), "--ibl5", str(jsbn), "--max-day", str(max_day),
            "--label", "head"]


def _calls(loop_dir):
    path = loop_dir / "calls.txt"
    return path.read_text().split() if path.exists() else []


def test_child_replays_until_first_mismatch_day(tmp_path):
    jsbn, loop_dir, overlay = _stub_jsbn(tmp_path, mismatch_day=2)
    res = rp.run_child(_args(jsbn, loop_dir, overlay, 7), 60)
    assert _calls(loop_dir) == ["1", "2"]
    assert res["first"]["day"] == 2


def test_child_stops_at_max_day(tmp_path):
    jsbn, loop_dir, overlay = _stub_jsbn(tmp_path)
    res = rp.run_child(_args(jsbn, loop_dir, overlay, 2), 60)
    assert _calls(loop_dir) == ["1", "2"]
    assert res["first"] is None


def test_child_output_has_no_address_fields(tmp_path):
    import json
    jsbn, loop_dir, overlay = _stub_jsbn(tmp_path, mismatch_day=1)
    res = rp.run_child(_args(jsbn, loop_dir, overlay, 7), 60)
    assert res["first"]["kind"] == "draw"
    assert "target" not in res["first"] and "jsb_fn" not in res["first"]
    text = json.dumps(res)
    assert "0xFEEDF00D" not in text and "FUN_FAKE01" not in text


def test_child_writes_only_under_overlay_state(tmp_path):
    jsbn, loop_dir, overlay = _stub_jsbn(tmp_path)
    (overlay / "parity" / "state").mkdir(parents=True)
    rp.run_child(_args(jsbn, loop_dir, overlay, 1), 60)
    assert (overlay / "parity" / "state" / "replay" / "day1").is_dir()
    assert not (jsbn / "parity" / "state").exists()


def test_child_maps_replay_error_to_unavailable(tmp_path):
    jsbn, loop_dir, overlay = _stub_jsbn(tmp_path, error_day=2)
    res = rp.run_child(_args(jsbn, loop_dir, overlay, 7), 60)
    assert res == {"status": "unavailable", "why": "replay-tool-failed:day2"}


def test_child_reports_missing_recording(tmp_path):
    jsbn, loop_dir, overlay = _stub_jsbn(tmp_path, recording=None)
    res = rp.run_child(_args(jsbn, loop_dir, overlay, 7), 60)
    assert res["why"] == "no-recording"


def test_child_rejects_unknown_flag():
    proc = subprocess.run([sys.executable, "-I", str(rp.CHILD), "--bogus", "x"],
                          capture_output=True, text=True)
    assert proc.returncode == 2
    assert rp.run_child(["--mode"], 30)["why"] == "child-failed"


def test_run_child_timeout_is_budget_unavailable(tmp_path):
    jsbn, loop_dir, overlay = _stub_jsbn(tmp_path, sleep_s=30)
    start = time.monotonic()
    res = rp.run_child(_args(jsbn, loop_dir, overlay, 7), 1)
    assert res["why"] == "budget"
    assert time.monotonic() - start < 10


def test_jsb_native_import_is_lazy():
    code = ("import sys\n"
            "from harness import trace_pinned, replay_progress\n"
            "assert 'diverge' not in sys.modules and 'loop' not in sys.modules\n")
    proc = subprocess.run([sys.executable, "-c", code], cwd=HARNESS_DIR,
                          capture_output=True, text=True)
    assert proc.returncode == 0, proc.stderr
