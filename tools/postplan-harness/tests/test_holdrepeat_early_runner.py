"""Phase 3: in-run early hold-repeat decline in runner.run(), before the first LLM call.

A first replay run holds on arming condition 3 (a planned file the diff never touched) and
writes the hold-repeat record. A second run with a CHANGED diff but the same MISSING set must
decline before `_pr_copy`, with zero LLM invocations. Strict subsets, supersets, a prior hold
on another condition, no record, and POSTPLAN_FORCE=1 all proceed.
"""
import json
import os
import stat
import subprocess
import sys
import types
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import conformance, heldmarker, holdrepeat
from harness.adapters.llm import FixtureLlm
from harness.planfile import locate_plan
from harness.state import TerminalState, UsageLedger

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

SLUG = "holdrepeat-early"

CANNED = {
    "pr-copy": {"type": "chore", "title": "chore: replay", "commit_subject": "chore: replay commit",
                "summary_md": "## Summary\n- x\n"},
    "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
    "security-audit": [],
    "safety-verdict": {"holds": []},
    "manual-classify": [],
    "retrospective": {"save": False},
}

CRITICAL = "ibl5/classes/EarlyMissing.php"
CRITICAL_2 = "ibl5/classes/EarlyMissingToo.php"
MATRIX_TEST = "ibl5/tests/EarlyMissingTest.php"

MISSING_PLAN = (
    "# Synthetic plan\n\n"
    f"## Critical Files\n\n- `{CRITICAL}` (new)\n\n"
    "## Verification Matrix\n\n"
    "| # | What | Test type | Timing | File |\n|---|---|---|---|---|\n"
    f"| 1 | thing works | PHPUnit | post-impl | `{MATRIX_TEST}` |\n"
)
MISSING_PLAN_PLUS = MISSING_PLAN.replace(
    f"- `{CRITICAL}` (new)\n", f"- `{CRITICAL}` (new)\n- `{CRITICAL_2}` (new)\n")
HOLD_7_PLAN = "---\nauto_merge: false\n---\n# Synthetic plan\n\nBody with no matrix.\n"

DIFF_X = "diff --git a/ibl5/x.php b/ibl5/x.php\n+<?php echo 1;\n"
DIFF_Y = "diff --git a/ibl5/y.php b/ibl5/y.php\n+<?php echo 2;\n"
DIFF_XY = DIFF_X + DIFF_Y
DIFF_XY_PRESENT = DIFF_XY + f"diff --git a/{CRITICAL} b/{CRITICAL}\n+<?php echo 3;\n"


def _fixture(plan=MISSING_PLAN, diff=DIFF_X, **over):
    fx = {
        "slug": SLUG,
        "diff": diff,
        "pr_number": 4242,
        "pr_meta": {"number": 4242, "title": "fix: synthetic",
                    "body": "## Manual Testing\n\nNo manual testing needed\n",
                    "headRefOid": "deadbeef"},
        "labels": [],
        "final_state": "OPEN",
        "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": "OK (1 test)", "phpstan": "[OK] No errors"},
        "plan_content": plan,
    }
    fx.update(over)
    return fx


@pytest.fixture(autouse=True)
def _const_fingerprint(monkeypatch):
    monkeypatch.setattr(holdrepeat, "fingerprint", lambda *a, **k: "fp-const")


@pytest.fixture
def dm(tmp_path, monkeypatch):
    """Fake DM command: appends argv to a file, exits per HOLDREPEAT_FAKE_RC."""
    log = tmp_path / "dm.log"
    script = tmp_path / "fake-dm"
    script.write_text(f'#!/bin/sh\nprintf \'%s\\n---\\n\' "$*" >> "{log}"\n'
                      'exit "${HOLDREPEAT_FAKE_RC:-0}"\n')
    script.chmod(script.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("HOLDREPEAT_DM_CMD", str(script))
    monkeypatch.setenv("HOLDREPEAT_FAKE_RC", "0")

    def invocations():
        if not log.exists():
            return 0
        return log.read_text().count("---\n")

    return types.SimpleNamespace(log=log, count=invocations)


def _run(tmp_path, n, *, fixture=None):
    out = str(tmp_path / f"out{n}")
    ledger = UsageLedger()
    llm = FixtureLlm(ledger, dict(CANNED))
    res = runner.run(fixture or _fixture(), out, llm, mode="replay", live=False,
                     state_dir=str(tmp_path / "state"))
    return res, ledger, out


def _record(tmp_path):
    return json.loads((tmp_path / "state" / f"{SLUG}.holdrepeat.json").read_text())


def _calls(ledger):
    return ledger.totals()["llm_invocations"]


def _first_hold(tmp_path):
    res, ledger, _ = _run(tmp_path, 1)
    assert res.terminal == TerminalState.SHIPPED_HELD
    assert any(c.number == 3 for c in res.arm.holds)
    assert _calls(ledger) > 0
    return res


def test_changed_diff_same_missing_declines(tmp_path, dm):
    """The #2800 shape: the diff changed, the MISSING set did not."""
    _first_hold(tmp_path)
    res, ledger, out = _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    assert res.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert _calls(ledger) == 0
    assert runner.exit_code_for(res) == 0
    assert res.pr_number is None
    doc = json.loads(Path(out, "result.json").read_text())
    assert doc["pr_number"] is None
    assert doc["hold_repeat"]["early_decline"] is True
    line = runner.verdict_line(res, runner.exit_code_for(res))
    assert "declined: same hold as last run (" in line
    assert "no tokens spent" in line
    actions = Path(out, "actions.jsonl")
    assert (not actions.exists()) or actions.read_text().strip() == ""


def test_missing_file_now_present_proceeds(tmp_path, dm):
    """A strict subset of the prior MISSING set is progress, so the run goes on."""
    _first_hold(tmp_path)
    res, ledger, _ = _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY_PRESENT))
    assert res.terminal != TerminalState.HOLD_REPEAT_DECLINED
    assert _calls(ledger) > 0


def test_new_missing_item_proceeds(tmp_path, dm):
    """A superset of the prior MISSING set is a different hold, so the run goes on."""
    _first_hold(tmp_path)
    res, ledger, _ = _run(tmp_path, 2, fixture=_fixture(plan=MISSING_PLAN_PLUS, diff=DIFF_XY))
    assert res.terminal != TerminalState.HOLD_REPEAT_DECLINED
    assert _calls(ledger) > 0


def test_prior_hold_cond7_only_proceeds(tmp_path, dm):
    res1, _, _ = _run(tmp_path, 1, fixture=_fixture(plan=HOLD_7_PLAN))
    assert res1.terminal == TerminalState.SHIPPED_HELD
    assert {c.number for c in res1.arm.holds} & {7}
    assert 3 not in {c.number for c in res1.arm.holds}
    res, ledger, _ = _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    assert res.terminal != TerminalState.HOLD_REPEAT_DECLINED
    assert _calls(ledger) > 0


def test_no_record_proceeds(tmp_path, dm):
    res, ledger, _ = _run(tmp_path, 1, fixture=_fixture(diff=DIFF_XY))
    assert res.terminal != TerminalState.HOLD_REPEAT_DECLINED
    assert _calls(ledger) > 0


def test_force_env_proceeds(tmp_path, dm, monkeypatch):
    _first_hold(tmp_path)
    monkeypatch.setenv("POSTPLAN_FORCE", "1")
    res, ledger, _ = _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    assert res.terminal != TerminalState.HOLD_REPEAT_DECLINED
    assert _calls(ledger) > 0
    assert any("skipped (POSTPLAN_FORCE=1)" in line for line in res.audit)


def test_force_env_empty_still_declines(tmp_path, dm, monkeypatch):
    _first_hold(tmp_path)
    monkeypatch.setenv("POSTPLAN_FORCE", "")
    res, ledger, _ = _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    assert res.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert _calls(ledger) == 0


def test_force_env_zero_still_declines(tmp_path, dm, monkeypatch):
    _first_hold(tmp_path)
    monkeypatch.setenv("POSTPLAN_FORCE", "0")
    res, ledger, _ = _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    assert res.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert _calls(ledger) == 0


def test_early_decline_dm_once(tmp_path, dm):
    res1 = _first_hold(tmp_path)
    assert _record(tmp_path)["repeat_count"] == 1
    assert dm.count() == 0
    res2, _, _ = _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    assert res2.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert res2.hold_repeat["dm_sent"] is True
    assert dm.count() == 1
    sent = dm.log.read_text()
    assert SLUG in sent and "bin/post-plan-now --force" in sent
    assert "--quiet" in sent and "--no-fallback" in sent
    res3, _, _ = _run(tmp_path, 3, fixture=_fixture(diff=DIFF_XY))
    assert res3.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert dm.count() == 1
    assert res3.hold_repeat["dm_sent"] is False
    assert res1.terminal == TerminalState.SHIPPED_HELD


def test_early_decline_dm_failure_still_declines(tmp_path, dm, monkeypatch):
    _first_hold(tmp_path)
    monkeypatch.setenv("HOLDREPEAT_FAKE_RC", "1")
    res2, _, _ = _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    assert res2.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert res2.hold_repeat["dm_sent"] is False
    assert _record(tmp_path)["dm_sent_key"] is None
    assert dm.count() == 1
    res3, _, _ = _run(tmp_path, 3, fixture=_fixture(diff=DIFF_XY))
    assert res3.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert dm.count() == 2


def test_early_decline_leaves_record_counts(tmp_path, dm):
    _first_hold(tmp_path)
    before = _record(tmp_path)
    res, _, _ = _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    assert res.terminal == TerminalState.HOLD_REPEAT_DECLINED
    after = _record(tmp_path)
    assert after["repeat_count"] == before["repeat_count"] == 1
    assert after["structural_key"] == before["structural_key"]
    assert after["structural"] == before["structural"]
    assert after["fingerprint"] == before["fingerprint"]


def test_early_check_exception_fails_open(tmp_path, dm, monkeypatch):
    _first_hold(tmp_path)

    def boom(*a, **k):
        raise RuntimeError("boom")

    monkeypatch.setattr(holdrepeat, "early_repeat_reason", boom)
    res, ledger, _ = _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    assert res.terminal != TerminalState.HOLD_REPEAT_DECLINED
    assert _calls(ledger) > 0
    assert any("phase2 early hold-repeat: skipped (" in line for line in res.audit)


# --- non-replay row: LiveGit.conformance_files feeds the check on a real tree -------------

_ENV = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}


def _git(d, *args):
    # core.hooksPath=/dev/null: a developer's global hooks must not fire on fixture commits.
    return subprocess.run(["git", "-c", "core.hooksPath=/dev/null", "-C", str(d), *args],
                          check=True, capture_output=True, text=True, env=_ENV)


def _write(d, rel, text):
    target = Path(d) / rel
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(text)


def _real_repo(tmp_path):
    """Bare origin + clone on branch SLUG with one committed unrelated file past master."""
    origin, seed, wt = tmp_path / "origin.git", tmp_path / "seed", tmp_path / "wt"
    _git(tmp_path, "init", "-q", "--bare", "-b", "master", str(origin))
    _git(tmp_path, "init", "-q", "-b", "master", str(seed))
    _write(seed, "a.txt", "base\n")
    _git(seed, "add", "-A")
    _git(seed, "commit", "-qm", "base")
    _git(seed, "remote", "add", "origin", str(origin))
    _git(seed, "push", "-q", "origin", "master")
    _git(tmp_path, "clone", "-q", str(origin), str(wt))
    _git(wt, "config", "user.email", "t@t")
    _git(wt, "config", "user.name", "t")
    _git(wt, "checkout", "-qb", SLUG)
    return wt


def test_isolated_real_repo_declines(tmp_path, dm):
    plans = tmp_path / "plans"
    plans.mkdir()
    (plans / f"{SLUG}.md").write_text(MISSING_PLAN)
    plan = locate_plan(SLUG, plans_dir=str(plans))
    items = conformance.early_missing_items(plan, [])
    assert items and all(i.startswith(("MISSING:", "MISSING-FILE:")) for i in items)
    reason = "; ".join(items)

    state = tmp_path / "state"
    cond = types.SimpleNamespace(number=3, name="plan_conformance", blocked=True, reason=reason)
    holdrepeat.observe(str(state), SLUG, armed=False, conditions=[cond],
                       fingerprint="fp-const", pr=4242, now="2026-10-02T12:00:00Z")

    wt = _real_repo(tmp_path)
    _write(wt, "unrelated/new_file.txt", "unrelated\n")
    _git(wt, "add", "-A")
    _git(wt, "commit", "-qm", "feat: unrelated work")

    ledger = UsageLedger()
    llm = FixtureLlm(ledger, dict(CANNED))
    res = runner.run(None, str(tmp_path / "out"), llm, mode="live", live=False,
                     worktree=str(wt), plans_dir=str(plans), state_dir=str(state))
    assert res.slug == SLUG
    assert res.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert _calls(ledger) == 0
    assert res.hold_repeat["early_decline"] is True


# --- held-unfixable marker (written by the decline, consulted by the schedulers) -----------

@pytest.fixture
def plan_file(tmp_path, monkeypatch):
    """Replay mode resolves the plan from fixture text with an empty path.

    The marker keys on the plan's file, so give the resolved plan a real path."""
    path = tmp_path / "plans" / f"{SLUG}.md"
    path.parent.mkdir()
    path.write_text(MISSING_PLAN)
    real = runner.locate_plan

    def locate(slug, *a, **k):
        info = real(slug, *a, **k)
        if k.get("content_override") is not None:
            info.path = str(path)
        return info

    monkeypatch.setattr(runner, "locate_plan", locate)
    return path


def _marker_file(tmp_path):
    return tmp_path / "state" / f"{SLUG}.held-unfixable.json"


def test_decline_writes_active_marker(tmp_path, dm, plan_file):
    _first_hold(tmp_path)
    res, _, _ = _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    assert res.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert _marker_file(tmp_path).exists()
    assert heldmarker.active(str(tmp_path / "state"), SLUG, str(plan_file))[0] is True


def test_decline_dm_names_marker_and_clear_steps(tmp_path, dm, plan_file):
    _first_hold(tmp_path)
    _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    sent = dm.log.read_text()
    assert str(_marker_file(tmp_path)) in sent
    assert "delete the marker file" in sent
    assert "bin/post-plan-now --force" in sent


def test_second_decline_sends_no_second_dm(tmp_path, dm, plan_file):
    _first_hold(tmp_path)
    _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    _run(tmp_path, 3, fixture=_fixture(diff=DIFF_XY))
    assert dm.count() == 1


def test_new_marker_dms_even_when_dm_key_already_sent(tmp_path, dm, plan_file):
    _first_hold(tmp_path)
    state = str(tmp_path / "state")
    key = _record(tmp_path)["structural_key"]
    holdrepeat.mark_dm_sent(state, SLUG, key)
    assert not _marker_file(tmp_path).exists()
    res, _, _ = _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    assert res.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert dm.count() == 1
    assert str(_marker_file(tmp_path)) in dm.log.read_text()


def test_marker_write_never_touches_arm_or_pr(tmp_path, dm, plan_file):
    _first_hold(tmp_path)
    res = runner.RunResult(terminal=TerminalState.FAILED, slug=SLUG, plan=None, arm="SENTINEL",
                           pr_number=4242)
    plan = locate_plan(SLUG, content_override=MISSING_PLAN)
    plan.path = str(plan_file)
    out = str(tmp_path / "out-direct")
    done = runner._early_hold_repeat(res, plan, [], SLUG, out, False, str(tmp_path / "state"),
                                     lambda m: None)
    assert done is True
    assert res.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert res.arm == "SENTINEL"
    assert res.pr_number == 4242


def test_marker_write_failure_still_declines(tmp_path, dm, plan_file, monkeypatch):
    _first_hold(tmp_path)

    def boom(*a, **k):
        raise RuntimeError("boom")

    monkeypatch.setattr(heldmarker, "write", boom)
    res, ledger, _ = _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    assert res.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert _calls(ledger) == 0
    assert res.pr_number is None
    assert res.hold_repeat["marker"] == ""
    assert "Marker:" not in dm.log.read_text()


def test_force_writes_no_marker(tmp_path, dm, plan_file, monkeypatch):
    _first_hold(tmp_path)
    monkeypatch.setenv("POSTPLAN_FORCE", "1")
    _run(tmp_path, 2, fixture=_fixture(diff=DIFF_XY))
    assert not _marker_file(tmp_path).exists()


def test_proceed_writes_no_marker(tmp_path, dm, plan_file):
    _first_hold(tmp_path)
    res, _, _ = _run(tmp_path, 2, fixture=_fixture(plan=MISSING_PLAN_PLUS, diff=DIFF_XY))
    assert res.terminal != TerminalState.HOLD_REPEAT_DECLINED
    assert not _marker_file(tmp_path).exists()


# --- UNREALISED-ASSERTION-only holds: head-keyed decline in a real checkout ----------------

ROW_V2 = "UNREALISED-ASSERTION: row V2 -- tests/test_x.py::test_y"
TOKEN = "zz_absent_token"
CLEAN_PLAN = "# Synthetic plan\n\nNo critical files and no matrix.\n"


def _fake_matrix(tmp_path, *, code=1, sentinel=None):
    """Executable stand-in for bin/lib/plan-matrix-assertions: one row plus one token."""
    path = tmp_path / "fake-matrix"
    body = ["#!/bin/sh"]
    if sentinel:
        body.append(f"touch '{sentinel}'")
    body.append(f"printf '%s\\n' '{ROW_V2}'")
    body.append(f"printf '%s\\n' '  missing: {TOKEN}' >&2")
    body.append(f"exit {code}")
    path.write_text("\n".join(body) + "\n")
    path.chmod(path.stat().st_mode | stat.S_IEXEC)
    return str(path)


def _seed_hold(tmp_path, reason):
    cond = types.SimpleNamespace(number=3, name="plan_conformance", blocked=True, reason=reason)
    holdrepeat.observe(str(tmp_path / "state"), SLUG, armed=False, conditions=[cond],
                       fingerprint="fp-const", pr=4242, now="2026-10-02T12:00:00Z")


def _unr_env(tmp_path, monkeypatch, *, plan_text=CLEAN_PLAN, reason=ROW_V2, code=1,
             sentinel=None):
    plans = tmp_path / "plans"
    plans.mkdir()
    (plans / f"{SLUG}.md").write_text(plan_text)
    plan = locate_plan(SLUG, plans_dir=str(plans))
    _seed_hold(tmp_path, reason)
    wt = _real_repo(tmp_path)
    monkeypatch.setattr(conformance, "_MATRIX_ASSERTIONS_SCRIPT",
                        _fake_matrix(tmp_path, code=code, sentinel=sentinel))
    return plan, wt


def _early(tmp_path, plan, wt, log, n=1):
    res = runner.RunResult(terminal=TerminalState.FAILED, slug=SLUG, plan=None,
                           arm="SENTINEL", pr_number=4242)
    done = runner._early_hold_repeat(res, plan, [], SLUG, str(tmp_path / f"out{n}"), False,
                                     str(tmp_path / "state"), log.append,
                                     diff_body="", worktree=str(wt))
    return res, done


def _head(wt):
    return _git(wt, "rev-parse", "HEAD").stdout.strip()


def test_early_unrealised_only_hold_declines_and_writes_head_keyed_marker(
        tmp_path, dm, monkeypatch):
    plan, wt = _unr_env(tmp_path, monkeypatch)
    state, log = str(tmp_path / "state"), []
    res1, done1 = _early(tmp_path, plan, wt, log, 1)
    assert done1 is True
    assert res1.terminal == TerminalState.HOLD_REPEAT_DECLINED
    head = _head(wt)
    marker = json.loads(_marker_file(tmp_path).read_text())
    assert marker["head_sha"] == head
    assert marker["missing_tokens"] == [TOKEN]
    assert heldmarker.active(state, SLUG, plan.path, head_sha=head, repo=str(wt))[0] is True
    res2, done2 = _early(tmp_path, plan, wt, log, 2)
    assert done2 is True
    assert res2.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert dm.count() == 1
    sent = dm.log.read_text()
    assert "Push a test that realises these matrix rows" in sent
    assert "the plan file, the branch head or this hold changes" in sent
    assert any("same UNREALISED-ASSERTION set" in ln for ln in log)


def test_early_unrealised_marker_stale_after_new_commit(tmp_path, dm, monkeypatch):
    plan, wt = _unr_env(tmp_path, monkeypatch)
    _, done = _early(tmp_path, plan, wt, [])
    assert done is True
    old = _head(wt)
    _write(wt, "b.txt", "more\n")
    _git(wt, "add", "-A")
    _git(wt, "commit", "-qm", "another commit")
    new = _head(wt)
    assert new != old
    state = str(tmp_path / "state")
    assert heldmarker.active(state, SLUG, plan.path, head_sha=new, repo=str(wt))[0] is False
    assert heldmarker.active(state, SLUG, plan.path, head_sha=old, repo=str(wt))[0] is True


def test_early_unrealised_proceeds_when_check_unavailable(tmp_path, dm, monkeypatch):
    plan, wt = _unr_env(tmp_path, monkeypatch, code=2)
    log = []
    res, done = _early(tmp_path, plan, wt, log)
    assert done is False
    assert res.terminal == TerminalState.FAILED
    assert not _marker_file(tmp_path).exists()
    assert any("unrealised: matrix check unavailable" in ln for ln in log)


def test_early_unrealised_proceeds_when_master_gains_token(tmp_path, dm, monkeypatch):
    plan, wt = _unr_env(tmp_path, monkeypatch)
    other = tmp_path / "other"
    _git(tmp_path, "clone", "-q", str(tmp_path / "origin.git"), str(other))
    _git(other, "config", "user.email", "t@t")
    _git(other, "config", "user.name", "t")
    _write(other, f"tests/{TOKEN}.txt", "now realised\n")
    _git(other, "add", "-A")
    _git(other, "commit", "-qm", "master gains the token")
    _git(other, "push", "-q", "origin", "master")
    log = []
    res, done = _early(tmp_path, plan, wt, log)
    assert done is False
    assert not _marker_file(tmp_path).exists()
    assert any("unrealised: master carries a missing token" in ln for ln in log)


def test_early_unrealised_proceeds_on_dirty_worktree(tmp_path, dm, monkeypatch):
    plan, wt = _unr_env(tmp_path, monkeypatch)
    _write(wt, "a.txt", "edited but uncommitted\n")
    log = []
    res, done = _early(tmp_path, plan, wt, log)
    assert done is False
    assert not _marker_file(tmp_path).exists()
    assert any("unrealised: worktree dirty or unreadable" in ln for ln in log)


def test_early_unrealised_proceeds_when_fetch_fails(tmp_path, dm, monkeypatch):
    plan, wt = _unr_env(tmp_path, monkeypatch)
    # The local origin/master ref stays and lacks the token, so only the fetch arm
    # can make this run proceed.
    _git(wt, "remote", "set-url", "origin", str(tmp_path / "missing.git"))
    log = []
    res, done = _early(tmp_path, plan, wt, log)
    assert done is False
    assert not _marker_file(tmp_path).exists()
    assert any("unrealised: fetch failed" in ln for ln in log)


def test_early_missing_only_log_line_unchanged(tmp_path, dm, monkeypatch):
    sentinel = tmp_path / "sentinel"
    plans = tmp_path / "plans"
    plans.mkdir()
    two = MISSING_PLAN
    one = MISSING_PLAN.replace(f"## Critical Files\n\n- `{CRITICAL}` (new)\n\n", "")
    (plans / f"{SLUG}.md").write_text(two)
    plan = locate_plan(SLUG, plans_dir=str(plans))
    prior = conformance.early_missing_items(locate_plan(SLUG, content_override=one), [])
    assert len(prior) == 1
    assert len(conformance.early_missing_items(plan, [])) == 2
    _seed_hold(tmp_path, "; ".join(prior))
    wt = _real_repo(tmp_path)
    monkeypatch.setattr(conformance, "_MATRIX_ASSERTIONS_SCRIPT",
                        _fake_matrix(tmp_path, sentinel=sentinel))
    log = []
    res, done = _early(tmp_path, plan, wt, log)
    assert done is False
    assert "phase2 early hold-repeat: proceed (2 MISSING item(s))" in log
    assert not sentinel.exists()


def test_run_wires_diff_and_worktree_into_unrealised_decline(tmp_path, dm, monkeypatch):
    """runner.run() must hand the worktree to the early check, or the run never declines."""
    plan, wt = _unr_env(tmp_path, monkeypatch)
    _write(wt, "unrelated/new_file.txt", "unrelated\n")
    _git(wt, "add", "-A")
    _git(wt, "commit", "-qm", "feat: unrelated work")
    ledger = UsageLedger()
    llm = FixtureLlm(ledger, dict(CANNED))
    res = runner.run(None, str(tmp_path / "out"), llm, mode="live", live=False,
                     worktree=str(wt), plans_dir=str(tmp_path / "plans"),
                     state_dir=str(tmp_path / "state"))
    assert res.terminal == TerminalState.HOLD_REPEAT_DECLINED
    assert _calls(ledger) == 0
    assert json.loads(_marker_file(tmp_path).read_text())["head_sha"] == _head(wt)
