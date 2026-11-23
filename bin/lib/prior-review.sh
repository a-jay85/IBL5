#!/usr/bin/env bash
# Shared rule: does a prior own "Code review" already cover the PR's current head?
#
# prior_review_covers_head PR [GH_CMD]
#   exit 0  a review/comment by the current gh login covers headRefOid
#   exit 1  not covered, or anything failed (fails toward running the review)
#
# A GitHub review covers the head when its commit.oid equals headRefOid. An
# issue comment covers it only when its body carries the hidden reviewed-head
# marker for that head (written by post_review_summary and harness/review.py).
# Legacy unmarked comments never cover a head.
#
# Callers: bin/pr-review-now (embedded in the runner with `declare -f`) and
# .claude/skills/pr-review/SKILL.md Step 1 (d) (sources this file).
# This file is SOURCED, not executed.

prior_review_covers_head() {
    local pr="$1" gh="${2:-gh}" me n prog
    me="$("$gh" api user --jq .login 2>/dev/null)" || return 1
    case "$me" in ''|*[!A-Za-z0-9-]*) return 1 ;; esac
    prog='(.headRefOid // "") as $h
      | if ($h | test("\\A[0-9a-f]{40}\\z")) then
          [ (.comments[]?, .reviews[]?)
            | select(.author.login == "'"$me"'")
            | (.body // "") as $b
            | select($b | test("\\A\\s*(#{1,6} +Code review|<details><summary>✅ Code review — no issues found</summary>)"))
            | select((.commit.oid // "") == $h
                     or ($b | contains("<!-- reviewed-head: " + $h + " -->"))) ]
          | length
        else 0 end'
    n="$("$gh" pr view "$pr" --json comments,reviews,headRefOid --jq "$prog" 2>/dev/null)" || return 1
    case "$n" in ''|*[!0-9]*) return 1 ;; esac
    [ "$n" -gt 0 ]
}
