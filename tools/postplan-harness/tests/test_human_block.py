"""Tests for runner.human_block() and its path extractors.

The block is the plain-language exit-3 message. Every test calls the builder
directly; the one-line RESULT: verdict is frozen separately in test_verdict_line.py.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.state import RunResult, TerminalState

WT = "/Users/x/IBL5-worktrees/feat-thing"
LOG = "/tmp/post-plan-now-feat-thing.log"
# Main-checkout bin/postplan-fix, derived from this file's own location (not from runner).
FIX = os.path.join(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))),
    "bin", "postplan-fix")

_ADR_ERR = ("pre-push-adr-hook: Decision-trigger surfaces detected:\n"
            "  - [bin-script] bin/foo — Tool script\n"
            "  - [workflow] .github/workflows/x.yml — CI workflow\n\n"
            "FAIL: the diff touches decision-trigger surfaces without an accompanying ADR.")
_BUDGET_ERR = ("Trim the rule(s) above\n"
               "FAIL  big-rule.md  9000 bytes  always-loaded (path-unscoped) tier  cap 8000\n"
               "FAIL  other-rule.md  9500 bytes  lazy (path-scoped) tier  cap 8000\n"
               "FAIL  aggregate path-unscoped rules: 99999 bytes  (total budget 90000)")
_DOC_ERR = ("Bump last_verified\n"
            "ibl5/docs/FOO.md: body changed but last_verified not bumped (still 2026-01-01) "
            "— run `bin/check-docs --fix-dates`")

# error_kind, error, extra fields. One row per human_block class key.
_CLASSES = {
    "gate-adr": ("local-gate", _ADR_ERR, {}),
    "gate-adr-drafted": ("local-gate", _ADR_ERR,
                         {"adr_drafted": True, "adr_path": "ibl5/docs/decisions/0999-x.md"}),
    "gate-stale-base": ("local-gate", "branch does not contain origin/master", {}),
    "gate-byte-budget": ("local-gate", _BUDGET_ERR, {}),
    "gate-doc-staleness": ("local-gate", _DOC_ERR, {}),
    "gate-unknown": ("local-gate", "One or more checks failed:", {}),
    "rebase-conflict": ("rebase-conflict", "predicted by merge-tree probe vs origin/master: a.php, b.php", {}),
    "remote-head-diverged": ("remote-head-diverged", "phase4: head moved", {}),
    "llm-usage-limit": ("llm-usage-limit", "Claude usage limit reached", {}),
    "unknown": (None, "", {}),
}


def _res(kind, error, slug="feat/thing", **extra):
    r = RunResult(terminal=TerminalState.FAILED)
    r.error_kind = kind
    r.error = error
    r.slug = slug
    for k, v in extra.items():
        setattr(r, k, v)
    return r


def _block(key, **over):
    kind, error, extra = _CLASSES[key]
    return runner.human_block(_res(kind, error, **{**extra, **over}), 3, WT, LOG)


@pytest.mark.parametrize("rc", [0, 1, 4])
def test_block_empty_when_rc_not_3(rc):
    assert runner.human_block(_res("local-gate", _ADR_ERR), rc, WT, LOG) == ""


@pytest.mark.parametrize("key", list(_CLASSES))
def test_block_shape_every_class(key):
    block = _block(key)
    lines = block.split("\n")
    assert lines[0].endswith("did not ship. No PR opened.")
    assert "\nWhy: " in block
    assert f"\nFix:\n  1. cd {WT}" in block
    steps = [ln for ln in lines if ln.startswith("  ") and ln.strip()[:1].isdigit()]
    assert steps[-1].endswith(". bin/post-plan-now")
    assert lines[-3] == f"Log: {LOG}"
    assert lines[-2] == "Or paste this to have Claude fix it:"
    assert lines[-1] == f"{FIX} feat/thing"
    assert "Or open Claude in that folder" not in block


@pytest.mark.parametrize("key", list(_CLASSES))
def test_block_has_no_jargon_every_class(key):
    low = _block(key).lower()
    for word in ("harness", "sentinel", "fallback", "terminal", "class=", "rc=", "phase2"):
        assert word not in low, word


def test_adr_block_names_trigger_files_and_next_adr():
    block = _block("gate-adr")
    assert "bin/foo" in block
    assert ".github/workflows/x.yml" in block
    assert 'bin/next-adr "thing"' in block
    assert "bin/adr-check --commit" in block


def test_adr_drafted_block_points_at_draft():
    block = _block("gate-adr-drafted")
    assert "ibl5/docs/decisions/0999-x.md" in block
    assert "bin/next-adr" not in block


def test_stale_base_block_gives_fetch_and_rebase():
    block = _block("gate-stale-base")
    assert "git fetch origin master" in block
    assert "git rebase origin/master" in block


def test_byte_budget_block_names_over_cap_files():
    block = _block("gate-byte-budget")
    assert "  big-rule.md" in block
    assert "  other-rule.md" in block
    assert "aggregate" not in block
    assert "bin/check-rules-byte-budget" in block


def test_doc_staleness_block_names_docs_and_last_verified():
    block = _block("gate-doc-staleness")
    assert "  ibl5/docs/FOO.md" in block
    assert "last_verified:" in block


def test_rebase_conflict_block_names_paths_both_forms():
    probe = "predicted by merge-tree probe vs origin/master: a.php, b.php | auto-resolve declined: x"
    block = runner.human_block(_res("rebase-conflict", probe), 3, WT, LOG)
    assert "  a.php" in block and "  b.php" in block
    assert "auto-resolve" not in block
    live = "CONFLICT (content): Merge conflict in c.php | auto-resolve declined: y"
    block = runner.human_block(_res("rebase-conflict", live), 3, WT, LOG)
    assert "  c.php" in block
    assert "auto-resolve" not in block


def test_remote_diverged_block_gives_inspect_and_reset():
    block = _block("remote-head-diverged")
    assert "git log --oneline HEAD..origin/feat/thing" in block
    assert "git reset --hard origin/feat/thing" in block
    with_pr = _block("remote-head-diverged", pr_number=42)
    assert with_pr.split("\n")[0] == "feat/thing did not ship. PR #42 was not updated."


def test_usage_limit_block_says_wait():
    assert "Wait for the limit to reset" in _block("llm-usage-limit")


def test_unknown_block_points_at_log():
    for key in ("unknown", "gate-unknown"):
        block = _block(key)
        assert "Read the log named on the Log line below" in block
        why_to_fix = block.split("Why: ", 1)[1].split("\nFix:", 1)[0]
        assert "\n  " not in why_to_fix  # no path lines under Why


def test_paths_extracted_from_full_error_past_300_chars():
    error = ("x" * 400) + "\n" + _ADR_ERR
    assert "  bin/foo" in runner.human_block(_res("local-gate", error), 3, WT, LOG)


def test_path_list_capped_at_five_with_more_count():
    error = "\n".join(f"CONFLICT (content): Merge conflict in f{i}.php" for i in range(8))
    block = runner.human_block(_res("rebase-conflict", error), 3, WT, LOG)
    why_to_fix = block.split("Why: ", 1)[1].split("\nFix:", 1)[0]
    path_lines = [ln for ln in why_to_fix.split("\n")[1:] if ln.strip()]
    assert path_lines == [f"  f{i}.php" for i in range(5)] + ["  +3 more"]


def test_long_path_truncated_to_120():
    path = "d/" + "a" * 198
    block = runner.human_block(
        _res("rebase-conflict", f"Merge conflict in {path}"), 3, WT, LOG)
    line = next(ln for ln in block.split("\n") if ln.startswith("  d/"))
    assert len(line.strip()) == 120
    assert line.endswith("…")


def test_worst_case_block_under_budget_keeps_fix_and_log(monkeypatch):
    paths = [f"{i:02d}" + "p" * 198 for i in range(20)]
    error = ("n" * 5000) + "\n" + "\n".join(f"Merge conflict in {p}" for p in paths)
    branch, wt, log = "z" * 150, "w" * 500, "l" * 500
    res = _res("rebase-conflict", error, slug=branch)
    # With the cap lifted the 5-path render is over budget, so the zero-path
    # re-render is what keeps the shipped block under it.
    with monkeypatch.context() as m:
        m.setattr(runner, "_BLOCK_BUDGET", 10**6)
        assert len(runner.human_block(res, 3, wt, log)) > 1800
    assert runner._BLOCK_BUDGET <= 1845
    block = runner.human_block(res, 3, wt, log)
    assert len(block) <= 1800
    lines = block.split("\n")
    assert lines[-1].startswith(FIX)
    assert f"Log: {log}" in lines
    for step in ("git fetch origin master", "git rebase origin/master",
                 "git add <file> && git rebase --continue", ". bin/post-plan-now"):
        assert step in block
    assert paths[0] not in block


def test_log_line_defaults_when_log_path_empty():
    block = runner.human_block(_res("local-gate", _ADR_ERR), 3, WT, "")
    lines = block.split("\n")
    assert lines[-1].startswith(FIX)
    assert "Log: (see the run log)" in lines


def test_paste_line_uses_pr_number_when_known():
    block = _block("gate-adr", pr_number=17)
    assert block.split("\n")[-1] == f"{FIX} 17"


def test_no_paste_line_without_slug_or_pr():
    kind, error, extra = _CLASSES["gate-adr"]
    block = runner.human_block(_res(kind, error, slug="", **extra), 3, WT, LOG)
    assert block.split("\n")[-1] == f"Log: {LOG}"
    assert "postplan-fix" not in block
    assert "Or paste this" not in block


def test_paste_line_survives_budget_shrink(monkeypatch):
    error = "\n".join(f"Merge conflict in ibl5/classes/X{i}.php" for i in range(40))
    res = _res("rebase-conflict", error, slug="z" * 200)
    wt, log = "w" * 500, "l" * 500
    with monkeypatch.context() as m:
        m.setattr(runner, "_BLOCK_BUDGET", 10**6)
        full = runner.human_block(res, 3, wt, log)
    shrunk = runner.human_block(res, 3, wt, log)
    assert len(shrunk) < len(full)
    assert "ibl5/classes/X0.php" not in shrunk
    lines = shrunk.split("\n")
    assert lines[-2] == "Or paste this to have Claude fix it:"
    assert lines[-1].startswith(FIX)


@pytest.mark.parametrize("fn", ["_adr_trigger_paths", "_conflict_paths",
                                "_over_budget_paths", "_stale_doc_paths"])
def test_extractors_return_empty_on_no_match(fn):
    extractor = getattr(runner, fn)
    assert extractor("") == []
    assert extractor(None) == []
    assert extractor("nothing useful here, just prose.") == []
