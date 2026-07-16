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
    # In STUB_HUNT_MODE it instead impersonates the hunter agent: record the tier (ladder
    # order), optionally emit a usage-limit line, and write a per-tier/default hunt-result.json
    # into the (starved-env cwd) worktree — optionally dirtying a tracked file so the ship path's
    # empty-diff guard sees a real change.
    cat > "$STUB/bin/claude" <<'SH'
#!/usr/bin/env bash
echo "claude $*" >> "$STUB/claude.log"
echo "CLAUDE" >> "$STUB/calls.log"
touch "$STUB/claude.sentinel"
if [ "${STUB_HUNT_MODE:-0}" = 1 ]; then
    # Parse --model <id> to record ladder order.
    model=""; prev=""
    for a in "$@"; do [ "$prev" = "--model" ] && model="$a"; prev="$a"; done
    echo "$model" >> "$STUB/hunt-models.log"
    # Blanket usage-limit simulation → env-error line, no result written.
    if [ -f "$STUB/envfail-$model" ] || [ -f "$STUB/envfail" ]; then
        echo "API Error: 429 rate_limit_error"; exit 0
    fi
    mkdir -p "$PWD/.bug-pipeline"
    # Verdict source: a SEQUENCE (one token per hunter invocation, for per-attempt control) wins;
    # else per-tier result-<model>.json; else the blanket result.json. Sequence tokens:
    #   fixed | escalate | escalate!(=terminal) | blocked(=usage-limit line, writes no result)
    if [ -f "$STUB/verdicts" ]; then
        idx=$(( $(cat "$STUB/verdict-idx" 2>/dev/null || echo 0) + 1 )); echo "$idx" > "$STUB/verdict-idx"
        tok="$(awk -v n="$idx" 'NR==n{print;exit}' "$STUB/verdicts")"
        case "$tok" in
            blocked) echo "API Error: 429 rate_limit_error"; exit 0 ;;
            *!)      printf '{"verdict":"%s","terminal":true}'  "${tok%!}" > "$PWD/.bug-pipeline/hunt-result.json" ;;
            "")      : ;;   # sequence exhausted (defensive; a well-formed case never exhausts)
            *)       printf '{"verdict":"%s","terminal":false}' "$tok"     > "$PWD/.bug-pipeline/hunt-result.json" ;;
        esac
    elif [ -f "$STUB/result-$model.json" ]; then cp "$STUB/result-$model.json" "$PWD/.bug-pipeline/hunt-result.json"
    elif [ -f "$STUB/result.json" ];        then cp "$STUB/result.json"        "$PWD/.bug-pipeline/hunt-result.json"
    fi
    # Dirty a tracked file so ship_via_cron's empty-diff guard sees a real change.
    [ -f "$STUB/make-dirty" ] && printf 'hunter edit\n' >> "$PWD/hunted.txt"
    exit 0
fi
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
  *"pr list"*)
      [ "${GH_FAIL:-0}" = 1 ] && exit 1
      # Vary output by --head so a case can encode "this head has no PR, but the legacy
      # head does" (PR #1487 cutover). Falls back to the head-agnostic gh-pr-list.out.
      head=""; prev=""
      for a in "$@"; do [ "$prev" = "--head" ] && head="$a"; prev="$a"; done
      if [ -n "$head" ] && [ -f "$STUB/gh-pr-list-$head.out" ]; then
          cat "$STUB/gh-pr-list-$head.out"
      else
          cat "$STUB/gh-pr-list.out" 2>/dev/null || true   # driver's --jq already applied → emit the final value
      fi
      ;;
  *"pr view"*)      [ "${GH_FAIL:-0}" = 1 ] && exit 1; cat "$STUB/gh-pr-view.out" 2>/dev/null || true ;;
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

    # claim-next.php stub: log args; --resume=<id> reads a distinct file from the default claim
    # (so maybe_hunt's plain claim and resume_blocked_hunt's --resume claim don't collide).
    cat > "$STUB/scripts/claim-next.php" <<'SH'
#!/usr/bin/env bash
echo "claim-next $*" >> "$STUB/claim-next.log"
case "$*" in
  *--resume*) cat "$STUB/claim-next-resume.out" 2>/dev/null || true ;;
  *)          cat "$STUB/claim-next.out"        2>/dev/null || true ;;
esac
SH

    # list-pr-open.php stub: log; emit the case's pr_open rows (default: empty array).
    cat > "$STUB/scripts/list-pr-open.php" <<'SH'
#!/usr/bin/env bash
echo "list-pr-open $*" >> "$STUB/list-pr-open.log"
cat "$STUB/list-pr-open.out" 2>/dev/null || echo '[]'
SH

    # wt-new stub: create the worktree tree ($WT_BASE/<slug>/ibl5) as a real git repo with one
    # committed tracked file, so it starts CLEAN (empty-diff guard testable) and can be dirtied.
    cat > "$STUB/bin/wt-new" <<'SH'
#!/usr/bin/env bash
echo "wt-new $*" >> "$STUB/wt-new.log"
echo "WT_NEW $*" >> "$STUB/calls.log"
[ "${WT_NEW_FAIL:-0}" = 1 ] && exit 1
slug="$1"; root="$BUG_PIPELINE_WT_BASE/$slug"
mkdir -p "$root/ibl5"
git -C "$root" init -q 2>/dev/null
git -C "$root" config user.email t@t; git -C "$root" config user.name t
printf 'base\n' > "$root/ibl5/hunted.txt"
git -C "$root" add -A 2>/dev/null; git -C "$root" commit -qm base 2>/dev/null
exit 0
SH

    # post-plan-now stub: the trusted cron ship trigger — log only (no push).
    cat > "$STUB/bin/post-plan-now" <<'SH'
#!/usr/bin/env bash
echo "post-plan-now $*" >> "$STUB/post-plan-now.log"
echo "POST_PLAN_NOW $*" >> "$STUB/calls.log"
exit 0
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
    # Hunter seams (#5b) — point the driver at the stub worktree tooling + tree.
    export BUG_PIPELINE_WT_NEW_BIN="$STUB/bin/wt-new"
    export BUG_PIPELINE_POST_PLAN_NOW_BIN="$STUB/bin/post-plan-now"
    export BUG_PIPELINE_WT_BASE="$STUB/wt"
    export BUG_PIPELINE_HUNT_LOG="$STUB/hunt.log"
    export BUG_PIPELINE_CODE_REPO="test/code-fake"
    mkdir -p "$STUB/wt"
    trap 'rm -rf "$STUB"' EXIT
}

# bpt_reset — clear per-case logs / stub outputs.
bpt_reset() {
    rm -f "$STUB"/*.log "$STUB"/claude.sentinel "$STUB"/claude.out "$STUB"/claude.rc \
          "$STUB"/actionable.json "$STUB"/transcript.json "$STUB"/claim-next.out \
          "$STUB"/claim-next-resume.out "$STUB"/list-pr-open.out "$STUB"/plans/*.md \
          "$STUB"/result*.json "$STUB"/envfail* "$STUB"/make-dirty \
          "$STUB"/verdicts "$STUB"/verdict-idx \
          "$STUB"/gh-pr-list.out "$STUB"/gh-pr-list-*.out "$STUB"/gh-pr-view.out 2>/dev/null
    rm -rf "$STUB"/wt/* 2>/dev/null
    printf '[]' > "$STUB/actionable.json"
    unset GH_FAIL STUB_THREAD_ID STUB_MESSAGE_ID STUB_ISSUE_NUMBER WT_NEW_FAIL
}

bpt_set_actionable()  { printf '%s' "$1" > "$STUB/actionable.json"; }
# Wrap a result object in the claude --output-format json envelope.
bpt_set_claude_result() { printf '{"type":"result","is_error":false,"result":%s}' "$(printf '%s' "$1" | jq -Rs .)" > "$STUB/claude.out"; }
bpt_set_claude_raw()    { printf '%s' "$1" > "$STUB/claude.out"; }
bpt_set_claude_rc()     { printf '%s' "$1" > "$STUB/claude.rc"; }
bpt_set_transcript()    { printf '%s' "$1" > "$STUB/transcript.json"; }

# ── Hunter (#5b) per-case setters ─────────────────────────────────────────────
bpt_set_claim()        { printf '%s' "$1" > "$STUB/claim-next.out"; }
bpt_set_resume()       { printf '%s' "$1" > "$STUB/claim-next-resume.out"; }  # --resume=<id> claim
bpt_set_list_pr_open() { printf '%s' "$1" > "$STUB/list-pr-open.out"; }
bpt_set_result()       { printf '%s' "$1" > "$STUB/result.json"; }            # verdict for every tier
bpt_set_result_for()   { printf '%s' "$2" > "$STUB/result-$1.json"; }         # <model> <json>
bpt_set_verdicts()     { printf '%s\n' "$@" > "$STUB/verdicts"; }             # one token per hunter call
bpt_hunt_dirty()       { : > "$STUB/make-dirty"; }                            # ship path sees a real diff
bpt_envfail()          { : > "$STUB/envfail${1:+-$1}"; }                      # [model] usage-limit at a tier
bpt_set_gh_pr_list()   { printf '%s' "$1" > "$STUB/gh-pr-list.out"; }
bpt_set_gh_pr_list_for() { printf '%s' "$2" > "$STUB/gh-pr-list-$1.out"; }   # <head> <value>
bpt_set_gh_pr_view()   { printf '%s' "$1" > "$STUB/gh-pr-view.out"; }
# bpt_count <name> <want> <file>  — assert a stub log has exactly <want> non-blank lines
# (grep -c prints 0 and exits 1 on no-match, so read its stdout and default empty→0; no `|| echo`).
bpt_count() { local got; got="$(grep -c . "$3" 2>/dev/null)"; bpt_expect_eq "$1" "$2" "${got:-0}"; }

# bpt_run — run the real driver; sets BPT_RC.
# shellcheck disable=SC2034
bpt_run() { "$BPT_DRIVER" > "$STUB/tick.out" 2>&1; BPT_RC=$?; }
