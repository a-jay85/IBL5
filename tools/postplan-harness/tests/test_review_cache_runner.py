"""Review-cache plumbing: per-file patch-ids, the runner's key context, RunResult
serialisation and the sticky `Reused from` line."""
import json
import os
import sys
import types

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity, reviewcache
from harness.state import HarnessError, RunResult, TerminalState
import runner

PLAN_HASH = "c" * 64


def _section(path, hunk_start, added, old_path=None):
    return (
        f"diff --git a/{old_path or path} b/{path}\n"
        "index abc1234..def5678 100644\n"
        f"--- a/{old_path or path}\n"
        f"+++ b/{path}\n"
        f"@@ -{hunk_start},3 +{hunk_start},3 @@\n"
        " context one\n"
        f"-old line\n"
        f"+{added}\n"
        " context two\n"
    )


def test_split_diff_by_file_keys_post_image_path():
    rename = (
        "diff --git a/old.py b/renamed.py\n"
        "similarity index 90%\n"
        "rename from old.py\n"
        "rename to renamed.py\n"
        "index abc1234..def5678 100644\n"
        "--- a/old.py\n"
        "+++ b/renamed.py\n"
        "@@ -1,3 +1,3 @@\n"
        " a\n"
        "-b\n"
        "+c\n"
        " d\n"
    )
    diff = _section("b.py", 5, "new b") + rename
    sections = reviewcache.split_diff_by_file(diff)
    assert set(sections) == {"b.py", "renamed.py"}
    assert sections["renamed.py"].startswith("diff --git a/old.py b/renamed.py")


def test_per_file_patch_ids_ignore_hunk_line_numbers():
    ids_a = reviewcache.per_file_patch_ids(_section("x.py", 10, "same added"))
    ids_b = reviewcache.per_file_patch_ids(_section("x.py", 40, "same added"))
    assert ids_a and ids_a == ids_b
    assert all(len(v) == 40 for v in ids_a.values())


def test_per_file_patch_ids_change_on_content_change():
    before = _section("a.py", 10, "alpha") + _section("b.py", 20, "beta")
    after = _section("a.py", 10, "alpha CHANGED") + _section("b.py", 20, "beta")
    ids_before = reviewcache.per_file_patch_ids(before)
    ids_after = reviewcache.per_file_patch_ids(after)
    assert set(ids_before) == set(ids_after) == {"a.py", "b.py"}
    assert ids_before["a.py"] != ids_after["a.py"]
    assert ids_before["b.py"] == ids_after["b.py"]


def test_per_file_patch_ids_fail_closed_on_bad_section(monkeypatch):
    monkeypatch.setattr("harness.fidelity.diff_patch_id", lambda section: "")
    assert reviewcache.per_file_patch_ids(_section("a.py", 1, "x")) == {}


class _FakeGit:
    def __init__(self, *diffs):
        self._diffs = list(diffs)

    def diff_vs_base(self):
        return self._diffs.pop(0)


def _plan():
    return types.SimpleNamespace(found=False, path="")


def test_review_cache_context_version_empty_when_procedure_missing(monkeypatch):
    def _missing(*a, **k):
        raise HarnessError("fidelity-procedure", "x")

    monkeypatch.setattr(runner.fidelity, "_find_procedure", _missing)
    logs = []
    key, _ = runner._review_cache_context(
        _FakeGit(_section("a.py", 1, "x")), "/wt", "m" * 40, {"title": "t"}, _plan(),
        runner.HARNESS_ROOT, logs.append)
    assert key["version"] == ""
    assert logs
    record = {"key": dict(key)}
    assert reviewcache.key_matches(record, key) is False


def test_review_cache_context_keys_post_phase45_diff(monkeypatch):
    monkeypatch.setattr(runner.fidelity, "_find_procedure", lambda *a, **k: "proc text")
    diff1 = _section("a.py", 1, "before ingestion")
    diff2 = _section("a.py", 1, "after ingestion")
    git = _FakeGit(diff1, diff2)
    args = ("/wt", "m" * 40, {"title": "t"}, _plan(), runner.HARNESS_ROOT, lambda m: None)
    key1, files1 = runner._review_cache_context(git, *args)
    key2, files2 = runner._review_cache_context(git, *args)
    assert key1["diff_id"] == fidelity.diff_patch_id(diff1) != ""
    assert key2["diff_id"] == fidelity.diff_patch_id(diff2) != ""
    assert key1["diff_id"] != key2["diff_id"]
    assert key1["version"] and key1["version"] == key2["version"]
    assert set(files1) == set(files2) == {"a.py"}
    assert key1["file_list"] == ["a.py"]


def test_run_result_pops_empty_reused_from_and_run_id():
    empty = json.loads(RunResult(terminal=TerminalState.SHIPPED_HELD, slug="s",
                                 pr_number=5).to_json())
    for k in ("reused_from", "run_id", "review_delta"):
        assert k not in empty
    full = json.loads(RunResult(terminal=TerminalState.SHIPPED_HELD, slug="s", pr_number=5,
                                reused_from={"review": "live-1"}).to_json())
    assert full["reused_from"] == {"review": "live-1"}


def test_compose_sticky_reused_from_line():
    def _compose(reused_from):
        fid = {"verdict_1": "READY", "reviewed_tree": "a" * 40, "error_kind": None}
        return fidelity.compose_sticky(
            "rebased", "ci", fid, None, ["a", "b", "c", "d", "e"], "findings",
            fidelity.terminal_line("READY", None, None, None, None, 0),
            diff_id="e" * 40, plan_hash=PLAN_HASH, reused_from=reused_from)

    body = _compose({"review": "live-1", "fidelity": "live-1"})
    lines = [ln for ln in body.splitlines() if ln.startswith("**Reused from:**")]
    assert lines == ["**Reused from:** live-1 (fidelity, review)"]
    all_lines = body.splitlines()
    assert all_lines.index(lines[0]) > all_lines.index(f"**Plan hash:** {PLAN_HASH}")
    assert fidelity.STICKY_REUSED_FROM_RE.search(body)

    assert "**Reused from:**" not in _compose(None)
