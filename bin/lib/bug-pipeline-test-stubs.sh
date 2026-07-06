#!/usr/bin/env bash
# shellcheck shell=bash
#
# bin/lib/bug-pipeline-test-stubs.sh — shared stub scaffolding for the
# bin/test-bug-pipeline-* harnesses (mirrors the bin/test-adr-check convention:
# temp dir, untracked stub scripts, env seams). NOT a test itself.
#
# Each harness sources this, then per case: bpt_reset; set the actionable set +
# claude/curl/gh stub outputs; bpt_run; assert on the *.log files.

BPT_DRIVER="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/bug-pipeline-tick"

# FAILED / BPT_RC are read by the sourcing harnesses (shellcheck can't see that).
FAILED=0
bpt_ok()   { echo "ok: $1"; }
# shellcheck disable=SC2034  # FAILED consumed by the sourcing harness
bpt_fail() { echo "FAIL: $1"; FAILED=1; }

# bpt_expect_eq <name> <want> <got>
bpt_expect_eq() { if [ "$2" = "$3" ]; then bpt_ok "$1"; else bpt_fail "$1 — want [$2] got [$3]"; fi; }
# bpt_log_has <name> <file> <pattern>
bpt_log_has()   { if grep -qF -- "$3" "$1/$2" 2>/dev/null; then bpt_ok "$4"; else bpt_fail "$4 — '$3' absent from $2"; fi; }
bpt_log_lacks() { if grep -qF -- "$3" "$1/$2" 2>/dev/null; then bpt_fail "$4 — '$3' unexpectedly present in $2"; else bpt_ok "$4"; fi; }
bpt_file_absent() { if [ -e "$1" ]; then bpt_fail "$2 — $1 exists"; else bpt_ok "$2"; fi; }

# bpt_setup — one-time: build the stub tree, export env seams. Sets $STUB (root).
bpt_setup() {
    STUB="$(mktemp -d)"
    mkdir -p "$STUB/bin" "$STUB/scripts" "$STUB/plans"

    # php shim: exec the target script directly (stub .php files are executable bash).
    cat > "$STUB/bin/php" <<'SH'
#!/bin/sh
exec "$@"
SH

    # claude stub: log the call, drop a sentinel, emit whatever the case wrote to claude.out.
    cat > "$STUB/bin/claude" <<'SH'
#!/usr/bin/env bash
echo "claude $*" >> "$STUB/claude.log"
echo "CLAUDE" >> "$STUB/calls.log"
touch "$STUB/claude.sentinel"
# Simulate a successful /plan run: drop a new plan file into PLANS_DIR.
case "$*" in
  */plan\ *|*"/plan "*) [ "${STUB_MAKE_PLAN:-0}" = 1 ] && : > "$BUG_PIPELINE_PLANS_DIR/new-feature-plan.md" ;;
esac
[ -f "$STUB/claude.out" ] && cat "$STUB/claude.out"
exit "$(cat "$STUB/claude.rc" 2>/dev/null || echo 0)"
SH

    # curl stub: endpoint-aware bot responder; logs every call.
    cat > "$STUB/bin/curl" <<'SH'
#!/usr/bin/env bash
echo "curl $*" >> "$STUB/curl.log"
case "$*" in *create-thread*) echo "CREATE_THREAD" >> "$STUB/calls.log";; *post-to-thread*) echo "POST_TO_THREAD" >> "$STUB/calls.log";; *mention*) echo "MENTION" >> "$STUB/calls.log";; esac
case "$*" in
  *create-thread*)       echo '{"thread_id":"'"${STUB_THREAD_ID:-880000000000000001}"'"}' ;;
  *mention*)             echo '{"message_id":"'"${STUB_MESSAGE_ID:-1420098765432109876}"'"}' ;;
  *get-thread-messages*) cat "$STUB/transcript.json" 2>/dev/null || echo '{"messages":[]}' ;;
  *)                     echo '{}' ;;
esac
SH

    # gh stub: log; emit a fake issue URL on create.
    cat > "$STUB/bin/gh" <<'SH'
#!/usr/bin/env bash
echo "gh $*" >> "$STUB/gh.log"
echo "GH $*" >> "$STUB/calls.log"
printf '%s\n' "$@" >> "$STUB/gh.args.log"   # one arg per line — proves title is a single argv element
case "$*" in
  *"issue create"*) [ "${GH_FAIL:-0}" = 1 ] && exit 1; echo "https://github.com/${BUG_PIPELINE_ISSUE_REPO}/issues/${STUB_ISSUE_NUMBER:-4242}" ;;
  *) [ "${GH_FAIL:-0}" = 1 ] && exit 1; : ;;
esac
SH

    # list-active-conversations.php stub: emit the case's actionable set.
    cat > "$STUB/scripts/list-active-conversations.php" <<'SH'
#!/usr/bin/env bash
echo "DB_NAME=$DB_NAME" >> "$STUB/env.log"
cat "$STUB/actionable.json" 2>/dev/null || echo '[]'
SH

    # transition.php stub: log args, echo ok (no DB).
    cat > "$STUB/scripts/transition.php" <<'SH'
#!/usr/bin/env bash
echo "transition $*" >> "$STUB/transition.log"
echo "TRANSITION $*" >> "$STUB/calls.log"
echo '{"ok":true}'
SH

    chmod +x "$STUB"/bin/* "$STUB"/scripts/*.php

    export STUB
    export PHP_BIN="$STUB/bin/php"
    export CLAUDE_BIN="$STUB/bin/claude"
    export CURL_BIN="$STUB/bin/curl"
    export GH_BIN="$STUB/bin/gh"
    export BUG_PIPELINE_SCRIPTS_DIR="$STUB/scripts"
    export BUG_PIPELINE_PLANS_DIR="$STUB/plans"
    export BUG_PIPELINE_ISSUE_REPO="test/bugs-fake"
    export BUG_PIPELINE_APPROVER_ID="770000000000000007"
    export BUG_PIPELINE_IDLE_SECS=100
    export BUG_PIPELINE_BACKOFF_SECS=100
    trap 'rm -rf "$STUB"' EXIT
}

# bpt_reset — clear per-case logs / stub outputs.
bpt_reset() {
    rm -f "$STUB"/*.log "$STUB"/claude.sentinel "$STUB"/claude.out "$STUB"/claude.rc \
          "$STUB"/actionable.json "$STUB"/transcript.json "$STUB"/plans/*.md 2>/dev/null
    printf '[]' > "$STUB/actionable.json"
    unset GH_FAIL STUB_THREAD_ID STUB_MESSAGE_ID STUB_ISSUE_NUMBER
}

bpt_set_actionable()  { printf '%s' "$1" > "$STUB/actionable.json"; }
# Wrap a result object in the claude --output-format json envelope.
bpt_set_claude_result() { printf '{"type":"result","is_error":false,"result":%s}' "$(printf '%s' "$1" | jq -Rs .)" > "$STUB/claude.out"; }
bpt_set_claude_raw()    { printf '%s' "$1" > "$STUB/claude.out"; }
bpt_set_claude_rc()     { printf '%s' "$1" > "$STUB/claude.rc"; }
bpt_set_transcript()    { printf '%s' "$1" > "$STUB/transcript.json"; }

# bpt_run — run the real driver; sets BPT_RC.
# shellcheck disable=SC2034
bpt_run() { "$BPT_DRIVER" > "$STUB/tick.out" 2>&1; BPT_RC=$?; }
