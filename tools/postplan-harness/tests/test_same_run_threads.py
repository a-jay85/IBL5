"""Phase 4.5b same-run thread pass: candidates, this-run filter, one round, prune."""
import ast
import inspect
import json
import os
import sys
import textwrap

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import reviewcache, same_run_threads
from harness.adapters.ghad import RecordingGh
from harness.adapters.gitad import ReplayGit
from harness.adapters.llm import FixtureLlm
from harness.labels import render_body
from harness.same_run_threads import (
    MAX_SAME_RUN_ROUNDS,
    candidate_ids,
    prune_findings,
    run_same_run_pass,
)
from harness.state import Finding, UsageLedger
from harness.thread_ingestion import PURPOSE

PR = 2340
PATH = "ibl5/classes/Foo/FooRepository.php"
BODY = "Guard the null lookup before dereferencing the row."

FIX = '{"verdict":"FIX","reason":"Added the guard"}'
DECLINE = ('{"verdict":"DECLINE","reason":"guard already present",'
           '"category":"already-fixed"}')


def _thread(cid, *, path=PATH, body=BODY, login="a-jay85", typ="User"):
    # GitHub shows the body the recorder rendered: label and score markers included.
    body = render_body(Finding(source="code-review", agent="A", path=path, line=41, body=body,
                               score=85), 1000)
    return {
        "id": f"PRRT_{cid}", "commentId": cid, "isResolved": False, "isOutdated": False,
        "path": path, "line": 41, "score": 85, "body": body, "agent": "A",
        "authorLogin": login, "authorType": typ,
    }


class _CountingGh(RecordingGh):
    def __init__(self, out_dir, fixture):
        super().__init__(out_dir, fixture)
        self.trusted_calls = 0
        self.thread_id_calls = 0

    def pr_thread_ids(self, pr):
        self.thread_id_calls += 1
        return super().pr_thread_ids(pr)

    def trusted_open_threads(self, pr):
        self.trusted_calls += 1
        return super().trusted_open_threads(pr)


def _gh(tmp_path, fixture, *, seed=((PATH, BODY),), cls=_CountingGh):
    gh = cls(str(tmp_path), dict(fixture, pr_number=PR))
    if seed:
        gh.post_review_findings(PR, "deadbeef", "Code review", [
            Finding(source="code-review", agent="A", path=p, line=41, body=b, score=85)
            for p, b in seed])
    return gh


def _llm(verdict=FIX):
    return FixtureLlm(UsageLedger(), {PURPOSE: verdict})


def _resolves(gh):
    return [a for a in gh.actions() if a.get("action") == "pr_resolve_thread"]


def _run(tmp_path, gh, llm, *, pre=frozenset({111}), git=None, commit=None, push=None,
         review_delta=None, outcome_log_path=None):
    def _never_delta(delta):
        raise AssertionError("review_delta must not be called")

    return run_same_run_pass(
        gh, llm, git or ReplayGit({}), str(tmp_path), PR, set(pre), str(tmp_path),
        lambda m: None,
        commit=commit or (lambda m: ""),
        push=push or (lambda: ""),
        outcome_log_path=outcome_log_path,
        review_delta=review_delta or _never_delta)


def _file_diff(path, line):
    return (f"diff --git a/{path} b/{path}\n"
            f"--- a/{path}\n+++ b/{path}\n"
            f"@@ -1,2 +1,2 @@\n context\n-old line\n+{line}\n")


DIFF_A = _file_diff("stable/path.py", "same") + _file_diff("changed/path.py", "before")
DIFF_B = _file_diff("stable/path.py", "same") + _file_diff("changed/path.py", "after")


class _SwitchGit(ReplayGit):
    """diff_vs_base returns diff A until a commit lands, diff B afterwards."""

    def __init__(self):
        super().__init__({})
        self.committed = False

    def diff_vs_base(self, base="origin/master"):
        return DIFF_B if self.committed else DIFF_A


def test_candidate_ids_is_post_minus_pre():
    assert candidate_ids({1, 2, 3}, {1}) == {2, 3}
    assert candidate_ids(set(), {1}) == set()


def test_same_run_thread_enters_candidates_and_fix_commits_and_resolves(tmp_path):
    gh = _gh(tmp_path, {"thread_ids": [111, 222], "trusted_threads": [_thread(222)]})
    llm = _llm(FIX)
    result = _run(tmp_path, gh, llm, commit=lambda m: "abc123", push=lambda: "def456")

    assert result["found"] == 1
    assert result["fixed"] == 1
    assert result["origin"] == "same-run"
    res = _resolves(gh)
    assert len(res) == 1
    assert res[0]["comment_id"] == 222
    assert res[0]["body"].startswith("Fixed in")
    assert os.path.exists(os.path.join(str(tmp_path), "thread-ingestion-same-run.json"))


def test_decline_resolves_with_reason_and_category_no_commit(tmp_path):
    gh = _gh(tmp_path, {"thread_ids": [111, 222], "trusted_threads": [_thread(222)]})
    log_path = str(tmp_path / "outcomes.jsonl")

    def _no_commit(msg):
        raise AssertionError("commit must not be called for a DECLINE")

    result = _run(tmp_path, gh, _llm(DECLINE), commit=_no_commit, outcome_log_path=log_path)

    assert result["declined"] == 1
    res = _resolves(gh)
    assert len(res) == 1
    assert res[0]["body"].startswith("Declined: guard already present")
    with open(log_path) as fh:
        lines = [json.loads(line) for line in fh if line.strip()]
    assert len(lines) == 1
    assert lines[0]["origin"] == "same-run"
    assert lines[0]["basis"] == "already-fixed"


def test_pre_existing_thread_is_not_a_same_run_candidate(tmp_path):
    gh = _gh(tmp_path, {"thread_ids": [111, 222], "trusted_threads": [_thread(111)]})
    llm = _llm(FIX)
    result = _run(tmp_path, gh, llm, commit=lambda m: "abc123")

    assert result["found"] == 0
    assert [r for r in _resolves(gh) if r["comment_id"] == 111] == []
    assert llm.tooled_argvs == []


def test_untrusted_author_same_run_thread_is_excluded(tmp_path):
    gh = _gh(tmp_path, {"thread_ids": [111, 222],
                        "trusted_threads": [_thread(222, login="stranger", typ="User")]})
    llm = _llm(FIX)
    result = _run(tmp_path, gh, llm, commit=lambda m: "abc123")

    assert result["found"] == 0
    assert llm.tooled_argvs == []
    assert _resolves(gh) == []


def test_thread_not_posted_by_this_run_is_excluded(tmp_path):
    other = _thread(333, body="A human wrote this unrelated comment.")
    gh = _gh(tmp_path, {"thread_ids": [111, 333], "trusted_threads": [other]})
    llm = _llm(FIX)
    result = _run(tmp_path, gh, llm, commit=lambda m: "abc123")

    assert result["found"] == 0
    assert llm.tooled_argvs == []
    assert _resolves(gh) == []


def test_nothing_posted_short_circuits(tmp_path):
    gh = _gh(tmp_path, {"thread_ids": [111, 222], "trusted_threads": [_thread(222)]}, seed=())
    llm = _llm(FIX)
    result = _run(tmp_path, gh, llm)

    assert result["reason"] == "nothing-posted"
    assert result["found"] == 0
    assert gh.trusted_calls == 0
    assert llm.tooled_argvs == []


def test_fetch_none_keeps_hold(tmp_path):
    gh = _gh(tmp_path, {"thread_ids": [111, 222], "trusted_threads": None})
    llm = _llm(FIX)
    result = _run(tmp_path, gh, llm)

    assert result["found"] == 0
    assert result["reason"] == "cap-or-api-error"
    assert gh.trusted_calls == 1
    assert llm.tooled_argvs == []
    assert _resolves(gh) == []


def test_post_snapshot_failure_acts_on_nothing(tmp_path):
    gh = _gh(tmp_path, {"thread_ids": [], "trusted_threads": [_thread(222)]})
    llm = _llm(FIX)
    result = _run(tmp_path, gh, llm)

    assert result["reason"] == "no-same-run-threads"
    assert gh.trusted_calls == 0
    assert llm.tooled_argvs == []
    assert _resolves(gh) == []


def test_same_run_pass_error_returns_error_and_acts_on_nothing(tmp_path):
    class _BoomGh(_CountingGh):
        def trusted_open_threads(self, pr):
            raise RuntimeError("graphql exploded")

    gh = _gh(tmp_path, {"thread_ids": [111, 222], "trusted_threads": [_thread(222)]},
             cls=_BoomGh)
    llm = _llm(FIX)
    result = _run(tmp_path, gh, llm)

    assert result["reason"] == "error"
    assert "graphql exploded" in result["error"]
    assert result["found"] == 0
    assert _resolves(gh) == []
    assert llm.tooled_argvs == []


def test_one_round_only_delta_review_findings_are_never_reingested(tmp_path):
    gh = _gh(tmp_path, {"thread_ids": [111, 222], "trusted_threads": [_thread(222)]})
    llm = _llm(FIX)
    git = _SwitchGit()
    seen = []

    def _commit(msg):
        git.committed = True
        return "c1"

    def _review_delta(delta):
        seen.append(set(delta))
        # the delta review posts a new finding as a new open thread
        gh.fixture["thread_ids"].append(444)
        gh.fixture["trusted_threads"].append(_thread(444))
        gh.post_review_findings(PR, "deadbeef", "Delta review", [
            Finding(source="code-review", agent="A", path=PATH, line=41, body=BODY, score=85)])
        return 1

    result = _run(tmp_path, gh, llm, git=git, commit=_commit, push=lambda: "c1",
                  review_delta=_review_delta)

    assert result["rounds"] == 1
    assert gh.thread_id_calls == 1
    assert len(llm.tooled_argvs) == 1
    assert [r for r in _resolves(gh) if r["comment_id"] == 444] == []
    assert result["delta_review"] == {"paths": ["changed/path.py"], "new_findings": 1}
    assert seen == [{"changed/path.py"}]


def test_delta_review_not_called_without_fix_commit(tmp_path):
    gh = _gh(tmp_path, {"thread_ids": [111, 222], "trusted_threads": [_thread(222)]})

    def _never(delta):
        raise AssertionError("review_delta must not run without a fix commit")

    result = _run(tmp_path, gh, _llm(DECLINE), review_delta=_never)

    assert result["declined"] == 1
    assert "delta_review" not in result


def test_file_delta_feeds_review_delta(tmp_path):
    assert reviewcache.per_file_patch_ids(DIFF_A), "diff A must yield patch-ids"
    assert reviewcache.per_file_patch_ids(DIFF_B), "diff B must yield patch-ids"

    gh = _gh(tmp_path, {"thread_ids": [111, 222], "trusted_threads": [_thread(222)]})
    git = _SwitchGit()
    deltas = []

    def _commit(msg):
        git.committed = True
        return "c1"

    def _review_delta(delta):
        deltas.append(set(delta))
        return 0

    result = _run(tmp_path, gh, _llm(FIX), git=git, commit=_commit, push=lambda: "c1",
                  review_delta=_review_delta)

    assert deltas == [{"changed/path.py"}]
    assert result["delta_review"] == {"paths": ["changed/path.py"], "new_findings": 0}


def test_prune_findings_drops_fixed_and_declined_keeps_skipped():
    def f(path, body):
        return Finding(source="code-review", agent="A", path=path, line=1, body=body, score=85)

    fixed, declined, skipped = f("a.php", "fixed body"), f("b.php", "declined body"), \
        f("c.php", "skipped body")
    scored = [{"path": x.path, "body_head": x.body[:160], "score": 85}
              for x in (fixed, declined, skipped)]
    result = {"threads": [
        {"commentId": 1, "outcome": "fixed", "path": "a.php", "body_head": "fixed body"},
        {"commentId": 2, "outcome": "declined", "path": "b.php", "body_head": "declined body"},
        {"commentId": 3, "outcome": "skipped", "path": "c.php", "body_head": "skipped body"},
    ]}
    keys = same_run_threads.dispositioned_keys(result)
    kept, kept_scored = prune_findings([fixed, declined, skipped], scored, keys)

    assert kept == [skipped]
    assert [r["path"] for r in kept_scored] == ["c.php"]


def test_failed_delta_review_prunes_nothing():
    result = {"threads": [{"commentId": 1, "outcome": "fixed", "path": "a.php",
                           "body_head": "fixed body"}],
              "delta_review": {"paths": ["a.php"], "new_findings": None}}
    assert same_run_threads.dispositioned_keys(result) == set()


def test_failed_delta_review_marks_degraded():
    failed = {"delta_review": {"paths": ["a.php"], "new_findings": None}}
    assert same_run_threads.delta_degraded(failed) == ["same-run-delta-review"]
    assert same_run_threads.delta_degraded(
        {"delta_review": {"paths": ["a.php"], "new_findings": 0}}) == []
    assert same_run_threads.delta_degraded({}) == []


def test_max_same_run_rounds_is_one():
    assert MAX_SAME_RUN_ROUNDS == 1
    src = inspect.getsource(run_same_run_pass)
    assert "while" not in src
    assert "for round" not in src
    tree = ast.parse(textwrap.dedent(src))
    loops = [n for n in ast.walk(tree) if isinstance(n, (ast.For, ast.While, ast.AsyncFor))]
    assert loops == []
