"""Unit tests for harness/workflow_scope.py: which workflow diff lines change a send."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import workflow_scope as ws
from harness.merge_danger import _NOTIFY_TEXT_RE

P = ".github/workflows/deploy.yml"
HEADER = f"diff --git a/{P} b/{P}\n--- a/{P}\n+++ b/{P}\n"

_MAIN_LIKE = """\
name: Deploy
on:
  push:
    branches: [master]
  schedule:
    - cron: '0 6 * * *'
concurrency:
  group: deploy-${{ github.ref }}
# deploy host comes from repo vars
env:
  DEPLOY_HOST: example.org
jobs:
  setup:
    runs-on: ubuntu-26.04
    if: github.event_name == 'push'
    steps:
      - run: echo setup
  pre-flight:
    needs: setup
    runs-on: ubuntu-26.04
    if: github.repository == 'a-jay85/IBL5'
    steps:
      - run: echo ok
  build-and-deploy:
    needs: pre-flight
    runs-on: ubuntu-26.04
    if: >-
      github.ref == 'refs/heads/master'
      && !contains(github.event.head_commit.message, '[skip deploy]')
    timeout-minutes: 20
    steps:
      - name: Flush OPcache
        if: success()
        run: |
          curl -fsS "https://$DEPLOY_HOST/opcache-reset.php"
          echo flushed
  lint:
    runs-on: ubuntu-26.04
    steps:
      - name: Lint
        if: always()
        run: echo lint
        shell: bash
  notify-deploy-failure:
    runs-on: ubuntu-26.04
    timeout-minutes: 5
    needs: [pre-flight, build-and-deploy]
    if: ${{ always() && needs.build-and-deploy.result == 'failure' }}
    steps:
      - name: Build Discord message
        id: msg
        run: |
          # summary line for the DM
          echo "text=Deploy failed on $DEPLOY_HOST" >> "$GITHUB_OUTPUT"
      - name: Send Discord DM
        uses: ./.github/actions/notify-discord
        with:
          webhook-url: ${{ secrets.DISCORD_DEV_WEBHOOK_URL }}
          message: ${{ steps.msg.outputs.text }}
"""

_ENV_ONLY = """\
name: Nightly
on:
  schedule:
    - cron: '0 3 * * *'
env:
  DISCORD_WEBHOOK: ${{ secrets.DISCORD_WEBHOOK }}
jobs:
  report:
    runs-on: ubuntu-26.04
    steps:
      - run: bin/report --post
"""


def _line_of(text, snippet):
    """The 1-based number of the one line containing `snippet`."""
    hits = [i for i, line in enumerate(text.split("\n"), 1) if snippet in line]
    assert len(hits) == 1, f"{snippet!r} matched lines {hits}"
    return hits[0]


def _edit(text, snippet, new):
    """(candidate with that line replaced, its diff section)."""
    n = _line_of(text, snippet)
    lines = text.split("\n")
    old = lines[n - 1]
    lines[n - 1] = new
    return "\n".join(lines), f"{HEADER}@@ -{n} +{n} @@\n-{old}\n+{new}\n"


def _insert(text, after_snippet, new):
    """(candidate with `new` inserted after that line, its diff section)."""
    n = _line_of(text, after_snippet)
    lines = text.split("\n")
    lines.insert(n, new)
    return "\n".join(lines), f"{HEADER}@@ -{n},0 +{n + 1} @@\n+{new}\n"


def _delete_at(text, n):
    lines = text.split("\n")
    old = lines.pop(n - 1)
    return "\n".join(lines), f"{HEADER}@@ -{n} +{n - 1},0 @@\n-{old}\n"


def _delete(text, snippet):
    """(candidate without that line, its diff section)."""
    return _delete_at(text, _line_of(text, snippet))


def _run(candidate, section):
    return ws.workflow_send_change(candidate, section, _NOTIFY_TEXT_RE)


def test_added_notify_step_fires():
    candidate, section = _insert(_MAIN_LIKE, "run: echo lint", "      - uses: ./.github/actions/notify-discord")
    assert _run(candidate, section) == "notify-text"


def test_removed_notify_step_fires():
    assert _run(*_delete(_MAIN_LIKE, "uses: ./.github/actions/notify-discord")) == "notify-text"


def test_edited_discord_secret_line_fires():
    candidate, section = _edit(_MAIN_LIKE, "webhook-url:",
                               "          webhook-url: ${{ secrets.DISCORD_OPS_WEBHOOK_URL }}")
    assert _run(candidate, section) == "notify-text"


def test_notify_text_in_comment_fires():
    candidate, section = _insert(_MAIN_LIKE, "name: Deploy", "# pings notify-discord on failure")
    assert _run(candidate, section) == "notify-text"


def test_on_cron_edit_fires():
    candidate, section = _edit(_MAIN_LIKE, "- cron:", "    - cron: '0 7 * * *'")
    assert _run(candidate, section) == "top-level:on"


def test_top_level_env_edit_fires():
    candidate, section = _edit(_MAIN_LIKE, "DEPLOY_HOST: example.org", "  DEPLOY_HOST: example.net")
    assert _run(candidate, section) == "top-level:env"


def test_sender_job_if_edit_fires():
    candidate, section = _edit(_MAIN_LIKE, "if: ${{ always()",
                               "    if: ${{ needs.build-and-deploy.result == 'failure' }}")
    assert _run(candidate, section) == "sender-job:notify-deploy-failure"


def test_build_discord_message_body_edit_fires():
    candidate, section = _edit(_MAIN_LIKE, 'echo "text=Deploy failed',
                               '          echo "text=Deploy broke on $DEPLOY_HOST" >> "$GITHUB_OUTPUT"')
    assert _run(candidate, section) == "sender-job:notify-deploy-failure"


def test_needed_job_if_edit_fires():
    candidate, section = _edit(_MAIN_LIKE, "if: github.repository", "    if: github.repository == 'a-jay85/other'")
    assert _run(candidate, section) == "needed-job:pre-flight:if"


def test_needed_job_multiline_if_continuation_fires():
    candidate, section = _edit(_MAIN_LIKE, "&& !contains(",
                               "      && !contains(github.event.head_commit.message, '[no deploy]')")
    assert _run(candidate, section) == "needed-job:build-and-deploy:if"


def test_transitively_needed_job_if_fires():
    candidate, section = _edit(_MAIN_LIKE, "if: github.event_name", "    if: github.event_name != 'schedule'")
    assert _run(candidate, section) == "needed-job:setup:if"


def test_timeout_minutes_in_sender_job_is_two_way():
    candidate, section = _edit(_MAIN_LIKE, "timeout-minutes: 5", "    timeout-minutes: 10")
    assert _run(candidate, section) is None


def test_step_if_in_non_sender_job_is_two_way():
    assert _run(*_edit(_MAIN_LIKE, "if: always()", "        if: success()")) is None
    assert _run(*_edit(_MAIN_LIKE, "if: success()", "        if: always()")) is None


def test_comment_line_edit_is_two_way():
    candidate, section = _edit(_MAIN_LIKE, "# deploy host comes from repo vars", "# host comes from repo vars")
    assert _run(candidate, section) is None


def test_hash_line_inside_sender_run_block_fires():
    candidate, section = _edit(_MAIN_LIKE, "# summary line for the DM", "          # new summary for the DM")
    assert _run(candidate, section) == "sender-job:notify-deploy-failure"


def test_removed_runs_on_between_needs_and_if_is_two_way():
    n = _line_of(_MAIN_LIKE, "needs: pre-flight") + 1
    assert "runs-on" in _MAIN_LIKE.split("\n")[n - 1]
    assert _run(*_delete_at(_MAIN_LIKE, n)) is None


def test_removed_step_line_at_sender_boundary_fires():
    assert _run(*_delete(_MAIN_LIKE, "shell: bash")) == "job-id:notify-deploy-failure"


def test_no_named_sender_job_makes_every_job_a_sender():
    candidate, section = _edit(_ENV_ONLY, "- run: bin/report --post", "      - run: bin/report --quiet")
    assert _run(candidate, section) == "sender-job:report"


def test_missing_section_fires():
    assert _run(_MAIN_LIKE, None) == "no-diff-section"


def test_garbage_hunk_header_fires():
    assert _run(_MAIN_LIKE, f"{HEADER}@@ garbage @@\n-a\n+b\n") == "unparseable-hunk"


def test_trailing_garbage_after_hunk_fires():
    candidate, section = _edit(_MAIN_LIKE, "timeout-minutes: 5", "    timeout-minutes: 10")
    assert _run(candidate, section + 'diff --git "a/x y" "b/x y"\n') == "unparseable-hunk"


def test_unknown_hunk_tag_fires():
    n = _line_of(_MAIN_LIKE, "name: Deploy")
    assert _run(_MAIN_LIKE, f"{HEADER}@@ -{n} +{n} @@\n?name: Deploy\n") == "unparseable-hunk"


def test_misaligned_context_fires():
    assert _run(_MAIN_LIKE, f"{HEADER}@@ -1,2 +1,2 @@\n name: Wrong\n-on:\n+on:\n") == "misaligned"


def test_misaligned_added_line_fires():
    n = _line_of(_MAIN_LIKE, "timeout-minutes: 5")
    assert _run(_MAIN_LIKE, f"{HEADER}@@ -{n} +{n} @@\n-    timeout-minutes: 5\n+    timeout-minutes: 99\n") == "misaligned"


def test_truncated_hunk_fires():
    line_8 = _MAIN_LIKE.split("\n")[7]
    assert _run(_MAIN_LIKE, f"{HEADER}@@ -8,3 +8,3 @@\n {line_8}\n") == "truncated-hunk"


@pytest.mark.parametrize("candidate", [
    _MAIN_LIKE.replace("  lint:", "\tlint:"),
    "name: Deploy\non:\n  push:\n",
    _MAIN_LIKE.replace("  lint:", "  lint: &lintjob"),
    _MAIN_LIKE.replace("    runs-on: ubuntu-26.04\n    steps:\n      - name: Lint",
                       "    <<: *base\n    steps:\n      - name: Lint"),
    _MAIN_LIKE.replace("  lint:", "---\n  lint:"),
], ids=["tab-indent", "no-jobs", "anchor", "merge-key", "mid-file-document-marker"])
def test_unreadable_candidate_fires(candidate):
    assert _run(candidate, f"{HEADER}@@ -1 +1 @@\n-name: Old\n+name: New\n") == "unreadable-candidate"


def test_binary_section_fires():
    assert _run(_MAIN_LIKE, f"diff --git a/{P} b/{P}\nBinary files a/{P} and b/{P} differ\n") == "unparseable-section"


def test_pure_rename_section_is_two_way():
    section = f"diff --git a/old.yml b/{P}\nsimilarity index 100%\nrename from old.yml\nrename to {P}\n"
    assert _run(_MAIN_LIKE, section) is None


def test_new_file_section_fires():
    lines = _MAIN_LIKE.split("\n")[:-1]
    added = "\n".join(f"+{line}" for line in lines)
    section = (f"diff --git a/{P} b/{P}\nnew file mode 100644\n--- /dev/null\n+++ b/{P}\n"
               f"@@ -0,0 +1,{len(lines)} @@\n{added}\n")
    assert _run(_MAIN_LIKE, section)
