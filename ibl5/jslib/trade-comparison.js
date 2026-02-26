/**
 * Trade Comparison Panel + Live Cap Totals
 *
 * Dynamically loads player stat tables when checkboxes are toggled on the
 * trade offer form. Also recalculates and displays live cap totals as
 * players are checked/unchecked and cash exchange values change.
 *
 * Reads config from window.IBL_TRADE_CONFIG:
 *   {
 *     apiBaseUrl:              'modules.php?name=Trading&op=comparison-api',
 *     userTeam:                'Lakers',
 *     partnerTeam:             'Celtics',
 *     userTeamId:              1,
 *     partnerTeamId:           2,
 *     switchCounter:           12,
 *     userPlayerContracts:     { "101": [500, 600, 0, 0, 0, 0], ... },
 *     partnerPlayerContracts:  { "205": [300, 400, 500, 0, 0, 0], ... },
 *     userFutureSalary:        [3500, 2800, 1200, 0, 0, 0],
 *     partnerFutureSalary:     [4000, 3200, 2000, 0, 0, 0],
 *     hardCap:                 7000,
 *     seasonEndingYear:        2025,
 *     seasonPhase:             'Regular Season',
 *     cashStartYear:           1,
 *     cashEndYear:             6
 *   }
 */
(function () {
    'use strict';

    var config = window.IBL_TRADE_CONFIG;
    if (!config || !config.apiBaseUrl) {
        return;
    }

    var panel = document.getElementById('trade-comparison-panel');
    if (!panel) {
        return;
    }

    var form = document.querySelector('form[name="Trade_Offer"]');
    if (!form) {
        return;
    }

    // State
    var debounceTimer = null;
    var abortControllers = { user: null, partner: null };
    var currentDisplay = 'ratings';

    // ========================================================================
    // COMPARISON PANEL LOGIC
    // ========================================================================

    /**
     * Collect checked player PIDs, separated by team side.
     * Returns { user: [pid, ...], partner: [pid, ...] }
     */
    function getCheckedPids() {
        var switchCounter = config.switchCounter;
        var userPids = [];
        var partnerPids = [];
        var k = 0;

        while (true) {
            var checkbox = form.elements['check' + k];
            if (!checkbox) {
                break;
            }

            // Only player checkboxes have type hidden with value "1"
            var typeField = form.elements['type' + k];
            var isPlayer = typeField && typeField.value === '1';

            if (isPlayer && checkbox.type === 'checkbox' && checkbox.checked) {
                var indexField = form.elements['index' + k];
                if (indexField) {
                    var pid = parseInt(indexField.value, 10);
                    if (!isNaN(pid)) {
                        if (k < switchCounter) {
                            userPids.push(pid);
                        } else {
                            partnerPids.push(pid);
                        }
                    }
                }
            }

            k++;
        }

        return { user: userPids, partner: partnerPids };
    }

    /**
     * Fetch comparison table HTML for one team side.
     */
    function fetchComparison(side, pids, teamID, display) {
        var container = panel.querySelector('.trade-comparison__team[data-side="' + side + '"] .table-scroll-container');
        if (!container) {
            return;
        }

        if (pids.length === 0) {
            container.innerHTML = '<div class="trade-comparison__empty">No players selected</div>';
            return;
        }

        // Cancel previous request for this side
        if (abortControllers[side]) {
            abortControllers[side].abort();
        }
        abortControllers[side] = new AbortController();

        container.innerHTML = '<div class="trade-comparison__loading">Loading</div>';

        var url = config.apiBaseUrl
            + '&pids=' + encodeURIComponent(pids.join(','))
            + '&teamID=' + encodeURIComponent(teamID);

        if (display.indexOf('split:') === 0) {
            url += '&display=split&split=' + encodeURIComponent(display.substring(6));
        } else {
            url += '&display=' + encodeURIComponent(display);
        }

        fetch(url, { signal: abortControllers[side].signal })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to fetch comparison');
                }
                return response.json();
            })
            .then(function (data) {
                if (data.html) {
                    // Clear inline width constraints from responsive-tables.js
                    container.style.width = '';
                    container.style.maxWidth = '';
                    var wrapper = container.closest('.table-scroll-wrapper');
                    if (wrapper) {
                        wrapper.style.maxWidth = '';
                    }

                    container.innerHTML = data.html;

                    // Re-initialize sorting
                    if (typeof sorttable !== 'undefined') {
                        var tables = container.querySelectorAll('table.sortable');
                        for (var i = 0; i < tables.length; i++) {
                            sorttable.makeSortable(tables[i]);
                        }
                    }

                    // Re-run responsive table logic
                    if (typeof window.IBL_refreshResponsiveTables === 'function') {
                        window.IBL_refreshResponsiveTables();
                    }
                } else {
                    container.innerHTML = '<div class="trade-comparison__empty">No data available</div>';
                }
            })
            .catch(function (err) {
                if (err.name !== 'AbortError') {
                    container.innerHTML = '<div class="trade-comparison__empty">Error loading comparison</div>';
                }
            });
    }

    /**
     * Main handler: fetch comparison panels for both sides.
     */
    function refreshComparison() {
        var checked = getCheckedPids();
        var anyChecked = checked.user.length > 0 || checked.partner.length > 0;

        panel.style.display = anyChecked ? '' : 'none';

        fetchComparison('user', checked.user, config.userTeamId, currentDisplay);
        fetchComparison('partner', checked.partner, config.partnerTeamId, currentDisplay);
    }

    /**
     * Debounced refresh to avoid excessive API calls during rapid checking.
     */
    function debouncedRefresh() {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(refreshComparison, 300);
    }

    // Delegated checkbox change listener
    form.addEventListener('change', function (e) {
        if (e.target.type === 'checkbox') {
            debouncedRefresh();
            updateCapTotals();
        }
    });

    // Shared dropdown change listener
    var sharedDropdown = document.getElementById('trade-comparison-display');
    if (sharedDropdown) {
        sharedDropdown.addEventListener('change', function () {
            currentDisplay = sharedDropdown.value;
            refreshComparison();
        });
    }

    // ========================================================================
    // LIVE CAP TOTALS LOGIC
    // ========================================================================

    /**
     * Get cash amounts from the cash exchange inputs for a given side and year.
     */
    function getCashValues() {
        var userSends = [];
        var partnerSends = [];

        for (var i = config.cashStartYear; i <= config.cashEndYear; i++) {
            var userInput = form.elements['userSendsCash' + i];
            var partnerInput = form.elements['partnerSendsCash' + i];

            var userVal = userInput ? parseInt(userInput.value, 10) || 0 : 0;
            var partnerVal = partnerInput ? parseInt(partnerInput.value, 10) || 0 : 0;

            userSends.push({ year: i, amount: userVal });
            partnerSends.push({ year: i, amount: partnerVal });
        }

        return { userSends: userSends, partnerSends: partnerSends };
    }

    /**
     * Determine which display year indices (0-5) a cash year maps to.
     * Cash year 1 maps to display index 0 during regular season,
     * but during offseason cashStartYear is 2, so year 2 maps to index 0.
     */
    function cashYearToDisplayIndex(cashYear) {
        return cashYear - config.cashStartYear;
    }

    /**
     * Recalculate and update cap total cells in the DOM.
     */
    function updateCapTotals() {
        var checked = getCheckedPids();
        var cash = getCashValues();

        var isOffseason = config.seasonPhase === 'Playoffs'
            || config.seasonPhase === 'Draft'
            || config.seasonPhase === 'Free Agency';

        var seasonsToDisplay = isOffseason ? 5 : 6;

        // Calculate salary deltas from checked players
        var userOutgoing = [0, 0, 0, 0, 0, 0];
        var partnerOutgoing = [0, 0, 0, 0, 0, 0];

        for (var u = 0; u < checked.user.length; u++) {
            var uPid = String(checked.user[u]);
            var uContract = config.userPlayerContracts[uPid];
            if (uContract) {
                for (var y = 0; y < 6; y++) {
                    userOutgoing[y] += uContract[y] || 0;
                }
            }
        }

        for (var p = 0; p < checked.partner.length; p++) {
            var pPid = String(checked.partner[p]);
            var pContract = config.partnerPlayerContracts[pPid];
            if (pContract) {
                for (var y2 = 0; y2 < 6; y2++) {
                    partnerOutgoing[y2] += pContract[y2] || 0;
                }
            }
        }

        // Calculate cash deltas per display index
        var userCashOut = [0, 0, 0, 0, 0, 0];
        var userCashIn = [0, 0, 0, 0, 0, 0];
        var partnerCashOut = [0, 0, 0, 0, 0, 0];
        var partnerCashIn = [0, 0, 0, 0, 0, 0];

        for (var c = 0; c < cash.userSends.length; c++) {
            var idx = cashYearToDisplayIndex(cash.userSends[c].year);
            if (idx >= 0 && idx < 6) {
                userCashOut[idx] += cash.userSends[c].amount;
                partnerCashIn[idx] += cash.userSends[c].amount;
            }
        }

        for (var c2 = 0; c2 < cash.partnerSends.length; c2++) {
            var idx2 = cashYearToDisplayIndex(cash.partnerSends[c2].year);
            if (idx2 >= 0 && idx2 < 6) {
                partnerCashOut[idx2] += cash.partnerSends[c2].amount;
                userCashIn[idx2] += cash.partnerSends[c2].amount;
            }
        }

        // Update each cap total row (two separate card tables)
        var userCapTable = document.querySelector('.trading-cap-totals[data-side="user"]');
        var partnerCapTable = document.querySelector('.trading-cap-totals[data-side="partner"]');
        if (!userCapTable || !partnerCapTable) {
            return;
        }

        var userRows = userCapTable.querySelectorAll('tbody tr');
        var partnerRows = partnerCapTable.querySelectorAll('tbody tr');

        for (var z = 0; z < seasonsToDisplay && z < userRows.length; z++) {
            var userCell = userRows[z].querySelector('td');
            var partnerCell = partnerRows[z] ? partnerRows[z].querySelector('td') : null;
            if (!userCell || !partnerCell) {
                continue;
            }

            var userBase = config.userFutureSalary[z] || 0;
            var partnerBase = config.partnerFutureSalary[z] || 0;

            // User post-trade = base - outgoing + incoming - cash sent + cash received
            var userPost = userBase - userOutgoing[z] + partnerOutgoing[z] - userCashOut[z] + userCashIn[z];
            var partnerPost = partnerBase - partnerOutgoing[z] + userOutgoing[z] - partnerCashOut[z] + partnerCashIn[z];

            var anyChanges = checked.user.length > 0 || checked.partner.length > 0
                || userCashOut[z] > 0 || partnerCashOut[z] > 0;

            // Build display strings
            var displayEndingYear = config.seasonEndingYear;
            if (isOffseason) {
                displayEndingYear++;
            }
            var yearLabel = (displayEndingYear + z - 1) + '-' + (displayEndingYear + z);

            if (anyChanges) {
                userCell.innerHTML = escapeHtml(yearLabel) + ': '
                    + userBase + ' &rarr; '
                    + '<span class="trade-comparison__delta' + (userPost > config.hardCap ? ' trade-comparison__delta--over' : '') + '">'
                    + userPost + '</span>';
                partnerCell.innerHTML = escapeHtml(yearLabel) + ': '
                    + partnerBase + ' &rarr; '
                    + '<span class="trade-comparison__delta' + (partnerPost > config.hardCap ? ' trade-comparison__delta--over' : '') + '">'
                    + partnerPost + '</span>';
            } else {
                userCell.innerHTML = escapeHtml(yearLabel) + ': ' + userBase;
                partnerCell.innerHTML = escapeHtml(yearLabel) + ': ' + partnerBase;
            }
        }
    }

    /**
     * Simple HTML escaping for user-provided team names.
     */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Listen for cash input changes (two card tables)
    var cashTables = document.querySelectorAll('.trading-cash-exchange');
    for (var ct = 0; ct < cashTables.length; ct++) {
        cashTables[ct].addEventListener('input', function (e) {
            if (e.target.type === 'number') {
                updateCapTotals();
            }
        });
    }

    // Initial cap totals state (in case of session-restored checkboxes)
    document.addEventListener('DOMContentLoaded', function () {
        var checked = getCheckedPids();
        if (checked.user.length > 0 || checked.partner.length > 0) {
            refreshComparison();
            updateCapTotals();
        }
    });
})();
