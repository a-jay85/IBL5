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
