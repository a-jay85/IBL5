#!/usr/bin/env bash
set -euo pipefail
[ -z "${1:-}" ] && { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 1; }
# (?m) flag makes ^ match at the start of each line within the body string, not just the start of the whole string.
# #{1,6} matches any Markdown heading level (# through ######), so the detection is heading-level-agnostic.
#
# Column 5 is the ENVELOPE discriminator, and it is the machine-computed answer to
# "is this hit a real review artifact?" — a question SKILL.md previously left to judgement.
#   helper-envelope = the body carries one of the literal strings ONLY the shared posting
#                     helper emits (post_review_summary's collapsed <details> banner, or
#                     either "Found N issue(s)" line from post_review_findings). Nothing
#                     else in this repo writes them, so the hit needs no further reading.
#   freehand        = the heading matched but no helper envelope did. That is either a
#                     hand-composed review (real, but outside the envelope — backlog E24)
#                     or a comment merely QUOTING a review heading. This is the only class
#                     that still needs a read before PHASE_4B_RAN is recorded.
# A `comment` row is NOT weaker evidence than a `review` row: post_review_summary posts via
# `gh pr comment`, so the clean path lands as an issue comment and the /pulls/<N>/reviews
# endpoint is EMPTY BY CONSTRUCTION. Never read that emptiness as "no review ran".
gh api "repos/{owner}/{repo}/issues/$1/comments" --paginate \
  --jq '.[] | select((.body // "") | test("(?m)^#{1,6} +Code review\\b")) | "comment\t\(.id)\t\(.user.login)\t\(.created_at)\t\(if ((.body // "") | test("<summary>✅ .* — no issues found</summary>|Found [0-9]+ issue\\(s\\)")) then "helper-envelope" else "freehand" end)"' \
  || echo "PROBE-ERROR: issue-comments probe failed for PR #$1"
gh api "repos/{owner}/{repo}/pulls/$1/reviews" --paginate \
  --jq '.[] | select((.body // "") | test("(?m)^#{1,6} +Code review\\b")) | "review\t\(.id)\t\(.user.login)\t\(.submitted_at)\t\(if ((.body // "") | test("<summary>✅ .* — no issues found</summary>|Found [0-9]+ issue\\(s\\)")) then "helper-envelope" else "freehand" end)"' \
  || echo "PROBE-ERROR: reviews probe failed for PR #$1"
echo "PROBE-COMPLETE"
