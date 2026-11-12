"""Replays for merge-danger trigger 2's non-workflow external paths.

PR #3804 and #3830 touched `bin/db-sync-prod` without changing what the sync reads from prod,
yet the floor held both one-way on the path touch alone. The fixtures are the
`bin/db-sync-prod` sections of each PR diff, captured once with `gh pr diff`. Tests never call
`gh`. The synthetic sections pin the true positives and the fail-closed shapes.
"""
import os
import sys
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import merge_danger as md
from harness.gate_backtest import changes_from_unified_diff
from test_merge_danger_fixtures import _none, _replay_text

FIX = Path(__file__).parent / "fixtures" / "merge_danger"
SYNC = "bin/db-sync-prod"
DM = "bin/discord-dm"
MAIL = "ibl5/classes/Mail/MailService.php"
PROMOTE = ".github/workflows/promote-to-production.yml"


def _load(name):
    return (FIX / name).read_text(encoding="utf-8")


def _section(path, start, body, meta=None):
    """One diff section with hunk counts computed from `body` (lines carry their ' '/'-'/'+' tag)."""
    old_n = sum(1 for ln in body if ln[:1] in (" ", "-"))
    new_n = sum(1 for ln in body if ln[:1] in (" ", "+"))
    head = meta if meta is not None else f"index 1111111..2222222 100755\n--- a/{path}\n+++ b/{path}\n"
    return (f"diff --git a/{path} b/{path}\n{head}"
            f"@@ -{start},{old_n} +{start},{new_n} @@\n" + "\n".join(body) + "\n")


def _assert_one_way(d, trigger):
    assert d.door == "one-way"
    assert trigger in d.triggers
    assert trigger in d.why


def _assert_two_way_quiet(d):
    assert d.door == "two-way"
    assert d.blast != "external"
    assert "external-path:" not in d.why
    assert not any(t.startswith("external-path:") for t in d.triggers)


# Shared by the missing-section fail-closed test and the comment-only quiet test.
_DM_COMMENT_SECTION = _section(DM, 2, [
    "-# discord-dm — send an owner notice to the #dev webhook, or a Discord DM through IBLbot, with retries.",
    "+# discord-dm sends an owner notice to the #dev webhook, or a Discord DM through IBLbot, with retries.",
    " #",
])

# Bodies shared with tests/test_external_scope.py.
REMOTE_DUMP_BODY = [
    " remote_dump() {",
    '     MYSQL_PWD="$REMOTE_PASSWORD" mariadb-dump \\',
    '         -h "$REMOTE_HOST" -P "$REMOTE_PORT" \\',
    '-        --skip-lock-tables "$@"',
    '+        --single-transaction "$@"',
    " }",
]
GEN_RE_BODY = [
    ' GEN_RE=""',
    " for t in $GENERATED_TABLES; do",
    '-    GEN_RE="${GEN_RE:+$GEN_RE|}$t"',
    '+    GEN_RE="${GEN_RE:+$GEN_RE|}${t}_v2"',
    " done",
]
FILTER_NEAR_MISS_BODY = [
    " for name in $ALL_TABLES; do",
    '-    if [ -n "$GEN_RE" ] && echo "$name" | grep -qE "^($GEN_RE)$"; then',
    '+    if [ -n "$GEN_RE" ] && grep -qE "($GEN_RE)" <<< "$name"; then',
    "         continue",
]
SSH_BODY = [
    ' REGULAR_TABLES=""',
    '+ssh iblhoops.net "cat /home/iblhoops/app/.env" > /dev/null',
    " for name in $ALL_TABLES; do",
]
PRESERVE_ASSIGN_BODY = [
    ' PRESERVE_EXISTING=""',
    '+PRESERVE_TABLES="$PRESERVE_TABLES ibl_debug_log"',
    ' IGNORE_ARGS=""',
]
MAIL_DOCBLOCK_BODY = [
    "     /**",
    "-     * Send an email.",
    "+     * Send one email through the configured transport.",
    "      */",
]
MAIL_README = "ibl5/classes/Mail/README.md"
MAIL_README_BODY = [" # Mail", "-Sends mail.", "+Sends mail through PHP's mail()."]
ACTION_YML = ".github/actions/notify-discord/action.yml"
ACTION_YML_BODY = [" name: notify-discord", "-# Posts a message.", "+# Posts one message to Discord.", " inputs:"]


def test_fixtures_have_no_bare_blank_lines():
    for f in sorted(FIX.glob("*.diff")):
        in_hunk = False
        for line in f.read_text(encoding="utf-8").splitlines():
            if line.startswith("@@"):
                in_hunk = True
                continue
            if line.startswith("diff --git "):
                in_hunk = False
            if in_hunk:
                assert line != "", f"bare blank line inside a hunk of {f.name}"


# Sync true positives.

def test_sync_dump_flag_in_continued_command_is_one_way():
    _assert_one_way(_replay_text(_section(SYNC, 270, REMOTE_DUMP_BODY)), f"external-path:{SYNC}")


def test_sync_gen_re_assignment_is_one_way():
    _assert_one_way(_replay_text(_section(SYNC, 340, GEN_RE_BODY)), f"external-path:{SYNC}")


def test_sync_filter_pattern_change_is_one_way():
    _assert_one_way(_replay_text(_section(SYNC, 365, FILTER_NEAR_MISS_BODY)), f"external-path:{SYNC}")


def test_sync_new_ssh_command_is_one_way():
    _assert_one_way(_replay_text(_section(SYNC, 364, SSH_BODY)), f"external-path:{SYNC}")


def test_sync_preserve_tables_assignment_is_one_way():
    _assert_one_way(_replay_text(_section(SYNC, 327, PRESERVE_ASSIGN_BODY)), f"external-path:{SYNC}")


# Send true positives.

def test_send_discord_dm_new_send_call_is_one_way():
    body = [
        '     if [ "$USE_WEBHOOK" = 1 ]; then send_webhook_one "$chunk" "$IDX"; else send_one "$chunk"; fi',
        '+    send_one "copy: $chunk"',
        " done",
    ]
    _assert_one_way(_replay_text(_section(DM, 200, body)), f"external-path:{DM}")


def test_send_discord_dm_curl_flag_is_one_way():
    body = [
        '         curl -sS -o "$WH_BODY" -w \'%{http_code}\' \\',
        "-            -X POST -H 'Content-Type: application/json' --data-binary \"@$WH_PAYLOAD\" \\",
        "+            -X PUT -H 'Content-Type: application/json' --data-binary \"@$WH_PAYLOAD\" \\",
        '             "$WEBHOOK_URL"',
    ]
    _assert_one_way(_replay_text(_section(DM, 150, body)), f"external-path:{DM}")


def test_send_mail_transport_line_is_one_way():
    body = [
        "     {",
        "-        return mail($to, $subject, $body, $headers);",
        '+        return mail($to, $subject, $body . "\\n--\\nIBL", $headers);',
        "     }",
    ]
    _assert_one_way(_replay_text(_section(MAIL, 40, body)), f"external-path:{MAIL}")


def test_send_mail_php_attribute_is_one_way():
    body = [
        "+    #[\\Override]",
        "     public function send(string $to, string $subject, string $body, string $fromEmail, string $fromName = ''): bool",
        "     {",
    ]
    _assert_one_way(_replay_text(_section(MAIL, 30, body)), f"external-path:{MAIL}")


def test_send_notify_action_curl_is_one_way():
    path = ".github/actions/notify-discord/send.sh"
    body = [' MSG_FILE="$1"', '+curl -sS -X POST --data-binary @"$MSG_FILE" "$URL"', " exit 0"]
    _assert_one_way(_replay_text(_section(path, 10, body)), f"external-path:{path}")


# Fail-closed shapes.

def test_failclosed_comment_only_delete_is_one_way():
    sec = (f"diff --git a/{DM} b/{DM}\ndeleted file mode 100755\nindex 1111111..0000000\n"
           f"--- a/{DM}\n+++ /dev/null\n@@ -1,2 +0,0 @@\n-# one\n-# two\n")
    _assert_one_way(_replay_text(sec), f"external-path:{DM}")


def test_failclosed_rename_away_is_one_way():
    new = "bin/legacy-discord-dm"
    sec = (f"diff --git a/{DM} b/{new}\nsimilarity index 100%\n"
           f"rename from {DM}\nrename to {new}\n")
    d = _replay_text(sec)
    assert d.door == "one-way"
    assert any(t.startswith("external-path:") for t in d.triggers)


def test_failclosed_rename_into_mail_is_one_way():
    old, new = "ibl5/classes/Legacy/Sender.php", "ibl5/classes/Mail/Sender.php"
    sec = (f"diff --git a/{old} b/{new}\nsimilarity index 96%\n"
           f"rename from {old}\nrename to {new}\n--- a/{old}\n+++ b/{new}\n"
           "@@ -3,3 +3,3 @@\n namespace Mail;\n-// Legacy sender.\n+// Mail sender.\n class Sender\n")
    _assert_one_way(_replay_text(sec), f"external-path:{new}")


def test_failclosed_missing_section_is_one_way():
    d = md.derive_merge_danger(changes_from_unified_diff(_DM_COMMENT_SECTION), "", [], _none)
    _assert_one_way(d, f"external-path:{DM}")


def test_failclosed_bad_hunk_header_is_one_way():
    sec = (f"diff --git a/{DM} b/{DM}\nindex 1111111..2222222 100755\n--- a/{DM}\n+++ b/{DM}\n"
           "@@ bad @@\n-# one\n+# two\n")
    _assert_one_way(_replay_text(sec), f"external-path:{DM}")


# Recorded forks.

def test_promote_workflow_comment_only_is_one_way():
    body = [" name: Promote to production", "-# Promote master to prod.", "+# Promote the master branch to prod.", " on:"]
    _assert_one_way(_replay_text(_section(PROMOTE, 1, body)), f"external-path:{PROMOTE}")


def test_pin_pr3813_mutation_still_notify_workflow():
    sec = _load("pr3813-mutation-yml.diff")
    yml = _load("pr3813-mutation.yml")
    d = md.derive_merge_danger(changes_from_unified_diff(sec), sec, [],
                               lambda p: yml if p == ".github/workflows/mutation.yml" else None)
    _assert_one_way(d, "notify-workflow:.github/workflows/mutation.yml")


def test_llm_one_way_survives_two_way_floor():
    body = ("**Merge danger:** Door: one-way · Blast radius: localized · Why: a reviewer judged "
            "the sync rewrite risky enough to wait for the owner.")
    floor = _replay_text(_section("tools/postplan-harness/README.md", 1, [" a", "-b", "+c"]))
    assert floor.door == "two-way"
    assert md.classify_with_floor(body, floor) == "ONE-WAY"
