# opus-trial-cost.jq: per-task cost stats for one window of automouse-pricer output.
# <!-- no-adr: read-only measurement query for the inline-edit trial in .claude/rules/work-triage.md; no new architectural constraint -->
# Trial review date 2026-11-23. Usage:
#   bin/lib/automouse-pricer --index --since FROM \
#     | jq -s --arg from FROM --arg to TO -f bin/lib/opus-trial-cost.jq
# A task is one IBL5-worktrees proj dir. Its cost is the sum of `cost` over its
# sessions. A task belongs to the window holding the UTC date of its earliest
# first_ts, half-open [from, to). n < 20 tasks is an insufficient sample,
# the same MIN_N as bin/measure-delegate-cost.
def r4: if . == null then null else ((. * 10000) | round) / 10000 end;
def median:
  if length == 0 then null
  elif length % 2 == 1 then .[(length - 1) / 2]
  else (.[length / 2 - 1] + .[length / 2]) / 2 end;
def p90: if length == 0 then null else .[((length * 0.9) | ceil) - 1] end;
if ($from | test("^[0-9]{4}-[0-9]{2}-[0-9]{2}$") | not)
   or ($to | test("^[0-9]{4}-[0-9]{2}-[0-9]{2}$") | not)
then error("from and to must be YYYY-MM-DD")
elif $from >= $to then error("from must be earlier than to")
else
  [ .[]
    | select(.first_ts != null)
    | {task: ((.proj_dir // "") | split("/") | last), cost: (.cost // 0),
       day: .first_ts[0:10]}
    | select(.task | contains("IBL5-worktrees")) ]
  | group_by(.task)
  | map({cost: (map(.cost) | add), day: (map(.day) | min)})
  | map(select(.day >= $from and .day < $to))
  | (map(.cost) | sort) as $c
  | ($c | length) as $n
  | {from: $from, to: $to, n: $n,
     median: ($c | median | r4),
     mean: (if $n == 0 then null else ($c | add) / $n end | r4),
     p90: ($c | p90 | r4),
     note: (if $n < 20 then "insufficient-sample" else null end)}
end
