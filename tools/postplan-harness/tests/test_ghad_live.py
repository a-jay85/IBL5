import json
import os
import stat
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import ciwatch
from harness.adapters.ghad import LiveGh
from harness.state import Finding

SHIM = """#!/usr/bin/env bash
args="$*"
case "$args" in
  *"--input -"*) cat > "${GH_SHIM_LOG}.input" ;;
esac
echo "${args//$'\\n'/\\\\n}" >> "$GH_SHIM_LOG"
case "$1 $2" in
  "pr create") echo "https://github.com/o/r/pull/123" ;;
  "pr diff") [ -n "${GH_SHIM_DIFF:-}" ] && cat "$GH_SHIM_DIFF"; exit 0 ;;
  "issue create") echo "https://github.com/a-jay85/IBL5-backlog/issues/456" ;;
  "issue list") echo '[{"title":"existing issue"}]' ;;
  "pr view")
    if [ "$3" = "--json" ] || [ "$4" = "--json" ]; then
      case "$*" in
        *"-q .state"*) echo "MERGED" ;;
        *"--json comments"*) echo '{"comments":[{"body":"<!-- pr-ready-verdict -->"}]}' ;;
        *) echo '{"number":123,"title":"chore: t","body":"b","headRefOid":"abc","labels":[{"name":"x"}],"state":"OPEN"}' ;;
      esac
    fi ;;
  "repo view") echo "o/r" ;;
  "api "*)
    [ "${GH_SHIM_API_FAIL:-}" = "1" ] && { echo "422" >&2; exit 1; }
    if [ -n "${GH_SHIM_API_FAIL_ON_LINE:-}" ] && [ -f "${GH_SHIM_LOG}.input" ] \
       && grep -q "\"line\": ${GH_SHIM_API_FAIL_ON_LINE}[,}]" "${GH_SHIM_LOG}.input"; then
      echo "422" >&2; exit 1
    fi
    case "$args" in
      *"issues/123/comments"*)
        sleep "${GH_SHIM_SLEEP:-0}"
        printf '%s\n' "${GH_SHIM_STICKY_ID:-}" ;;
    esac ;;
esac
exit 0
"""


@pytest.fixture()
def shim(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    gh = bindir / "gh"
    gh.write_text(SHIM)
    gh.chmod(gh.stat().st_mode | stat.S_IEXEC)
    log = tmp_path / "gh-calls.log"
    log.write_text("")
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    monkeypatch.setenv("GH_SHIM_LOG", str(log))
    return log


def calls(log):
    return [l for l in log.read_text().splitlines() if l]


def test_pr_create_parses_number_and_audits(shim, tmp_path):
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    pr = gh.pr_create("chore: t", "body", "master")
    assert pr == 123
    acts = gh.actions()
    assert acts[0]["action"] == "pr_create" and acts[0]["executed"] is True
    assert any(c.startswith("pr create") and "--head my-branch" in c for c in calls(shim))


def test_merge_auto_omits_delete_branch(shim, tmp_path):
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    gh.pr_merge_auto(123)
    merge = [c for c in calls(shim) if c.startswith("pr merge")]
    assert merge == ["pr merge 123 --squash --auto"]
    assert "--delete-branch" not in merge[0]


def test_every_mutation_is_allowlisted(shim, tmp_path):
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    gh.pr_create("t", "b", "master")
    gh.pr_edit_body(123, "new body")
    gh.post_review_summary(123, "Code review", "No issues found.")
    gh.post_review_findings(123, "abc", "Security audit",
                            [Finding("security-audit", "security", "a.php", 3, "x", 90)])
    gh.pr_merge_auto(123)
    gh.label_add(123, "human-approved")
    gh.pr_sticky_verdict(123, "body\n<!-- pr-ready-verdict -->\n")
    allowed = ("pr create", "pr edit", "pr comment", "pr merge", "pr view",
               "pr list", "pr diff", "repo view", "api repos/o/r/pulls/123/reviews",
               "api repos/{owner}/{repo}/issues/123/comments",
               "api --method PATCH repos/{owner}/{repo}/issues/comments/")
    for c in calls(shim):
        assert c.startswith(allowed), f"non-allowlisted gh call: {c}"
    assert {a["action"] for a in gh.actions()} == {
        "pr_create", "pr_edit_body", "pr_comment", "pr_review_findings",
        "pr_merge_auto", "label_add", "pr_sticky_verdict"}


def test_review_findings_degrade_to_comment_on_api_422(shim, tmp_path, monkeypatch):
    monkeypatch.setenv("GH_SHIM_API_FAIL", "1")
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    gh.post_review_findings(123, "abc", "Code review",
                            [Finding("code-review", "A", "a.php", 3, "x", 85)])
    assert any(c.startswith("pr comment 123") for c in calls(shim))
    assert gh.actions()[-1]["action"] == "pr_review_findings"


DIFF_FIXTURE = """diff --git a/a.php b/a.php
--- a/a.php
+++ b/a.php
@@ -1,5 +1,6 @@
 l1
 l2
+l3
 l4
 l5
 l6
"""


@pytest.fixture()
def diff_file(tmp_path, monkeypatch):
    path = tmp_path / "pr.diff"
    path.write_text(DIFF_FIXTURE)
    monkeypatch.setenv("GH_SHIM_DIFF", str(path))
    return path


def _three_findings():
    return [
        Finding("code-review", "A", "a.php", 3, "important body", 90, label="important"),
        Finding("code-review", "A", "a.php", 5, "context body", 85, label="pre-existing"),
        Finding("code-review", "A", "a.php", 999, "offdiff body", 85, label="pre-existing"),
    ]


def _review_payload(log):
    return json.loads(open(str(log) + ".input").read())


def _pr_comments(log):
    return [c for c in calls(log) if c.startswith("pr comment 123")]


# LiveGh.post_review_findings: visible-diff findings go inline, off-diff pre-existing to one summary.
def test_review_findings_partition_inline_vs_summary_by_visibility(shim, tmp_path, diff_file):
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    gh.post_review_findings(123, "abc", "Code review", _three_findings())
    comments = _review_payload(shim)["comments"]
    assert [c["line"] for c in comments] == [3, 5]
    assert all(c["body"].startswith("<!-- label: ") for c in comments)
    prc = _pr_comments(shim)
    assert len(prc) == 1
    assert "a.php:999" in prc[0] and "**Pre-existing**" in prc[0]
    assert not any("a.php:3" in c for c in prc)


def test_review_findings_important_inline_survives_offdiff_preexisting(
        shim, tmp_path, diff_file, monkeypatch):
    monkeypatch.setenv("GH_SHIM_API_FAIL_ON_LINE", "999")
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    gh.post_review_findings(123, "abc", "Code review", _three_findings())
    assert any(c.startswith("api repos/o/r/pulls/123/reviews") for c in calls(shim))
    assert [c["line"] for c in _review_payload(shim)["comments"]] == [3, 5]
    # the Important thread was created; the only comment is the off-diff summary
    prc = _pr_comments(shim)
    assert len(prc) == 1 and "a.php:999" in prc[0] and "a.php:3" not in prc[0]


def test_review_findings_marker_is_first_line_after_truncation(shim, tmp_path, diff_file):
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    gh.post_review_findings(123, "abc", "Code review", [
        Finding("code-review", "A", "a.php", 3, "x" * 3000, 90, label="important")])
    body = _review_payload(shim)["comments"][0]["body"]
    lines = body.split("\n")
    assert len(body) <= 1000
    assert lines[0] == "<!-- label: important -->"
    assert lines[1] == "<!-- score: 90 -->"


def test_review_findings_without_diff_use_single_review_like_today(shim, tmp_path, monkeypatch):
    monkeypatch.delenv("GH_SHIM_DIFF", raising=False)
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    gh.post_review_findings(123, "abc", "Code review", _three_findings())
    assert [c["line"] for c in _review_payload(shim)["comments"]] == [3, 5, 999]
    assert _pr_comments(shim) == []


def test_summary_comment_renders_label_bug_class_and_instances(shim, tmp_path, diff_file):
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    gh.post_review_findings(123, "abc", "Code review", [
        Finding("code-review", "A", "a.php", 999, "offdiff body", 85,
                bug_class="unbounded-fetch", other_instances=["b.php:7"],
                label="pre-existing")])
    prc = _pr_comments(shim)
    assert len(prc) == 1
    assert "bug class: `unbounded-fetch`" in prc[0]
    assert "`b.php:7`" in prc[0]
    assert "**Pre-existing**" in prc[0]


def test_recording_gh_records_label_and_rendered_body(tmp_path):
    from harness.adapters.ghad import RecordingGh
    gh = RecordingGh(str(tmp_path / "out"), {})
    gh.post_review_findings(123, "abc", "Code review", [
        Finding("code-review", "A", "a.php", 3, "body", 90, label="nit")])
    rec = [a for a in gh.actions() if a["action"] == "pr_review_findings"][0]["findings"][0]
    assert rec["label"] == "nit"
    assert rec["body"].startswith("<!-- label: nit -->")


def test_live_reads_and_dep_state(shim, tmp_path):
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    assert gh.pr_exists() and gh.pr_number() == 123
    assert gh.pr_labels() == ["x"]
    assert gh.pr_state(999) == "MERGED"          # dep lookup via gh pr view N


def test_watch_live_parses_failures(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    gh = bindir / "gh"
    gh.write_text("#!/usr/bin/env bash\nprintf 'lint\\tpass\\t1m\\turl\\nphpunit\\tfail\\t2m\\turl\\n'\nexit 8\n")
    gh.chmod(gh.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    out = ciwatch.watch_live(str(tmp_path), 123, timeout=60)
    assert out.exit_code == 8 and out.failed == ["phpunit"]


# --- Phase 5: the sticky verdict comment -------------------------------------

def _sticky_body():
    return "REBASE=clean\nCI: pass\n\n### Merge digest\n**What changed:** x\n" \
           "\nREADY\n<!-- pr-ready-verdict -->\n"


def test_sticky_patches_an_existing_comment_and_returns_its_id(shim, tmp_path, monkeypatch):
    monkeypatch.setenv("GH_SHIM_STICKY_ID", "555")
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    assert gh.pr_sticky_verdict(123, _sticky_body()) == "555"
    log = calls(shim)
    assert any(c.startswith("api --method PATCH") for c in log), log
    assert gh.actions()[-1]["comment_id"] == "555"


def test_sticky_posts_a_new_comment_when_none_exists(shim, tmp_path):
    """No id on the read-back means nothing to PATCH; the helper falls back to pr comment.

    The harness reports "" so the runner can record sticky-post-failed — arming is
    deliberately unaffected either way.
    """
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    assert gh.pr_sticky_verdict(123, _sticky_body()) == ""
    assert any(c.startswith("pr comment 123 --body-file") for c in calls(shim))


def test_sticky_is_bounded_by_the_adapter_timeout(shim, tmp_path, monkeypatch):
    import time
    monkeypatch.setenv("GH_SHIM_SLEEP", "5")
    monkeypatch.setenv("GH_SHIM_STICKY_ID", "555")
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch", timeout=1)
    t0 = time.time()
    assert gh.pr_sticky_verdict(123, _sticky_body()) == ""
    assert time.time() - t0 < 3


@pytest.mark.parametrize("gh_cmd", ["/nonexistent", ""])
def test_sticky_strips_a_leaked_gh_cmd(shim, tmp_path, monkeypatch, gh_cmd):
    """A stub left in the parent env must never stand in for real `gh`."""
    monkeypatch.setenv("GH_CMD", gh_cmd)
    monkeypatch.setenv("GH_SHIM_STICKY_ID", "777")
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    assert gh.pr_sticky_verdict(123, _sticky_body()) == "777"
    assert calls(shim), "the PATH shim was never reached"


def test_sticky_body_is_written_to_the_run_dir(shim, tmp_path):
    out = tmp_path / "out"
    gh = LiveGh(str(out), str(tmp_path), "my-branch")
    gh.pr_sticky_verdict(123, _sticky_body())
    assert (out / "sticky-verdict-123.md").read_text() == _sticky_body()


def test_issue_create_parses_number_and_records_argv(shim, tmp_path):
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    n = gh.issue_create("Add index on email", "PR link\n\nDetail.", "maintenance")
    assert n == 456
    log = calls(shim)
    assert any("--repo a-jay85/IBL5-backlog" in c and "--label maintenance" in c
               and "--body" in c for c in log)
    acts = [a for a in gh.actions() if a["action"] == "issue_create"]
    assert acts[0]["executed"] is True and acts[0]["issue"] == 456


def test_issue_titles_returns_title_list(shim, tmp_path):
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    titles = gh.issue_titles("maintenance")
    assert titles == ["existing issue"]
    assert any("issue list" in c and "--label maintenance" in c
               for c in calls(shim))


def test_pr_sticky_body_reads_the_marked_comment(shim, tmp_path):
    """Uses the PATH shim to exercise the real _gh subprocess path.

    The shim answers `gh pr view <n> --json comments` with a marked comment body.
    """
    from harness.adapters.ghad import PR_STICKY_MARKER
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    body = gh.pr_sticky_body(123)
    assert body is not None
    assert PR_STICKY_MARKER in body


# --- code-review source cached from the one comments fetch ---------------------

CODE_REVIEW_FIXTURE = os.path.join(
    os.path.dirname(os.path.abspath(__file__)), os.pardir, os.pardir, os.pardir,
    "bin", "fixtures", "review-shared", "post-plan-sticky-pr3267-v1.json")


def _gh_returning(tmp_path, payload):
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    seen = []

    def fake_gh(*args, input_text=None):
        seen.append(args)
        return payload

    gh._gh = fake_gh
    return gh, seen


def test_pr_sticky_body_records_code_review_comment(tmp_path):
    with open(CODE_REVIEW_FIXTURE) as fh:
        raw = fh.read()
    gh, seen = _gh_returning(tmp_path, raw)
    body = gh.pr_sticky_body(3267)
    assert body == json.loads(raw)["comments"][-1]["body"]
    found = gh.code_review_seen(3267)
    assert found["scanned"] is True
    assert found["comment"]["created_at"] == "2026-10-16T06:51:18Z"
    assert found["comment"]["summary"] == "No issues found."
    # One fetch serves both reads: code_review_seen never calls gh.
    assert len(seen) == 1


def test_code_review_seen_ignores_reviewer_heading_and_gh_failure(tmp_path):
    from harness.state import HarnessError
    payload = json.dumps({"comments": [
        {"body": "## Code reviewer notes\n\nnits", "createdAt": "t", "url": "u"}]})
    gh, _ = _gh_returning(tmp_path, payload)
    gh.pr_sticky_body(7)
    assert gh.code_review_seen(7) == {"scanned": True, "comment": None}

    failing = LiveGh(str(tmp_path / "out2"), str(tmp_path), "my-branch")

    def boom(*args, input_text=None):
        raise HarnessError("gh", "down")

    failing._gh = boom
    assert failing.pr_sticky_body(7) is None
    assert failing.code_review_seen(7) is None
    assert failing.code_review_seen(8) is None
