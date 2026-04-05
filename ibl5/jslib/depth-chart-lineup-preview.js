/**
 * Depth Chart Lineup Preview
 *
 * Implements JSB's 5-pass lineup selection algorithm to show GMs a projected
 * starting lineup and bench based on their current depth chart settings.
 *
 * Updates live whenever a role slot select or active toggle changes.
 *
 * Algorithm (from decompiled JSB 5.60, 00_MASTER_REFERENCE.md):
 *   5 sequential passes (dc_bh→dc_di→dc_oi→dc_df→dc_of), each selects ONE player.
 *   Per pass, candidates collected via TWO paths into ONE list:
 *     BONUS PATH (dc > 0): any position, score = quality + (10 - dc) * 240
 *     FALLBACK PATH (dc ≤ 0): position must match slot, score = raw quality
 *   Candidates sorted: quality DESC, then dc ASC (dc=1 before dc=2).
 *   First candidate selected, removed from pool for subsequent passes.
 *   Bench: remaining candidates in same sorted order.
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

    var BACKUP_ROWS = 2;

    /**
     * Collect all player data from the desktop table rows.
     * Returns an array of player objects with pid, name, quality, pos, and
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
            var quality = parseFloat(row.getAttribute('data-quality') || '0');
            var pos = row.getAttribute('data-pos') || '';

            // Read player name from the hidden Name input
            var nameInput = row.querySelector('input[name^="Name"]');
            var name = nameInput ? nameInput.value : '?';

            // Read active status
            var idx = i + 1;
            var activeSel = form.querySelector('select[name="canPlayInGame' + idx + '"]');
            var active = activeSel ? activeSel.value === '1' : true;

            // Read dc_minutes — multiplied into quality per JSB formula
            var minSel = form.querySelector('select[name="min' + idx + '"]');
            var dcMinutes = minSel ? parseInt(minSel.value, 10) : 0;

            // Effective quality = baseQuality × (dc_minutes + 100)
            // This matches JSB's quality multiplier (line 5723 in PLR loader)
            var effectiveQuality = quality * (dcMinutes + 100);

            // Read dc values for each slot
            var dcValues = {};
            for (var s = 0; s < SLOTS.length; s++) {
                var sel = form.querySelector('select[name="' + SLOTS[s].field + idx + '"]');
                dcValues[SLOTS[s].field] = sel ? parseInt(sel.value, 10) : 0;
            }

            players.push({
                pid: pid,
                name: name,
                baseQuality: quality,
                quality: effectiveQuality,
                dcMinutes: dcMinutes,
                pos: pos,
                active: active,
                dc: dcValues,
                index: idx
            });
        }

        return players;
    }

    /**
     * Run the 5-pass lineup selection algorithm.
     *
     * Per the decompiled code (00_MASTER_REFERENCE.md, lines 688-716):
     * Each pass builds a SINGLE candidate list from two paths:
     *   BONUS: dc > 0, any position, score = quality + (10-dc)*240
     *   FALLBACK: dc ≤ 0, position must match slot, score = raw quality
     * Candidates sorted: first by quality DESC, then by dc ASC.
     * dc sort is final, so dc=1 beats dc=2 regardless of quality.
     * First candidate selected, removed from pool.
     *
     * Bench: remaining candidates in same sorted order (up to BACKUP_ROWS per slot).
     */
    function selectLineup(players) {
        var activePlayers = players.filter(function (p) { return p.active; });
        var taken = {};  // pid → true for players already selected as starters

        var starters = [];

        // 5 passes for starters
        for (var pass = 0; pass < SLOTS.length; pass++) {
            var slot = SLOTS[pass];
            var fieldKey = slot.field;
            var candidates = [];

            for (var i = 0; i < activePlayers.length; i++) {
                var p = activePlayers[i];
                if (taken[p.pid]) continue;

                var dc = p.dc[fieldKey];
                if (dc > 0) {
                    // Bonus path: any position, adjusted score
                    candidates.push({
                        player: p,
                        score: p.quality + (10 - dc) * 240,
                        dc: dc
                    });
                } else if (matchesPosition(p.pos, slot.fallbackPos)) {
                    // Fallback path: position must match, raw quality
                    candidates.push({
                        player: p,
                        score: p.quality,
                        dc: 0
                    });
                }
            }

            // Sort: quality/score DESC first, then dc ASC (dc=1 before dc=2)
            // dc sort is final so lower dc wins within comparable quality
            candidates.sort(function (a, b) {
                if (a.dc !== b.dc) return a.dc - b.dc;  // dc ASC (lower dc = higher priority)
                return b.score - a.score;                // score DESC within same dc
            });

            var starter = candidates.length > 0 ? candidates[0].player : null;
            if (starter) {
                taken[starter.pid] = true;
            }
            starters.push(starter);
        }

        // Bench roster: per slot, remaining candidates in same sorted order
        var benchTaken = {};
        var bench = [];

        for (var b = 0; b < SLOTS.length; b++) {
            var bSlot = SLOTS[b];
            var bFieldKey = bSlot.field;
            var bCandidates = [];

            for (var k = 0; k < activePlayers.length; k++) {
                var bp = activePlayers[k];
                if (taken[bp.pid] || benchTaken[bp.pid]) continue;

                var bdc = bp.dc[bFieldKey];
                if (bdc > 0) {
                    bCandidates.push({
                        player: bp,
                        score: bp.quality + (10 - bdc) * 240,
                        dc: bdc
                    });
                } else if (matchesPosition(bp.pos, bSlot.fallbackPos)) {
                    bCandidates.push({
                        player: bp,
                        score: bp.quality,
                        dc: 0
                    });
                }
            }

            // Same sort: dc ASC, then score DESC
            bCandidates.sort(function (a, b2) {
                if (a.dc !== b2.dc) return a.dc - b2.dc;
                return b2.score - a.score;
            });

            var slotBench = [];
            for (var m = 0; m < Math.min(bCandidates.length, BACKUP_ROWS); m++) {
                slotBench.push(bCandidates[m].player);
                benchTaken[bCandidates[m].player.pid] = true;
            }
            bench.push(slotBench);
        }

        return { starters: starters, bench: bench };
    }

    /**
     * Check if a player's position matches a slot's fallback position.
     * Handles compound positions like 'G' (PG or SG), 'F' (SF or PF), 'GF' (any guard/forward).
     */
    function matchesPosition(playerPos, slotPos) {
        if (playerPos === slotPos) return true;
        if (playerPos === 'G' && (slotPos === 'PG' || slotPos === 'SG')) return true;
        if (playerPos === 'F' && (slotPos === 'SF' || slotPos === 'PF')) return true;
        if (playerPos === 'GF') return slotPos !== 'C';
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

        // Bench rows
        for (var row = 0; row < BACKUP_ROWS; row++) {
            var label = row === 0 ? '1st' : '2nd';
            html += '<tr><td class="dc-lineup-preview__row-label">' + label + '</td>';
            for (var c = 0; c < SLOTS.length; c++) {
                var benchPlayer = lineup.bench[c][row];
                if (benchPlayer) {
                    html += '<td>' + escapeHtml(abbreviateName(benchPlayer.name)) + '</td>';
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
     * Shows base quality in the Pos cell and effective score (quality + bonus)
     * next to each role slot select where dc > 0.
     * Only renders on localhost.
     */
    function updateScoreDebug(players) {
        if (!isLocalhost()) return;

        var form = document.forms['DepthChartEntry'];
        if (!form) return;

        var rows = form.querySelectorAll('.depth-chart-table tbody tr[data-pid]');

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var quality = parseFloat(row.getAttribute('data-quality') || '0');
            var idx = i + 1;

            // Read minutes for this player
            var minSel = form.querySelector('select[name="min' + idx + '"]');
            var dcMin = minSel ? parseInt(minSel.value, 10) : 0;
            var minMultiplier = dcMin + 100;
            var effectiveQuality = quality * minMultiplier;

            // Update quality in Pos cell: base × (min+100) = effective
            var qualitySpan = row.querySelector('.dc-quality-debug');
            if (qualitySpan) {
                qualitySpan.textContent = ' (' + quality.toFixed(1) + '×' + minMultiplier + '=' + Math.round(effectiveQuality) + ')';
            }

            // Update effective score next to each role slot select
            for (var s = 0; s < SLOTS.length; s++) {
                var sel = form.querySelector('select[name="' + SLOTS[s].field + idx + '"]');
                if (!sel) continue;
                var scoreSpan = sel.parentNode.querySelector('.dc-score-debug');
                if (!scoreSpan) continue;

                var dc = parseInt(sel.value, 10);
                if (dc > 0) {
                    var bonus = (10 - dc) * 240;
                    var effective = effectiveQuality + bonus;
                    scoreSpan.textContent = Math.round(effective);
                } else {
                    scoreSpan.textContent = '';
                }
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
