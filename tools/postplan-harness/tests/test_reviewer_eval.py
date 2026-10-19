import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import reviewer_eval as re_
from harness.state import Finding


def _f(path, line):
    return Finding(source="code", agent="a", path=path, line=line, body="x", score=90)


R = re_.ExpectedRange


# ---------------------------------------------------------------------------
# Phase 1: match rule, score math, score line
# ---------------------------------------------------------------------------

def test_match_window_inclusive_boundary():
    rng = R("a.php", 10, 20)
    assert re_.finding_matches("a.php", 5, rng)
    assert re_.finding_matches("a.php", 25, rng)
    assert not re_.finding_matches("a.php", 4, rng)
    assert not re_.finding_matches("a.php", 26, rng)


def test_match_wrong_path_rejected():
    assert not re_.finding_matches("b.php", 15, R("a.php", 10, 20))


def test_match_line_zero_never_matches():
    rng = R("a.php", 1, 3)
    assert not re_.finding_matches("a.php", 0, rng)
    assert not re_.case_hit([_f("a.php", 0)], [rng])
    assert re_.file_only_count([_f("a.php", 0)], [rng]) == 1


def test_match_path_normalization():
    rng = R("a.php", 10, 20)
    for p in ("./a.php", "/a.php", ".//a.php", " a.php "):
        assert re_.finding_matches(p, 12, rng), p


def test_case_hit_multi_range():
    expected = [R("a.php", 10, 20), R("b.php", 100, 110)]
    assert re_.case_hit([_f("b.php", 105)], expected)
    assert not re_.case_hit([_f("b.php", 50), _f("a.php", 40)], expected)


def _out(id_, kind, n_findings=0, hit=None, degraded=()):
    return re_.CaseOutcome(id=id_, kind=kind,
                           findings=tuple(_f("a.php", i + 1) for i in range(n_findings)),
                           degraded_agents=tuple(degraded), hit=hit)


def test_compute_score_counts():
    bug = [_out("b1", "bug", 1, True), _out("b2", "bug", 1, True),
           _out("b3", "bug", 0, False, degraded=("A",))]
    clean = [_out("c1", "clean", 3), _out("c2", "clean", 0)]
    s = re_.compute_score(bug, clean, v="0" * 12, c="1" * 12)
    assert (s.hits, s.scored, s.false_alarms, s.clean_flagged, s.clean_total, s.degraded) \
        == (2, 3, 3, 1, 2, 1)


def test_compute_score_zero_bug_cases_is_usage_error():
    with pytest.raises(re_.EvalUsageError):
        re_.compute_score([], [_out("c1", "clean", 0)], v="0" * 12, c="1" * 12)


def test_score_line_round_trip():
    s = re_.Score(v="abcdef012345", c="543210fedcba", hits=3, scored=7, false_alarms=4,
                  clean_flagged=2, clean_total=6, degraded=1)
    assert re_.parse_score_line(re_.format_score_line(s)) == s
    lit = re_.parse_score_line("reviewer-eval v=0123456789ab c=ba9876543210 recall=5/8 "
                               "false_alarms=3 clean_flagged=2/8 degraded=0")
    assert lit == re_.Score(v="0123456789ab", c="ba9876543210", hits=5, scored=8,
                            false_alarms=3, clean_flagged=2, clean_total=8, degraded=0)


@pytest.mark.parametrize("line", [
    "reviewer-eval v=0123456789a c=ba9876543210 recall=5/8 false_alarms=3 clean_flagged=2/8 degraded=0",
    "reviewer-eval v=0123456789AB c=ba9876543210 recall=5/8 false_alarms=3 clean_flagged=2/8 degraded=0",
    "reviewer-eval v=0123456789ab c=ba9876543210 recall=5/8 false_alarms=3 clean_flagged=2/8 degraded=0 extra",
    "reviewer-eval v=0123456789ab c=ba9876543210 recall=9/8 false_alarms=3 clean_flagged=2/8 degraded=0",
    "reviewer-eval v=0123456789ab c=ba9876543210 recall=0/0 false_alarms=3 clean_flagged=2/8 degraded=0",
])
def test_parse_score_line_rejects_malformed(line):
    with pytest.raises(re_.EvalUsageError):
        re_.parse_score_line(line)


def _s(hits, fa, scored=8, clean_total=8):
    return re_.Score(v="0" * 12, c="1" * 12, hits=hits, scored=scored, false_alarms=fa,
                     clean_flagged=0, clean_total=clean_total, degraded=0)


def test_is_worse_rule():
    assert re_.is_worse(_s(5, 3), _s(4, 3))
    assert re_.is_worse(_s(5, 3), _s(5, 4))
    assert not re_.is_worse(_s(5, 3), _s(5, 3))
    assert not re_.is_worse(_s(5, 3), _s(6, 3))
    with pytest.raises(re_.EvalUsageError):
        re_.is_worse(_s(5, 3), _s(5, 3, scored=9))


# ---------------------------------------------------------------------------
# Phase 2: surface hash and corpus digest
# ---------------------------------------------------------------------------

LLM_PY = ('import os\n\nMODEL_MAP = {\n    "haiku": "claude-haiku-5-5",\n'
          '    "sonnet": "claude-sonnet-5-5",\n}\n\nOTHER = 1\n')


def _surface_tree(root):
    for rel in re_.REVIEW_SURFACE_PATHS:
        p = root / rel
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(f"content of {rel}\n")
    llm = root / re_.MODEL_MAP_SOURCE
    llm.parent.mkdir(parents=True, exist_ok=True)
    llm.write_text(LLM_PY)
    return root


def test_surface_hash_deterministic(tmp_path):
    root = _surface_tree(tmp_path)
    a = re_.surface_hash_worktree(root)
    b = re_.surface_hash_worktree(root)
    assert a == b
    assert len(a) == 12 and all(ch in "0123456789abcdef" for ch in a)


def test_surface_hash_one_byte_change(tmp_path):
    root = _surface_tree(tmp_path)
    before = re_.surface_hash_worktree(root)
    with open(root / ".claude/review-shared/_review-rubric.md", "ab") as fh:
        fh.write(b"x")
    assert re_.surface_hash_worktree(root) != before


def test_surface_hash_model_map_edit_changes(tmp_path):
    root = _surface_tree(tmp_path)
    before = re_.surface_hash_worktree(root)
    llm = root / re_.MODEL_MAP_SOURCE
    llm.write_text(LLM_PY.replace('"claude-sonnet-5-5"', '"claude-sonnet-5-6"'))
    assert re_.surface_hash_worktree(root) != before


def test_surface_hash_unrelated_llm_edit_no_change(tmp_path):
    root = _surface_tree(tmp_path)
    before = re_.surface_hash_worktree(root)
    llm = root / re_.MODEL_MAP_SOURCE
    llm.write_text(LLM_PY + "# an unrelated comment\n")
    assert re_.surface_hash_worktree(root) == before


def test_surface_hash_missing_model_map_is_error(tmp_path):
    root = _surface_tree(tmp_path)
    (root / re_.MODEL_MAP_SOURCE).write_text("import os\nOTHER = 1\n")
    with pytest.raises(re_.SurfaceError):
        re_.surface_hash_worktree(root)


def test_surface_hash_absent_file_marker(tmp_path):
    root = _surface_tree(tmp_path)
    sec = root / ".claude/review-shared/_security-agents.md"
    present = re_.surface_hash_worktree(root)
    sec.unlink()
    absent = re_.surface_hash_worktree(root)
    assert absent == re_.surface_hash_worktree(root)
    sec.write_bytes(b"")
    empty = re_.surface_hash_worktree(root)
    assert len({present, absent, empty}) == 3


def test_surface_hash_unreadable_file_is_error(tmp_path):
    root = _surface_tree(tmp_path)
    agents = root / ".claude/review-shared/_review-agents.md"
    agents.unlink()
    agents.mkdir()
    with pytest.raises(re_.SurfaceError):
        re_.surface_hash_worktree(root)


def _git(root, *argv, env=None):
    return subprocess.run(["git", "-C", str(root), "-c", "user.email=t@t", "-c", "user.name=t",
                           *argv], capture_output=True, text=True, check=True, env=env).stdout


def test_surface_hash_ref_matches_worktree(tmp_path):
    root = _surface_tree(tmp_path)
    _git(root, "init", "-q")
    _git(root, "add", "-A")
    _git(root, "commit", "-q", "-m", "base")
    assert re_.surface_hash_ref(root, "HEAD") == re_.surface_hash_worktree(root)
    with pytest.raises(re_.SurfaceError):
        re_.surface_hash_ref(root, "no-such-ref")


def _corpus():
    return {
        "schema_version": 1, "window_days": 60,
        "cases": [{"id": "b1", "kind": "bug", "status": "confirmed", "pr": 10,
                   "commit": "a" * 40, "fix_pr": 11, "fix_commit": "b" * 40,
                   "expected": [{"path": "a.php", "start": 10, "end": 14}],
                   "description": "off by one"}],
        "clean": [{"id": "c1", "pr": 12, "commit": "c" * 40, "checked_through": "2026-10-01"}],
    }


def test_corpus_digest_scope():
    base = re_.corpus_digest(_corpus())
    c = _corpus()
    c["cases"][0]["description"] = "a different description"
    assert re_.corpus_digest(c) == base
    c = _corpus()
    c["cases"].append({**c["cases"][0], "id": "b2", "status": "unconfirmed"})
    assert re_.corpus_digest(c) == base
    c = _corpus()
    c["cases"][0]["expected"][0]["end"] = 15
    assert re_.corpus_digest(c) != base
    c = _corpus()
    c["clean"][0]["commit"] = "d" * 40
    assert re_.corpus_digest(c) != base
