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


# --- Phase 2: progress rule, verdict and cache -----------------------------------------------

def _git(repo, *args):
    subprocess.run(["git", "-C", str(repo), *args], check=True, capture_output=True)


def _engine_repo(tmp_path):
    """A repo whose base commit holds engine/a.go and whose HEAD commit changes it."""
    repo = tmp_path / "repo"
    (repo / "engine").mkdir(parents=True)
    _git(repo, "init", "-q")
    _git(repo, "config", "user.email", "t@example.com")
    _git(repo, "config", "user.name", "t")
    (repo / "engine" / "a.go").write_text("package a // base\n")
    _git(repo, "add", "-A")
    _git(repo, "commit", "-qm", "base")
    base = subprocess.check_output(["git", "-C", str(repo), "rev-parse", "HEAD"], text=True).strip()
    (repo / "engine" / "a.go").write_text("package a // head\n")
    _git(repo, "add", "-A")
    _git(repo, "commit", "-qm", "head")
    return repo, base


def _first(day=1, game=0, gi=0, matched=0):
    return {"day": day, "game": game, "global_index": gi, "matched_draws": matched,
            "total_draws": 900, "kind": "draw", "target": "0xFEEDF00D"}


def _fake_child(base_first, head_first, calls, probes=None, replay_status="ok"):
    probes = list(probes or [])

    def fake(args, timeout):
        calls.append(list(args))
        if "probe" in args:
            rec = probes.pop(0) if probes else "rec-a"
            return {"status": "ok", "recording": rec, "baseline_sha256": "b1"}
        if replay_status != "ok":
            return {"status": "unavailable", "why": "replay-tool-failed:day1"}
        label = args[args.index("--label") + 1]
        return {"status": "ok", "first": base_first if label == "base" else head_first}
    return fake


def _jsbn_dir(tmp_path):
    loop_dir = tmp_path / "jsbn-real" / "tools" / "parity_loop"
    loop_dir.mkdir(parents=True, exist_ok=True)
    (loop_dir / "diverge.py").write_text("")
    return tmp_path / "jsbn-real"


def _replay_calls(calls):
    return [c for c in calls if "replay" in c]


def _run_verdict(tmp_path, base_first, head_first, probes=None, branch="jsb-port-pl-0027-r1",
                 clock=None, replay_status="ok"):
    if (tmp_path / "repo").exists():
        repo = tmp_path / "repo"
        base = subprocess.check_output(["git", "-C", str(repo), "rev-parse", "HEAD~1"],
                                       text=True).strip()
    else:
        repo, base = _engine_repo(tmp_path)
    calls = []
    kw = {"clock": clock} if clock else {}
    result = rp.verdict(repo, branch, base, _jsbn_dir(tmp_path),
                        run_child=_fake_child(base_first, head_first, calls, probes, replay_status),
                        cache_dir=tmp_path / "cache", **kw)
    return result, calls


def test_progressed_ranks_box_diff_after_draw_mismatch():
    base = {"day": 1, "game": 2, "global_index": 5}
    head = {"day": 1, "game": 0, "global_index": None}
    assert rp.progressed(base, head) is True


def test_progressed_breaks_draw_ties_on_matched_draws():
    base = {"day": 1, "game": 1, "global_index": 5, "matched_draws": 40}
    assert rp.progressed(base, dict(base, matched_draws=41)) is True
    assert rp.progressed(base, dict(base)) is False


def test_verdict_no_progress_when_head_equals_base(tmp_path):
    first = _first()
    result, _ = _run_verdict(tmp_path, first, dict(first))
    assert result == "replay-no-progress:pl-0027:day1:game0:draw0:matched0"


def test_verdict_progress_when_head_further(tmp_path):
    result, _ = _run_verdict(tmp_path, _first(gi=0), _first(gi=7))
    assert result == "ok"


def test_verdict_progress_when_head_clean_through_base_day(tmp_path):
    result, calls = _run_verdict(tmp_path, _first(), None)
    assert result == "ok"
    head = [c for c in _replay_calls(calls) if "head" in c][0]
    assert head[head.index("--max-day") + 1] == "1"


def test_verdict_regression_earlier_day_holds(tmp_path):
    result, _ = _run_verdict(tmp_path, _first(day=3), _first(day=1))
    assert result.startswith("replay-no-progress:pl-0027:day1:")


def test_verdict_base_clean_week_holds(tmp_path):
    result, calls = _run_verdict(tmp_path, None, _first())
    assert result == "replay-no-progress:pl-0027:base-clean"
    assert not [c for c in _replay_calls(calls) if "head" in c]


def test_verdict_unavailable_without_jsb_native(tmp_path):
    repo, base = _engine_repo(tmp_path)
    calls = []
    child = _fake_child(None, None, calls)
    empty = tmp_path / "empty"
    empty.mkdir()
    for jsbn in (None, empty):
        res = rp.verdict(repo, "jsb-port-pl-0027-r1", base, jsbn, run_child=child,
                         cache_dir=tmp_path / "cache")
        assert res == "replay-unavailable:no-jsb-native"
    assert calls == []


def test_verdict_unavailable_when_recording_changes(tmp_path):
    result, _ = _run_verdict(tmp_path, _first(), _first(gi=7), probes=["rec-a", "rec-b"])
    assert result == "replay-unavailable:recording-changed"


def test_verdict_budget_exhausted_holds(tmp_path):
    ticks = iter([0, 0, 0])

    def clock():
        return next(ticks, 10000)

    result, calls = _run_verdict(tmp_path, _first(), _first(gi=7), clock=clock)
    assert result == "replay-unavailable:budget"
    assert not [c for c in _replay_calls(calls) if "head" in c]


def test_cache_hit_skips_replay_for_same_engine_tree(tmp_path):
    first, again = _first(), _first(gi=7)
    res1, calls1 = _run_verdict(tmp_path, first, again)
    res2, calls2 = _run_verdict(tmp_path, first, again)
    assert len(_replay_calls(calls1)) == 2
    assert _replay_calls(calls2) == []
    assert res1 == res2 == "ok"


def test_unavailable_result_is_not_cached(tmp_path):
    res1, _ = _run_verdict(tmp_path, _first(), _first(gi=7), replay_status="unavailable")
    assert res1 == "replay-unavailable:replay-tool-failed:day1"
    res2, calls2 = _run_verdict(tmp_path, _first(), _first(gi=7))
    assert res2 == "ok"
    assert _replay_calls(calls2)


@pytest.mark.parametrize("branch,expected", [
    ("jsb-port-pl-0027-r1", "pl-0027"),
    ("jsb-port-pl-27-r1", None),
    ("jsb-port-pl-0027", None),
    ("jsb-port-pl-0027-r1-extra", None),
    ("xjsb-port-pl-0027-r1", None),
    ("jsb-port-replay-progress-gate", None),
    ("", None),
])
def test_row_id_parse_near_misses(tmp_path, branch, expected):
    assert rp.row_id(branch) == expected
    if expected is None and branch in ("jsb-port-replay-progress-gate", ""):
        result, _ = _run_verdict(tmp_path, _first(), _first(), branch=branch)
        assert result == "replay-unavailable:row-unparsed"
