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


# ---------------------------------------------------------------------------
# Phase 3: corpus validate
# ---------------------------------------------------------------------------

import json  # noqa: E402

NOW = 1_790_000_000
DAY = 86400


def _commit(repo, msg, ts, files):
    for name, text in files.items():
        p = repo / name
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(text)
    env = {**os.environ, "GIT_COMMITTER_DATE": f"{ts} +0000", "GIT_AUTHOR_DATE": f"{ts} +0000"}
    subprocess.run(["git", "-C", str(repo), "add", "-A"], check=True, env=env)
    subprocess.run(["git", "-C", str(repo), "-c", "user.email=t@t", "-c", "user.name=t",
                    "-c", "commit.gpgsign=false", "commit", "-q", "--no-verify", "-m", msg],
                   check=True, env=env)
    return subprocess.run(["git", "-C", str(repo), "rev-parse", "HEAD"], check=True,
                          capture_output=True, text=True).stdout.strip()


def _mk_repo(tmp_path):
    repo = tmp_path / "repo"
    repo.mkdir()
    subprocess.run(["git", "init", "-q", str(repo)], check=True)
    path = "ibl5/classes/Foo.php"
    lines = [f"line{n:02d}" for n in range(1, 31)]
    text = lambda ls: "\n".join(ls) + "\n"  # noqa: E731
    base = _commit(repo, "chore: base (#1)", NOW - 100 * DAY, {path: text(lines)})
    for n in range(10, 15):
        lines[n - 1] = f"intro{n}"
    intro = _commit(repo, "feat: add rule (#10)", NOW - 60 * DAY, {path: text(lines)})
    lines = [f"pad{n}" for n in range(1, 6)] + lines   # shifts I's lines down by 5
    shift = _commit(repo, "chore: shift (#12)", NOW - 55 * DAY, {path: text(lines)})
    lines[lines.index("intro12")] = "fixed12"
    fix = _commit(repo, "fix: off by one (#11)", NOW - 50 * DAY, {path: text(lines)})
    lines[lines.index("line25")] = "tweak25"
    fix2 = _commit(repo, "fix: unrelated tweak (#13)", NOW - 45 * DAY, {path: text(lines)})
    return {"root": repo, "base": base, "intro": intro, "shift": shift, "fix": fix, "fix2": fix2}


def _bug(r, **kw):
    c = {"id": "b1", "kind": "bug", "status": "confirmed", "pr": 10, "commit": r["intro"],
         "fix_pr": 11, "fix_commit": r["fix"],
         "expected": [{"path": "ibl5/classes/Foo.php", "start": 10, "end": 14}],
         "description": "off by one"}
    c.update(kw)
    return c


def _vcorpus(cases, clean=()):
    return {"schema_version": 1, "window_days": 30, "cases": list(cases), "clean": list(clean)}


def _clean(cid, pr, commit):
    return {"id": cid, "pr": pr, "commit": commit, "checked_through": "2026-09-01"}


def _vg(r, corpus):
    return re_.validate_git(r["root"], corpus, now=NOW)


def test_validate_schema_rejects_bad_fields(tmp_path):
    r = {"intro": "a" * 40, "fix": "b" * 40}
    assert re_.validate_schema(_vcorpus([_bug(r)])) == []
    variants = [
        _bug(r, commit="a" * 39),
        _bug(r, expected=[{"path": "x.php", "start": 5, "end": 4}]),
        _bug(r, extra="x"),
        _bug(r, description="mail me@example.com"),
        _bug(r, pr=True),
    ]
    for v in variants:
        errs = re_.validate_schema(_vcorpus([v]))
        assert len(errs) == 1, (v, errs)
    errs = re_.validate_schema(_vcorpus([_bug(r), _bug(r)]))
    assert len(errs) == 1 and "duplicate" in errs[0]


def test_validate_git_confirmed_case_passes(tmp_path):
    r = _mk_repo(tmp_path)
    assert _vg(r, _vcorpus([_bug(r)])) == []


def test_validate_range_outside_added_lines(tmp_path):
    r = _mk_repo(tmp_path)
    c = _bug(r, expected=[{"path": "ibl5/classes/Foo.php", "start": 20, "end": 22}])
    errs = _vg(r, _vcorpus([c]))
    assert any("b1" in e and "outside the lines added" in e for e in errs), errs


def test_validate_fix_not_touching_range(tmp_path):
    r = _mk_repo(tmp_path)
    c = _bug(r, fix_commit=r["fix2"], fix_pr=13)
    errs = _vg(r, _vcorpus([c]))
    assert any("b1" in e and "does not corroborate" in e for e in errs), errs
    assert len(errs) == 1


def test_validate_fix_must_be_later(tmp_path):
    r = _mk_repo(tmp_path)
    c = _bug(r, commit=r["fix"], pr=11, fix_commit=r["intro"], fix_pr=10)
    errs = _vg(r, _vcorpus([c]))
    assert any("b1" in e and "must be later" in e for e in errs), errs


def test_validate_subject_pr_mismatch(tmp_path):
    r = _mk_repo(tmp_path)
    errs = _vg(r, _vcorpus([_bug(r, pr=99)]))
    assert any("b1" in e and "subject" in e and "(#99)" in e for e in errs), errs


def test_validate_unconfirmed_skips_git(tmp_path):
    r = _mk_repo(tmp_path)
    u = _bug(r, id="u1", status="unconfirmed", commit="f" * 40, fix_commit="e" * 40)
    assert _vg(r, _vcorpus([_bug(r), u])) == []


def test_validate_zero_confirmed_is_error(tmp_path):
    r = _mk_repo(tmp_path)
    u = _bug(r, id="u1", status="unconfirmed")
    assert "no confirmed bug cases" in _vg(r, _vcorpus([u]))


def test_validate_clean_blamed_by_fix_rejected(tmp_path):
    r = _mk_repo(tmp_path)
    errs = _vg(r, _vcorpus([_bug(r)], [_clean("c1", 10, r["intro"])]))
    assert any("c1" in e and f"blamed by fix {r['fix'][:12]}" in e for e in errs), errs
    assert _vg(r, _vcorpus([_bug(r)], [_clean("c2", 1, r["base"])])) == []


def test_validate_clean_too_young(tmp_path):
    r = _mk_repo(tmp_path)
    young = _commit(r["root"], "chore: late (#14)", NOW - 5 * DAY, {"ibl5/classes/Bar.php": "x\n"})
    errs = _vg(r, _vcorpus([_bug(r)], [_clean("c1", 14, young)]))
    assert any("c1" in e and "too young" in e for e in errs), errs


def test_validate_cli_exit_codes(tmp_path, capsys):
    r = _mk_repo(tmp_path)

    def write(name, corpus):
        p = tmp_path / name
        p.write_text(json.dumps(corpus))
        return str(p)

    good = write("good.json", _vcorpus([_bug(r)], [_clean("c1", 1, r["base"])]))
    bad = write("bad.json", _vcorpus([_bug(r, pr=99)]))
    repo = str(r["root"])
    assert re_.main(["validate", "--corpus", good, "--repo", repo]) == 0
    assert "validate ok: 1 confirmed, 0 unconfirmed, 1 clean" in capsys.readouterr().out
    assert re_.main(["validate", "--corpus", bad, "--repo", repo]) == 1
    assert "FAIL b1:" in capsys.readouterr().out
    assert re_.main(["validate", "--corpus", str(tmp_path / "nope.json"), "--repo", repo]) == 2
    assert "error:" in capsys.readouterr().err
    with pytest.raises(SystemExit) as exc:
        re_.main(["validate", "--bogus"])
    assert exc.value.code == 2
