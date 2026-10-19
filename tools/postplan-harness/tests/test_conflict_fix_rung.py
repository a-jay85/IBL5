"""Tests for the conflict ladder's fourth rung (harness/conflict_fix.py).

Every test builds a real temp repo whose HEAD is a hand-built merge commit, then drives
`make_fix_rung` through `run_ladder` with `_SessionLlm` standing in for the model session.
"""
from __future__ import annotations

import dataclasses
import os
import sys
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness import conflict_fix
from harness.adapters.llm import MODEL_MAP
from harness.conflict_fix import (
    FIX_SUMMARY_CAP,
    _fix_prompt,
    attempted,
    make_fix_rung,
    marker_path,
    record_attempt,
    summarize,
)
from harness.conflict_ladder import SESSION_PROOF_CAP, default_rungs, run_ladder
from harness.state import HarnessError
from test_conflict_ladder import (  # noqa: F401  (key is a fixture)
    _KEEPME_PROOF,
    _a_repo,
    _build_merge_repo,
    _ctx,
    _flag_value,
    _git,
    _head,
    _keepme_prove,
    _SessionLlm,
    key,
)

_OK_REPLY = "SUMMARY: restored keep-me\nRESOLVED"


def _a_repo_with_master_test(tmp_path):
    """Like `_a_repo`, but master also adds ibl5/tests/FooTest.php."""
    d = tmp_path / "repo"
    pre, master, merge = _build_merge_repo(
        d, {"a.txt": "base\n"}, {"a.txt": "feature\nkeep-me\n"},
        {"a.txt": "master\n", "ibl5/tests/FooTest.php": "<?php // master test\n"},
        {"a.txt": "master\n"})
    return d, pre, master, merge


def _edit_keepme(cwd):
    (Path(cwd) / "a.txt").write_text("master\nkeep-me\n")


def _fix_ladder(tmp_path, key, reply, effect, repo=_a_repo, seam=True):
    """Returns (d, pre, master, merge, llm, ctx, seam_calls)."""
    d, pre, master, merge = repo(tmp_path)
    llm = _SessionLlm({conflict_fix.FIX_PURPOSE: reply}, effect)
    seam_calls: list = []
    ctx = _ctx(d, key, pre, master, ("a.txt",), _keepme_prove(d), llm=llm)
    ctx = dataclasses.replace(
        ctx, state_dir=str(tmp_path / "state"),
        extend_pre_patch=seam_calls.append if seam else None)
    return d, pre, master, merge, llm, ctx, seam_calls


def _assert_untouched(d, merge):
    assert _head(d) == merge
    assert (Path(d) / "a.txt").read_text() == "master\n"


def test_fix_rung_argv_model_and_denied(tmp_path, key):
    d, pre, master, merge, llm, ctx, _ = _fix_ladder(tmp_path, key, _OK_REPLY, _edit_keepme)
    run_ladder(ctx, _KEEPME_PROOF, (make_fix_rung("feat"),))
    assert [p for p, _ in llm.tooled_argvs] == ["conflict-fix-session"]
    argv = llm.tooled_argvs[0][1]
    assert _flag_value(argv, "--model") == MODEL_MAP["sonnet"]
    assert _flag_value(argv, "--max-turns") == "100"
    assert _flag_value(argv, "--max-budget-usd") == "5.00"
    denied = _flag_value(argv, "--disallowedTools").split(",")
    for entry in ("Bash(git push:*)", "Bash(git -C:*)", "Bash(git update-ref:*)",
                  "Bash(git stash:*)", "Bash(claude:*)", "WebFetch"):
        assert entry in denied


def test_fix_rung_passes_when_session_satisfies_proof(tmp_path, key):
    d, pre, master, merge, llm, ctx, _ = _fix_ladder(tmp_path, key, _OK_REPLY, _edit_keepme)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_fix_rung("feat"),))
    assert out.rung == "sonnet-fix"
    assert out.trail[-1] == "sonnet-fix: passed"
    assert "keep-me" in (Path(d) / "a.txt").read_text()


def test_fix_rung_keeps_merge_parents(tmp_path, key):
    d, pre, master, merge, llm, ctx, _ = _fix_ladder(tmp_path, key, _OK_REPLY, _edit_keepme)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_fix_rung("feat"),))
    assert out.rung == "sonnet-fix"
    assert _head(d) != merge
    parents = _git(d, "rev-list", "--parents", "-n", "1", "HEAD").stdout.split()[1:]
    assert parents == [pre, master]


def test_fix_rung_failed_verdict_resets_tree(tmp_path, key):
    d, pre, master, merge, llm, ctx, _ = _fix_ladder(
        tmp_path, key, "SUMMARY: could not finish\nFAILED", _edit_keepme)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_fix_rung("feat"),))
    assert out.rung == ""
    assert out.trail[0].startswith("sonnet-fix: session ended FAILED")
    _assert_untouched(d, merge)


def test_fix_rung_blocks_moved_remote_ref(tmp_path, key):
    def effect(cwd):
        _edit_keepme(cwd)
        _git(cwd, "update-ref", "refs/remotes/origin/feat", "HEAD")

    d, pre, master, merge = _a_repo(tmp_path)
    _git(d, "update-ref", "refs/remotes/origin/feat", pre)
    llm = _SessionLlm({conflict_fix.FIX_PURPOSE: _OK_REPLY}, effect)
    ctx = dataclasses.replace(
        _ctx(d, key, pre, master, ("a.txt",), _keepme_prove(d), llm=llm),
        state_dir=str(tmp_path / "state"))
    out = run_ladder(ctx, _KEEPME_PROOF, (make_fix_rung("feat"),))
    assert out.rung == ""
    assert out.trail[0].startswith("sonnet-fix: session moved refs: ")
    assert "refs/remotes/origin/feat" in out.trail[0]
    _assert_untouched(d, merge)


def test_fix_rung_blocks_local_commit(tmp_path, key):
    def effect(cwd):
        _edit_keepme(cwd)
        _git(cwd, "commit", "-am", "x")

    d, pre, master, merge, llm, ctx, _ = _fix_ladder(tmp_path, key, _OK_REPLY, effect)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_fix_rung("feat"),))
    assert out.rung == ""
    assert out.trail[0].startswith("sonnet-fix: session moved ")
    _assert_untouched(d, merge)


def test_fix_rung_blocks_new_branch(tmp_path, key):
    def effect(cwd):
        _edit_keepme(cwd)
        _git(cwd, "branch", "rogue")

    d, pre, master, merge, llm, ctx, _ = _fix_ladder(tmp_path, key, _OK_REPLY, effect)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_fix_rung("feat"),))
    assert out.rung == ""
    assert out.trail[0].startswith("sonnet-fix: session moved refs: refs/heads/rogue")
    _assert_untouched(d, merge)


def test_fix_rung_rejects_non_test_path_outside_branch(tmp_path, key):
    def effect(cwd):
        _edit_keepme(cwd)
        (Path(cwd) / "other.txt").write_text("stray\n")

    d, pre, master, merge, llm, ctx, _ = _fix_ladder(tmp_path, key, _OK_REPLY, effect)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_fix_rung("feat"),))
    assert out.rung == ""
    assert out.trail[0].startswith("sonnet-fix: session touched other.txt outside its remit: ")
    assert not (Path(d) / "other.txt").exists()
    _assert_untouched(d, merge)


def test_fix_rung_allows_master_test_edit_and_extends_pre_patch(tmp_path, key):
    def effect(cwd):
        _edit_keepme(cwd)
        (Path(cwd) / "ibl5/tests/FooTest.php").write_text("<?php // adapted to the branch\n")

    d, pre, master, merge, llm, ctx, seam = _fix_ladder(
        tmp_path, key, _OK_REPLY, effect, repo=_a_repo_with_master_test)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_fix_rung("feat"),))
    assert out.rung == "sonnet-fix"
    assert seam == [("ibl5/tests/FooTest.php",)]
    assert "ibl5/tests/FooTest.php" in out.resolved_files


def test_fix_rung_test_edit_without_seam_fails(tmp_path, key):
    def effect(cwd):
        _edit_keepme(cwd)
        (Path(cwd) / "ibl5/tests/FooTest.php").write_text("<?php // adapted to the branch\n")

    d, pre, master, merge, llm, ctx, seam = _fix_ladder(
        tmp_path, key, _OK_REPLY, effect, repo=_a_repo_with_master_test, seam=False)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_fix_rung("feat"),))
    assert out.rung == ""
    assert out.trail[0].startswith(
        "sonnet-fix: test edits outside the branch need the pre-patch seam")
    assert _head(d) == merge


def test_fix_rung_skips_without_state_dir(tmp_path, key):
    d, pre, master, merge, llm, ctx, _ = _fix_ladder(tmp_path, key, _OK_REPLY, _edit_keepme)
    ctx = dataclasses.replace(ctx, state_dir="")
    out = run_ladder(ctx, _KEEPME_PROOF, (make_fix_rung("feat"),))
    assert out.rung == ""
    assert out.trail[0] == "sonnet-fix: skipped (no state dir; cost guard unavailable)"
    assert llm.tooled_argvs == []


def test_fix_rung_one_attempt_per_head_and_master(tmp_path, key):
    d, pre, master, merge, llm, ctx, _ = _fix_ladder(
        tmp_path, key, "SUMMARY: gave up\nFAILED", _edit_keepme)
    rung = make_fix_rung("feat")
    first = run_ladder(ctx, _KEEPME_PROOF, (rung,))
    second = run_ladder(ctx, _KEEPME_PROOF, (rung,))
    assert first.trail[0].startswith("sonnet-fix: session ended FAILED")
    assert second.trail[0].startswith("sonnet-fix: skipped (already attempted head ")
    assert [p for p, _ in llm.tooled_argvs] == ["conflict-fix-session"]


def test_fix_rung_new_master_tip_allows_attempt(tmp_path, key):
    d, pre, master, merge, llm, ctx, _ = _fix_ladder(
        tmp_path, key, "SUMMARY: gave up\nFAILED", _edit_keepme)
    record_attempt(ctx.state_dir, key, pre, "0" * 40, "now")
    assert not attempted(ctx.state_dir, key, pre, master)
    record_attempt(ctx.state_dir, key, "1" * 40, master, "now")
    assert not attempted(ctx.state_dir, key, pre, master)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_fix_rung("feat"),))
    assert out.trail[0].startswith("sonnet-fix: session ended FAILED")
    assert len(llm.tooled_argvs) == 1
    assert attempted(ctx.state_dir, key, pre, master)


def test_fix_rung_marker_written_before_call(tmp_path, key):
    seen: list = []

    def effect(cwd):
        seen.append(Path(marker_path(str(tmp_path / "state"), key)).exists())
        raise HarnessError("llm-tooled-cli", "crashed mid-call")

    d, pre, master, merge, llm, ctx, _ = _fix_ladder(tmp_path, key, _OK_REPLY, effect)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_fix_rung("feat"),))
    assert seen == [True]
    assert out.trail[0].startswith("sonnet-fix: llm-tooled-cli")
    assert attempted(ctx.state_dir, key, pre, master)


def test_fix_rung_not_reached_after_usage_limit(tmp_path, key):
    d, pre, master, merge, llm, ctx, _ = _fix_ladder(tmp_path, key, _OK_REPLY, _edit_keepme)

    def limited(c, p):
        raise HarnessError("llm-usage-limit", "limit")

    out = run_ladder(ctx, _KEEPME_PROOF, (("session", limited), make_fix_rung("feat")))
    assert len(out.trail) == 1
    assert out.trail[0].startswith("session: llm-usage-limit")
    assert llm.tooled_argvs == []
    assert not Path(marker_path(ctx.state_dir, key)).exists()


def test_default_rungs_ends_with_fix():
    assert [name for name, _ in default_rungs("feat")] == [
        "regen", "opus-retry", "session", "sonnet-fix"]


def test_summarize_strips_and_caps():
    assert summarize("one\n\ntwo\nthree\nfour\n") == "two / three / four"
    assert summarize("a\x1b[31m b\x00\tc​") == "a[31m b c"
    assert summarize(None) == ""
    capped = summarize("x" * 1000)
    assert len(capped) == FIX_SUMMARY_CAP


def test_fix_prompt_names_remit_and_tests(tmp_path, key):
    d, pre, master, merge, llm, ctx, _ = _fix_ladder(tmp_path, key, _OK_REPLY, _edit_keepme)
    prompt = _fix_prompt(ctx, _KEEPME_PROOF, ["a.txt"])
    for needle in (
        pre, master, "a.txt", "LOST: a.txt: +keep-me",
        "ibl5/tests/", "bin/test-", "tools/postplan-harness/tests/",
        f"git show {pre}:<path>", f"git show {master}:<path>",
        "python3 -m pytest -q tests", "vendor/bin/phpunit --no-progress",
        "PHPUNIT: unavailable", "ibl5/tests/DatabaseIntegration is out of remit",
        "only those count", "untrusted program output",
        "SUMMARY: <one line>", "exactly RESOLVED or exactly FAILED",
    ):
        assert needle in prompt, needle
    assert "[proof output truncated]" not in prompt
    big = _fix_prompt(ctx, "LOST: a.txt: +" + "x" * (SESSION_PROOF_CAP + 100), ["a.txt"])
    assert "[proof output truncated]" in big
