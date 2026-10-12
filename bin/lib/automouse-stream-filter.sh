#!/bin/bash
# Usage: claude ... --output-format stream-json | automouse-stream-filter <heartbeat_file> [<cmdpid_file>] [<slug>] [<phase>] [<failsig_file>]
set -euo pipefail

HEARTBEAT_FILE="${1:?Usage: automouse-stream-filter <heartbeat_file> [<cmdpid_file>] [<slug>] [<phase>] [<failsig_file>]}"
CMDPID_FILE="${2:-}"
SLUG="${3:-}"
PHASE="${4:-}"
FAILSIG_FILE="${5:-}"
TOOL_COUNT=0
PEAK_CTX=0
TURN_COUNT=1
START_EPOCH=$(date +%s)
# Last non-empty assistant *text* block seen on the stream. The `result` event's
# `.result` field is a snapshot the harness takes when the main loop FIRST yields,
# so for any run that hands off to a background `Agent` (every /post-plan run with
# parallel review agents) that snapshot is the "waiting for the review agents" line
# rather than the run's real closing message. Assistant text is otherwise never
# logged, so that stale line became the log's last human-readable output and made
# fully-completed runs look like they had exited before their agents returned.
# Track the real final message ourselves and print that instead. Sub-agent messages
# never reach the parent stream, so this only ever holds the main loop's own text.
LAST_TEXT=""

# Line prefix: the elapsed timestamp, plus the active plan slug when one was
# passed. Concurrent runners interleave into per-day logs, so the slug column
# right after the timestamp says which plan each line belongs to. When a phase
# (impl/postplan) is passed it is appended as `<slug>/<phase>`, so the two
# phases of one plan are distinguishable within the interleaved day log.
line_prefix() {
    local now diff tag
    now=$(date +%s)
    diff=$(( now - START_EPOCH ))
    tag="$SLUG"
    [ -n "$SLUG" ] && [ -n "$PHASE" ] && tag="$SLUG/$PHASE"
    if [ -n "$tag" ]; then
        printf '[%02d:%02d:%02d] %s' $(( diff / 3600 )) $(( (diff % 3600) / 60 )) $(( diff % 60 )) "$tag"
    else
        printf '[%02d:%02d:%02d]' $(( diff / 3600 )) $(( (diff % 3600) / 60 )) $(( diff % 60 ))
    fi
}

# Failure-signature capture (ADR-0160). Active
# only when a 5th arg is given. The record goes to a SIDE FILE, never stdout:
# bin/automouse/run's env-error scan reads the log, and a tool result quoting
# an API error must not trip it. Raw record format is the input contract of
# bin/lib/automouse-failure-signature.
FAILSIG_RECENT_RESULTS=5   # an error followed by MORE than this many tool results was recovered from
FAILSIG_USE_IDS=()
FAILSIG_USE_NAMES=()
FAILSIG_USE_CMDS=()
FAILSIG_RESULTS_SINCE_ERR=0
if [ -n "$FAILSIG_FILE" ]; then rm -f "$FAILSIG_FILE" "$FAILSIG_FILE.tmp" 2>/dev/null || true; fi

failsig_note_uses() {   # $1 = assistant event line
    local tsv id name cmd
    tsv=$(printf '%s' "$1" | jq -r '
        .message.content[]? | select(type=="object" and .type=="tool_use")
        | [ (.id // ""), (.name // "?"), ((.input.command // "") | tostring | split("\n")[0]) ]
        | @tsv' 2>/dev/null) || return 0
    while IFS=$'\t' read -r id name cmd; do
        [ -n "$id" ] || continue
        FAILSIG_USE_IDS+=("$id"); FAILSIG_USE_NAMES+=("$name"); FAILSIG_USE_CMDS+=("$cmd")
    done <<< "$tsv"
    return 0
}

failsig_note_results() {   # $1 = user event line
    local n out id text i name cmd
    n=$(printf '%s' "$1" | jq -r '[.message.content[]? | select(type=="object" and .type=="tool_result")] | length' 2>/dev/null) || return 0
    out=$(printf '%s' "$1" | jq -r '
        [ .message.content[]? | select(type=="object" and .type=="tool_result" and .is_error == true) ]
        | last // empty
        | (.tool_use_id // ""),
          ( .content
            | if type == "string" then .
              elif type == "array" then ([ .[] | select(type=="object" and .type=="text") | .text ] | join("\n"))
              else "" end )' 2>/dev/null) || return 0
    if [ -z "$out" ]; then
        FAILSIG_RESULTS_SINCE_ERR=$(( FAILSIG_RESULTS_SINCE_ERR + ${n:-0} ))
        return 0
    fi
    id=${out%%$'\n'*}
    case "$out" in *$'\n'*) text=${out#*$'\n'} ;; *) text="" ;; esac
    name="?"; cmd=""
    i=${#FAILSIG_USE_IDS[@]}
    while [ "$i" -gt 0 ]; do
        i=$(( i - 1 ))
        if [ "${FAILSIG_USE_IDS[$i]}" = "$id" ]; then
            name=${FAILSIG_USE_NAMES[$i]}; cmd=${FAILSIG_USE_CMDS[$i]}; break
        fi
    done
    {
        printf 'tool: %s\ncommand: %s\nerror:\n' "$name" "$cmd"
        printf '%s\n' "$text" | head -c 4000
    } > "$FAILSIG_FILE.tmp" 2>/dev/null && mv -f "$FAILSIG_FILE.tmp" "$FAILSIG_FILE" 2>/dev/null
    FAILSIG_RESULTS_SINCE_ERR=0
    return 0
}

# An error the agent recovered from (followed by more than
# FAILSIG_RECENT_RESULTS further tool results) is not the failure the attempt
# ended on: drop it, so the signature is empty and never matches (fail-open).
failsig_finalize() {
    [ -n "$FAILSIG_FILE" ] || return 0
    if [ "$FAILSIG_RESULTS_SINCE_ERR" -gt "$FAILSIG_RECENT_RESULTS" ]; then
        rm -f "$FAILSIG_FILE" 2>/dev/null
    fi
    return 0
}

while IFS= read -r line; do
    date +%s > "$HEARTBEAT_FILE"

    ev_type=$(printf '%s' "$line" | jq -r '.type // empty' 2>/dev/null) || continue
    case "$ev_type" in
        assistant)
            tool_name=$(printf '%s' "$line" | jq -r '
                .message.content[]
                | select(.type=="tool_use")
                | .name // empty
            ' 2>/dev/null) || true
            if [ -n "$tool_name" ]; then
                TOOL_COUNT=$(( TOOL_COUNT + 1 ))
                printf '%s tool: %s (#%d, turn %d)\n' "$(line_prefix)" "$tool_name" "$TOOL_COUNT" "$TURN_COUNT"
            fi
            if [ -n "$FAILSIG_FILE" ]; then failsig_note_uses "$line" || true; fi
            # Text blocks only — `thinking` blocks are not the run's answer, and a
            # whitespace-only block must not clobber a real preceding message.
            msg_text=$(printf '%s' "$line" | jq -r '
                [ .message.content[]? | select(.type=="text") | .text ]
                | join("\n")
            ' 2>/dev/null) || msg_text=""
            case "$msg_text" in
                *[![:space:]]*) LAST_TEXT="$msg_text" ;;
            esac
            # Mirror bin/lib/automouse-pricer's peak rule exactly — the two must agree
            # because run's `provenance=unknown` fallback reads THIS number into the
            # same report column. iterations[] REPLACE the top-level usage (which is
            # their sum); advisor_message iterations ran against a separate window.
            ctx=$(printf '%s' "$line" | jq -r '
                def occ: ((.input_tokens // 0)|tonumber)
                       + ((.cache_read_input_tokens // 0)|tonumber)
                       + ((.cache_creation_input_tokens // 0)|tonumber);
                (.message.usage // {}) as $u
                | ($u.iterations // []) as $its
                | ([ $its[] | select(.type != "advisor_message") ]) as $cand
                | if   ($cand | length) > 0 then [ $cand[] | occ ] | max
                  elif ($its  | length) > 0 then [ $its[]  | occ ] | max
                  else ($u | occ) end
            ' 2>/dev/null) || ctx=0
            if [ -n "$ctx" ] && [ "$ctx" -gt "${PEAK_CTX:-0}" ] 2>/dev/null; then
                PEAK_CTX=$ctx
            fi
            ;;
        user)
            TURN_COUNT=$(( TURN_COUNT + 1 ))
            if [ -n "$FAILSIG_FILE" ]; then failsig_note_results "$line" || true; fi
            ;;
        system)
            # Claude Code emits a `system`/`compact_boundary` event when context is
            # compacted (auto at the autoCompactWindow threshold, or manual /compact).
            # Before this arm these fell through silently — a long impl/postplan phase
            # could compact mid-run with nothing in the log to show it happened. We
            # record it as a discrete COMPACTION line so a run's context pressure is
            # visible alongside the tool/exit lines. Match the subtype with a glob:
            # the exact-string was a moving target (docs say `compact_boundary`), so a
            # `*compact*` guard records the event even if the subtype label shifts,
            # which is the whole failure mode we're fixing. The field names differ
            # between the stored transcript (camelCase `compactMetadata.preTokens`) and
            # the documented wire schema (snake_case `compact_metadata.pre_tokens`), so
            # extract across both casings and fall back to `?`.
            ev_subtype=$(printf '%s' "$line" | jq -r '.subtype // empty' 2>/dev/null) || true
            case "$ev_subtype" in
                *compact*)
                    trigger=$(printf '%s' "$line" | jq -r '
                        .compact_metadata.trigger // .compactMetadata.trigger // .trigger // "?"
                    ' 2>/dev/null) || true
                    pre_tokens=$(printf '%s' "$line" | jq -r '
                        .compact_metadata.pre_tokens // .compactMetadata.preTokens
                        // .pre_tokens // .preTokens // "?"
                    ' 2>/dev/null) || true
                    printf '%s COMPACTION: subtype=%s trigger=%s pre_tokens=%s\n' \
                        "$(line_prefix)" "$ev_subtype" "$trigger" "$pre_tokens"
                    ;;
            esac
            ;;
        result)
            result_text=$(printf '%s' "$line" | jq -r '.result // empty' 2>/dev/null) || true
            if [ -n "$LAST_TEXT" ]; then
                printf '%s\n' "$LAST_TEXT"
            elif [ -n "$result_text" ]; then
                printf '%s\n' "$result_text"
            fi
            stop_reason=$(printf '%s' "$line" | jq -r '.stop_reason // "unknown"' 2>/dev/null) || true
            num_turns=$(printf '%s' "$line" | jq -r '.num_turns // "?"' 2>/dev/null) || true
            duration_ms=$(printf '%s' "$line" | jq -r '.duration_ms // "?"' 2>/dev/null) || true
            cost_usd=$(printf '%s' "$line" | jq -r '.total_cost_usd // "?"' 2>/dev/null) || true
            in_tokens=$(printf '%s' "$line" | jq -r '.usage.input_tokens // "?"' 2>/dev/null) || true
            out_tokens=$(printf '%s' "$line" | jq -r '.usage.output_tokens // "?"' 2>/dev/null) || true
            cache_tokens=$(printf '%s' "$line" | jq -r '.usage.cache_read_input_tokens // "?"' 2>/dev/null) || true
            cache_write=$(printf '%s' "$line" | jq -r '.usage.cache_write_input_tokens // .usage.cache_creation_input_tokens // "?"' 2>/dev/null) || true
            session_id=$(printf '%s' "$line" | jq -r '.session_id // "?"' 2>/dev/null) || true
            printf '%s exit: stop_reason=%s turns=%s tools=%d duration=%sms cost=$%s in=%s out=%s cache=%s cache_write=%s peak_ctx=%d session_id=%s\n' \
                "$(line_prefix)" "$stop_reason" "$num_turns" "$TOOL_COUNT" "$duration_ms" \
                "$cost_usd" "$in_tokens" "$out_tokens" "$cache_tokens" "$cache_write" "${PEAK_CTX:-0}" \
                "$session_id"
            failsig_finalize || true
            # result is the final stream-json event — terminate claude and exit
            if [ -n "$CMDPID_FILE" ]; then
                _target_pid=$(cat "$CMDPID_FILE" 2>/dev/null) || true
                if [ -n "${_target_pid:-}" ] && kill -0 "$_target_pid" 2>/dev/null; then
                    kill -TERM "$_target_pid" 2>/dev/null || true
                fi
            fi
            exit 0
            ;;
    esac
done
failsig_finalize || true
