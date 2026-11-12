"""Unit tests for harness/external_scope.py, the changed-hunk test behind trigger 2's
non-workflow external paths."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.external_scope import external_send_change
from test_merge_danger_external_replay import (
    ACTION_YML,
    ACTION_YML_BODY,
    DM,
    FILTER_NEAR_MISS_BODY,
    MAIL,
    MAIL_DOCBLOCK_BODY,
    MAIL_README,
    MAIL_README_BODY,
    PRESERVE_ASSIGN_BODY,
    REMOTE_DUMP_BODY,
    SYNC,
    _section,
)


def _head(path):
    return f"diff --git a/{path} b/{path}\nindex 1111111..2222222 100755\n--- a/{path}\n+++ b/{path}\n"


# Fail-closed shapes.

def test_missing_section_returns_reason():
    assert external_send_change(DM, None) == "missing-section"


@pytest.mark.parametrize("section", [
    f"diff --git a/{DM} b/{DM}\nold mode 100755\nnew mode 100644\n",
    f"diff --git a/{DM} b/{DM}\nindex 1111111..2222222\nBinary files a/{DM} and b/{DM} differ\n",
])
def test_section_without_hunk_is_unparseable(section):
    assert external_send_change(DM, section) == "unparseable-section"


@pytest.mark.parametrize("section", [
    _head(DM) + "@@ bad @@\n-a\n+b\n",
    _section(DM, 1, [" a", "-b", "+c"]) + "garbage\n",
])
def test_bad_hunk_header_is_unparseable(section):
    assert external_send_change(DM, section) == "unparseable-hunk"


def test_short_hunk_is_truncated():
    section = _head(DM) + "@@ -1,5 +1,5 @@\n a\n-b\n+c\n"
    assert external_send_change(DM, section) == "truncated-hunk"


# Cancellation.

def test_herestring_rewrite_cancels():
    body = [
        ' REGULAR_TABLES=""',
        " for name in $ALL_TABLES; do",
        '-    if [ -n "$GEN_RE" ] && echo "$name" | grep -qE "^($GEN_RE)$"; then',
        '+    if [ -n "$GEN_RE" ] && grep -qE "^($GEN_RE)$" <<< "$name"; then',
        "         continue",
        "     fi",
    ]
    assert external_send_change(SYNC, _section(SYNC, 364, body)) is None


def test_near_miss_rewrite_with_new_pattern_fires():
    assert external_send_change(SYNC, _section(SYNC, 365, FILTER_NEAR_MISS_BODY)) == "sync-change"


def test_reordered_block_does_not_cancel():
    body = [
        " # table they create is missing.",
        '-IGNORE_ARGS=""',
        '-PRESERVE_EXISTING=""',
        '+PRESERVE_EXISTING=""',
        '+IGNORE_ARGS=""',
        " for t in $PRESERVE_TABLES; do",
    ]
    assert external_send_change(SYNC, _section(SYNC, 326, body)) == "sync-change"


def test_unequal_block_does_not_cancel():
    body = [
        ' PRESERVE_EXISTING=""',
        '-IGNORE_ARGS=""',
        '+IGNORE_ARGS=""',
        '+IGNORE_ARGS="$IGNORE_ARGS --skip-triggers"',
        " for t in $PRESERVE_TABLES; do",
    ]
    assert external_send_change(SYNC, _section(SYNC, 327, body)) == "sync-change"


# Continuation join.

def test_continued_command_joins_context_head():
    assert external_send_change(SYNC, _section(SYNC, 270, REMOTE_DUMP_BODY)) == "sync-change"


def test_continuation_reaching_hunk_top_fails_closed():
    body = [
        "         --opt-a \\",
        "         --opt-b \\",
        '-        --opt-c "$@"',
        '+        --opt-d "$@"',
    ]
    assert external_send_change(SYNC, _section(SYNC, 280, body)) == "truncated-continuation"


# Sync tokens.

def test_local_container_command_is_quiet():
    body = [
        ' PRESERVE_EXISTING=""',
        ' IGNORE_ARGS=""',
        '+PRE_SYNC_VERSIONS="$(db_cmd "$DB_CONTAINER" mariadb "$DB_DEFAULT_NAME" -N -B \\',
        '+    -e "SELECT version FROM schema_migrations /* pre-sync */" 2>/dev/null || true)"',
        " for t in $PRESERVE_TABLES; do",
    ]
    assert external_send_change(SYNC, _section(SYNC, 327, body)) is None


def test_preserve_tables_read_is_quiet():
    body = [
        ' echo "Replaying migrations that touch preserved tables..."',
        '+if ! replay_preserved_migrations "$DB_CONTAINER" "$DB_DEFAULT_NAME" "$MIG_DIR" \\',
        '+        "$PRE_SYNC_VERSIONS" $PRESERVE_TABLES; then',
        " fi",
    ]
    assert external_send_change(SYNC, _section(SYNC, 538, body)) is None


def test_preserve_tables_assignment_fires():
    assert external_send_change(SYNC, _section(SYNC, 327, PRESERVE_ASSIGN_BODY)) == "sync-change"


# Comment syntax per file type.

def test_shebang_change_fires():
    body = ["-#!/usr/bin/env bash", "+#!/bin/sh", " set -euo pipefail"]
    assert external_send_change(DM, _section(DM, 1, body)) == "send-change"


def test_shell_comment_is_quiet():
    body = [" #!/usr/bin/env bash", "-# Send a notice.", "+# Send one owner notice.", " set -euo pipefail"]
    assert external_send_change(DM, _section(DM, 1, body)) is None


def test_php_attribute_fires():
    body = [
        "+    #[\\Override]",
        "     public function send(string $to, string $subject, string $body, string $fromEmail, string $fromName = ''): bool",
    ]
    assert external_send_change(MAIL, _section(MAIL, 30, body)) == "send-change"


def test_php_docblock_is_quiet():
    assert external_send_change(MAIL, _section(MAIL, 20, MAIL_DOCBLOCK_BODY)) is None


def test_yaml_comment_is_quiet():
    assert external_send_change(ACTION_YML, _section(ACTION_YML, 1, ACTION_YML_BODY)) is None


def test_markdown_under_prefix_is_quiet():
    assert external_send_change(MAIL_README, _section(MAIL_README, 1, MAIL_README_BODY)) is None


def test_unknown_extension_line_fires():
    path = "ibl5/classes/Mail/config.json"
    assert external_send_change(path, _section(path, 1, ['-{"a": 1}', '+{"a": 2}'])) == "send-change"
