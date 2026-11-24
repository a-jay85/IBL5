"""Replay-progress check for jsb-port-* trace-pinned PRs.

Stdlib only at module level. jsb-native's parity-loop modules are imported
inside _child(), which runs in its own `python3 -I` process.
"""
import hashlib
import json
import os
import re
import shutil
import signal
import subprocess
import sys
import tarfile
import tempfile
import time
from pathlib import Path

CHILD = Path(__file__).resolve()
CHILD_FLAGS = {"--mode", "--loop-dir", "--jsbn", "--overlay", "--ibl5", "--max-day", "--label"}
SAFE_KEYS = ("day", "game", "global_index", "matched_draws", "total_draws", "kind")
BUDGET_OUT = {"status": "unavailable", "why": "budget"}
WHY_RE = re.compile(r"^(recording-sha-mismatch|replay-corrupt|replay-tool-failed)(?::(day\d+))?")


def _why(message):
    m = WHY_RE.match(message or "")
    if not m:
        return "replay-error"
    return m.group(1) + (":" + m.group(2) if m.group(2) else "")


def _child(argv):
    if len(argv) % 2 or set(argv[0::2]) - CHILD_FLAGS:
        raise SystemExit(2)
    a = dict(zip(argv[0::2], argv[1::2]))
    sys.path.insert(0, a["--loop-dir"])
    import diverge  # noqa: E402  jsb-native, child process only
    import loop  # noqa: E402
    jsbn = Path(a["--jsbn"])
    index, rec_id = loop._current_recording(jsbn)
    if rec_id is None:
        return {"status": "unavailable", "why": "no-recording"}
    rec = index["recordings"][rec_id]
    out = {"status": "ok", "recording": rec_id, "baseline_sha256": rec.get("baseline_sha256")}
    if a["--mode"] == "probe":
        return out
    first = None
    try:
        diverge.verify_recording(jsbn, index, rec_id)
        for day in range(1, int(a["--max-day"]) + 1):
            got = diverge.run_day(Path(a["--ibl5"]), Path(a["--overlay"]), rec_id, day)
            report = diverge.first_mismatch(game_entry_address=rec.get("game_entry_address"), **got)
            if not report["match"]:
                first = {k: dict(report, day=day).get(k) for k in SAFE_KEYS}
                break
    except diverge.ReplayError as exc:
        return {"status": "unavailable", "why": _why(str(exc))}
    out["first"] = first
    return out


def run_child(args, timeout):
    """Run _child in its own process group and kill the whole group on timeout."""
    if timeout <= 0:
        return dict(BUDGET_OUT)
    proc = subprocess.Popen([sys.executable, "-I", str(CHILD), *args], stdout=subprocess.PIPE,
                            stderr=subprocess.PIPE, text=True, start_new_session=True)
    try:
        stdout, _ = proc.communicate(timeout=timeout)
    except subprocess.TimeoutExpired:
        os.killpg(proc.pid, signal.SIGKILL)
        proc.communicate()
        return dict(BUDGET_OUT)
    if proc.returncode != 0:
        return {"status": "unavailable", "why": "child-failed"}
    try:
        return json.loads(stdout.strip().splitlines()[-1])
    except (ValueError, IndexError):
        return {"status": "unavailable", "why": "child-output"}


REPLAY_BUDGET_S = 2400
WEEK_DAYS = 7
ROW_RE = re.compile(r"^jsb-port-(pl-\d{4})-r\d+$")
CACHE_DIR = Path.home() / ".cache" / "ibl5-postplan" / "replay-progress"


def row_id(branch):
    m = ROW_RE.match(branch or "")
    return m.group(1) if m else None


def position(first):
    gi = first.get("global_index")
    return (first["day"], gi is None, first.get("game") or 0, gi or 0, first.get("matched_draws") or 0)


def progressed(base_first, head_first):
    if head_first is None:
        return True
    return position(head_first) > position(base_first)


def _pos_text(first):
    gi = first.get("global_index")
    draw = "box" if gi is None else "draw%s" % gi
    return "day%s:game%s:%s:matched%s" % (first.get("day"), first.get("game"), draw,
                                          first.get("matched_draws"))


def engine_tree(root, rev):
    proc = subprocess.run(["git", "-C", str(root), "rev-parse", "--verify", "%s:engine" % rev],
                          capture_output=True, text=True)
    return proc.stdout.strip() if proc.returncode == 0 else None


def _cache_path(cache_dir, tree, probe, max_day):
    key = "|".join([tree, str(probe["recording"]), str(probe["baseline_sha256"]), str(max_day)])
    return Path(cache_dir) / (hashlib.sha256(key.encode()).hexdigest() + ".json")


def _cache_get(path):
    try:
        data = json.loads(path.read_text())
    except (OSError, ValueError):
        return None
    return data if isinstance(data, dict) and data.get("status") == "ok" and "first" in data else None


def _cache_put(path, result):
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(".tmp%d" % os.getpid())
    tmp.write_text(json.dumps({"status": "ok", "first": result.get("first")}))
    os.replace(tmp, path)


def _replay_side(root, rev, tree, max_day, label, ctx):
    path = _cache_path(ctx["cache_dir"], tree, ctx["probe"], max_day)
    cached = _cache_get(path)
    if cached is not None:
        return cached
    work = Path(tempfile.mkdtemp(prefix="replay-progress-"))
    try:
        tar = work / "engine.tar"
        archived = subprocess.run(["git", "-C", str(root), "archive", "--format=tar", "-o", str(tar),
                                   rev, "engine"], capture_output=True)
        if archived.returncode != 0:
            return {"status": "unavailable", "why": "archive-failed"}
        kw = {"filter": "data"} if hasattr(tarfile, "data_filter") else {}
        with tarfile.open(tar) as tf:
            tf.extractall(work / "ibl5", **kw)
        overlay = work / "overlay"
        (overlay / "parity" / "state").mkdir(parents=True)
        (overlay / "parity" / "recordings").symlink_to(Path(ctx["jsbn"]) / "parity" / "recordings")
        res = ctx["run_child"](ctx["common"] + ["--mode", "replay", "--label", label,
                                                "--ibl5", str(work / "ibl5"), "--overlay", str(overlay),
                                                "--max-day", str(max_day)],
                               ctx["deadline"] - ctx["clock"]())
        if res.get("status") == "ok":
            _cache_put(path, res)
        return res
    finally:
        shutil.rmtree(work, ignore_errors=True)


def verdict(root, branch, base, jsbn_dir, *, run_child=run_child, cache_dir=CACHE_DIR,
            budget_s=REPLAY_BUDGET_S, clock=time.monotonic):
    """"ok" when the PR head's replay gets further than base's; else a reason string."""
    row = row_id(branch)
    if row is None:
        return "replay-unavailable:row-unparsed"
    loop_dir = Path(jsbn_dir or "") / "tools" / "parity_loop"
    if not jsbn_dir or not (loop_dir / "diverge.py").is_file():
        return "replay-unavailable:no-jsb-native"
    base_tree, head_tree = engine_tree(root, base), engine_tree(root, "HEAD")
    if not base_tree or not head_tree:
        return "replay-unavailable:engine-tree"
    deadline = clock() + budget_s
    common = ["--loop-dir", str(loop_dir), "--jsbn", str(jsbn_dir)]
    probe = run_child(common + ["--mode", "probe"], deadline - clock())
    if probe.get("status") != "ok":
        return "replay-unavailable:%s" % probe.get("why", "probe")
    ctx = {"cache_dir": cache_dir, "probe": probe, "jsbn": jsbn_dir, "run_child": run_child,
           "common": common, "deadline": deadline, "clock": clock}
    base_res = _replay_side(root, base, base_tree, WEEK_DAYS, "base", ctx)
    if base_res.get("status") != "ok":
        return "replay-unavailable:%s" % base_res.get("why", "base")
    base_first = base_res.get("first")
    if base_first is None:
        return "replay-no-progress:%s:base-clean" % row
    if clock() >= deadline:
        return "replay-unavailable:budget"
    head_res = _replay_side(root, "HEAD", head_tree, base_first["day"], "head", ctx)
    if head_res.get("status") != "ok":
        return "replay-unavailable:%s" % head_res.get("why", "head")
    again = run_child(common + ["--mode", "probe"], max(deadline - clock(), 30))
    if (again.get("recording"), again.get("baseline_sha256")) != (probe["recording"], probe["baseline_sha256"]):
        return "replay-unavailable:recording-changed"
    head_first = head_res.get("first")
    if progressed(base_first, head_first):
        return "ok"
    return "replay-no-progress:%s:%s" % (row, _pos_text(head_first))


if __name__ == "__main__":
    print(json.dumps(_child(sys.argv[1:])))
