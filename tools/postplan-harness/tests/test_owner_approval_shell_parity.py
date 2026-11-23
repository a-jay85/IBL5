"""Shell and Python agree on condition (7)'s owner-approval state.

Python: harness.armable.owner_approval_state. Shell: pr_owner_approval_hold in
bin/lib/pr-armable.sh. Both wrap hs_approval_state; this pins the mapping."""
import json
import subprocess
from pathlib import Path

import pytest

from harness import armable
from tests.test_armable_owner_approval import BOT, _fingerprint, _record, scratch  # noqa: F401

PR_ARMABLE = Path(__file__).resolve().parents[3] / "bin" / "lib" / "pr-armable.sh"


def _shell(repo, comments_path, labels):
    labels_json = json.dumps([{"name": n} for n in labels])
    out = subprocess.run(
        ["bash", "-c", 'source "$0" && pr_owner_approval_hold "$@"', str(PR_ARMABLE),
         "master", "pr", str(comments_path), labels_json],
        cwd=repo, capture_output=True, text=True, timeout=60, check=False)
    return out.stdout.strip()


@pytest.mark.parametrize("scenario", ["match", "no-label", "no-record", "mismatch", "error",
                                      "near-miss"])
def test_shell_and_python_agree_on_every_state(scratch, tmp_path, scenario):
    labels = ["human-approved"]
    comments = [_record(_fingerprint(scratch, "master", "pr"))]
    if scenario == "no-label":
        labels = []
    elif scenario == "near-miss":
        labels = ["human-approved-old"]
    elif scenario == "no-record":
        comments.append({"created_at": "2026-11-02T00:00:00Z", "user": BOT,
                         "body": "<!-- hs-approved-diff: revoked -->\nRemoved."})
    elif scenario == "mismatch":
        comments = [_record(_fingerprint(scratch, "master", "older"))]

    path = tmp_path / "comments.json"
    py_comments = comments
    if scenario == "error":
        py_comments = None
        path = tmp_path / "does-not-exist.json"
    else:
        path.write_text(json.dumps(comments))

    py = armable.owner_approval_state(labels, py_comments, "master", "pr", scratch)
    sh = _shell(scratch, path, labels)
    want = {"near-miss": "no-label"}.get(scenario, scenario)
    assert py == want
    assert (py == "match") == (sh == "")
    if py != "match":
        assert sh == f"approval-{py}"


# ---- bin/owner-approve's tree binding against a rendered fix-round sticky ----

OWNER_APPROVE = Path(__file__).resolve().parents[3] / "bin" / "owner-approve"
HELD_PRS = Path(__file__).resolve().parents[3] / "bin" / "lib" / "held-prs.sh"
TREE_A = "c" * 40   # the tree verdict 1 reviewed (TREE in test_fidelity_sticky)
TREE_B = "b" * 40   # the tree after the fix round's push

_OA_DRIVER = r"""
. "$HELD_PRS"
eval "$(awk '/^_oa_verdict\(\) \{/ {p=1} p {print} p && /^\}/ {exit}' "$OWNER_APPROVE")"
_oa_comments() { me=owner; comments=$(cat "$COMMENTS"); }
_oa_refuse() { printf 'REFUSE %s\n' "$2"; exit "$1"; }
gh() { printf '%s\n' "$HEAD_TREE"; }
OA_KEPT_ACCEPTED=0; OA_REPO=r/r; OA_PR=1; head=0000000000000000000000000000000000000001
_oa_verdict final && printf 'ACCEPT %s\n' "$head_tree_armed"
"""


def _oa_verdict_shell(tmp_path, sticky_body, head_tree):
    """Run bin/owner-approve's own _oa_verdict (extracted) on a sticky and a head tree."""
    comments = tmp_path / "oa-comments.json"
    comments.write_text(json.dumps([{"user": {"login": "owner"}, "body": sticky_body}]))
    out = subprocess.run(
        ["bash", "-c", _OA_DRIVER],
        env={"PATH": "/usr/bin:/bin:/usr/local/bin:/opt/homebrew/bin", "HELD_PRS": str(HELD_PRS),
             "OWNER_APPROVE": str(OWNER_APPROVE), "COMMENTS": str(comments),
             "HEAD_TREE": head_tree},
        capture_output=True, text=True, timeout=60, check=False)
    return out.returncode, out.stdout.strip()


def _fix_round_sticky(monkeypatch, v2, *, banner_word):
    from tests.test_fidelity_sticky import _decision, _sticky, rejudged_fid
    from harness import fidelity
    fid = rejudged_fid(monkeypatch, v2)
    body = _sticky(fid=fid, terminal=fidelity.sticky_terminal(fid),
                   decision=_decision(fidelity_verdict=banner_word))
    return fid, body


def test_owner_approve_refuses_stale_tree_when_rejudge_indeterminate(tmp_path):
    """#4088: verdict 1 judged tree A, the fix round moved the head to B, nothing re-judged."""
    from tests.test_fidelity_sticky import _sticky
    from harness import fidelity
    fid = {"verdict_1": "READY WITH NOTES", "error_kind": None, "reviewed_tree": TREE_A,
           "remediation_sha": None, "verdict_2": None, "reviewed_tree_2": None}
    body = _sticky(fid=fid, terminal=fidelity.sticky_terminal(fid))
    assert "**Re-reviewed tree:**" not in body
    rc, out = _oa_verdict_shell(tmp_path, body, TREE_B)
    assert rc == 4
    assert out.startswith("REFUSE")
    assert TREE_A in out and TREE_B in out


def test_owner_approve_binds_fix_round_rereviewed_tree(tmp_path, monkeypatch):
    fid, body = _fix_round_sticky(monkeypatch, "READY", banner_word="READY")
    assert f"**Re-reviewed tree:** {TREE_B} (READY)" in body
    rc, out = _oa_verdict_shell(tmp_path, body, TREE_B)
    assert (rc, out) == (0, f"ACCEPT {TREE_B}")


def test_owner_approve_refuses_fix_round_not_ready_rejudge(tmp_path, monkeypatch):
    fid, body = _fix_round_sticky(monkeypatch, "NOT READY", banner_word="READY")
    assert f"**Re-reviewed tree:** {TREE_B} (NOT READY)" in body
    rc, out = _oa_verdict_shell(tmp_path, body, TREE_B)
    assert rc == 4
    # The rendered banner already carries the NOT READY terminal word, so the banner check
    # fires before the tree-word check. Either way the shell refuses and names the verdict.
    assert out in ("REFUSE the verdict is NOT READY.",
                   f"REFUSE the re-review of tree {TREE_B} says NOT READY, not READY.")
