"""A backed owner amendment keeps the work it asked for and lifts a NOT READY built only
on that work. Every amendment here comes from parse_amendments plus backed_amendments,
so a skipped backing check shows up as a wrong result."""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity, unplanned_scope as us

from tests.test_owner_amendments import CONDITION, comment, plan_with

PATH = "ibl5/templates/trade/review.php"
SLICE = CONDITION[:30]   # "rename the Save button to Subm"
BLOCKER = (f"- [BLOCKING] [unplanned-scope] Save button renamed to Submit in `{PATH}` "
           f'beyond the plan. owner: "{SLICE}"')
UNQUOTED = (f"- [BLOCKING] [unplanned-scope] Tooltip rewritten in `{PATH}` with no plan "
            "phase and no owner request")
BUG = "- [BLOCKING] The renamed button posts to the old route and 404s."


def _amendments(comments):
    backed, _ = us.backed_amendments(us.parse_amendments(plan_with(comment())), comments)
    return backed


def _ctx(comments=None, subjects=("feat: rename the trade button",), preserve=(), removed=()):
    return {"changed_paths": [PATH], "subjects_by_path": {PATH: list(subjects)},
            "must_preserve": set(preserve), "removed_guard_paths": set(removed),
            "amendments": _amendments([comment()] if comments is None else comments)}


def _verdict(tmp_path, *items):
    path = tmp_path / "verdict.md"
    path.write_text("## FINDINGS\n" + "\n".join(items) + "\n\nNOT READY\n", encoding="utf-8")
    return str(path)


def test_downgrade_backed_amendment_posts_ready(tmp_path):
    v = _verdict(tmp_path, BLOCKER)
    assert fidelity.parse_verdict(v) == "NOT READY"
    assert us.downgrade_applies(v, _ctx(), "PR body with no kept block") is True


def test_downgrade_unbacked_amendment_stays_not_ready(tmp_path):
    ctx = _ctx(comments=[])
    assert ctx["amendments"] == []
    assert us.downgrade_applies(_verdict(tmp_path, BLOCKER), ctx, "body") is False


def test_downgrade_code_beyond_condition_stays_not_ready(tmp_path):
    v = _verdict(tmp_path, BLOCKER, UNQUOTED)
    assert us.downgrade_applies(v, _ctx(), "body") is False


def test_downgrade_near_miss_quote_stays_not_ready(tmp_path):
    short = BLOCKER.replace(f'"{SLICE}"', f'"{CONDITION[:11]}"')
    assert us.owner_quote(short) is None
    assert us.downgrade_applies(_verdict(tmp_path, short), _ctx(), "body") is False
    curly = BLOCKER.replace(f'"{SLICE}"', f"“{SLICE}”")
    assert us.downgrade_applies(_verdict(tmp_path, curly), _ctx(), "body") is False


def test_downgrade_amendment_needs_no_kept_block(tmp_path):
    v = _verdict(tmp_path, BLOCKER)
    assert us.KEPT_BEGIN not in "plain body"
    assert us.downgrade_applies(v, _ctx(), "plain body") is True
    no_amend = dict(_ctx(), amendments=[])
    assert us.downgrade_applies(v, no_amend, "plain body") is False


def test_downgrade_mixed_blockers_stays_not_ready(tmp_path):
    v = _verdict(tmp_path, BLOCKER, BUG)
    assert us.downgrade_applies(v, _ctx(), "body") is False


def test_classify_amendment_quote_overrides_remove():
    machine = [fidelity.REMEDIATION_COMMIT_MSG]
    ctx = _ctx(subjects=machine)
    assert us.classify(BLOCKER, **ctx) == "keep"
    assert us.classify(BLOCKER, **dict(ctx, amendments=[])) == "remove"


def test_classify_amendment_quote_keeps_hold():
    ctx = _ctx(preserve=[PATH], removed=[PATH])
    assert us.classify(BLOCKER, **ctx) == "hold"
