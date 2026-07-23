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
echo "${args//$'\\n'/\\\\n}" >> "$GH_SHIM_LOG"
case "$1 $2" in
  "pr create") echo "https://github.com/o/r/pull/123" ;;
  "pr view")
    if [ "$3" = "--json" ] || [ "$4" = "--json" ]; then
      case "$*" in
        *"-q .state"*) echo "MERGED" ;;
        *) echo '{"number":123,"title":"chore: t","body":"b","headRefOid":"abc","labels":[{"name":"x"}],"state":"OPEN"}' ;;
      esac
    fi ;;
  "repo view") echo "o/r" ;;
  "api "*) [ "${GH_SHIM_API_FAIL:-}" = "1" ] && { echo "422" >&2; exit 1; } ;;
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
    allowed = ("pr create", "pr edit", "pr comment", "pr merge", "pr view",
               "repo view", "api repos/o/r/pulls/123/reviews")
    for c in calls(shim):
        assert c.startswith(allowed), f"non-allowlisted gh call: {c}"
    assert {a["action"] for a in gh.actions()} == {
        "pr_create", "pr_edit_body", "pr_comment", "pr_review_findings",
        "pr_merge_auto", "label_add"}


def test_review_findings_degrade_to_comment_on_api_422(shim, tmp_path, monkeypatch):
    monkeypatch.setenv("GH_SHIM_API_FAIL", "1")
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    gh.post_review_findings(123, "abc", "Code review",
                            [Finding("code-review", "A", "a.php", 3, "x", 85)])
    assert any(c.startswith("pr comment 123") for c in calls(shim))
    assert gh.actions()[-1]["action"] == "pr_review_findings"


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
