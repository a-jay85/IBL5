# shellcheck shell=bash
#
# bin/lib/human-signoff-classifier.sh — single source of truth for the
# .github/workflows/human-signoff.yml feature-PR sign-off classifier (ADR-0062),
# sourced by BOTH the workflow (pull_request + merge_group branches) and the
# regression harness bin/test-human-signoff-classifier so the logic cannot drift.
#
# Usage: source "$(dirname "$0")/lib/human-signoff-classifier.sh"
# This file is SOURCED, not executed: no `set -euo pipefail` at file scope.
# It IS, however, sourced INTO a `set -euo pipefail` workflow step, so every
# function must be strict-mode safe (see hs_pr_numbers_in_range / ADR-0072 D3).
#
# Test seam: GH_CMD (default `gh`) and GIT_CMD (default `git`) are single-token
# commands; the harness points GH_CMD at a shim and runs GIT_CMD in a throwaway
# repo (by cd-ing into it, so the default `git` operates there).

GH_CMD="${GH_CMD:-gh}"
GIT_CMD="${GIT_CMD:-git}"

# hs_is_feat_title <title> — 0 if the title is a conventional-commit feature
# (feat:, feat(scope):, feat!:, feat(scope)!:), 1 otherwise. Mirrors the grep in
# /post-plan Phase 6.5 condition (8) and pr-armable.sh pr_feat_hold.
hs_is_feat_title() {
    printf '%s' "$1" | grep -qiE '^feat(\([^)]*\))?!?:'
}

# hs_pr_cleared <title> <labels_csv> — 0 if the PR may merge (non-feat, OR feat
# with the human-approved label), 1 if it is a feat: PR still awaiting sign-off.
# Comma-wrap both sides so the label match is whole-name, never a substring.
hs_pr_cleared() {
    local title="$1" labels="$2"
    if ! hs_is_feat_title "$title"; then
        return 0
    fi
    if printf '%s' ",$labels," | grep -q ',human-approved,'; then
        return 0
    fi
    return 1
}

# hs_pr_numbers_in_range <base_sha> <head_sha> — echo, one per line (sorted,
# deduped), the PR numbers parsed from the squash/merge commit subjects GitHub
# writes on the merge-group temp branch (each carries a trailing "(#N)").
#
# Strict-mode safe (ADR-0072 D3): `git log` is captured SEPARATELY with `|| return
# 1` so a real failure (bad SHA) surfaces fail-closed; the PARSE-ONLY pipeline is
# `|| true`-terminated so a no-match is empty-output-and-success, NOT a `set -e`
# abort. Do NOT collapse these into one piped assignment — that re-introduces the
# fail-closed brick-the-queue bug.
hs_pr_numbers_in_range() {
    local subjects
    subjects="$("$GIT_CMD" log --format='%s' "${1}..${2}")" || return 1
    printf '%s\n' "$subjects" \
        | grep -oE '\(#[0-9]+\)' \
        | grep -oE '[0-9]+' \
        | sort -un \
        || true
}

# hs_eval_pull_request <title> <labels_csv> — the ENTRY gate (behaviour
# unchanged from the original inline classifier). 0 = cleared, 1 = needs sign-off.
hs_eval_pull_request() {
    if hs_pr_cleared "$1" "$2"; then
        echo "Entry gate: PR cleared (title: $1)"
        return 0
    fi
    echo "::error title=Human sign-off required::This feature PR must be manually inspected by a maintainer, who then applies the 'human-approved' label. Auto-merge cannot satisfy this gate."
    return 1
}

# hs_eval_merge_group <base_sha> <head_sha> — defense-in-depth re-check on the
# merge group. Every batched PR is classified; any feat: PR without the
# human-approved label fails the whole group (the merge stalls). Entry already
# enforced this per-PR, so an EMPTY enumeration is a vacuous PASS (warn, do not
# brick the queue) — ADR-0072 D3.
hs_eval_merge_group() {
    local prs n title labels fail=0 count=0
    prs="$(hs_pr_numbers_in_range "$1" "$2")"
    if [ -z "$prs" ]; then
        echo "::warning::merge_group: no PR numbers parsed from ${1}..${2}; entry gate already enforced sign-off — passing (defense-in-depth no-op)."
        return 0
    fi
    for n in $prs; do
        count=$((count + 1))
        title="$("$GH_CMD" pr view "$n" --json title --jq .title)"
        labels="$("$GH_CMD" pr view "$n" --json labels --jq '[.labels[].name] | join(",")')"
        if hs_pr_cleared "$title" "$labels"; then
            echo "merge_group: PR #$n cleared (title: $title)"
        else
            echo "::error title=Human sign-off required::merge_group PR #$n is a feat: PR without the 'human-approved' label (title: $title)."
            fail=1
        fi
    done
    echo "merge_group: evaluated $count PR(s)."
    return $fail
}
