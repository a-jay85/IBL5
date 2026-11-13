"""Second-reviewer eval tests: schema, score line, miner labeling, run mode."""
import argparse
import copy
import json
import os
import subprocess
from pathlib import Path

import pytest

from harness import fidelity, second_review
from harness import second_review_eval as sre
from harness.reviewer_eval import EvalUsageError

SHA = "a" * 40
AGENT = ".claude/agents/pr-second-reviewer.md"


def case(**over):
    c = {
        "id": "pr3891-clear17", "pr": 3891, "status": "confirmed",
        "expected": "clear", "label_basis": "seat-cleared-clean",
        "base_sha": SHA, "replay_sha": SHA,
        "holds": {"17": "hold text"}, "reason": "frozen reason",
        "reason_source": "plan", "evidence": "label",
    }
    c.update(over)
    return c


def corp(*cases):
    return {"version": 1, "cases": list(cases)}


def test_schema_accepts_valid_case():
    assert sre.validate_schema(corp(case())) == []


def test_schema_rejects_unclearable_hold():
    errs = sre.validate_schema(corp(case(holds={"8": "x"})))
    assert any("clearable" in e for e in errs)


def test_schema_rejects_numeric_id():
    errs = sre.validate_schema(corp(case(id="3874")))
    assert any("id must match" in e for e in errs)


def test_digest_ignores_unconfirmed():
    base = corp(case())
    more = corp(case(), case(id="pr1-unconfirmed", status="unconfirmed"))
    assert sre.corpus_digest(base) == sre.corpus_digest(more)


def test_digest_changes_on_hold_edit():
    a = corp(case())
    b = copy.deepcopy(a)
    b["cases"][0]["holds"]["17"] = "edited"
    assert sre.corpus_digest(a) != sre.corpus_digest(b)


def test_score_line_round_trip():
    s = sre.SecondScore("a" * 12, "b" * 12, 1, 2, 0, 3, 0)
    line = sre.format_score_line(s)
    assert line == ("second-review-eval v=aaaaaaaaaaaa c=bbbbbbbbbbbb "
                    "missed_holds=1/2 extra_holds=0/3 degraded=0")
    assert sre.parse_score_line(line) == s


def test_parse_score_line_rejects_near_miss():
    line = sre.format_score_line(sre.SecondScore("a" * 12, "b" * 12, 1, 2, 0, 3, 0))
    with pytest.raises(EvalUsageError):
        sre.parse_score_line(line + " trailing")
    with pytest.raises(EvalUsageError):
        sre.parse_score_line(line.replace("missed_holds=1/2", "missed_holds=1"))


def test_partial_clear_counts_as_held():
    assert sre.seat_cleared("CLEAN", [7], {"7": "a", "12": "b"}) is False
    assert sre.seat_cleared("CLEAN", [7, 12], {"7": "a", "12": "b"}) is True
    assert sre.seat_cleared("REJECT", [7, 12], {"7": "a", "12": "b"}) is False


def test_compute_score_degraded_leaves_denominators():
    outs = [(case(expected="clear"), None, []),
            (case(expected="hold"), "CLEAN", [17])]
    s = sre.compute_score(outs, v="a" * 12, c="b" * 12)
    assert (s.missed, s.should_hold, s.extra, s.should_clear, s.degraded) == (1, 1, 0, 0, 1)


def test_surface_hash_tracks_agent_def():
    files = {
        sre.SURFACE_PATHS[0]: b"seat",
        AGENT: b"agent v1",
        sre.FIDELITY_PATH: b"X = 1\nREMEDIATION_DIFF_INLINE_CAP = 100\n",
    }
    h1 = sre.surface_hash(files.get)
    files[AGENT] = b"agent v2"
    assert sre.surface_hash(files.get) != h1


def test_surface_hash_ignores_other_fidelity_lines():
    files = {sre.FIDELITY_PATH: b"A = 1\nREMEDIATION_DIFF_INLINE_CAP = 100\n"}
    h1 = sre.surface_hash(files.get)
    files[sre.FIDELITY_PATH] = b"A = 2\nREMEDIATION_DIFF_INLINE_CAP = 100\n"
    assert sre.surface_hash(files.get) == h1


def test_validate_main_missing_corpus_exits_2(tmp_path):
    assert sre.main(["validate", "--corpus", str(tmp_path / "nope.json")]) == 2


# ---------------------------------------------------------------------------
# Phase 2: miner labeling, held-block parsing, eligibility, read-only gh
# ---------------------------------------------------------------------------

TREE = "c" * 40
REPLAY = "d" * 40
DISARM = fidelity.DISARM_LINE


def _classify(**over):
    kw = {"labels": {"human-approved"}, "replay_patch_id": "p1", "final_patch_id": "p1",
          "changes_requested": False, "fix_shas": []}
    kw.update(over)
    return sre.classify(**kw)


def test_label_approved_as_is_is_clear():
    assert _classify() == ("clear", "approved-as-is")


def test_label_changed_after_hold_is_hold():
    assert _classify(final_patch_id="p2") == ("hold", "changed-after-hold")


def test_label_rebase_only_change_stays_clear():
    # Different trees after a rebase-only push still carry equal patch ids.
    assert _classify(replay_patch_id="same", final_patch_id="same") == ("clear", "approved-as-is")


def test_label_needs_a_human_is_hold():
    assert _classify(labels={"needs-a-human"}) == ("hold", "needs-a-human")


def test_label_later_fix_flips_to_hold():
    assert _classify(fix_shas=["e" * 40]) == ("hold", "reverted")


def test_parse_held_block_ignores_prose_parens():
    text = ("<!-- pr-ready-verdict -->\nAuto-merge held:\n"
            "- (12) The plan review said the PR is not ready.\n"
            "checks 3 and 4\n"
            "see condition (7) notes\n"
            f"{DISARM}\n\n**Reviewed tree:** {TREE}\n")
    assert sre.parse_held_block(text) == {12: "The plan review said the PR is not ready."}


def _sticky(bullets):
    lines = "\n".join(f"- ({n}) hold {n} text" for n in bullets)
    return (f"{fidelity.STICKY_MARKER}\nAuto-merge held:\n{lines}\n{DISARM}\n\n"
            f"**Reviewed tree:** {TREE}\n")


def _fake_env(sticky_body, *, diff_text="diff --git a/x b/x\n+one\n", labels=("human-approved",)):
    pr = {"number": 4001, "title": "A title", "body": "## Summary\n\nBody text.",
          "labels": [{"name": name} for name in labels], "headRefName": "no-such-plan-branch",
          "headRefOid": "f" * 40, "mergeCommit": {"oid": "e" * 40},
          "mergedAt": "2026-11-10T00:00:00Z"}
    calls = []

    def gh(argv):
        calls.append(list(argv))
        if argv[:2] == ["pr", "list"]:
            return [pr]
        if argv[1] == "graphql":
            return {"data": {"node": {"userContentEdits": {"nodes": []}}}}
        if argv[1].endswith("/comments"):
            return [[{"body": sticky_body, "node_id": "IC_x"}]]
        if argv[1].endswith("/reviews"):
            return [[]]
        raise AssertionError(argv)

    def git(argv, stdin=None):
        if argv[0] == "log":
            return f"{REPLAY} {TREE}\n"
        if argv[0] == "merge-base":
            return "b" * 40 + "\n"
        if argv[0] == "diff":
            return diff_text
        if argv[0] == "patch-id":
            return "9" * 40 + " " + "0" * 40 + "\n"
        raise AssertionError(argv)
    return gh, git, calls


def _mine_rows(tmp_path, gh, git):
    out = tmp_path / "out" / "candidates.jsonl"
    sre.mine(tmp_path, gh_json=gh, git=git, plans_dir=tmp_path / "plans", since="2026-10-13",
             out_path=out, now=0, fixes=lambda c: [])
    return [json.loads(ln) for ln in out.read_text().splitlines()]


def test_mine_eligible_row(tmp_path):
    gh, git, _ = _fake_env(_sticky([12]))
    (row,) = _mine_rows(tmp_path, gh, git)
    assert row["seat_eligible"] is True and row["replay_sha"] == REPLAY
    assert row["holds"] == {"12": "hold 12 text"}
    assert (row["expected"], row["label_basis"]) == ("clear", "approved-as-is")
    assert row["id"] == "pr4001-clear12" and row["reason_source"] == "pr-body"


def test_ui_hold_version_is_ineligible(tmp_path):
    # expect: seat_eligible: false, skip_reason: "non-clearable-hold"
    gh, git, _ = _fake_env(_sticky([8, 12]))
    (row,) = _mine_rows(tmp_path, gh, git)
    assert row["seat_eligible"] is False
    assert row["skip_reason"] == "non-clearable-hold"


def test_diff_over_cap_is_ineligible(tmp_path):
    big = "x" * (fidelity.REMEDIATION_DIFF_INLINE_CAP + 1)
    gh, git, _ = _fake_env(_sticky([12]), diff_text=big)
    (row,) = _mine_rows(tmp_path, gh, git)
    assert row["seat_eligible"] is False
    assert row["skip_reason"] == "diff-over-cap"


def test_gh_write_argv_raises(tmp_path):
    with pytest.raises(EvalUsageError):
        sre._assert_read_only(["api", "-X", "POST", "repos/a-jay85/IBL5/issues/1/comments"])
    with pytest.raises(EvalUsageError):
        sre._assert_read_only(["pr", "edit", "1"])
    gh, git, calls = _fake_env(_sticky([12]))
    _mine_rows(tmp_path, gh, git)
    assert len(calls) >= 4
    for argv in calls:
        sre._assert_read_only(argv)


def test_graphql_mutation_argv_raises():
    with pytest.raises(EvalUsageError):
        sre._assert_read_only(["api", "graphql", "-f", "query=mutation { x }"])


# ---------------------------------------------------------------------------
# Phase 3: run mode against a throwaway git repo with a fake seat
# ---------------------------------------------------------------------------

CLEAN_REPLY = "CLEARS 7: the hold is safe to clear for this diff\nVERDICT: CLEAN"


@pytest.fixture(autouse=True)
def _private_verdict_dir(tmp_path, monkeypatch):
    """Point verdict files at this test's tmp_path.

    The run tests share one case id, so the real verdict_path gives them one
    /tmp file. Under xdist, one test's cleanup deletes the file another test
    just wrote, and that test reads no verdict.
    """
    vdir = tmp_path / "verdicts"
    vdir.mkdir()
    monkeypatch.setattr(second_review, "verdict_path",
                        lambda pr: str(vdir / f"postplan-second-review-{pr}.md"))


def _git(repo, *argv):
    return subprocess.run(["git", "-C", str(repo), *argv], capture_output=True, text=True,
                          check=True).stdout.strip()


@pytest.fixture
def repo(tmp_path):
    r = tmp_path / "repo"
    r.mkdir()
    _git(r, "init", "-q")
    _git(r, "config", "user.email", "t@example.invalid")
    _git(r, "config", "user.name", "t")
    (r / "a.txt").write_text("one\n")
    _git(r, "add", "a.txt")
    _git(r, "commit", "-qm", "base")
    base = _git(r, "rev-parse", "HEAD")
    (r / "a.txt").write_text("one\ntwo\n")
    _git(r, "commit", "-qam", "change")
    return r, base, _git(r, "rev-parse", "HEAD")


def _run_case(repo, **over):
    _, base, head = repo
    c = case(id="pr999999-clear7", pr=999999, base_sha=base, replay_sha=head,
             holds={"7": "smoke hold"})
    c.update(over)
    return c


def _factory(reply=CLEAN_REPLY, calls=None, raises=False):
    lines = reply.splitlines()
    assert second_review._CLEARS_RE.match(lines[0]) and second_review._VERDICT_RE.match(lines[-1])

    def factory(wt):
        def spawn(agent, prompt):
            if calls is not None:
                calls.append(prompt)
            if raises:
                raise RuntimeError("spawn failed")
            return reply
        return spawn
    return factory


def test_run_never_touches_real_pr_verdict(repo):
    c = _run_case(repo)
    real = second_review.verdict_path(999999)
    prior = Path(real).read_bytes() if os.path.exists(real) else None
    Path(real).write_bytes(b"SENTINEL\nVERDICT: REJECT keep\n")
    try:
        word, clears = sre.run_case(repo[0], c, spawn_factory=_factory())
        assert word == "CLEAN" and clears == {7: "the hold is safe to clear for this diff"}
        assert Path(real).read_bytes() == b"SENTINEL\nVERDICT: REJECT keep\n"
        assert not os.path.exists(second_review.verdict_path(sre.eval_key(c)))
    finally:
        if prior is None:
            os.unlink(real)
        else:
            Path(real).write_bytes(prior)


def test_replay_checkout_uses_current_agent_def(repo):
    root = repo[0]
    agent = root / sre.SURFACE_PATHS[1]
    agent.parent.mkdir(parents=True)
    agent.write_text("current agent def\n")
    seen = []

    def factory(wt):
        seen.append((Path(wt) / sre.SURFACE_PATHS[1]).read_text())
        return _factory()(wt)

    word, _ = sre.run_case(root, _run_case(repo), spawn_factory=factory)
    assert word == "CLEAN"
    assert seen == ["current agent def\n"]


def test_checkout_removed_after_spawn_error(repo):
    word, clears = sre.run_case(repo[0], _run_case(repo), spawn_factory=_factory(raises=True))
    assert (word, clears) == (None, {})
    listing = _git(repo[0], "worktree", "list", "--porcelain")
    assert [ln for ln in listing.splitlines() if ln.startswith("worktree ")] == [
        f"worktree {Path(repo[0]).resolve()}"]


def test_degraded_case_counted(repo, tmp_path):
    corpus = corp(_run_case(repo, expected="hold", label_basis="needs-a-human"))
    s = sre.run_eval(repo[0], corpus, spawn_factory=_factory(raises=True),
                     cache_dir=tmp_path / "cache")
    assert s.degraded == 1 and s.should_hold == 0 and s.should_clear == 0
    assert not list((tmp_path / "cache").glob("*.json"))


def test_degraded_case_leaves_denominators(repo, tmp_path):
    s = sre.run_eval(repo[0], corp(_run_case(repo)), spawn_factory=_factory(raises=True),
                     cache_dir=tmp_path / "cache")
    assert "extra_holds=0/0" in sre.format_score_line(s)


def test_rerun_reuses_cache(repo, tmp_path):
    corpus = corp(_run_case(repo))
    calls: list = []
    first = sre.run_eval(repo[0], corpus, spawn_factory=_factory(calls=calls),
                         cache_dir=tmp_path / "cache")
    assert len(calls) == 1 and first.extra == 0 and first.should_clear == 1
    calls.clear()
    second = sre.run_eval(repo[0], corpus, spawn_factory=_factory(calls=calls),
                          cache_dir=tmp_path / "cache")
    assert calls == [] and second == first


def test_run_exits_3_when_degraded(repo, tmp_path, capsys):
    path = tmp_path / "corpus.json"
    path.write_text(json.dumps(corp(_run_case(repo))))
    args = argparse.Namespace(repo=str(repo[0]), corpus=str(path), case=None,
                              cache_dir=str(tmp_path / "cache"), score_out=None)
    assert sre._cmd_run(args, spawn_factory=_factory(raises=True)) == 3
    out = capsys.readouterr()
    assert out.out.strip().splitlines()[-1].endswith("degraded=1")
    assert "seat=degraded" in out.err


def _score_file(tmp_path, name, line):
    f = tmp_path / name
    f.write_text("noise\n" + line + "\n")
    return str(f)


def _line(c="c" * 12, mh="0/2", eh="0/3"):
    return f"second-review-eval v={'a' * 12} c={c} missed_holds={mh} extra_holds={eh} degraded=0"


def test_compare_worse_exits_1(tmp_path, capsys):
    f1 = _score_file(tmp_path, "b.txt", _line())
    f2 = _score_file(tmp_path, "a.txt", _line(mh="1/2"))
    assert sre.main(["compare", "--before", f1, "--after", f2]) == 1
    assert "worse: missed_holds 0->1 extra_holds 0->0" in capsys.readouterr().out


def test_compare_differing_denominators_exits_2(tmp_path, capsys):
    f1 = _score_file(tmp_path, "b.txt", _line())
    f2 = _score_file(tmp_path, "a.txt", _line(mh="0/3"))
    assert sre.main(["compare", "--before", f1, "--after", f2]) == 2
    assert "denominators differ" in capsys.readouterr().err


def test_compare_equal_exits_0(tmp_path, capsys):
    f1 = _score_file(tmp_path, "b.txt", _line())
    f2 = _score_file(tmp_path, "a.txt", _line())
    assert sre.main(["compare", "--before", f1, "--after", f2]) == 0
    out = capsys.readouterr().out
    assert "before:" in out and "after:" in out and "worse:" not in out


def test_compare_missing_file_exits_2(tmp_path):
    f1 = _score_file(tmp_path, "b.txt", _line())
    assert sre.main(["compare", "--before", f1, "--after", str(tmp_path / "nope")]) == 2
