/**
 * Depth Chart Lineup Preview
 *
 * Implements JSB 5.60's lineup selection algorithm (FUN_004cfa50) to show GMs
 * a projected starting lineup and one bench player per slot based on the
 * current depth chart form values. Updates live as the GM edits the form.
 *
 * 100% faithful to the verified decompiled algorithm documented in:
 *   - DEPTH_CHART_STRATEGY_GUIDE.md "Implementation Spec: Lineup Projection Engine"
 *   - 00_MASTER_REFERENCE.md "Starter selection / Backup selection"
 *
 * Source line citations (from `jsb560_decompiled.c` / earlier `per_possession_update_RAW.c`):
 *   - Reads dc_minutes from player struct +0xd3c at line 90870
 *   - Candidate score formula at lines 91697-91733
 *   - Two-pass bubble sort at lines 91751-91863
 *   - Backup selection (position-only) at lines 91562-91634 / 1148-1211
 *   - Self-backup state at line 1183 / 1475-1476
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
 * BACKUP SELECTION (one backup per slot, runs AFTER starters are picked):
 *
 *   Fundamentally different from starter selection — POSITION MATCH ONLY.
 *   There is NO dc > 0 override for backups. A center with dc_df > 0 can
 *   START at PF via the bonus path, but cannot BACK UP that PF slot.
 *
 *   For each slot, scan all non-starter active players whose position string
 *   matches the slot's fallback position. Highest dc_minutes wins (tiebreak
 *   by roster order). Greedy pool removal: a player chosen as backup for one
 *   slot cannot back up other slots.
 *
 *   If no position-matching non-starter exists, the lineup-memory backup
 *   pointer points back to the starter (self-backup, verified at
 *   per_possession_update_RAW.c:1183). At runtime FUN_004db520's bench-scan
 *   fallback bypasses this self-backup state — the player is NOT locked on
 *   court — so the preview replaces the self-backup cell with the result of
 *   benchScanFallback() and renders it in italic so the GM can tell which
 *   slots are using the structured backup vs the runtime bench scan.
 *
 * BENCH-SCAN FALLBACK (FUN_004db520, jsb560_decompiled.c:95513-95630):
 *
 *   Triggered ONLY for slots whose lineup-memory backup is self (i.e. no
 *   position-matching non-starter exists). At runtime the per-slot ladder
 *   {preliminary, sort1, sort2, sort3, backup} cycles through the same
 *   self-backed pid and the slot output stays 0, so the dispatcher falls
 *   through to a 3-pass scan over the team's 15-pid roster array starting
 *   from `param_1[+0x33f8]` (team 1) or `+0x33fc` (team 2):
 *     Pass 1 (95513-95548) — strict: position match + PF<6 + +0x138<4 + not on court
 *     Pass 2 (95553-95580) — relaxed: drop the position-match requirement
 *     Pass 3 (95594-95630) — very relaxed: allow PF≥6 in some branches
 *
 *   Game-start preview assumptions: every active player has PF=0 and
 *   +0x138=0, so eligibility collapses to "active && not on court" and
 *   passes 1-2 are the only ones that fire (pass 3 is unreachable until a
 *   player reaches 6 fouls, which can't happen at game start).
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

    var BACKUP_ROWS = 1; // JSB fills 1 backup per slot (position-match only)

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

            // Read active status
            var idx = i + 1;
            var activeSel = form.querySelector('select[name="canPlayInGame' + idx + '"]');
            var active = activeSel ? activeSel.value === '1' : true;

            // Read dc_minutes — THIS IS the candidate quality per JSB line 90870
            var minSel = form.querySelector('select[name="min' + idx + '"]');
            var dcMinutes = minSel ? parseInt(minSel.value, 10) : 0;

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

        // === Backup Selection (one backup per slot) ===
        // POSITION MATCH ONLY — no dc > 0 override. Greedy pool removal.
        // Highest dc_minutes wins (tiebreak by roster order). When no
        // position-matching non-starter exists, lineup memory has +0x4ca0 ==
        // +0x4c80 (self-backup), so we instead route to FUN_004db520's
        // bench-scan fallback (see benchScanFallback() below) and mark the
        // resulting entry with viaBenchScan: true so the renderer can
        // italicize it.
        //
        // Bench entries are { player, viaBenchScan } objects.
        var benchTaken = {};
        var bench = [];

        for (var bSlotIdx = 0; bSlotIdx < SLOTS.length; bSlotIdx++) {
            var bSlot = SLOTS[bSlotIdx];
            var bestBackup = null;
            var bestMinutes = -Infinity;

            for (var k = 0; k < activePlayers.length; k++) {
                var bp = activePlayers[k];
                if (taken[bp.pid] || benchTaken[bp.pid]) continue;
                if (!matchesPosition(bp.pos, bSlot.fallbackPos)) continue;

                // Backup ranking is by dc_minutes (no bonus, no dc override).
                if (bp.dcMinutes > bestMinutes) {
                    bestMinutes = bp.dcMinutes;
                    bestBackup = bp;
                }
            }

            var slotBench = [];
            if (bestBackup) {
                slotBench.push({ player: bestBackup, viaBenchScan: false });
                benchTaken[bestBackup.pid] = true;
            } else if (starters[bSlotIdx]) {
                // Self-backup case → run FUN_004db520's bench-scan fallback.
                // Non-reservation: do NOT mark benchTaken — engine doesn't
                // reserve, and two self-backed slots may show the same pick.
                var benchScanPick = benchScanFallback(bSlot, starters, activePlayers);
                if (benchScanPick) {
                    slotBench.push({ player: benchScanPick, viaBenchScan: true });
                } else {
                    // Bench scan exhausted (only happens if every active
                    // player is already on court). Fall back to the
                    // lineup-memory self-backup as a last resort.
                    slotBench.push({ player: starters[bSlotIdx], viaBenchScan: false });
                }
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
     * Returns the first eligible Player, or null if every active player is
     * already on court (only possible with ≤5 active players on the roster).
     */
    function benchScanFallback(slot, starters, activePlayers) {
        var onCourt = {};
        for (var s = 0; s < starters.length; s++) {
            if (starters[s]) onCourt[starters[s].pid] = true;
        }

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
            if (onCourt[p.pid]) continue;
            if (matchesPosition(p.pos, slot.fallbackPos)) return p;
        }

        // Pass 2 — relaxed: drop position match
        for (var j = 0; j < rosterSorted.length; j++) {
            var q = rosterSorted[j];
            if (onCourt[q.pid]) continue;
            return q;
        }

        return null;
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
     * Render the lineup preview into the container element.
     */
    function renderPreview(lineup) {
        var container = document.getElementById('dc-lineup-preview');
        if (!container) return;

        var html = '<div class="dc-lineup-preview__title">Projected Lineup</div>';
        html += '<table class="ibl-data-table dc-lineup-preview-table"><thead><tr>';
        html += '<th class="dc-lineup-preview__row-label"></th>';
        for (var h = 0; h < SLOTS.length; h++) {
            html += '<th>' + SLOTS[h].label + '</th>';
        }
        html += '</tr></thead><tbody>';

        // Starters row
        html += '<tr><td class="dc-lineup-preview__row-label">Start</td>';
        for (var s = 0; s < SLOTS.length; s++) {
            var starter = lineup.starters[s];
            if (starter) {
                html += '<td class="dc-lineup-preview__starter">' + escapeHtml(abbreviateName(starter.name)) + '</td>';
            } else {
                html += '<td class="dc-lineup-preview__empty">&mdash;</td>';
            }
        }
        html += '</tr>';

        // Bench rows. Bench entries are { player, viaBenchScan } objects.
        // viaBenchScan = true means the slot was self-backed in lineup memory
        // and FUN_004db520's bench-scan fallback picked this player at runtime;
        // we render those in italic via .dc-lineup-preview__bench-scan and
        // attach a tooltip explaining the source.
        for (var row = 0; row < BACKUP_ROWS; row++) {
            var label = row === 0 ? '1st' : '2nd';
            html += '<tr><td class="dc-lineup-preview__row-label">' + label + '</td>';
            for (var c = 0; c < SLOTS.length; c++) {
                var benchEntry = lineup.bench[c][row];
                if (benchEntry) {
                    var cellAttrs = '';
                    if (benchEntry.viaBenchScan) {
                        cellAttrs = ' class="dc-lineup-preview__bench-scan"'
                            + ' title="Bench-scan fallback: this slot has no'
                            + ' position-matching backup, so the in-game'
                            + ' substitution dispatcher picks this player from'
                            + ' the team roster instead."';
                    }
                    html += '<td' + cellAttrs + '>'
                        + escapeHtml(abbreviateName(benchEntry.player.name))
                        + '</td>';
                } else {
                    html += '<td class="dc-lineup-preview__empty">&mdash;</td>';
                }
            }
            html += '</tr>';
        }

        html += '</tbody></table>';
        container.innerHTML = html;
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

            var minSel = form.querySelector('select[name="min' + idx + '"]');
            var dcMin = minSel ? parseInt(minSel.value, 10) : 0;

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
        renderPreview(lineup);
        updateScoreDebug(players);
    }

    // Expose globally for reset button and saved DC loading
    window.IBL_recalculateLineupPreview = recalculate;

    function initLineupPreview() {
        var form = document.forms['DepthChartEntry'];
        if (!form) return;

        // Initial render
        recalculate();

        // Listen for changes on role slot selects and active selects
        form.addEventListener('change', function (e) {
            var target = e.target;
            if (target.tagName !== 'SELECT') return;
            recalculate();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLineupPreview);
    } else {
        initLineupPreview();
    }
})();
