"""Tree proof with master-adapted lines (ADR-0134 addendum 2026-10-10).

Fixture repos run the REAL lostwork.sh. The stub LLM writes the merged file as the
resolver, then answers the adaptation reviewer and CONFLICT-REVIEW from a reply table.
The base fixture replays the bin/test-plan-now failure: master rewrote every
`"/tmp/plan-now-$RES_TS` path to `"$RL/plan-now-$RES_TS`, and the branch added a new
`/tmp/` line that the resolver adapted.
"""
import json
import os
import shutil
import subprocess
import sys
import tempfile
import uuid

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness.adapters.gitad import LiveGit
from harness.armable import conflict_flag_path
from harness.state import HarnessError
from test_gitad_stacked_rebase import _COLLAPSE, _LOSTWORK, _cleanup_full, _rev, _sh

_PATH = "bin/test-plan-now"
_ORIGINAL = """        parkedlog) printf '...' > "/tmp/plan-now-$RES_TS.log" ;;"""
_ADAPTED = """        parkedlog) printf '...' > "$RL/plan-now-$RES_TS.log" ;;"""
_EXTRA_EDIT = """        parkedlog) printf '...' > "$RL/plan-now-$RES_TS.txt" ;;"""

_BASE = (
    "#!/usr/bin/env bash\n"
    "setup\n"
    """    printf 'a\\n' > "/tmp/plan-now-$RES_TS.sh"\n"""
    """    printf 'b\\n' > "/tmp/plan-now-$RES_TS.session"\n"""
    "keep one\n"
    "mode=base\n"
    "keep two\n"
    "keep three\n"
    "keep four\n"
    "tail\n"
)
_MASTER = (
    _BASE.replace('"/tmp/plan-now', '"$RL/plan-now').replace("mode=base", "mode=master")
)
_BRANCH = _BASE.replace("mode=base", "mode=branch") + _ORIGINAL + "\n"


def _merged(added_line):
    """The resolver's output: master's rewrites, the branch's mode line, `added_line`."""
    text = _MASTER.replace("mode=master", "mode=branch")
    return text + (added_line + "\n" if added_line is not None else "")


def _justification(old="/tmp", new="$RL", to=_ADAPTED):
    return "ADAPTED-LINE: " + json.dumps(
        {"from": _ORIGINAL, "to": to, "old": old, "new": new})


class _Llm:
    """Resolver writes `content`; the adaptation reviewer and CONFLICT-REVIEW answer
    from fixed replies. A reply that is an Exception instance is raised."""

    def __init__(self, worktree, content, *, resolve_reply="RESOLVED",
                 adapt_reply="ADAPTED-LINE-1=CONFIRMED",
                 review_reply="CONFLICT-REVIEW=CLEAN"):
        self.calls = []
        self._full = os.path.join(worktree, _PATH)
        self._content = content
        self._replies = {"resolve": resolve_reply, "conflict-adapt-review": adapt_reply,
                         "conflict-review": review_reply}

    def call_tooled(self, purpose, model, prompt, *, cwd, allowed_tools,
                    denied_tools=(), add_dirs=(), max_turns=None, **_):
        self.calls.append({"purpose": purpose, "prompt": prompt,
                           "allowed_tools": allowed_tools, "add_dirs": add_dirs})
        if purpose.startswith("conflict-resolve"):
            with open(self._full, "w") as fh:
                fh.write(self._content)
            reply = self._replies["resolve"]
        else:
            reply = self._replies[purpose]
        if isinstance(reply, Exception):
            raise reply
        return reply

    def purposes(self):
        return [c["purpose"].split(":")[0] for c in self.calls]


def _make_repo(lostwork_script=None):
    suffix = uuid.uuid4().hex[:8]
    branch = f"feature-al-{suffix}"
    key = branch
    d = tempfile.mkdtemp(prefix="postplan-al-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")
    os.makedirs(os.path.join(d, "bin"))
    open(os.path.join(d, _PATH), "w").write(_BASE)
    scripts = os.path.join(d, ".claude", "review-shared", "scripts")
    os.makedirs(scripts)
    if lostwork_script is None:
        shutil.copy(_LOSTWORK, os.path.join(scripts, "lostwork.sh"))
    else:
        open(os.path.join(scripts, "lostwork.sh"), "w").write(lostwork_script)
    shutil.copy(_COLLAPSE, os.path.join(scripts, "collapse-guard.sh"))
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")
    base_sha = _rev(d, "HEAD")

    open(os.path.join(d, _PATH), "w").write(_MASTER)
    _sh(d, "commit", "-am", "chore: move plan-now files under $RL")
    master_sha = _rev(d, "HEAD")
    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)

    _sh(d, "checkout", "-b", branch, base_sha)
    open(os.path.join(d, _PATH), "w").write(_BRANCH)
    _sh(d, "commit", "-am", "feat: parked log")
    return d, master_sha, key, branch


@pytest.fixture
def repo():
    made = []

    def make(lostwork_script=None):
        d, master_sha, key, branch = _make_repo(lostwork_script)
        made.append((key, branch, d))
        return d, master_sha, key, branch

    yield make
    for key, branch, d in made:
        shutil.rmtree(f"/tmp/postplan-adapt-review-{key}", ignore_errors=True)
        _cleanup_full(key, branch, d)


def _head_file(d):
    return _sh(d, "show", f"HEAD:{_PATH}").stdout


def _assert_restored(d, key, branch, pre):
    assert _rev(d, "HEAD") == pre
    assert _sh(d, "status", "--porcelain").stdout.strip() == ""
    assert not os.path.exists(f"/tmp/postplan-conflict-files-{key}.txt")
    assert not os.path.exists(conflict_flag_path(branch))


def _rebase_fails(d, llm):
    g = LiveGit(d, llm=llm)
    with pytest.raises(HarnessError) as exc:
        g.rebase_onto("origin/master")
    assert exc.value.kind == "rebase-conflict"
    assert "tree proof failed:" in exc.value.detail
    return exc.value.detail


def test_regression_replay_tmp_to_rl_is_accepted_and_hold_still_set(repo):
    d, _master, key, branch = repo()
    llm = _Llm(d, _merged(_ADAPTED), resolve_reply=_justification() + "\nRESOLVED")
    g = LiveGit(d, llm=llm)
    g.rebase_onto("origin/master")

    assert _ADAPTED in _head_file(d).splitlines()
    assert os.path.exists(conflict_flag_path(branch))
    res = g.last_conflict_resolution
    assert len(res.adapted_lines) == 1
    a = res.adapted_lines[0]
    assert (a.path, a.original, a.adapted, a.old, a.new) == (
        _PATH, _ORIGINAL, _ADAPTED, "/tmp", "$RL")
    notes = open(res.notes_path).read()
    assert "## Adapted lines" in notes
    assert _ADAPTED in notes
    verdict = open(f"/tmp/postplan-adapt-review-{key}/verdict.txt").read()
    assert verdict.splitlines()[0] == "ADAPTED-LINE-1=CONFIRMED"
    assert llm.purposes() == ["conflict-resolve", "conflict-adapt-review", "conflict-review"]
    assert "adaptations.txt" in llm.calls[2]["prompt"]
    assert os.path.exists(f"/tmp/postplan-conflict-review-{key}/adaptations.txt")


def test_reviewer_denies_adaptation_exits_fail_closed(repo):
    d, _master, key, branch = repo()
    pre = _rev(d, "HEAD")
    llm = _Llm(d, _merged(_ADAPTED), resolve_reply=_justification() + "\nRESOLVED",
               adapt_reply="ADAPTED-LINE-1=DENIED")
    detail = _rebase_fails(d, llm)
    assert "[1]=DENIED" in detail
    _assert_restored(d, key, branch, pre)


def test_reviewer_verdict_absent_fails_closed(repo):
    d, _master, key, branch = repo()
    pre = _rev(d, "HEAD")
    llm = _Llm(d, _merged(_ADAPTED), resolve_reply=_justification() + "\nRESOLVED",
               adapt_reply="looks good to me")
    detail = _rebase_fails(d, llm)
    assert "[1]=ABSENT" in detail
    _assert_restored(d, key, branch, pre)


def test_reviewer_call_raises_fails_closed(repo):
    d, _master, key, branch = repo()
    pre = _rev(d, "HEAD")
    llm = _Llm(d, _merged(_ADAPTED), resolve_reply=_justification() + "\nRESOLVED",
               adapt_reply=RuntimeError("reviewer timed out"))
    detail = _rebase_fails(d, llm)
    assert "[1]=ABSENT" in detail
    _assert_restored(d, key, branch, pre)


def test_dropped_line_without_near_match_stays_lost(repo):
    d, _master, key, branch = repo()
    pre = _rev(d, "HEAD")
    llm = _Llm(d, _merged(None), resolve_reply=_justification() + "\nRESOLVED")
    detail = _rebase_fails(d, llm)
    assert "no near-match" in detail
    assert "conflict-adapt-review" not in llm.purposes()
    _assert_restored(d, key, branch, pre)


def test_near_match_with_extra_edit_stays_lost(repo):
    d, _master, key, branch = repo()
    pre = _rev(d, "HEAD")
    llm = _Llm(d, _merged(_EXTRA_EDIT),
               resolve_reply=_justification(to=_EXTRA_EDIT) + "\nRESOLVED")
    detail = _rebase_fails(d, llm)
    assert "no near-match" in detail
    assert len(llm.calls) == 1
    _assert_restored(d, key, branch, pre)


def test_missing_justification_fails_closed(repo):
    d, _master, key, branch = repo()
    pre = _rev(d, "HEAD")
    llm = _Llm(d, _merged(_ADAPTED))
    detail = _rebase_fails(d, llm)
    assert "no resolver justification" in detail or "claimed no adaptation" in detail
    assert "conflict-adapt-review" not in llm.purposes()
    _assert_restored(d, key, branch, pre)


def test_wrong_transform_in_justification_fails_closed(repo):
    d, _master, key, branch = repo()
    pre = _rev(d, "HEAD")
    llm = _Llm(d, _merged(_ADAPTED),
               resolve_reply=_justification(old="tmp") + "\nRESOLVED")
    detail = _rebase_fails(d, llm)
    assert "no resolver justification" in detail
    assert "conflict-adapt-review" not in llm.purposes()
    _assert_restored(d, key, branch, pre)


_DIVERGED_SCRIPT = (
    "#!/usr/bin/env bash\n"
    f"printf '%s\\n' 'LOST: {_PATH}: +{_ORIGINAL}'\n"
    "echo 'CHECKED: files=1 added=2 deleted=1'\n"
    "echo 'TREE DIVERGED — inspect before pushing'\n"
    "exit 0\n"
)


def test_no_resolved_files_keeps_strict_proof(repo):
    d, master_sha, key, _branch = repo(lostwork_script=_DIVERGED_SCRIPT)
    llm = _Llm(d, _merged(_ADAPTED))
    g = LiveGit(d, llm=llm)
    script = g._load_lostwork(master_sha, key)
    from harness.adaptations import Justification
    j = Justification(_PATH, _ORIGINAL, _ADAPTED, "/tmp", "$RL")
    ok, out, adapted = g._prove_with_adaptations(
        script, key, resolved_files=(), adaptations=(j,),
        pre_rebase_sha=_rev(d, "HEAD"), master_sha=master_sha)
    assert ok is False
    assert adapted == ()
    assert out.startswith("tree proof failed:")
    assert "adaptation not applicable" in out
    assert llm.calls == []


def test_prove_lostwork_rerebase_stays_strict(repo):
    d, _master, key, _branch = repo(lostwork_script=_DIVERGED_SCRIPT)
    llm = _Llm(d, _merged(_ADAPTED))
    ok, out = LiveGit(d, llm=llm).prove_lostwork(key)
    assert ok is False
    assert "TREE DIVERGED" in out
    assert llm.calls == []


def test_strict_pass_makes_no_model_call(repo):
    d, _master, key, branch = repo()
    llm = _Llm(d, _merged(_ORIGINAL))
    g = LiveGit(d, llm=llm)
    g.rebase_onto("origin/master")
    assert _ORIGINAL in _head_file(d).splitlines()
    assert g.last_conflict_resolution.adapted_lines == ()
    assert llm.purposes() == ["conflict-resolve", "conflict-review"]
    assert not os.path.exists(f"/tmp/postplan-conflict-review-{key}/adaptations.txt")
