"""Exit-code tests for `python3 -m harness.phase_replay <plan.md> <diff-file>`.

Each test is named for the mutation it kills. The 3923 and 3928 fixtures come from
test_conformance_phase_content_match; their cited paths are tracked in the repo, so
`_tracked_files()` keeps them as candidates.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness import phase_replay
from test_conformance_phase_content_match import DIFF_3923, PYTEST_3928, SHELLCHECK_3923


def _write(tmp_path, name, text):
    p = tmp_path / name
    p.write_text(text, encoding="utf-8")
    return str(p)


def test_replay_exits_zero_when_identifier_clears(tmp_path, capsys):
    """Mutation caught: `return 1 if items else 0` replaced with `return 1`."""
    plan = _write(tmp_path, "plan.md", SHELLCHECK_3923)
    diff = _write(tmp_path, "pr.diff", DIFF_3923)
    assert phase_replay.main([plan, diff]) == 0
    out = capsys.readouterr().out
    assert "MISSING-PHASE items: 0" in out
    assert "note: CONTENT-MATCHED-PHASE: 3" in out


def test_replay_exits_one_and_prints_missing_phase(tmp_path, capsys):
    """Mutation caught: `return 1 if items else 0` replaced with `return 0`."""
    plan = _write(tmp_path, "plan.md", PYTEST_3928)
    diff = _write(tmp_path, "pr.diff", (
        "diff --git a/bin/lib/prefire-verify b/bin/lib/prefire-verify\n"
        "--- a/bin/lib/prefire-verify\n+++ b/bin/lib/prefire-verify\n"
        "@@ -40,0 +41,1 @@\n+echo unrelated\n"))
    assert phase_replay.main([plan, diff]) == 1
    out = capsys.readouterr().out
    assert "MISSING-PHASE: 2 " in out
    assert "MISSING-PHASE items: 1" in out


def test_replay_exits_two_on_wrong_arg_count(tmp_path):
    """Mutation caught: `if len(args) != 2:` replaced with `if len(args) < 0:`."""
    plan = _write(tmp_path, "plan.md", SHELLCHECK_3923)
    diff = _write(tmp_path, "pr.diff", DIFF_3923)
    assert phase_replay.main([plan]) == 2
    assert phase_replay.main([plan, diff, "x"]) == 2


def test_replay_exits_two_on_unreadable_diff(tmp_path):
    """Mutation caught: the read guard catches only `UnicodeDecodeError`."""
    plan = _write(tmp_path, "plan.md", SHELLCHECK_3923)
    assert phase_replay.main([plan, str(tmp_path / "missing.diff")]) == 2


def test_replay_exits_two_on_diff_without_file_headers(tmp_path, capsys):
    """Mutation caught: `or not changed` dropped from the empty-input check."""
    plan = _write(tmp_path, "plan.md", PYTEST_3928)
    diff = _write(tmp_path, "pr.diff", "+stage_pytest() {\n")
    assert phase_replay.main([plan, diff]) == 2
    assert "MISSING-PHASE items: 0" not in capsys.readouterr().out


def test_changed_files_from_diff_reads_b_paths():
    """Mutation caught: `_DIFF_GIT` captures the a-side path."""
    diff = ("diff --git a/old/x.sh b/new/x.sh\nsimilarity index 90%\n"
            "rename from old/x.sh\nrename to new/x.sh\n"
            "diff --git a/bin/a b/bin/a\n--- a/bin/a\n+++ b/bin/a\n")
    assert phase_replay.changed_files_from_diff(diff) == ["bin/a", "new/x.sh"]
