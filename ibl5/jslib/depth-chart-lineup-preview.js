/**
 * Depth Chart Lineup Preview
 *
 * Implements JSB 5.60's lineup selection algorithm (FUN_004cfa50) to show GMs
 * a projected starting lineup, ranked bench ladder (sort1/sort2/sort3), and a
 * per-cell predicted minute share based on the current depth chart form
 * values. Updates live as the GM edits the form.
 *
 * Two stages feed the rendered grid:
 *   1. selectLineup()       — produces the starter + per-slot candidate
 *                             ladder exactly as JSB's FUN_004cfa50 would.
 *   2. computeMinuteShares()— walks each slot's displayed ladder and
 *                             allocates the 48-minute budget top-down using
 *                             dc_minutes as a soft cap per player, tracking
 *                             cross-slot totals so no player exceeds their
 *                             dc_minutes target across the full game.
 *
 * Each column in the rendered grid sums to 48m. A player appearing in
 * multiple slots has their dc_minutes budget drawn down in the earliest
 * slot that claims them.
 *
 * 100% faithful to the verified decompiled algorithm documented in JSB 5.60
 * (strategy guide last revised 2026-04-06):
 *   - DEPTH_CHART_STRATEGY_GUIDE.md "Implementation Spec: Lineup Projection Engine"
 *     (Step 3: Starter Selection, Step 4: Backup Selection, Step 5: Substitution Engine)
 *   - 00_MASTER_REFERENCE.md "Starter selection / Backup selection" and the
 *     "Substitution Dispatcher (FUN_004db520) — VERIFIED FROM CODE" section
 *     (FUN_004db520 body NOW RECOVERED at jsb560_decompiled.c:94696-95733).
 *
 * Source line citations (from `jsb560_decompiled.c` / earlier `per_possession_update_RAW.c`):
 *   - Reads dc_minutes from player struct +0xd3c at line 90870
 *   - Candidate score formula at lines 91697-91733
 *   - Two-pass bubble sort at lines 91751-91863
 *   - Backup selection (position-only) at lines 91562-91634 / 1148-1211
 *   - Self-backup state at line 1183 / 1475-1476
 *   - FUN_004db520 in-game substitution dispatcher body at jsb560_decompiled.c:94696-95733
 *   - FUN_0040af90 PLR roster sort body at jsb560_decompiled.c:5652-5843 (formula 5723-5728)
 *   - FUN_0057e510 random injury / forced-rest roller body at jsb560_decompiled.c:171738-171765
 *     (writes the +0x138 forced-rest duration that the bench-scan eligibility check reads)
 *
 * STARTER SELECTION (5 sequential passes — BH→DI→OI→DF→OF, slots PG/SG/SF/PF/C):
 *
 *   Per pass, scan all not-yet-taken active players and assign to ONE list of
 *   candidates via the matching scoring branch:
 *
 *     BRANCH A — BONUS path (dc > 0 AND dc < 5):
 *       dc_minutes ≥ 12 → score = dc_minutes + 192
 *       dc_minutes < 12 → score = dc_minutes + 144
 *       Position is irrelevant (dc > 0 overrides position).
 *
 *     BRANCH B — DC>=5 path (dc > 0 AND dc ≥ 5, e.g. auto-fill):
 *       score = dc_minutes (no bonus)
 *       Position is irrelevant. Reachable only via auto-fill at dc=6
 *       (line 91710 — see "Auto-Fill Behavior" in the strategy guide) or
 *       legacy/imported data; the form's max input is 3.
 *
 *     BRANCH C — POSITION fallback (dc ≤ 0, position must match slot):
 *       dc_minutes ≥ 12 → score = dc_minutes + 48
 *       dc_minutes < 12 → score = dc_minutes (no bonus)
 *       dc values of -2, -1, and 0 are all identical (code only checks dc > 0).
 *
 *   The "duplicate-bonus" branch (dc > 0 AND already in another slot →
 *   dc_minutes + 96, line 91710) is dead code in the actual binary because
 *   greedy pool removal evicts already-taken players before the scoring step,
 *   so we mirror that here by skipping `taken[p.pid]` early.
 *
 *   After collection, candidates are sorted in TWO separate passes (matching
 *   JSB's two bubble sorts at lines 91751-91788 and 91789-91863):
 *     Pass 1: score DESC
 *     Pass 2: dc ASC, but ONLY swap when BOTH compared candidates have dc > 0.
 *             dc = 0 candidates are left in their pass-1 positions, so they
 *             stay behind every dc > 0 candidate.
 *   Net effect: dc=1 strictly dominates dc=2 (regardless of score), and any
 *   dc > 0 candidate strictly dominates any dc = 0 candidate (min gap = 96).
 *   Within the same dc value, higher dc_minutes wins (tiebreak via pass 1).
 *
 *   The first candidate is selected, marked as taken, and removed from the
 *   pool for all later passes. If no candidates exist, the engine falls back
 *   to its preliminary (dVar58-based) selection — we don't have season stats
 *   client-side, so we approximate with roster-order default (matches the
 *   spec's reference Python pseudocode in the strategy guide).
 *
 * BACKUP SELECTION — JSB DISPATCHER LADDER MODEL:
 *
 *   At runtime, when a sub trigger fires for slot N, FUN_004db520 walks the
 *   per-slot ladder {preliminary, sort1, sort2, sort3, +0x4ca0 backup} and
 *   picks the first eligible (not on-court, not in foul trouble, etc.)
 *   candidate. sort1/sort2/sort3 (offsets +0x4cc0/+0x4ce0/+0x4d00, stride
 *   0x20) are the 2nd/3rd/4th-best non-starter candidates from the SAME
 *   per-slot dc_minutes-based candidate sort that picks the starter — not
 *   from a separate position-match scan.
 *
 *   Verified at per_possession_update_RAW.c:1441-1477: the candidate-pick
 *   loop iterates `piVar43 = (int *)(iVar40 + 0x4cc0); piVar43 += 8;`
 *   (i.e. sort1, sort2, sort3 ... at stride 0x20) and assigns the first
 *   non-taken candidate to +0x4da0 (the final starter), with +0x4c80
 *   (preliminary) as the last fallback at line 1476.
 *
 *   So for the preview's "next sub" display we model:
 *
 *     For each slot, take its FULL sorted candidate list from the starter
 *     selection pass, drop the starter (index 0), filter out any candidates
 *     who became starters of OTHER slots (greedy by on-court state at
 *     runtime), and take the first BACKUP_ROWS as successive backups.
 *
 *     Per-slot candidate lists are NOT greedy across slots — the same
 *     player may appear as sort1 of multiple slots if they qualify for
 *     each slot's bonus or fallback path. At runtime the dispatcher's
 *     "not on court" check handles cross-slot conflicts naturally; we
 *     mirror that here with non-reservation across slots.
 *
 *   FALLBACK CHAIN — when the per-slot candidate list runs out:
 *
 *     1. +0x4ca0 position-match backup (verified at
 *        per_possession_update_RAW.c:1147-1211): scan all active non-starter
 *        non-already-displayed players whose position string matches the
 *        slot's fallback position, pick highest dc_minutes. NO dc > 0
 *        override — strictly position string. Greedy across slot rows
 *        (each player can fill at most one fallback row total).
 *
 *     2. FUN_004db520 bench-scan fallback (jsb560_decompiled.c:95513-95630):
 *        when even position-match is exhausted, walk the JSB-quality-sorted
 *        roster and pick the first non-on-court player (Pass 1 strict pos
 *        match, Pass 2 relaxed). Marked italic in the display via
 *        viaBenchScan: true so the GM can tell which subs come from the
 *        per-slot ladder vs the runtime fallback.
 *
 * BENCH-SCAN FALLBACK (FUN_004db520, jsb560_decompiled.c:95500-95630):
 *
 *   At runtime the dispatcher reaches the bench scan whenever a slot's
 *   per-slot ladder {preliminary, sort1, sort2, sort3, backup} fails to
 *   produce a usable starter — i.e. every ladder candidate is filtered out
 *   by one of the dispatcher's three "should sub" triggers (verified at
 *   jsb560_decompiled.c:95082-95093):
 *     1. Foul trouble — Threshold Set B (Q1≥2, Q2≥3, Q3≥4, Q4-early≥5,
 *        Q4-late/OT only PF==6) at lines 95085-95093. NOTE: this is the
 *        higher of the two foul-trouble tables — FUN_004e4450's Set A
 *        (used by defender_selector to reduce defensive load) is NOT
 *        called by FUN_004db520. The two tables are independent.
 *     2. Forced-out flag — *(int *)(player + 0x24) < 0
 *     3. Random injury — *(int *)(player + 0x138) > 0
 *   ...or all ladder candidates resolve to the same self-backed pid and
 *   the slot scratch output stays 0.
 *
 *   Bench scan implementation — 3 passes over the team's 15-pid roster
 *   array starting from `param_1[+0x33f8]` (team 1) or `+0x33fc` (team 2):
 *     Pass 1 (95513-95548) — strict: position match + PF<6 + +0x138<4 + not on court
 *     Pass 2 (95553-95580) — relaxed: drop the position-match requirement
 *     Pass 3 (95594-95630) — very relaxed: allow PF≥6 in some branches
 *
 *   Note on +0x138 (verified at jsb560_decompiled.c:95520-95525): this
 *   field is dual-use. Between games it's the conditioning counter
 *   (decrements by 3 per game). During gameplay FUN_0057e510 (body at
 *   jsb560_decompiled.c:171738-171765) writes a random forced-rest
 *   duration value (1-160 ticks) that the dispatcher reads as the
 *   "force out" trigger. Either way, +0x138 ≥ 4 blocks the bench scan
 *   from picking the player.
 *
 *   Game-start preview assumptions: every active player has PF=0,
 *   +0x138=0, and +0x24=0, so the foul-trouble / forced-out / injury
 *   triggers can never fire. Eligibility collapses to "active && not on
 *   court", which means the only path to the bench scan at preview time
 *   is the self-backup case. Passes 1-2 are the only ones that fire (pass
 *   3 is unreachable until a player reaches 6 fouls, which can't happen
 *   at game start).
 *
 *   Roster iteration order: walks players sorted by JSB's load-time roster
 *   sort formula `quality = (dc_minutes + 100) × production_composite`
 *   (FUN_0040af90, jsb560_decompiled.c:5723-5728), where production_composite
 *   = `2 × FGM_2pt + 3 × FGM_3pt + FTM + ORB + DRB + AST + STL + BLK`. This
 *   IS the array `+0x33f8`/`+0x33fc` that the bench scan walks at runtime
 *   (verified at the call site jsb560_decompiled.c:109230-109244, where the
 *   team-roster bases are written immediately before FUN_0040af90 is called).
 *
 *   The production composite is pre-computed PHP-side from `ibl_plr` stat
 *   columns and exposed via the `data-jsb-production` attribute on each row.
 *   The `(dc_minutes + 100)` multiplier is applied here in JS so the sort
 *   updates live when the GM edits the dc_minutes select — meaning the bench
 *   scan reflects what JSB will compute on its NEXT PLR load, not just what
 *   it computed at the last sim. The global scale `_DAT_00669ab8` is omitted
 *   because it's a positive monotonic constant that doesn't affect ordering.
 *
 *   Non-reservation: at runtime the dispatcher does NOT reserve bench
 *   bodies across slots — two self-backed slots could pull the same body
 *   in successive sub events. We mirror that here, so two self-backed
 *   slots in the preview can show the same bench-scan pick.
 */
(function () {
    'use strict';

    /**
     * The 5 role slots, mapping display label to form field prefix and
     * the position used for fallback matching.
     */
    var SLOTS = [
        { label: 'PG', field: 'BH', fallbackPos: 'PG' },
        { label: 'SG', field: 'DI', fallbackPos: 'SG' },
        { label: 'SF', field: 'OI', fallbackPos: 'SF' },
        { label: 'PF', field: 'DF', fallbackPos: 'PF' },
        { label: 'C',  field: 'OF', fallbackPos: 'C'  }
    ];

    // The dispatcher walks sort1, sort2, sort3, +0x4ca0 backup, etc. (5
    // ladder positions in total). We display the top 3 layers as "1st",
    // "2nd", and "3rd" backup rows — together with the starter row that
    // gives the GM the full per-slot ladder JSB will cycle through during
    // a game, which is also the set over which computeMinuteShares()
    // distributes the 48-minute budget. Increase this to surface additional
    // layers (+0x4ca0 backup, bench-scan fallback) in the preview.
    var BACKUP_ROWS = 3;

    /**
     * Collect all player data from the desktop table rows.
     * Returns an array of player objects with pid, name, dcMinutes, pos, and
     * current dc values + active status read from the form.
     */
    function collectPlayers() {
        var form = document.forms['DepthChartEntry'];
        if (!form) return [];

        var rows = form.querySelectorAll('.depth-chart-table tbody tr[data-pid]');
        var players = [];

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var pid = row.getAttribute('data-pid');
            var pos = row.getAttribute('data-pos') || '';

            // jsbProduction is the inner sum of FUN_0040af90's roster sort
            // formula (jsb560_decompiled.c:5723-5728), pre-computed PHP-side
            // from ibl_plr stat columns. Used by benchScanFallback() to
            // produce a live JSB roster ordering as the GM edits dc_minutes.
            var jsbProduction = parseInt(row.getAttribute('data-jsb-production') || '0', 10);

            // Read player name from the hidden Name input
            var nameInput = row.querySelector('input[name^="Name"]');
            var name = nameInput ? nameInput.value : '?';

            // Read active status (checkbox — desktop class .dc-active-cb,
            // mobile class .dc-card__active-cb; both share the canPlayInGame
            // name prefix and the type attribute selector hits both)
            var idx = i + 1;
            var activeCb = form.querySelector('input[type="checkbox"][name="canPlayInGame' + idx + '"]');
            var active = activeCb ? activeCb.checked : true;

            // Read dc_minutes from the number input — THIS IS the candidate
            // quality per JSB line 90870
            var minInput = form.querySelector('input[type="number"][name="min' + idx + '"]');
            var dcMinutes = minInput ? parseInt(minInput.value, 10) || 0 : 0;

            // Read dc values for each slot
            var dcValues = {};
            for (var s = 0; s < SLOTS.length; s++) {
                var sel = form.querySelector('select[name="' + SLOTS[s].field + idx + '"]');
                dcValues[SLOTS[s].field] = sel ? parseInt(sel.value, 10) : 0;
            }

            players.push({
                pid: pid,
                name: name,
                dcMinutes: dcMinutes,
                pos: pos,
                active: active,
                dc: dcValues,
                jsbProduction: jsbProduction,
                index: idx
            });
        }

        return players;
    }

    /**
     * Compute the JSB roster-sort quality for a player, matching FUN_0040af90's
     * formula at jsb560_decompiled.c:5723-5728:
     *
     *   quality = (dc_minutes + 100) × production_composite
     *
     * The production_composite (inner sum) is pre-computed PHP-side and
     * exposed via the `data-jsb-production` attribute. The `(dc_minutes + 100)`
     * multiplier is applied here so the sort updates live when the GM edits
     * the dc_minutes select. The global scale `_DAT_00669ab8` is omitted
     * because it's a positive monotonic constant that doesn't affect ordering.
     */
    function jsbRosterQuality(player) {
        return (player.dcMinutes + 100) * player.jsbProduction;
    }

    /**
     * Score constants — must match JSB 5.60 exactly. See file header for the
     * full algorithm description and source line citations.
     */
    var DC_MIN_THRESHOLD = 12;   // dc_minutes threshold for the higher bonus tier
    var BONUS_DC_HIGH    = 192;  // dc > 0, dc < 5, dc_minutes >= 12
    var BONUS_DC_LOW     = 144;  // dc > 0, dc < 5, dc_minutes <  12
    var BONUS_POS_HIGH   = 48;   // dc <= 0, position match, dc_minutes >= 12
    var BONUS_POS_LOW    = 0;    // dc <= 0, position match, dc_minutes <  12

    /**
     * Run the lineup selection algorithm. See the file header for the full
     * algorithm description, scoring branches, and JSB source line citations.
     *
     * Returns { starters: Player[5], bench: Player[5][1] } where bench[i] is
     * a single-element array containing slot i's backup (or self-backup).
     */
    function selectLineup(players) {
        var activePlayers = players.filter(function (p) { return p.active; });
        var taken = {};  // pid → true for players already selected as starters

        var starters = [];
        // Per-slot sorted candidate lists from the starter selection pass.
        // slotCandidates[i] holds the same array that JSB stores at the
        // per-slot scratch buffer, sorted by score DESC then dc ASC. Index 0
        // is the slot's starter; indexes 1..N correspond to JSB's sort1,
        // sort2, sort3, ... fields at offsets +0x4cc0/+0x4ce0/+0x4d00/...
        // (stride 0x20). The dispatcher walks these at runtime starting from
        // sort1 — see per_possession_update_RAW.c:1441-1477.
        var slotCandidates = [];

        // === Starter Selection (5 passes — BH→DI→OI→DF→OF) ===
        for (var pass = 0; pass < SLOTS.length; pass++) {
            var slot = SLOTS[pass];
            var fieldKey = slot.field;
            var candidates = [];

            for (var i = 0; i < activePlayers.length; i++) {
                var p = activePlayers[i];
                // Greedy pool removal — mirrors JSB's check that the player is
                // not already in another lineup slot. This makes the binary's
                // "duplicate bonus 96" branch (line 91710) dead code, so we
                // omit it here for the same reason.
                if (taken[p.pid]) continue;

                var dc = p.dc[fieldKey];

                if (dc > 0 && dc < 5) {
                    // BRANCH A — BONUS path: any position, dc_minutes + bonus
                    var bonusA = p.dcMinutes >= DC_MIN_THRESHOLD
                        ? BONUS_DC_HIGH
                        : BONUS_DC_LOW;
                    candidates.push({
                        player: p,
                        score: p.dcMinutes + bonusA,
                        dc: dc
                    });
                } else if (dc >= 5) {
                    // BRANCH B — DC>=5 path: any position, no bonus.
                    // Unreachable from valid form input (max dc = 3) but kept
                    // for spec fidelity / legacy data / auto-fill at dc=6.
                    candidates.push({
                        player: p,
                        score: p.dcMinutes,
                        dc: dc
                    });
                } else if (dc <= 0 && matchesPosition(p.pos, slot.fallbackPos)) {
                    // BRANCH C — POSITION fallback: dc must be ≤ 0 AND position
                    // string must match the slot's fallback position.
                    var bonusC = p.dcMinutes >= DC_MIN_THRESHOLD
                        ? BONUS_POS_HIGH
                        : BONUS_POS_LOW;
                    candidates.push({
                        player: p,
                        score: p.dcMinutes + bonusC,
                        dc: 0
                    });
                }
                // Else: dc <= 0 with position mismatch — not eligible this pass.
            }

            // Two-pass sort, matching JSB's two separate bubble sorts at
            // jsb560_decompiled.c lines 91751-91788 (Pass 1) and 91789-91863
            // (Pass 2). JS Array.sort is stable since ES2019, so applying the
            // sorts sequentially is equivalent to JSB's bubble-sort behavior.
            //
            // Pass 1: score DESC (within each dc tier, higher score wins).
            candidates.sort(function (a, b) {
                return b.score - a.score;
            });
            // Pass 2: dc ASC, BUT only swap when both compared candidates
            // have dc > 0. When either side has dc = 0, return 0 to preserve
            // the pass-1 ordering — leaving dc = 0 candidates strictly behind
            // every dc > 0 candidate (since dc > 0 scores are always ≥ 144
            // and dc = 0 scores are always ≤ 96).
            candidates.sort(function (a, b) {
                if (a.dc > 0 && b.dc > 0) {
                    return a.dc - b.dc;
                }
                return 0;
            });

            // Save the sorted candidate list for this slot — used below by
            // the backup display to extract sort1, sort2, sort3, etc.
            slotCandidates.push(candidates);

            var starter = null;
            if (candidates.length > 0) {
                starter = candidates[0].player;
            } else {
                // No viable candidate — JSB falls back to its preliminary
                // (dVar58-based) selection. We don't have season stats client
                // side, so we use the spec's reference fallback (roster-order
                // default — first untaken active player). See the strategy
                // guide's Python pseudocode in "Complete Lineup Selection".
                for (var f = 0; f < activePlayers.length; f++) {
                    if (!taken[activePlayers[f].pid]) {
                        starter = activePlayers[f];
                        break;
                    }
                }
            }

            if (starter) {
                taken[starter.pid] = true;
            }
            starters.push(starter);
        }

        // === Backup Selection — JSB DISPATCHER LADDER MODEL ===
        //
        // For each slot, walk its per-slot sorted candidate list (sort1,
        // sort2, sort3 ...) skipping any candidate who is now on court
        // (a starter of any slot). Take the first BACKUP_ROWS non-on-court
        // candidates as successive backups. If the per-slot list is exhausted
        // before BACKUP_ROWS is reached, fall through to:
        //   - position-match backup (+0x4ca0 equivalent), then
        //   - bench-scan fallback (FUN_004db520, marked italic).
        //
        // The position-match fallback chain is greedy across slot rows
        // (benchTaken set). The bench-scan fallback is non-reservation
        // (matches engine semantics — verified earlier).
        //
        // Bench entries are { player, viaBenchScan } objects.
        var benchTaken = {};
        var bench = [];

        for (var bSlotIdx = 0; bSlotIdx < SLOTS.length; bSlotIdx++) {
            var bSlot = SLOTS[bSlotIdx];
            var slotBench = [];

            // Layer 1: walk the per-slot sorted candidate list (sort1, sort2, ...).
            // Skip the starter (index 0) and any candidate who is on court
            // as a starter of another slot.
            var slotList = slotCandidates[bSlotIdx];
            for (var ci = 1; ci < slotList.length && slotBench.length < BACKUP_ROWS; ci++) {
                var cp = slotList[ci].player;
                if (taken[cp.pid]) continue;
                // Also skip if already chosen as a backup row for this slot
                // (greedy within slot — same player can't be 1st AND 2nd
                // backup for the same slot).
                var alreadyInSlot = false;
                for (var ai = 0; ai < slotBench.length; ai++) {
                    if (slotBench[ai].player.pid === cp.pid) {
                        alreadyInSlot = true;
                        break;
                    }
                }
                if (alreadyInSlot) continue;
                slotBench.push({ player: cp, viaBenchScan: false });
            }

            // Layer 2: position-match backup (+0x4ca0 equivalent), greedy
            // across slot rows. Only used when the per-slot ladder is
            // exhausted before BACKUP_ROWS.
            while (slotBench.length < BACKUP_ROWS) {
                var inSlotBench = {};
                for (var sb = 0; sb < slotBench.length; sb++) {
                    inSlotBench[slotBench[sb].player.pid] = true;
                }

                var bestBackup = null;
                var bestMinutes = -Infinity;
                for (var k = 0; k < activePlayers.length; k++) {
                    var bp = activePlayers[k];
                    if (taken[bp.pid] || benchTaken[bp.pid]) continue;
                    if (inSlotBench[bp.pid]) continue;
                    if (!matchesPosition(bp.pos, bSlot.fallbackPos)) continue;
                    if (bp.dcMinutes > bestMinutes) {
                        bestMinutes = bp.dcMinutes;
                        bestBackup = bp;
                    }
                }

                if (bestBackup) {
                    slotBench.push({ player: bestBackup, viaBenchScan: false });
                    benchTaken[bestBackup.pid] = true;
                    continue;
                }

                // Layer 3: bench-scan fallback (FUN_004db520). Walks the
                // JSB-quality-sorted roster and picks the first non-on-court
                // candidate not already shown for this slot. Non-reservation
                // across slots — matches engine semantics.
                var benchScanPick = benchScanFallback(
                    bSlot, starters, activePlayers, inSlotBench
                );
                if (benchScanPick) {
                    slotBench.push({ player: benchScanPick, viaBenchScan: true });
                    continue;
                }

                // Layer 4 (last resort): self-backup. Only happens if every
                // active player is on court and the bench scan returns null.
                if (starters[bSlotIdx]) {
                    slotBench.push({
                        player: starters[bSlotIdx],
                        viaBenchScan: false
                    });
                }
                break;  // can't go any deeper
            }

            bench.push(slotBench);
        }

        return { starters: starters, bench: bench };
    }

    /**
     * Model FUN_004db520's bench-scan fallback (jsb560_decompiled.c:95513-95630).
     *
     * Walks the team's 15-pid roster array in JSB's load-time sort order
     * (FUN_0040af90, formula at jsb560_decompiled.c:5723-5728) and runs up
     * to two passes:
     *
     *   Pass 1 — strict (lines 95513-95548):
     *     position match + PF<6 + +0x138<4 + not on court
     *   Pass 2 — relaxed (lines 95553-95580):
     *     drop the position-match requirement
     *
     * Pass 3 (lines 95594-95630, allow PF≥6 in some branches) is unreachable
     * at preview time because game-start state has PF=0 for every player.
     *
     * Game-start eligibility collapses to "active && not on court" — every
     * active player satisfies PF<6 and +0x138<4 trivially.
     *
     * Roster order: sorted by JSB quality DESC via jsbRosterQuality(), which
     * applies the `(dc_minutes + 100) × production_composite` formula. This
     * is the order JSB would compute on its NEXT PLR load given the current
     * dc_minutes values, so changes the GM makes immediately shift the
     * bench-scan ordering.
     *
     * The optional `excludePids` parameter is a `{pid: true}` map of players
     * already shown for the current slot's earlier backup rows; they're
     * filtered out so the same body doesn't appear twice in the same column.
     *
     * Returns the first eligible Player, or null if every active player is
     * already on court / excluded (only possible with ≤5 active players on
     * the roster).
     */
    function benchScanFallback(slot, starters, activePlayers, excludePids) {
        var onCourt = {};
        for (var s = 0; s < starters.length; s++) {
            if (starters[s]) onCourt[starters[s].pid] = true;
        }
        var exclude = excludePids || {};

        // Build a JSB-quality-sorted copy of the active player list. We sort
        // a copy (not the original) so other consumers of activePlayers still
        // see the DOM/ordinal order. Sort key: jsbRosterQuality() DESC; ties
        // broken by DOM/ordinal order via the .index field, matching JSB's
        // stable bubble sort which preserves input order on equal keys.
        var rosterSorted = activePlayers.slice().sort(function (a, b) {
            var qa = jsbRosterQuality(a);
            var qb = jsbRosterQuality(b);
            if (qb !== qa) return qb - qa;
            return a.index - b.index;
        });

        // Pass 1 — strict: position match required
        for (var i = 0; i < rosterSorted.length; i++) {
            var p = rosterSorted[i];
            if (onCourt[p.pid] || exclude[p.pid]) continue;
            if (matchesPosition(p.pos, slot.fallbackPos)) return p;
        }

        // Pass 2 — relaxed: drop position match
        for (var j = 0; j < rosterSorted.length; j++) {
            var q = rosterSorted[j];
            if (onCourt[q.pid] || exclude[q.pid]) continue;
            return q;
        }

        return null;
    }

    /**
     * Per-slot minute share model — distributes the 48-minute budget of each
     * slot across its displayed ladder (starter + BACKUP_ROWS bench picks)
     * using dc_minutes as a soft cap per player, mirroring JSB's runtime sub
     * dispatcher behavior:
     *
     *   1. The starter plays until their accumulated minutes hit dc_minutes.
     *   2. Sub dispatcher walks sort1/sort2/sort3 (bench rows 1..N), picking
     *      the first non-on-court, non-exhausted candidate.
     *   3. Each candidate plays up to `dc_minutes - already_allocated` more
     *      minutes, where `already_allocated` counts minutes they consumed
     *      in EARLIER slots (because a player can't be on court in two slots
     *      at once).
     *   4. If the displayed ladder runs out before the 48-minute budget is
     *      exhausted, the remainder is charged to the last displayed entry
     *      — matching the dispatcher's "preliminary as last fallback" behavior
     *      at per_possession_update_RAW.c:1476.
     *
     * Slot processing order (PG→SG→SF→PF→C) matches the starter selection
     * pass order. It affects where dual-eligible players are placed: whichever
     * slot pass claims them first gets their minutes. This is a deliberate
     * simplification of JSB's event-driven dispatcher — the engine would place
     * them wherever the first chronological sub event falls, which we can't
     * model statically. The sequential model matches starter selection's own
     * greedy pool removal and gives a stable, deterministic preview.
     *
     * Returns an array parallel to SLOTS where each entry is a
     * `{ pid: minutes }` map — the minutes this slot contributes to that
     * player's total. Total across all slots for one pid will never exceed
     * that player's dc_minutes (unless the dump-to-last clause assigns them
     * leftover from a fully-exhausted ladder, in which case it can exceed
     * slightly — matches the engine's actual overtime behavior).
     */
    function computeMinuteShares(lineup) {
        var allocatedTotal = {};  // pid -> total minutes allocated across all slots so far
        var slotMinutes = [];     // slotIdx -> { pid: minutes }

        for (var slotIdx = 0; slotIdx < SLOTS.length; slotIdx++) {
            var slotAlloc = {};
            var remaining = 48;

            // Build the displayed ladder: starter + bench entries (sort1/2/3).
            // We only charge minutes against what the preview actually shows,
            // so the numbers the GM sees always add up to 48 per slot.
            var ladder = [];
            if (lineup.starters[slotIdx]) {
                ladder.push(lineup.starters[slotIdx]);
            }
            var slotBench = lineup.bench[slotIdx] || [];
            for (var b = 0; b < slotBench.length; b++) {
                ladder.push(slotBench[b].player);
            }

            for (var li = 0; li < ladder.length; li++) {
                var p = ladder[li];
                var already = allocatedTotal[p.pid] || 0;
                var budget = Math.max(0, p.dcMinutes - already);
                var play = Math.min(budget, remaining);
                if (play > 0) {
                    slotAlloc[p.pid] = (slotAlloc[p.pid] || 0) + play;
                    allocatedTotal[p.pid] = already + play;
                    remaining -= play;
                    if (remaining <= 0) break;
                } else if (!(p.pid in slotAlloc)) {
                    // Record zero so the cell still renders "0m" — this
                    // visibly signals the dc_minutes trap (e.g. a dc=1
                    // starter with dc_minutes=1 already spent in another slot).
                    slotAlloc[p.pid] = 0;
                }
            }

            // Ladder exhausted before 48 min — dump remainder into the last
            // displayed entry. This matches JSB's runtime behavior where the
            // dispatcher falls back to the preliminary/self-backup candidate
            // when every sort1..sortN candidate is unavailable, and that
            // candidate plays the rest of the game's minutes in that slot.
            if (remaining > 0 && ladder.length > 0) {
                var last = ladder[ladder.length - 1];
                slotAlloc[last.pid] = (slotAlloc[last.pid] || 0) + remaining;
                allocatedTotal[last.pid] = (allocatedTotal[last.pid] || 0) + remaining;
            }

            slotMinutes.push(slotAlloc);
        }

        return slotMinutes;
    }

    /**
     * Check if a player's position string matches a slot's fallback position.
     * Compound positions expand per the spec table in DEPTH_CHART_STRATEGY_GUIDE.md
     * "Position Matching":
     *   PG/SG/SF/PF/C → match self only
     *   G  → {PG, SG}
     *   F  → {SF, PF}
     *   GF → {PG, SG, SF, PF} (NOT C)
     */
    function matchesPosition(playerPos, slotPos) {
        if (playerPos === slotPos) return true;
        if (playerPos === 'G') {
            return slotPos === 'PG' || slotPos === 'SG';
        }
        if (playerPos === 'F') {
            return slotPos === 'SF' || slotPos === 'PF';
        }
        if (playerPos === 'GF') {
            return slotPos === 'PG' || slotPos === 'SG'
                || slotPos === 'SF' || slotPos === 'PF';
        }
        return false;
    }

    /**
     * Depth-tier labels shared by both renderings. Index 0 is the starter;
     * indexes 1..BACKUP_ROWS map to JSB's per-slot ladder (sort1/sort2/sort3).
     */
    var DEPTH_LABELS = ['Starting', '2nd', '3rd', '4th', '5th'];

    /**
     * Render the lineup preview into the container element.
     *
     * Emits two tables into the same container — a desktop layout with
     * positions across the X-axis (and depth tiers down the Y-axis) and a
     * mobile layout with the axes swapped. CSS media queries in
     * `depth-chart.css` show exactly one at a time. The mobile layout puts
     * the starting lineup in the leftmost column so the most important
     * data is always visible without horizontal scrolling, and it uses more
     * of the device's vertical space for the depth ladder.
     *
     * Each populated cell shows the player's photo, a display name, and a
     * small "Nm" minute-share annotation from `slotMinutes` — the output of
     * computeMinuteShares(). Minutes across all 4 depth tiers of one slot
     * always sum to 48 (the slot's full budget).
     */
    function renderPreview(lineup, slotMinutes) {
        var container = document.getElementById('dc-lineup-preview');
        if (!container) return;

        var lastNameFreq = buildLastNameFrequency(lineup);

        var html = '<div class="dc-lineup-preview__title">Projected Lineup</div>';
        html += renderPreviewDesktopTable(lineup, slotMinutes);
        html += renderPreviewMobileTable(lineup, slotMinutes, lastNameFreq);
        container.innerHTML = html;
    }

    /**
     * Desktop rendering — positions on the X-axis, depth tier on the Y-axis.
     * Names use the "F. Last" abbreviation so the column width stays tight
     * and the full-name-with-initial reads clearly at desktop sizes.
     */
    function renderPreviewDesktopTable(lineup, slotMinutes) {
        var html = '<table class="ibl-data-table dc-lineup-preview-table dc-lineup-preview-table--desktop"><thead><tr>';
        html += '<th></th>';
        for (var h = 0; h < SLOTS.length; h++) {
            html += '<th>' + SLOTS[h].label + '</th>';
        }
        html += '</tr></thead><tbody>';

        // Starters row
        html += '<tr><td class="dc-lineup-preview__row-label">' + DEPTH_LABELS[0] + '</td>';
        for (var s = 0; s < SLOTS.length; s++) {
            var starter = lineup.starters[s];
            html += renderPlayerCell({
                player: starter,
                viaBenchScan: false,
                mins: playerMinutesIn(slotMinutes, s, starter),
                displayName: starter ? abbreviateName(starter.name) : '',
                isStarter: true
            });
        }
        html += '</tr>';

        // Bench rows. Bench entries are { player, viaBenchScan } objects —
        // see renderPlayerCell() for the bench-scan styling + tooltip.
        for (var row = 0; row < BACKUP_ROWS; row++) {
            var label = DEPTH_LABELS[row + 1] || ((row + 2) + 'th');
            html += '<tr><td class="dc-lineup-preview__row-label">' + label + '</td>';
            for (var c = 0; c < SLOTS.length; c++) {
                var benchEntry = lineup.bench[c][row];
                var benchPlayer = benchEntry ? benchEntry.player : null;
                html += renderPlayerCell({
                    player: benchPlayer,
                    viaBenchScan: benchEntry ? benchEntry.viaBenchScan : false,
                    mins: playerMinutesIn(slotMinutes, c, benchPlayer),
                    displayName: benchPlayer ? abbreviateName(benchPlayer.name) : '',
                    isStarter: false
                });
            }
            html += '</tr>';
        }

        html += '</tbody></table>';
        return html;
    }

    /**
     * Mobile rendering — positions on the Y-axis, depth tier on the X-axis
     * (swapped from desktop). The starting lineup occupies the leftmost
     * data column so it's visible in the viewport without horizontal
     * scrolling. Names show last-name-only to save horizontal space, with
     * a fallback to "F. Last" when two distinct players in the projected
     * lineup share the same last name (disambiguation computed by
     * buildLastNameFrequency() and applied via mobileDisplayName()).
     */
    function renderPreviewMobileTable(lineup, slotMinutes, lastNameFreq) {
        var html = '<table class="ibl-data-table dc-lineup-preview-table dc-lineup-preview-table--mobile"><thead><tr>';
        html += '<th></th>';
        for (var d = 0; d <= BACKUP_ROWS; d++) {
            html += '<th>' + (DEPTH_LABELS[d] || ((d + 1) + 'th')) + '</th>';
        }
        html += '</tr></thead><tbody>';

        for (var sIdx = 0; sIdx < SLOTS.length; sIdx++) {
            html += '<tr><td class="dc-lineup-preview__row-label">' + SLOTS[sIdx].label + '</td>';

            // Starter column (leftmost — most important data, always visible)
            var starter = lineup.starters[sIdx];
            html += renderPlayerCell({
                player: starter,
                viaBenchScan: false,
                mins: playerMinutesIn(slotMinutes, sIdx, starter),
                displayName: starter ? mobileDisplayName(starter, lastNameFreq) : '',
                isStarter: true
            });

            // Bench columns (2nd / 3rd / 4th)
            for (var row = 0; row < BACKUP_ROWS; row++) {
                var benchEntry = lineup.bench[sIdx][row];
                var benchPlayer = benchEntry ? benchEntry.player : null;
                html += renderPlayerCell({
                    player: benchPlayer,
                    viaBenchScan: benchEntry ? benchEntry.viaBenchScan : false,
                    mins: playerMinutesIn(slotMinutes, sIdx, benchPlayer),
                    displayName: benchPlayer ? mobileDisplayName(benchPlayer, lastNameFreq) : '',
                    isStarter: false
                });
            }

            html += '</tr>';
        }

        html += '</tbody></table>';
        return html;
    }

    /**
     * Return the minute share that slot `slotIdx` contributes to `player`,
     * or 0 when `player` is null or the slot has no entry for that pid.
     */
    function playerMinutesIn(slotMinutes, slotIdx, player) {
        if (!player || !slotMinutes[slotIdx]) return 0;
        return slotMinutes[slotIdx][player.pid] || 0;
    }

    /**
     * Render a single <td> for the lineup preview. Shared by the desktop
     * and mobile renderers so the cell markup (photo, link, bench-scan
     * italic + tooltip, empty placeholder, minute-share annotation) lives
     * in one place.
     *
     * opts = { player, viaBenchScan, mins, displayName, isStarter }
     *
     * `viaBenchScan = true` means this slot's per-slot candidate ladder
     * (sort1/sort2/sort3) AND its position-match backup chain were both
     * exhausted, so FUN_004db520's bench-scan fallback supplied the body.
     * Those cells render italic via .dc-lineup-preview__bench-scan and
     * carry a tooltip explaining the source.
     */
    function renderPlayerCell(opts) {
        if (!opts.player) {
            return '<td class="dc-lineup-preview__empty">&mdash;</td>';
        }
        var cellClass = 'ibl-player-cell';
        if (opts.isStarter) cellClass += ' dc-lineup-preview__starter';
        var cellExtra = '';
        if (opts.viaBenchScan) {
            cellClass += ' dc-lineup-preview__bench-scan';
            cellExtra = ' title="Bench-scan fallback: this slot has no'
                + ' more candidates in its per-slot ladder, so the'
                + ' in-game substitution dispatcher walks the team'
                + ' roster and picks this player instead."';
        }
        return '<td class="' + cellClass + '"' + cellExtra + '>'
            + '<a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=' + opts.player.pid + '">'
            + '<img src="./images/player/' + opts.player.pid + '.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy" onerror="this.style.display=\'none\'">'
            + escapeHtml(opts.displayName)
            + renderMinutes(opts.mins)
            + '</a></td>';
    }

    /**
     * Extract the last-name portion of a player's display name, matching
     * the "First Last..." split used by abbreviateName() — i.e. everything
     * after the first space. "James Harden" → "Harden"; "James Harden Jr."
     * → "Harden Jr.". Single-word names are returned unchanged.
     */
    function extractLastName(name) {
        var parts = name.split(' ');
        if (parts.length < 2) return name;
        return parts.slice(1).join(' ');
    }

    /**
     * Build a frequency map of last names across every distinct player in
     * the projected lineup (starters + bench, deduped by pid). The mobile
     * renderer uses this to decide whether a cell can show last-name-only
     * or must fall back to "F. Last" because another distinct player in
     * the preview shares the same last name.
     */
    function buildLastNameFrequency(lineup) {
        var freq = {};
        var seen = {};
        var all = [];
        for (var i = 0; i < lineup.starters.length; i++) {
            if (lineup.starters[i]) all.push(lineup.starters[i]);
        }
        for (var j = 0; j < lineup.bench.length; j++) {
            var slotBench = lineup.bench[j] || [];
            for (var k = 0; k < slotBench.length; k++) {
                all.push(slotBench[k].player);
            }
        }
        for (var m = 0; m < all.length; m++) {
            var p = all[m];
            if (seen[p.pid]) continue;
            seen[p.pid] = true;
            var key = extractLastName(p.name).toLowerCase();
            freq[key] = (freq[key] || 0) + 1;
        }
        return freq;
    }

    /**
     * Pick a display name for the mobile preview. Uses last-name-only to
     * save horizontal space, falling back to "F. Last" when another
     * distinct player in the lineup shares the same last name.
     */
    function mobileDisplayName(player, lastNameFreq) {
        var lname = extractLastName(player.name);
        if ((lastNameFreq[lname.toLowerCase()] || 0) > 1) {
            return abbreviateName(player.name);
        }
        return lname;
    }

    /**
     * Format a minute-share annotation. Rendered as a subdued span after the
     * abbreviated name so the eye still lands on the player, with the
     * predicted share as secondary information.
     */
    function renderMinutes(mins) {
        return ' <span class="dc-lineup-preview__mins">' + mins + 'm</span>';
    }

    /**
     * Abbreviate a player name to "F. Last" format.
     */
    function abbreviateName(name) {
        var parts = name.split(' ');
        if (parts.length < 2) return name;
        return parts[0].charAt(0) + '. ' + parts.slice(1).join(' ');
    }

    /**
     * Simple HTML escape.
     */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * Update the debug score annotations in the form.
     * Shows the candidate score next to each role slot select, computed per
     * the same scoring branches as selectLineup(). Only renders on localhost.
     */
    function updateScoreDebug(players) {
        if (!isLocalhost()) return;

        var form = document.forms['DepthChartEntry'];
        if (!form) return;

        var rows = form.querySelectorAll('.depth-chart-table tbody tr[data-pid]');

        for (var i = 0; i < rows.length; i++) {
            var idx = i + 1;
            var row = rows[i];
            var pos = row.getAttribute('data-pos') || '';

            var minInput = form.querySelector('input[type="number"][name="min' + idx + '"]');
            var dcMin = minInput ? parseInt(minInput.value, 10) || 0 : 0;

            for (var s = 0; s < SLOTS.length; s++) {
                var slot = SLOTS[s];
                var sel = form.querySelector('select[name="' + slot.field + idx + '"]');
                if (!sel) continue;
                var scoreSpan = sel.parentNode.querySelector('.dc-score-debug');
                if (!scoreSpan) continue;

                var dc = parseInt(sel.value, 10);
                var score = null;

                if (dc > 0 && dc < 5) {
                    score = dcMin + (dcMin >= DC_MIN_THRESHOLD ? BONUS_DC_HIGH : BONUS_DC_LOW);
                } else if (dc >= 5) {
                    score = dcMin;
                } else if (dc <= 0 && matchesPosition(pos, slot.fallbackPos)) {
                    score = dcMin + (dcMin >= DC_MIN_THRESHOLD ? BONUS_POS_HIGH : BONUS_POS_LOW);
                }

                scoreSpan.textContent = score === null ? '' : String(score);
            }
        }
    }

    function isLocalhost() {
        var h = window.location.hostname;
        return h === 'localhost' || h === '127.0.0.1' || h.indexOf('.localhost') !== -1;
    }

    /**
     * Recalculate and re-render the lineup preview.
     */
    function recalculate() {
        var players = collectPlayers();
        if (players.length === 0) return;
        var lineup = selectLineup(players);
        var slotMinutes = computeMinuteShares(lineup);
        renderPreview(lineup, slotMinutes);
        updateScoreDebug(players);
    }

    // Expose globally for reset button and saved DC loading
    window.IBL_recalculateLineupPreview = recalculate;

    function initLineupPreview() {
        var form = document.forms['DepthChartEntry'];
        if (!form) return;

        // Initial render
        recalculate();

        // Listen for changes to any depth-chart input that feeds the
        // projection: role slot selects (SELECT), the minutes number input,
        // and the active-status checkbox. The minutes input also fires on
        // 'input' so the preview updates live as the GM types or clicks the
        // native stepper, not just on blur.
        function isTrackedTarget(target) {
            if (!target) return false;
            if (target.tagName === 'SELECT') return true;
            if (target.tagName !== 'INPUT') return false;
            var type = target.type;
            if (type === 'number') return true;
            if (type === 'checkbox') return true;
            return false;
        }
        form.addEventListener('change', function (e) {
            if (isTrackedTarget(e.target)) recalculate();
        });
        form.addEventListener('input', function (e) {
            var target = e.target;
            if (target && target.tagName === 'INPUT' && target.type === 'number') {
                recalculate();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLineupPreview);
    } else {
        initLineupPreview();
    }
})();
