"""Phase 6.5 merge-danger refill (runner._retry_merge_danger): one bounded re-apply of the
line this run's Phase 2 wrote, never a real verdict, never a clean-tree rerun."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import merge_danger
from harness.armable import evaluate
from harness.state import HarnessError, RunResult, TerminalState

from tests.test_armable import inputs


class _Gh:
    """`body` is the stale snapshot the caller read before editing; pr_body() keeps returning
    it. `remote` is what GitHub holds now (our edit applied, plus any `concurrent_tail`
    another writer appended); only pr_body_fresh() sees it. The two diverge after an edit, so
    a retry that re-reads through pr_body() instead of pr_body_fresh() is caught."""

    def __init__(self, body, apply_edits=True, raise_on_edit=None, concurrent_tail=""):
        self.body, self.apply_edits, self.raise_on_edit = body, apply_edits, raise_on_edit
        self.remote, self.concurrent_tail = body, concurrent_tail
        self.edits = []

    def pr_edit_body(self, pr, body):
        if self.raise_on_edit:
            raise self.raise_on_edit
        self.edits.append(body)
        if self.apply_edits:
            self.remote = body + self.concurrent_tail

    def pr_body(self):
        return self.body

    def pr_body_fresh(self):
        return self.remote


BODY_NO_LINE = "## Summary\nx\n\n## Manual Testing\n\nNo manual testing needed.\n"


def _phase2_md(tmp_path, door=None):
    mech = merge_danger.derive_from_worktree(str(tmp_path), "")
    return merge_danger.merge_verdicts(mech, door, None, None)


def _retry(gh, md, tmp_path, res=None):
    res = res or RunResult(terminal=TerminalState.FAILED)
    out = runner._retry_merge_danger(gh, 1, gh.body, md, str(tmp_path), "", lambda *_: None, res)
    return out, res


def test_missing_after_phase2_wrote_refills_once(tmp_path):
    gh = _Gh(BODY_NO_LINE)
    out, res = _retry(gh, _phase2_md(tmp_path), tmp_path)
    assert merge_danger.classify_body(out) == "TWO-WAY"
    assert len(gh.edits) == 1
    assert res.retries == {"merge-danger": 1}


def test_refill_returns_the_fresh_read_not_the_stale_snapshot(tmp_path):
    tail = "\n<!-- concurrent writer -->\n"
    gh = _Gh(BODY_NO_LINE, concurrent_tail=tail)
    out, _ = _retry(gh, _phase2_md(tmp_path), tmp_path)
    assert out == gh.remote
    assert out.endswith(tail)
    assert out != gh.pr_body()


def test_second_miss_holds_after_one_retry(tmp_path):
    gh = _Gh(BODY_NO_LINE, apply_edits=False)
    out, res = _retry(gh, _phase2_md(tmp_path), tmp_path)
    assert merge_danger.classify_body(out) == "MISSING"
    assert len(gh.edits) == 1
    assert res.retries == {"merge-danger": 1}
    d = evaluate(inputs(merge_danger_state=merge_danger.classify_body(out),
                        retried_stages=sorted(res.retries)))
    c18 = next(c for c in d.conditions if c.number == 18)
    assert c18.reason == "state=MISSING after 1 retry"


def test_clean_tree_rerun_never_refills(tmp_path):
    gh = _Gh(BODY_NO_LINE)
    out, res = _retry(gh, None, tmp_path)
    assert out == BODY_NO_LINE
    assert gh.edits == []
    assert res.retries == {}


@pytest.mark.parametrize("kind", ["one-way", "malformed"])
def test_real_verdicts_never_refilled(tmp_path, kind):
    if kind == "one-way":
        line = merge_danger.render_merge_danger(_phase2_md(tmp_path, "one-way"))
    else:
        line = "**Merge danger:** Door: sideways"
    body = BODY_NO_LINE + "\n" + line + "\n"
    gh = _Gh(body)
    out, res = _retry(gh, _phase2_md(tmp_path), tmp_path)
    assert gh.edits == []
    assert res.retries == {}
    assert out == body


def test_two_way_body_makes_no_call(tmp_path):
    md = _phase2_md(tmp_path)
    gh = _Gh(BODY_NO_LINE + "\n" + merge_danger.render_merge_danger(md) + "\n")
    out, res = _retry(gh, md, tmp_path)
    assert gh.edits == []
    assert res.retries == {}


def test_refill_keeps_phase2_one_way_door(tmp_path):
    gh = _Gh(BODY_NO_LINE)
    out, res = _retry(gh, _phase2_md(tmp_path, "one-way"), tmp_path)
    assert merge_danger.parse_merge_danger(out).door == "one-way"


def test_edit_failure_fails_closed(tmp_path):
    gh = _Gh(BODY_NO_LINE, raise_on_edit=HarnessError("gh", "boom"))
    out, res = _retry(gh, _phase2_md(tmp_path), tmp_path)
    assert out == BODY_NO_LINE
    assert res.retries == {"merge-danger": 1}


def test_phase2_capture_is_none_on_clean_tree_rerun(tmp_path):
    assert runner._phase2_merge_danger_of("") is None
    injected = runner._merge_danger_body("## Summary\nx\n", str(tmp_path), "",
                                         lambda *_: None, "phase2")
    assert isinstance(runner._phase2_merge_danger_of(injected), merge_danger.MergeDanger)
    assert runner._phase2_merge_danger_of("**Merge danger:** Door: sideways\n") is None
