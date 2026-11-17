"""Unit tests for harness/external_scope.py, the changed-hunk test behind trigger 2's
non-workflow external paths."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.external_scope import (
    _R_DM_FLOW,
    _R_DM_REDIRECT,
    _R_DM_TOKEN,
    _R_DM_UNREADABLE,
    _R_SEND,
    _R_TRUNCATED,
    external_send_change,
)
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
    _load,
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
        r"+    #[\Override]",
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


# Multi-line quoted strings.

def test_string_cut_by_hunk_top_fails_closed():
    from test_merge_danger_external_replay import ALL_TABLES_CUT_BODY
    assert external_send_change(SYNC, _section(SYNC, 314, ALL_TABLES_CUT_BODY)) == "truncated-continuation"


def test_line_inside_string_joins_its_opener():
    body = [
        '     ALL_TABLES=$(remote_query "',
        "         SELECT table_name",
        "-        FROM information_schema.tables",
        "+        FROM information_schema.views",
        "         WHERE table_schema='$REMOTE_DATABASE'",
        "           AND engine IS NOT NULL",
        "         ORDER BY table_name;",
        '     ")',
    ]
    assert external_send_change(SYNC, _section(SYNC, 313, body)) == "sync-change"


def test_line_inside_local_string_is_quiet():
    body = [
        ' db_cmd "$DB_CONTAINER" mariadb "$DB_DEFAULT_NAME" -e "',
        "-    SELECT migration FROM migrations WHERE migration LIKE '%.sql';",
        "+    SELECT migration FROM migrations WHERE migration LIKE '%.sql' ORDER BY migration;",
        ' " 2> >(db_strip_warnings >&2) || true',
    ]
    assert external_send_change(SYNC, _section(SYNC, 523, body)) is None


# bin/discord-dm: a changed line fires only when it can change who gets a message or what is sent.

_DM_SEND_CONTEXT = '     if [ "$USE_WEBHOOK" = 1 ]; then send_webhook_one "$chunk" "$IDX"; else send_one "$chunk"; fi'


def _dm(start, *changed):
    """A discord-dm section whose changed lines sit between two context lines."""
    return _section(DM, start, ['     say "sending"', *changed, " "])


def test_dm_pr4009_fixture_is_quiet():
    assert external_send_change(DM, _load("pr4009-discord-dm.diff")) is None


def test_dm_webhook_file_default_change_is_one_way():
    sec = _dm(70,
              '-WEBHOOK_FILE="${DISCORD_DM_WEBHOOK_FILE:-$HOME/.iblbot-dev-webhook-url}"',
              '+WEBHOOK_FILE="${DISCORD_DM_WEBHOOK_FILE:-$HOME/.iblbot-prod-webhook-url}"')
    assert external_send_change(DM, sec) == _R_SEND


def test_dm_route_arm_change_is_one_way():
    sec = _dm(90,
              '-        --route)       ROUTE="${2:-}"; shift 2 ;;',
              '+        --route)       ROUTE="/discordDM"; shift 2 ;;')
    assert external_send_change(DM, sec) == _R_SEND


def test_dm_chunk_cap_change_is_one_way():
    assert external_send_change(DM, _dm(182, "-CAP=1900", "+CAP=500")) == _R_SEND


def test_dm_guard_added_before_unchanged_send_assignment_is_one_way():
    sec = _dm(120, '-USE_WEBHOOK=0; WEBHOOK_URL=""', '+false && USE_WEBHOOK=0; WEBHOOK_URL=""')
    assert external_send_change(DM, sec) is not None


def test_dm_curl_in_new_log_function_is_one_way():
    sec = _dm(350, "+log_sent() {", '+    curl -s -d "$FULL_MSG" "$LOG_URL" >/dev/null 2>&1', "+}")
    assert external_send_change(DM, sec) == _R_DM_TOKEN


def test_dm_eval_is_one_way():
    assert external_send_change(DM, _dm(350, '+eval "$DISCORD_DM_EXTRA"')) == _R_DM_TOKEN


def test_dm_dot_source_is_one_way():
    assert external_send_change(DM, _dm(350, '+. "$HOME/.discord-dm-rc"')) == _R_SEND


def test_dm_guarded_exit_deleted_is_one_way():
    assert external_send_change(DM, _dm(350, '-[ "$DELIVERED" = 1 ] && exit 0')) == _R_DM_FLOW


def test_dm_guarded_exit_test_changed_is_one_way():
    sec = _dm(350, '-[ "$DELIVERED" = 1 ] && exit 0',
              '+if [ "$DELIVERED" = 0 ]; then', "+    exit 0", "+fi")
    assert external_send_change(DM, sec) == _R_DM_FLOW


def test_dm_guarded_exit_rewrite_keeps_test_and_exit_is_quiet():
    sec = _dm(350, '-[ "$DELIVERED" = 1 ] && exit 0',
              '+if [ "$DELIVERED" = 1 ]; then', "+    exit 0", "+fi")
    assert external_send_change(DM, sec) is None


def test_dm_if_wrapping_context_send_is_one_way():
    sec = _section(DM, 340, [" for chunk in \"${CHUNKS[@]}\"; do", "+    if false; then",
                             _DM_SEND_CONTEXT, "+    fi", " done"])
    assert external_send_change(DM, sec) == _R_DM_FLOW
    # Unwrapping a send runs it again. Only the removed-side balance walk sees this split `if`.
    unwrap = _section(DM, 340, [" for chunk in \"${CHUNKS[@]}\"; do", "-    if false; then",
                                _DM_SEND_CONTEXT, "-    fi", " done"])
    assert external_send_change(DM, unwrap) == _R_DM_FLOW


def test_dm_redirect_into_spool_is_one_way():
    sec = _dm(350, """+    printf '%s\\n' "$FULL_MSG" >> "$SPOOL\"""")
    assert external_send_change(DM, sec) == _R_DM_REDIRECT


def test_dm_redirect_into_local_log_is_quiet():
    sec = _dm(350, """+    printf '%s\\n' "$FULL_MSG" >> "$log\"""")
    assert external_send_change(DM, sec) is None


def test_dm_existing_send_function_call_is_one_way():
    assert external_send_change(DM, _dm(350, '+chunk_message "$FULL_MSG"')) == _R_SEND


def test_dm_redefine_existing_function_is_one_way():
    sec = _dm(350, "+send_one() {", """+    printf '%s\\n' "$1\"""", "+}")
    assert external_send_change(DM, sec) == _R_SEND


def test_dm_send_var_prefix_name_is_quiet():
    assert external_send_change(DM, _dm(350, '+    TO_LOG="$TO"')) is None


def test_dm_quoted_send_word_fails_closed():
    assert external_send_change(DM, _dm(350, '+    say "retry with curl later"')) == _R_DM_TOKEN


def test_dm_unbalanced_quote_fails_closed():
    sec = _dm(350, """+    printf '%s\\n' "unterminated""")
    assert external_send_change(DM, sec) == _R_DM_UNREADABLE


def test_dm_truncated_hunk_fails_closed():
    sec = (_head(DM) + "@@ -170,4 +170,5 @@\n"
           '     say "sending"\n+    TO_LOG="$TO"\n \n')
    assert external_send_change(DM, sec) == _R_TRUNCATED


@pytest.mark.parametrize("line", [
    '+source "$HOME/x"',
    "+v=`date`",
    "+exec 3<>/dev/tcp/h/80",
    '+wget -q "$u"',
    "+cat <(printf x)",
])
def test_dm_deny_tokens_fire(line):
    assert external_send_change(DM, _dm(350, line)) == _R_DM_TOKEN


@pytest.mark.parametrize("line", [
    '+    sed -i "s/.*/123/" "$HOME/.iblbot-dm-id"',
    "+    sed -n 'w /tmp/x' \"$f\"",
    "+    sed 'e' \"$f\"",
    "+    sed 's/^/x/e'",
    "+    sed -n 'p' \"$f\" sed 'p'",
])
def test_dm_sed_that_writes_or_runs_fires(line):
    assert external_send_change(DM, _dm(350, line)) == _R_SEND


@pytest.mark.parametrize("line", [
    "+    sed -n '2,55p' \"$0\"",
    "+    sed 's/^# \\{0,1\\}//'",
    "+    sed '$d'",
])
def test_dm_read_only_sed_is_quiet(line):
    assert external_send_change(DM, _dm(350, line)) is None


@pytest.mark.parametrize("line", [
    "+    echo $((TO=5))",
    '+    echo "$((TO=5))"',
    "+    echo ${arr[TO=5]}",
    "+    x[TO=5]=1",
    "+    : $((n++))",
    '+    [[ 1 -eq TO=5 ]]',
])
def test_dm_arithmetic_assignment_fires(line):
    assert external_send_change(DM, _dm(350, line)) == _R_SEND


@pytest.mark.parametrize("old,new", [
    ("-        i=$(( i + 1 ))", "+        i=$(( i + 2 ))"),
    ("-    i=1", "+    i=2"),
    ("-        n=$(( n + 1 ))", "+        n=$(( n + 5 ))"),
    ("-    total=1", "+    total=5"),
])
def test_dm_counter_change_is_one_way(old, new):
    assert external_send_change(DM, _dm(200, old, new)) == _R_SEND


def test_dm_redirect_through_other_variable_fires():
    sec = _dm(350, '+    f="$HOME/.iblbot-dm-id"', """+    printf '%s\\n' 1 > "$f\"""")
    assert external_send_change(DM, sec) == _R_DM_REDIRECT


def test_dm_negated_guarded_exit_is_one_way():
    sec = _dm(350, '-[ "$DELIVERED" = 1 ] && exit 0', '+! [ "$DELIVERED" = 1 ] && exit 0')
    assert external_send_change(DM, sec) == _R_DM_FLOW


def test_dm_function_shadowing_builtin_is_one_way():
    assert external_send_change(DM, _dm(350, "+exit() { :; }")) == _R_SEND


def test_dm_log_alias_of_spool_is_one_way():
    sec = _dm(350, '+    log="$SPOOL"', """+    printf '%s\\n' x >> "$log\"""")
    assert external_send_change(DM, sec) == _R_SEND


def test_dm_printf_attached_v_flag_is_one_way():
    assert external_send_change(DM, _dm(350, "+    printf -vTO '%s' 123")) is not None


def test_dm_local_nameref_to_send_var_is_one_way():
    sec = _dm(350, "+    local -n r=TO", '+    r="$OTHER"')
    assert external_send_change(DM, sec) is not None


def test_dm_comment_trailing_backslash_does_not_hide_next_line():
    sec = _dm(350, "+    : # note \\", "+    TO=evil")
    assert external_send_change(DM, sec) is not None


def test_dm_exit_in_new_function_is_one_way():
    sec = _dm(150, "+early() {", "+    exit 0", "+}", "+early")
    assert external_send_change(DM, sec) == _R_DM_FLOW


def test_dm_break_in_new_function_without_own_loop_is_one_way():
    sec = _dm(150, "+stop() {", "+    break", "+}")
    assert external_send_change(DM, sec) == _R_DM_FLOW


def test_dm_return_and_own_loop_break_in_new_function_are_quiet():
    sec = _dm(150, "+h() {", "+    for w in a b; do", "+        break", "+    done",
              "+    return 0", "+}", "+h")
    assert external_send_change(DM, sec) is None


_DM_MSG_GUARD = '[ -n "$MSG" ] || { echo "discord-dm: no message" >&2; exit 2; }'


def test_dm_or_flipped_to_and_before_braced_exit_is_one_way():
    sec = _dm(108, "-" + _DM_MSG_GUARD, "+" + _DM_MSG_GUARD.replace("||", "&&"))
    assert external_send_change(DM, sec) == _R_DM_FLOW


def test_dm_braced_exit_guard_kept_is_quiet():
    sec = _dm(108, "-" + _DM_MSG_GUARD, "+" + _DM_MSG_GUARD.replace("no message", "empty message"))
    assert external_send_change(DM, sec) is None


def test_dm_exit_moved_into_else_arm_is_one_way():
    sec = _dm(350, '-[ "$DELIVERED" = 1 ] && exit 0',
              '+if [ "$DELIVERED" = 1 ]; then :; else exit 0; fi')
    assert external_send_change(DM, sec) == _R_DM_FLOW


def test_dm_one_line_if_exit_split_from_its_test_is_one_way():
    sec = _dm(350, '-if [ "$DELIVERED" = 1 ]; then exit 0; fi', '+[ "$DELIVERED" = 1 ]', "+exit 0")
    assert external_send_change(DM, sec) == _R_DM_FLOW


def test_dm_one_line_if_exit_kept_is_quiet():
    sec = _dm(350, '-if [ "$DELIVERED" = 1 ]; then exit 0; fi', '+[ "$DELIVERED" = 1 ] && exit 0')
    assert external_send_change(DM, sec) is None
