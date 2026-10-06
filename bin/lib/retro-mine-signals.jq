# bin/lib/retro-mine-signals.jq — per-transcript extractor and redactor for the
# bin/retro-mine tune-up stage. Run with `jq -R -s -c -f` over one root-level
# session jsonl; emits at most one thread object. With `--arg mode text` it only
# redacts its raw stdin (the outbound pass over DM and issue bodies).
#
# Args: --argjson cutoff (epoch) --argjson heavy (tokens) --argjson maxex
#       --argjson exch (excerpt chars) --arg marker --arg mode (thread|text|text-known)

# The only redactor for transcript text. Order matters: specific token shapes
# first, then emails and home paths, then any long opaque run. redact_known
# stops before the opaque-run rule. Report tables and issue bodies carry
# signatures, lowercased slugs that can run past 32 characters, and the next
# week's dedupe reads them back. Their source text was already redacted.
def redact_known:
  gsub("https://(canary\\.|ptb\\.)?discord(app)?\\.com/api/webhooks/[^\\s\"')]+"; "[redacted]")
  | gsub("sk-ant-[A-Za-z0-9_-]+"; "[redacted]")
  | gsub("gh[po]_[A-Za-z0-9]+"; "[redacted]")
  | gsub("github_pat_[A-Za-z0-9_]+"; "[redacted]")
  | gsub("xox[abp]-[A-Za-z0-9-]+"; "[redacted]")
  | gsub("AKIA[0-9A-Z]{16}"; "[redacted]")
  | gsub("Bearer [A-Za-z0-9._~+/=-]+"; "[redacted]")
  | gsub("[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z][A-Za-z]+"; "[redacted]")
  | gsub("/Users/[^/]+/"; "~/");

def redact: redact_known | gsub("[A-Za-z0-9_-]{32,}"; "[redacted]");

def cut: redact | .[0:$exch];

def ts_epoch:
  if (.timestamp | type) == "string"
  then (.timestamp | sub("\\.[0-9]+Z$"; "Z") | try fromdateiso8601 catch null)
  else null end;

def ctext:
  (.message.content // null) as $c
  | if ($c | type) == "string" then $c
    elif ($c | type) == "array" and ($c | length) > 0 and ($c[0].type == "text")
    then ($c | map(select(.type == "text") | .text) | join(" "))
    else null end;

def is_prompt:
  .type == "user" and ((.isMeta // false) | not) and ((.isCompactSummary // false) | not)
  and (ctext as $t | $t != null and (($t | startswith("<")) | not));

def trtext:
  (.message.content[0].content // "") as $c
  | if ($c | type) == "string" then $c
    elif ($c | type) == "array" then ($c | map(.text? // "") | join(" "))
    else "" end;

def is_deny:
  .type == "user" and ((.message.content | type) == "array")
  and ((.message.content | length) > 0)
  and (.message.content[0].type == "tool_result")
  and (.message.content[0].is_error == true)
  and (trtext | test("^(PreToolUse:|<tool_use_error>Blocked:|pre-push-adr-hook:)"));

def correction_re:
  "^(no\\b|nope|stop\\b|wrong|wait\\b|actually\\b|undo|revert|don'?t|do not|that'?s (not|wrong)|why (did|are) you|you (should|shouldn'?t|didn'?t|missed|forgot))";

def tokens:
  (.message.usage.input_tokens // 0) + (.message.usage.cache_creation_input_tokens // 0)
  + (.message.usage.output_tokens // 0);

if $mode == "text" then redact
elif $mode == "text-known" then redact_known
else
  split("\n") | map(fromjson? // empty | select(type == "object")) as $lines
  | (($lines | map(select(.entrypoint != null)) | .[0]) // {}) as $e
  | ($e.entrypoint // "cli") as $ep
  | (if $ep == "sdk-cli" then "headless"
     elif $ep == "sdk-ts" then (if (($e.cwd // "") | contains("postplan-llm-")) then "headless" else "interactive" end)
     else "interactive" end) as $kind
  | ($lines | map(select(is_prompt)) | .[0]) as $fp
  | (if $fp == null then "" else ($fp | ctext) end) as $first
  | if ($first | startswith($marker)) then empty
    else
      [$lines[] | (ts_epoch) as $t | select($t != null and $t >= $cutoff)] as $win
      | if ($win | length) == 0 then empty
        else
          ([$win[]
            | if is_prompt and $kind == "interactive" and (. != $fp) and (ctext | test(correction_re; "i"))
                then {type: "correction", ts: .timestamp, text: ctext}
              elif is_deny then {type: "hook_deny", ts: .timestamp, text: trtext}
              elif .type == "system" and .subtype == "stop_hook_summary" and (.hasOutput == true)
                   and ((.hookErrors // []) | length) > 0
                then {type: "stop_block", ts: .timestamp, text: (.hookErrors[0] | if type == "string" then . else tojson end)}
              elif .type == "system" and .subtype == "compact_boundary"
                then {type: "compaction", ts: .timestamp,
                      text: ("trigger=" + ((.compactMetadata.trigger // "") | tostring)
                             + " pre=" + ((.compactMetadata.preTokens // 0) | tostring))}
              else empty end]
           + ([$win[] | select(.type == "assistant" and (.message.id // null) != null and (.message.usage // null) != null)]
              | group_by(.message.id) | map(.[0])
              | map(select(tokens >= $heavy) | {type: "heavy_turn", ts: .timestamp, text: ("tokens=" + (tokens | tostring))})))
          | sort_by(.ts) | .[0:$maxex] | map(.text |= cut) as $events
          | {sid: ($e.sessionId // ($lines[0].sessionId // "unknown")), kind: $kind,
             cwd: (($e.cwd // "") | cut), branch: (($e.gitBranch // "") | cut),
             first: ($first | cut), events: $events}
        end
    end
end
