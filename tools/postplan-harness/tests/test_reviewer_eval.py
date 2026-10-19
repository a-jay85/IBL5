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
    # The introducing commit's subject must end (#pr) for the case's own pr number.
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


# ---------------------------------------------------------------------------
# Phase 4: run and smoke
# ---------------------------------------------------------------------------

import io  # noqa: E402

from harness.state import HarnessError  # noqa: E402

FOO = "ibl5/classes/Foo.php"


def _canned(path, line, score):
    return {"review-agent-a": [{"path": path, "line": line, "body": "b"}],
            "review-agent-b": [], "review-agent-d": [], "security-audit": [],
            "score-findings": [{"n": 1, "score": score}]}


class _StubLlm:
    def __init__(self, canned, raises=()):
        self.canned = canned
        self.raises = set(raises)
        self.calls = []

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        self.calls.append(purpose)
        if purpose in self.raises:
            raise HarnessError("llm-invalid-output", "x")
        data = self.canned[purpose]
        if normalizer is not None:
            data = normalizer(data)
        validate(data)
        return data


def _eval_repo(tmp_path):
    r = _mk_repo(tmp_path)
    _surface_tree(r["root"])
    return r


def _eval_corpus(r):
    b2 = _bug(r, id="b2", commit=r["fix"], pr=11, fix_commit=r["fix2"], fix_pr=13)
    u1 = _bug(r, id="u1", status="unconfirmed", commit="f" * 40, fix_commit="e" * 40)
    return _vcorpus([_bug(r), b2, u1], [_clean("c1", 13, r["fix2"])])


def _run(r, corpus, factory, **kw):
    out = io.StringIO()
    args = dict(kinds="all", case_ids=None, llm_factory=factory, cache_dir=None, dry_run=False)
    args.update(kw)
    rc = re_.run_eval(r["root"], corpus, out=out, **args)
    return rc, out.getvalue()


def _last(text):
    return text.strip().splitlines()[-1]


def _canned_by_case():
    return {"b1": _canned(FOO, 12, 90), "b2": _canned(FOO, 50, 90), "c1": _canned(FOO, 3, 90)}


def test_run_canned_hit_and_miss(tmp_path):
    r = _eval_repo(tmp_path)
    canned = _canned_by_case()
    rc, text = _run(r, _eval_corpus(r), lambda cid: _StubLlm(canned[cid]))
    assert rc == 0
    assert _last(text).endswith("recall=1/2 false_alarms=1 clean_flagged=1/1 degraded=0")
    assert "case b1 bug HIT" in text and "case b2 bug MISS" in text


def test_run_below_threshold_not_counted(tmp_path):
    r = _eval_repo(tmp_path)
    corpus = _vcorpus([_bug(r)])
    rc, text = _run(r, corpus, lambda cid: _StubLlm(_canned(FOO, 12, 79)))
    assert rc == 0
    assert "case b1 bug MISS findings=0" in text
    assert "recall=0/1" in _last(text)


def test_run_degraded_exit_3(tmp_path):
    r = _eval_repo(tmp_path)
    cache = tmp_path / "cache"
    rc, text = _run(r, _vcorpus([_bug(r)]),
                    lambda cid: _StubLlm(_canned(FOO, 12, 90), raises={"review-agent-a"}),
                    cache_dir=cache)
    assert rc == 3
    assert _last(text).endswith("degraded=1")
    assert "DEGRADED=review-agent-a" in text
    assert not cache.exists()


def test_run_cache_reuse(tmp_path):
    r = _eval_repo(tmp_path)
    corpus = _vcorpus([_bug(r)])
    cache = tmp_path / "cache"
    stub = _StubLlm(_canned(FOO, 12, 90))
    rc, first = _run(r, corpus, lambda cid: stub, cache_dir=cache)
    assert rc == 0 and stub.calls
    stub.calls.clear()
    rc, second = _run(r, corpus, lambda cid: stub, cache_dir=cache)
    assert rc == 0 and stub.calls == []
    assert _last(second).encode() == _last(first).encode()
    _run(r, corpus, lambda cid: stub, cache_dir=None)
    assert stub.calls
    stub.calls.clear()
    with open(r["root"] / ".claude/review-shared/_review-rubric.md", "ab") as fh:
        fh.write(b"edit")
    _run(r, corpus, lambda cid: stub, cache_dir=cache)
    assert stub.calls


def test_run_canned_never_writes_cache(tmp_path, capsys):
    r = _eval_repo(tmp_path)
    corpus = _eval_corpus(r)
    cpath = tmp_path / "corpus.json"
    cpath.write_text(json.dumps(corpus))
    fpath = tmp_path / "canned.json"
    fpath.write_text(json.dumps(_canned_by_case()))
    repo = str(r["root"])
    rc = re_.main(["run", "--canned", str(fpath), "--repo", repo, "--corpus", str(cpath)])
    assert rc == 0
    assert "recall=1/2" in capsys.readouterr().out
    assert not (r["root"] / "tools/postplan-harness/out/reviewer-eval/cache").exists()


def test_run_unconfirmed_excluded(tmp_path):
    r = _eval_repo(tmp_path)
    canned = _canned_by_case()
    seen = []

    def factory(cid):
        seen.append(cid)
        return _StubLlm(canned[cid])

    rc, text = _run(r, _eval_corpus(r), factory)
    assert rc == 0
    assert "u1" not in seen and sorted(seen) == ["b1", "b2", "c1"]
    assert "excluded_unconfirmed=1" in text


def test_run_subset_changes_c(tmp_path):
    r = _eval_repo(tmp_path)
    canned = _canned_by_case()
    corpus = _eval_corpus(r)
    _, full = _run(r, corpus, lambda cid: _StubLlm(canned[cid]))
    _, sub = _run(r, corpus, lambda cid: _StubLlm(canned[cid]), case_ids={"b1"})
    assert re_.parse_score_line(_last(sub)).c != re_.parse_score_line(_last(full)).c


def test_run_unknown_case_id_usage_error(tmp_path, capsys):
    r = _eval_repo(tmp_path)
    corpus = _eval_corpus(r)
    cpath = tmp_path / "corpus.json"
    cpath.write_text(json.dumps(corpus))
    fpath = tmp_path / "canned.json"
    fpath.write_text(json.dumps(_canned_by_case()))
    rc = re_.main(["run", "--canned", str(fpath), "--repo", str(r["root"]),
                   "--corpus", str(cpath), "--cases", "nope"])
    assert rc == 2
    assert "nope" in capsys.readouterr().err
    with pytest.raises(re_.EvalUsageError):
        _run(r, corpus, lambda cid: _StubLlm({}), case_ids={"nope"})


def test_run_dry_run_no_llm(tmp_path):
    r = _eval_repo(tmp_path)

    def factory(cid):
        raise AssertionError("llm_factory must not be called in dry-run")

    rc, text = _run(r, _eval_corpus(r), factory, dry_run=True)
    assert rc == 0
    assert "dry-run total est_calls=" in text
    assert "case b1 bug dry gates=A,security" in text


def test_smoke_stub_ok_and_failure(capsys):
    assert re_.smoke(_StubLlm({"score-findings": [{"n": 1, "score": 40}]})) == 0
    assert "smoke ok" in capsys.readouterr().out
    assert re_.smoke(_StubLlm({}, raises={"score-findings"})) == 1
    assert "smoke failed: llm-invalid-output: x" in capsys.readouterr().err


def test_null_gh_rejects_unknown_call():
    gh = re_.NullGh()
    assert gh.post_review_findings(1, "sha", "t", []) is None
    assert gh.post_review_summary(1, "t", "b") is None
    with pytest.raises(AttributeError):
        gh.get_pr


# ---------------------------------------------------------------------------
# Phase 5: miner, compare
# ---------------------------------------------------------------------------

import contextlib  # noqa: E402

from harness import reviewer_eval_mine as mine_  # noqa: E402


def _status(root):
    return subprocess.run(["git", "-C", str(root), "status", "--porcelain"], check=True,
                          capture_output=True, text=True).stdout


def _mine_bugs_out(root, capsys, *extra):
    rc = re_.main(["mine", "bugs", "--repo", str(root), "--since", "2000-01-01", *extra])
    assert rc == 0
    return [json.loads(ln) for ln in capsys.readouterr().out.splitlines() if ln.strip()]


def _simple_repo(tmp_path, *, path=FOO, intro_subject="feat: add rule (#10)", fixed=1, total=100):
    """base -> introducer rewrites every line -> fix rewrites the first `fixed` lines."""
    repo = tmp_path / "simple"
    repo.mkdir()
    subprocess.run(["git", "init", "-q", str(repo)], check=True)
    text = lambda ls: "\n".join(ls) + "\n"  # noqa: E731
    lines = [f"line{n:03d}" for n in range(1, total + 1)]
    _commit(repo, "chore: base (#1)", NOW - 100 * DAY, {path: text(lines)})
    lines = [f"intro{n:03d}" for n in range(1, total + 1)]
    intro = _commit(repo, intro_subject, NOW - 60 * DAY, {path: text(lines)})
    lines = [f"fixed{n:03d}" if n <= fixed else ln for n, ln in enumerate(lines, 1)]
    fix = _commit(repo, "fix: patch (#11)", NOW - 50 * DAY, {path: text(lines)})
    return {"root": repo, "intro": intro, "fix": fix}


def test_mine_bugs_blame_mapping(tmp_path, capsys):
    r = _mk_repo(tmp_path)
    cands = _mine_bugs_out(r["root"], capsys)
    assert len(cands) == 1
    c = cands[0]
    assert c["commit"] == r["intro"] and c["fix_commit"] == r["fix"]
    assert c["expected"] == [{"path": "ibl5/classes/Foo.php", "start": 12, "end": 12}]
    assert c["status"] == "unconfirmed" and c["pr"] == 10 and c["fix_pr"] == 11
    assert c["id"] == "bug-pr10-foo"
    curated = {k: v for k, v in c.items() if not k.startswith("_")}
    curated.update(status="confirmed", description="off by one")
    assert re_.validate_schema(_vcorpus([curated])) == []
    assert re_.validate_git(r["root"], _vcorpus([curated]), now=NOW) == []
    # the underscore hints fail the schema on purpose until a curator strips them
    assert re_.validate_schema(_vcorpus([c])) != []


def test_mine_bugs_skips_chore_introducer(tmp_path, capsys):
    ok = _simple_repo(tmp_path, intro_subject="feat: add rule (#10)")
    assert len(_mine_bugs_out(ok["root"], capsys)) == 1
    assert "chore" in mine_.SKIP_TYPES
    sub = tmp_path / "chore"
    sub.mkdir()
    r = _simple_repo(sub, intro_subject="chore: move (#12)")
    assert _mine_bugs_out(r["root"], capsys) == []


def test_mine_bugs_skips_large_fix(tmp_path, capsys):
    small = _simple_repo(tmp_path, fixed=5)
    assert len(_mine_bugs_out(small["root"], capsys)) == 1
    sub = tmp_path / "big"
    sub.mkdir()
    big = _simple_repo(sub, fixed=81)
    assert mine_.MAX_FIX_LINES == 80
    assert _mine_bugs_out(big["root"], capsys) == []


@pytest.mark.parametrize("path", ["ibl5/classes/FooTest.php", "ibl5/modules/Bar/tests/Helper.php"])
def test_mine_bugs_skips_test_paths(tmp_path, capsys, path):
    ok = _simple_repo(tmp_path)
    assert len(_mine_bugs_out(ok["root"], capsys)) == 1
    sub = tmp_path / "t"
    sub.mkdir()
    r = _simple_repo(sub, path=path)
    assert _mine_bugs_out(r["root"], capsys) == []


def test_mine_clean_excludes_blamed_and_young(tmp_path):
    r = _mk_repo(tmp_path)
    bar = _commit(r["root"], "feat: add bar (#20)", NOW - 40 * DAY, {"ibl5/classes/Bar.php": "x\n"})
    young = _commit(r["root"], "feat: young (#21)", NOW - 5 * DAY, {"ibl5/classes/Baz.php": "x\n"})
    buf = io.StringIO()
    assert mine_.mine_clean(r["root"], ref="HEAD", window_days=30, limit=20, now=NOW, out=buf) == 0
    rows = [json.loads(ln) for ln in buf.getvalue().splitlines()]
    commits = {row["commit"] for row in rows}
    assert bar in commits
    assert r["intro"] not in commits      # blamed by the fix
    assert young not in commits           # inside the window
    assert r["shift"] not in commits      # chore
    row = next(x for x in rows if x["commit"] == bar)
    assert row["id"] == "clean-pr20" and row["pr"] == 20
    assert re_.validate_schema(_vcorpus([_bug(r)], [row])) == []


def test_mine_never_writes_files(tmp_path, capsys):
    r = _mk_repo(tmp_path)
    before = _status(r["root"])
    assert re_.main(["mine", "bugs", "--repo", str(r["root"]), "--since", "2000-01-01"]) == 0
    assert capsys.readouterr().out.strip()
    assert re_.main(["mine", "clean", "--repo", str(r["root"]), "--window-days", "1"]) == 0
    assert capsys.readouterr().out.strip()
    assert _status(r["root"]) == before


class _Runner:
    def __init__(self, results):
        self.results = results
        self.calls = []

    def __call__(self, path, args):
        self.calls.append((path, list(args)))
        return self.results[len(self.calls) - 1]


class _Trees:
    def __init__(self, tree):
        self.tree = tree
        self.entered = []

    @contextlib.contextmanager
    def __call__(self, root, sha):
        self.entered.append(sha)
        yield self.tree


def _compare_repo(tmp_path):
    r = _mk_repo(tmp_path)
    r["tool"] = _commit(r["root"], "chore: add tool (#30)", NOW - DAY,
                        {"bin/reviewer-eval": "#!/bin/sh\n"})
    r["tree"] = tmp_path / "tree"
    r["tree"].mkdir()
    return r


def _compare(r, runner, corpus=None, **kw):
    out, err = io.StringIO(), io.StringIO()
    trees = _Trees(r["tree"])
    rc = re_.compare(r["root"], r["tool"], corpus or (r["root"] / "corpus.json"),
                     runner=runner, make_tree=trees, out=out, err=err, **kw)
    return rc, out.getvalue(), err.getvalue(), trees


def test_compare_prints_block(tmp_path):
    r = _compare_repo(tmp_path)
    a, b = re_.format_score_line(_s(5, 1)), re_.format_score_line(_s(6, 1))
    runner = _Runner([(0, f"noise\n{a}\n"), (0, f"noise\n{b}\n")])
    rc, out, _err, trees = _compare(r, runner)
    assert rc == 0
    assert out == f"Reviewer eval before: {a}\nReviewer eval after: {b}\n"
    assert runner.calls[0][0] == r["tree"] / "bin/reviewer-eval"
    assert runner.calls[1][0] == r["root"] / "bin/reviewer-eval"
    assert trees.entered == [r["tool"]]


def test_compare_worse_notes_stderr_and_exits_0(tmp_path):
    r = _compare_repo(tmp_path)
    a, b = re_.format_score_line(_s(6, 1)), re_.format_score_line(_s(5, 1))
    rc, out, err, _t = _compare(r, _Runner([(0, a), (0, b)]))
    assert rc == 0
    assert "reviewer-eval-regression-ok" in err
    assert "reviewer-eval-regression-ok" not in out


def test_compare_shared_corpus_and_cache(tmp_path):
    r = _compare_repo(tmp_path)
    line = re_.format_score_line(_s(5, 1))
    runner = _Runner([(0, line), (0, line)])
    corpus = tmp_path / "shared" / "corpus.json"
    cache = tmp_path / "shared" / "cache"
    rc, _out, _err, _t = _compare(r, runner, corpus, cache_dir=cache)
    assert rc == 0
    (_, base_args), (_, head_args) = runner.calls
    assert base_args == head_args
    assert base_args == ["run", "--corpus", str(corpus.resolve()),
                         "--cache-dir", str(cache.resolve())]
    assert os.path.isabs(base_args[2]) and os.path.isabs(base_args[4])


def test_compare_base_predates_tool(tmp_path):
    r = _compare_repo(tmp_path)
    runner = _Runner([])
    out, err = io.StringIO(), io.StringIO()
    trees = _Trees(r["tree"])
    rc = re_.compare(r["root"], r["base"], r["root"] / "corpus.json",
                     runner=runner, make_tree=trees, out=out, err=err)
    assert rc == 2
    assert "predates bin/reviewer-eval" in err.getvalue()
    assert trees.entered == [] and runner.calls == []
    rc = re_.compare(r["root"], "no-such-ref", r["root"] / "corpus.json",
                     runner=runner, make_tree=trees, out=out, err=err)
    assert rc == 2 and trees.entered == []


def test_compare_propagates_degraded_and_usage(tmp_path):
    r = _compare_repo(tmp_path)
    line = re_.format_score_line(_s(5, 1))
    rc, out, _err, _t = _compare(r, _Runner([(0, line), (3, line)]))
    assert rc == 3
    assert out.count("Reviewer eval ") == 2
    rc, out, _err, _t = _compare(r, _Runner([(2, ""), (0, line)]))
    assert rc == 2 and out == ""
    rc, out, _err, _t = _compare(r, _Runner([(0, line), (2, "")]))
    assert rc == 2 and out == ""
    rc, out, _err, _t = _compare(r, _Runner([(0, "garbage"), (0, line)]))
    assert rc == 2 and out == ""


# ---------------------------------------------------------------------------
# Phase 6: check_claim
# ---------------------------------------------------------------------------

def _git_run(root, *argv):
    subprocess.run(["git", "-C", str(root), "-c", "user.email=t@t", "-c", "user.name=t", *argv],
                   check=True, capture_output=True)


def _claim_repo(tmp_path):
    root = tmp_path / "claim"
    root.mkdir()
    _git_run(root, "init", "-q")
    for rel in re_.REVIEW_SURFACE_PATHS:
        (root / rel).parent.mkdir(parents=True, exist_ok=True)
        (root / rel).write_text("one\n")
    llm = root / re_.MODEL_MAP_SOURCE
    llm.parent.mkdir(parents=True, exist_ok=True)
    llm.write_text("MODEL_MAP = {\n    'a': 'b',\n}\n")
    corpus = root / re_.DEFAULT_CORPUS
    corpus.parent.mkdir(parents=True, exist_ok=True)
    corpus.write_text(json.dumps({
        "schema_version": 1, "window_days": 30,
        "cases": [{"id": "bug-one", "commit": "a" * 40, "status": "confirmed",
                   "expected": [{"path": "a.php", "start": 1, "end": 2}]}],
        "clean": [{"id": "clean-one", "commit": "b" * 40}],
    }))
    _git_run(root, "add", "-A")
    _git_run(root, "commit", "-q", "-m", "base")
    base = subprocess.run(["git", "-C", str(root), "rev-parse", "HEAD"], capture_output=True,
                          text=True, check=True).stdout.strip()
    return root, base


def _commit_all(root, msg):
    _git_run(root, "add", "-A")
    _git_run(root, "commit", "-q", "-m", msg)


def test_check_claim_unchanged_skips_body_loader(tmp_path):
    root, base = _claim_repo(tmp_path)
    (root / "unrelated.txt").write_text("x\n")
    _commit_all(root, "unrelated")

    def boom():
        raise AssertionError("body_loader must not run when the surface is unchanged")

    out = io.StringIO()
    rc = re_.check_claim(root, base, "HEAD", boom, out=out, err=io.StringIO())
    assert rc == 0
    assert "review surface unchanged" in out.getvalue()


def test_check_claim_regex_never_evals_body(tmp_path, monkeypatch):
    # The body is matched by regex only; passing it through str.format would expand {0}.
    root, base = _claim_repo(tmp_path)
    (root / re_.REVIEW_SURFACE_PATHS[3]).write_text("two\n")
    _commit_all(root, "change rubric")
    monkeypatch.chdir(tmp_path)
    body = "$(touch PWNED) `touch PWNED` {0} {} %s {body.__class__}\n"
    rc = re_.check_claim(root, base, "HEAD", lambda: body, out=io.StringIO(), err=io.StringIO())
    assert rc == 1
    assert not (tmp_path / "PWNED").exists()
