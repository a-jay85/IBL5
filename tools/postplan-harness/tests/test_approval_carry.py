"""Owner-approval carry across post-plan's own commits: ledger, decider and fix-first scope."""
from __future__ import annotations

import json
import os
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import approval_carry as ac
from harness import approval_ledger as al
from harness import unplanned_scope as us
from harness.fidelity import denied_local_gate_edits

REPO_ROOT = Path(__file__).resolve().parents[3]
HEAD = "e" * 40
F0, F1, F2 = "a" * 40, "b" * 40, "c" * 40
CREATED = "2026-11-19T10:00:00Z"
T1, T2, T3 = "2026-11-20T10:00:00Z", "2026-11-20T11:00:00Z", "2026-11-20T12:00:00Z"
BRANCH = "feat/carry"
FIX_A = "ibl5/classes/Foo/Foo.php"
FIX_B = "ibl5/classes/Foo/Bar.php"
OTHER = "ibl5/classes/Foo/Other.php"
GATE = "bin/lib/x.sh"


def _git(cwd, *args):
    return subprocess.run(["git", "-C", str(cwd), *args], capture_output=True, text=True, check=True).stdout


@pytest.fixture
def repo(tmp_path):
    root = tmp_path / "repo"
    root.mkdir()
    _git(root, "init", "-q", "-b", "master")
    (root / "f.txt").write_text("x\n")
    _git(root, "add", "f.txt")
    _git(root, "-c", "user.name=t", "-c", "user.email=t@t", "commit", "-q", "-m", "init")
    return root


def _link(kind, frm, to, files, ts, sha=HEAD):
    return {"v": 1, "kind": kind, "from": frm, "to": to, "sha": sha, "files": sorted(files), "ts": ts}


def _write_ledger(repo, links, raw=None):
    path = al.ledger_path(str(repo), BRANCH)
    path.parent.mkdir(parents=True, exist_ok=True)
    lines = [json.dumps(r, sort_keys=True) for r in links] + list(raw or [])
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")
    return path


def _approval(mode="approve", diff=F0, created=CREATED, span=None, id_=1):
    lines = [f'Owner approved 2026-11-19: "ship it" (card A). Head {HEAD}. Conversation: T3 thread t.']
    if span is not None:
        lines += ["", "Kept unplanned changes this approval accepts:", us.APPROVAL_KEPT_BEGIN, *span,
                  us.APPROVAL_KEPT_END]
    lines += ["", f"<!-- owner-approve mode={mode} head={HEAD} diff={diff} -->"]
    return {"id": id_, "user": {"login": "a-jay85", "type": "User"}, "author_association": "OWNER",
            "created_at": created, "body": "\n".join(lines) + "\n"}


def _pr_body(*lines):
    return "intro\n\n" + us.KEPT_BEGIN + "\n" + "\n".join(lines) + "\n" + us.KEPT_END + "\n"


# --- fix-first scope ---------------------------------------------------------------------


def _scope_ledger(repo, files, extra=()):
    _write_ledger(repo, [_link("fix-first-scope", F0, F1, files, T1), *extra])


def test_fix_first_scope_covers_fix_files(repo):
    _scope_ledger(repo, [FIX_A, FIX_B])
    links = al.read_links(str(repo), BRANCH)
    body = _pr_body(f"- Foo helper trimmed beyond plan (`{FIX_A}`)", f"- Other file touched (`{OTHER}`)")
    got = ac.fix_scope_evidence([_approval("fix-first")], links, F1, body, [FIX_A, FIX_B, OTHER])
    assert got is not None
    assert got.kept_span == f"- Foo helper trimmed beyond plan (`{FIX_A}`)"
    assert got.created_at == T1
    assert FIX_A in got.words and FIX_B in got.words


def test_fix_first_scope_ignores_non_fix_files(repo):
    _scope_ledger(repo, [FIX_A])
    links = al.read_links(str(repo), BRANCH)
    mixed = f"- Foo and Other both changed (`{FIX_A}`, `{OTHER}`)"
    only_mixed = ac.fix_scope_evidence([_approval("fix-first")], links, F1, _pr_body(mixed), [FIX_A, OTHER])
    assert only_mixed is None
    clean = f"- Foo helper trimmed beyond plan (`{FIX_A}`)"
    got = ac.fix_scope_evidence([_approval("fix-first")], links, F1, _pr_body(mixed, clean), [FIX_A, OTHER])
    assert got is not None and got.kept_span == clean


def test_fix_first_scope_applies_before_finish_arms(repo, monkeypatch):
    _scope_ledger(repo, [FIX_A])  # a scope link and no finish link yet
    monkeypatch.setattr(ac, "_changed_paths", lambda wt, base: [FIX_A, OTHER])
    comments = [_approval("fix-first")]
    body = _pr_body(f"- Foo helper trimmed beyond plan (`{FIX_A}`)")
    links = al.read_links(str(repo), BRANCH)
    assert [k.kind for k in links] == ["fix-first-scope"]
    assert ac.evaluate(comments, links, F1).reason == "no-finish"
    got = ac.harness_evidence(comments, str(repo), BRANCH, "origin/master", F1, body)
    assert got is not None
    assert got.kept_span == f"- Foo helper trimmed beyond plan (`{FIX_A}`)"


def test_fix_first_scope_excludes_gate_files(repo):
    assert denied_local_gate_edits([GATE]) == [GATE] and denied_local_gate_edits([FIX_A]) == []
    _scope_ledger(repo, [FIX_A, GATE])
    links = al.read_links(str(repo), BRANCH)
    body = _pr_body(f"- Foo helper trimmed (`{FIX_A}`)", f"- Shell helper changed (`{GATE}`)")
    got = ac.fix_scope_evidence([_approval("fix-first")], links, F1, body, [FIX_A, GATE])
    assert got is not None
    assert got.kept_span == f"- Foo helper trimmed (`{FIX_A}`)"
    assert GATE not in got.words
    only_gate = ac.fix_scope_evidence([_approval("fix-first")], links, F1,
                                      _pr_body(f"- Shell helper changed (`{GATE}`)"), [FIX_A, GATE])
    assert only_gate is None


def test_fix_first_scope_uses_first_finish_files(repo):
    _write_ledger(repo, [
        _link("fix-first-scope", F0, F1, [FIX_A], T1),
        _link("fix", F1, F2, [OTHER], T2),
        _link("fix-first-scope", F0, F2, [FIX_A, FIX_B], T3),  # a later --finish also spans bot commits
    ])
    links = al.read_links(str(repo), BRANCH)
    body = _pr_body(f"- Foo trimmed (`{FIX_A}`)", f"- Bar trimmed (`{FIX_B}`)")
    got = ac.fix_scope_evidence([_approval("fix-first")], links, F2, body, [FIX_A, FIX_B, OTHER])
    assert got is not None
    assert got.kept_span == f"- Foo trimmed (`{FIX_A}`)"
    assert got.created_at == T1


def test_scope_link_alone_never_carries(repo):
    _scope_ledger(repo, [FIX_A])
    links = al.read_links(str(repo), BRANCH)
    for fp in (F0, F1, F2):
        d = ac.evaluate([_approval("fix-first")], links, fp)
        assert (d.ok, d.reason, d.src_mode) == (False, "no-finish", "fix-first")


def test_carry_gate_list_covers_owner_approve():
    assert denied_local_gate_edits(["bin/owner-approve"]) == ["bin/owner-approve"]


# --- chain and ledger --------------------------------------------------------------------


def test_carry_ignores_links_before_approval(repo):
    _write_ledger(repo, [_link("fix", F0, F1, [FIX_A], "2026-11-18T10:00:00Z")])
    links = al.read_links(str(repo), BRANCH)
    d = ac.evaluate([_approval("approve", diff=F0)], links, F1)
    assert (d.ok, d.reason) == (False, "outside-commit")
    # An outside commit: a link that does not start at the approved diff cannot bridge to the head.
    _write_ledger(repo, [_link("fix", F2, F1, [FIX_A], T1)])
    d = ac.evaluate([_approval("approve", diff=F0)], al.read_links(str(repo), BRANCH), F1)
    assert (d.ok, d.reason) == (False, "outside-commit")
    _write_ledger(repo, [_link("fix", F0, F1, [FIX_A], T1)])
    d = ac.evaluate([_approval("approve", diff=F0)], al.read_links(str(repo), BRANCH), F1)
    assert (d.ok, d.reason, d.files) == (True, "eligible", (FIX_A,))


def test_ledger_rejects_near_miss_fingerprint(repo):
    short = "a" * 39
    _write_ledger(repo, [], raw=[json.dumps(_link("fix", short, F1, [FIX_A], T1))])
    assert al.read_links(str(repo), BRANCH) is None
    _write_ledger(repo, [_link("fix", F0, F1, [FIX_A], T1)])
    assert len(al.read_links(str(repo), BRANCH)) == 1
    path = al.ledger_path(str(repo), "other")
    assert not path.exists()
    assert al.append_link(str(repo), "other", "fix", short, F1, HEAD, [FIX_A]) is False
    assert not path.exists()
    assert al.append_link(str(repo), "other", "fix", F0, F1, "d" * 39, [FIX_A]) is False
    assert not path.exists()
    assert al.append_link(str(repo), "other", "fix", F0, F1, HEAD, [FIX_A]) is True


def test_ledger_path_is_untracked(repo):
    _git(repo, "branch", "side")
    wt = repo.parent / "wt"
    _git(repo, "worktree", "add", "-q", str(wt), "side")
    common = Path(_git(wt, "rev-parse", "--path-format=absolute", "--git-common-dir").strip())
    path = al.ledger_path(str(wt), BRANCH)
    assert common in path.parents
    assert al.append_link(str(wt), BRANCH, "fix", F0, F1, HEAD, [FIX_A]) is True
    assert path.exists()
    assert _git(wt, "status", "--porcelain") == ""
    assert _git(repo, "status", "--porcelain") == ""


# --- CI reader ---------------------------------------------------------------------------


def _bot(fp, created):
    return {"user": {"login": "github-actions[bot]", "type": "Bot"}, "created_at": created,
            "body": f"<!-- hs-approved-diff: {fp} -->\nRecorded."}


def _classify(tmp_path, comments, fp):
    f = tmp_path / "c.json"
    f.write_text(json.dumps(comments))
    lib = REPO_ROOT / "bin" / "lib" / "human-signoff-classifier.sh"
    script = (f'source "{lib}"; hs_eval_approval synchronize "" not-ui human-approved "{fp}" "{f}" >/dev/null; '
              'printf "%s|%s" "$HS_VERDICT" "$HS_DROP_REASON"')
    out = subprocess.run(["bash", "-c", script], capture_output=True, text=True)
    return out.stdout.strip()


def test_carry_survives_ci_reader(tmp_path):
    carry = _approval("approve", diff=F0, created="2026-11-19T10:05:00Z")
    carry["body"] = ("Owner approval carried to post-plan's own commits.\n\n"
                     f"<!-- owner-approve-carry head={HEAD} diff={F1} src=1 from={F0} -->\n")
    fa, fb = _bot(F0, "2026-11-19T10:01:00Z"), _bot(F1, "2026-11-19T10:10:00Z")
    assert _classify(tmp_path, [fa, carry, fb], F1) == "keep|"
    assert _classify(tmp_path, [fa, carry], F1) == "drop|mismatch"


def test_carry_record_is_not_an_approval():
    body = ("Owner approval carried.\n\n"
            f"<!-- owner-approve-carry head={HEAD} diff={F1} src=1 from={F0} -->\n")
    carry = {"id": 9, "user": {"login": "a-jay85", "type": "User"}, "author_association": "OWNER",
             "created_at": "2026-11-20T10:00:00Z", "body": body}
    assert ac.genuine_approval([carry]) is None
    assert us.approval_from_comments([carry], F1) is None
    # A body that carries the carry marker never counts, even with a valid approval line last.
    mixed = dict(carry, body=body + f"<!-- owner-approve mode=approve head={HEAD} diff={F1} -->\n")
    assert ac.genuine_approval([mixed]) is None
    assert ac.genuine_approval([mixed, _approval()])[1]["id"] == 1
