# Gate [LI] helper for bin/check-plan: flags plan lines that schedule a
# local Infection run. CI's required check "Infection PHP (per-PR diff)"
# scores every PR, so a plan never schedules one.
# Reads phase sections (## Phase N / ## Step N) and the Verification Matrix
# location cell. Skips fenced blocks and every other section.
# Output, one hit per line: FILENAME<TAB>P|M<TAB>line-or-row<TAB>text
FNR == 1 { in_fence = 0; fence_len = 0; sec = "other" }
{
    raw = $0
    t = raw; sub(/^[ \t]*/, "", t); n = 0
    while (substr(t, n + 1, 1) == "`") n++
    if (in_fence) { if (n >= fence_len && t ~ /^`+[ \t]*$/) { in_fence = 0; fence_len = 0 }; next }
    if (n >= 3) { in_fence = 1; fence_len = n; next }
    if (raw ~ /^## /) {
        if (raw ~ /^## (Phase|Step)[ \t]*[0-9]/) sec = "phase"
        else if (raw ~ /^## Verification Matrix/) sec = "matrix"
        else sec = "other"
        next
    }
    if (sec == "other") next
    l = tolower(raw)
    if (sec == "matrix") {
        if (l !~ /^[ \t]*\|/ || l ~ /^[ \t]*\|[ \t:|-]*$/) next
        gsub(/\\\|/, "\001", l)
        nc = split(l, c, "|")
        loc = c[nc]
        if (loc ~ /^[ \t]*$/ && nc > 1) loc = c[nc - 1]
        if (loc ~ /mutation-diff (--help|-h)/) next
        if (loc ~ /(^|[^a-z0-9_\/-])bin\/mutation-diff/ || loc ~ /vendor\/bin\/infection/ || loc ~ /composer (run )?mutation/) {
            num = c[2]; gsub(/[ \t]/, "", num)
            print FILENAME "\tM\t" num "\t" raw
        }
        next
    }
    hit = 0
    if (l ~ /vendor\/bin\/infection/ || l ~ /composer (run )?mutation/) hit = 1
    if (l ~ /(^|[^a-z0-9_\/-])bin\/mutation-diff/ && l ~ /(^|[^a-z])(run|runs|running|rerun|re-run|execute|invoke)([^a-z]|$)/) hit = 1
    if (l ~ /(^|[^a-z])infection([^a-z]|$)/ && (l ~ /(^|[^a-z])locally([^a-z]|$)/ || l ~ /(^|[^a-z])(run|runs|running|rerun|re-run) (the )?infection([^a-z]|$)/)) hit = 1
    if (!hit || l ~ /mutation-diff (--help|-h)/) next
    if (l ~ /(^|[^a-z])(do not|don't|never|must not|skip|skips|no local)([^a-z]|$)/) next
    if (l ~ /(^|[^a-z])ci([^a-z]|$)/ && l ~ /(fail|reported|reports)/) next
    print FILENAME "\tP\t" FNR "\t" raw
}
