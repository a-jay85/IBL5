# shellcheck shell=bash
#
# bin/lib/human-signoff-classifier.sh — single source of truth for the
# .github/workflows/human-signoff.yml feature-PR sign-off classifier (ADR-0062),
# sourced by BOTH the workflow and the regression harness
# bin/test-human-signoff-classifier so the logic cannot drift.
#
# Usage: source "$(dirname "$0")/lib/human-signoff-classifier.sh"
# This file is SOURCED, not executed: no `set -euo pipefail` at file scope.
# It IS, however, sourced INTO a `set -euo pipefail` workflow step, so every
# function must be strict-mode safe.

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

# hs_eval_pull_request <title> <labels_csv> — the entry gate. 0 = cleared,
# 1 = needs sign-off (emits the GitHub ::error:: that reds the required check).
hs_eval_pull_request() {
    if hs_pr_cleared "$1" "$2"; then
        echo "Entry gate: PR cleared (title: $1)"
        return 0
    fi
    echo "::error title=Human sign-off required::This feature PR must be manually inspected by a maintainer, who then applies the 'human-approved' label. Auto-merge cannot satisfy this gate."
    return 1
}
