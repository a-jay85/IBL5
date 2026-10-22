"""bench/replay_missing_file.py: compare split, two-root replay, exit codes and flags."""
import importlib.util
import json
import os
import subprocess
import sys
from pathlib import Path

HARNESS = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

_spec = importlib.util.spec_from_file_location(
    "replay_missing_file_t", HARNESS / "bench" / "replay_missing_file.py")
rmf = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(rmf)

SCRIPT = str(HARNESS / "bench" / "replay_missing_file.py")

FILES = [
    "ibl5/migrations/196_foo.sql",
    "ibl5/classes/Foo/A.php",
    "ibl5/classes/Foo/B.php",
    "ibl5/tests/Foo/ATest.php",
    "tools/postplan-harness/tests/test_a.py",
]
NNN_ITEM = "MISSING-FILE: ibl5/migrations/NNN_foo.sql (plan Critical File never appeared in the diff)"
CSS_ITEM = "MISSING-FILE: ibl5/themes/IBL/style/style.css (plan Critical File never appeared in the diff)"


def _write_plan(tmp_path):
    p = tmp_path / "plan.md"
    p.write_text(
        "---\nimpl_model: sonnet\nauto_merge: false\n---\n"
        "# Plan: x\n\n## Phase 1: x\n\nDo it.\n\n"
        "## Critical Files\n\n"
        "- `ibl5/migrations/NNN_foo.sql` — NEW\n"
        "- `ibl5/classes/Foo/`\n"
        "- `ibl5/themes/IBL/style/style.css`\n\n"
        "## Verification Matrix\n\n"
        "| # | What to verify | Test type | Timing | Test file / location |\n"
        "|---|---|---|---|---|\n"
        "| 1 | a | CLI-executable | post-impl | `tools/postplan-harness/tests/test_a.py` |\n")
    return str(p)


def _write_run(out_dir, name, slug, plan_path, files=FILES, pr=3281):
    d = Path(out_dir) / f"live-{name}"
    d.mkdir(parents=True)
    (d / "result.json").write_text(json.dumps({
        "slug": slug, "pr_number": pr,
        "plan": {"found": True, "path": plan_path},
        "classification": {"files": files},
        "unresolved_conformance": ["MISSING-FILE: stale/never-recomputed.txt (x)"],
    }))


def _stub_harness(tmp_path, name, items):
    root = tmp_path / name
    (root / "harness").mkdir(parents=True)
    (root / "harness" / "__init__.py").write_text("")
    (root / "harness" / "planfile.py").write_text(
        "import types\n"
        "def locate_plan(slug, explicit_path=None):\n"
        "    return types.SimpleNamespace(found=True)\n")
    (root / "harness" / "conformance.py").write_text(
        f"def check(plan, files, *a, **k):\n    return {items!r}\n")
    return str(root)


def _run(*args):
    return subprocess.run([sys.executable, SCRIPT, *args], capture_output=True, text=True)


def test_compare_run_splits_cleared_kept_regressions():
    """Mutation caught: swap `b - a` and `a - b`."""
    assert rmf.compare_run(["a", "b"], ["b", "c"]) == {
        "cleared": ["a"], "kept": ["b"], "regressions": ["c"]}


def test_real_harness_both_sides_recomputes_and_ignores_stored_items(tmp_path):
    """Mutation caught: worker returns `result["unresolved_conformance"]` instead of calling `check`."""
    out = tmp_path / "out"
    _write_run(out, "r1", "s1", _write_plan(tmp_path))
    js = tmp_path / "res.json"
    r = _run("--before-root", str(HARNESS), "--after-root", str(HARNESS),
             "--out-dir", str(out), "--json", str(js))
    assert r.returncode == 0, r.stdout + r.stderr
    data = json.loads(js.read_text())
    run = data["runs"]["live-r1"]
    assert run["before"] == run["after"] == [CSS_ITEM]
    assert "stale/never-recomputed.txt" not in js.read_text()


def test_two_roots_report_cleared_and_kept(tmp_path):
    """Mutation caught: load `--before-root` for both sides (cleared becomes empty, exit 1)."""
    out = tmp_path / "out"
    _write_run(out, "r1", "s1", _write_plan(tmp_path))
    before = _stub_harness(tmp_path, "before", [NNN_ITEM, CSS_ITEM])
    js = tmp_path / "res.json"
    r = _run("--before-root", before, "--after-root", str(HARNESS), "--out-dir", str(out),
             "--expect-cleared", "NNN_foo.sql", "--expect-kept", "style.css", "--json", str(js))
    assert r.returncode == 0, r.stdout + r.stderr
    run = json.loads(js.read_text())["runs"]["live-r1"]
    assert run["cleared"] == [NNN_ITEM]
    assert run["kept"] == [CSS_ITEM]


def test_regression_exits_one_and_names_it(tmp_path):
    """Mutation caught: `return 0` unconditionally in `main`."""
    out = tmp_path / "out"
    _write_run(out, "r1", "s1", _write_plan(tmp_path))
    before = _stub_harness(tmp_path, "before", [])
    after = _stub_harness(tmp_path, "after", ["MISSING-FILE: ibl5/new.php (x)"])
    r = _run("--before-root", before, "--after-root", after, "--out-dir", str(out))
    assert r.returncode == 1
    assert "REGRESSION MISSING-FILE: ibl5/new.php" in r.stdout


def test_failed_expectation_exits_one(tmp_path):
    """Mutation caught: delete the expectation loop."""
    out = tmp_path / "out"
    _write_run(out, "r1", "s1", _write_plan(tmp_path))
    r = _run("--before-root", str(HARNESS), "--after-root", str(HARNESS),
             "--out-dir", str(out), "--expect-cleared", "nothing-here")
    assert r.returncode == 1
    assert "EXPECT-FAILED: cleared 'nothing-here' matched nothing" in r.stdout


def test_unknown_flag_is_rejected(tmp_path):
    """Integration trigger: a rejected flag form fails loudly."""
    r = _run("--before-root", str(HARNESS), "--after-root", str(HARNESS), "--bogus")
    assert r.returncode == 2
    assert "usage:" in r.stderr


def test_slugs_filter_keeps_only_listed_runs(tmp_path):
    """Mutation caught: ignore `--slugs`."""
    out = tmp_path / "out"
    plan = _write_plan(tmp_path)
    _write_run(out, "a", "a", plan)
    _write_run(out, "b", "b", plan)
    js = tmp_path / "res.json"
    r = _run("--before-root", str(HARNESS), "--after-root", str(HARNESS),
             "--out-dir", str(out), "--slugs", "a", "--json", str(js))
    assert r.returncode == 0, r.stdout + r.stderr
    data = json.loads(js.read_text())
    assert list(data["runs"]) == ["live-a"]
    assert data["skipped"] == {"slug-filtered": 1}


def test_default_out_dir_is_under_before_root():
    """Mutation caught: default to `after_root`."""
    ns = rmf.parse_args(["--before-root", "/x/h", "--after-root", "/y/h"])
    assert ns.out_dir == "/x/h/out"
