/**
 * Depth Chart Lineup Preview
 *
 * Implements JSB's 5-pass lineup selection algorithm to show GMs a projected
 * starting lineup and bench based on their current depth chart settings.
 *
 * Updates live whenever a role slot select or active toggle changes.
 *
 * Algorithm (from decompiled JSB 5.60):
 *   Pass 1 (PG): dc_bh > 0 players, score = quality + (10 - dc) * 240
 *   Pass 2 (SG): dc_di > 0 players
 *   Pass 3 (SF): dc_oi > 0 players
 *   Pass 4 (PF): dc_df > 0 players
 *   Pass 5 (C):  dc_of > 0 players
 *   Fallback: if no dc > 0, select by position match on raw quality
 *   Bench: remaining dc > 0 players sorted by dc DESC, then quality DESC
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

            // Read dc values for each slot
            var dcValues = {};
            for (var s = 0; s < SLOTS.length; s++) {
                var sel = form.querySelector('select[name="' + SLOTS[s].field + idx + '"]');
                dcValues[SLOTS[s].field] = sel ? parseInt(sel.value, 10) : 0;
            }

            players.push({
                pid: pid,
                name: name,
                quality: quality,
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
     * Returns an object with:
     *   starters: array of 5 player objects (or null for unfilled slots)
     *   bench: array of 5 arrays, each containing up to BACKUP_ROWS player objects
     */
    function selectLineup(players) {
        var activePlayers = players.filter(function (p) { return p.active; });
        var taken = {};  // pid → true for players already assigned

        var starters = [];

        // 5 passes for starters
        for (var pass = 0; pass < SLOTS.length; pass++) {
            var slot = SLOTS[pass];
            var fieldKey = slot.field;
            var bestPlayer = null;
            var bestScore = -Infinity;

            // Bonus path: dc > 0 players
            for (var i = 0; i < activePlayers.length; i++) {
                var p = activePlayers[i];
                if (taken[p.pid]) continue;
                var dc = p.dc[fieldKey];
                if (dc > 0) {
                    var score = p.quality + (10 - dc) * 240;
                    if (score > bestScore) {
                        bestScore = score;
                        bestPlayer = p;
                    }
                }
            }

            // Fallback path: position match, raw quality
            if (!bestPlayer) {
                for (var j = 0; j < activePlayers.length; j++) {
                    var fp = activePlayers[j];
                    if (taken[fp.pid]) continue;
                    if (matchesPosition(fp.pos, slot.fallbackPos)) {
                        if (fp.quality > bestScore) {
                            bestScore = fp.quality;
                            bestPlayer = fp;
                        }
                    }
                }
            }

            if (bestPlayer) {
                taken[bestPlayer.pid] = true;
            }
            starters.push(bestPlayer);
        }

        // Bench roster: for each slot, find remaining dc > 0 players
        var benchTaken = {};  // pid → true for players already assigned as backup
        var bench = [];

        for (var b = 0; b < SLOTS.length; b++) {
            var bSlot = SLOTS[b];
            var bFieldKey = bSlot.field;
            var candidates = [];

            for (var k = 0; k < activePlayers.length; k++) {
                var bp = activePlayers[k];
                if (taken[bp.pid] || benchTaken[bp.pid]) continue;
                var bdc = bp.dc[bFieldKey];
                if (bdc > 0) {
                    candidates.push({ player: bp, dc: bdc });
                }
            }

            // Sort by dc DESC, then quality DESC
            candidates.sort(function (a, b2) {
                if (b2.dc !== a.dc) return b2.dc - a.dc;
                return b2.player.quality - a.player.quality;
            });

            var slotBench = [];
            for (var m = 0; m < Math.min(candidates.length, BACKUP_ROWS); m++) {
                slotBench.push(candidates[m].player);
                benchTaken[candidates[m].player.pid] = true;
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
        html += '<th></th>';
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
     * Recalculate and re-render the lineup preview.
     */
    function recalculate() {
        var players = collectPlayers();
        if (players.length === 0) return;
        var lineup = selectLineup(players);
        renderPreview(lineup);
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
