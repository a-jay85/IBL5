/**
 * Trade Roster Preview Panel
 *
 * Dynamically shows a full-roster preview (with dropdown views) for
 * the team currently being previewed. Toggling between teams is done
 * by clicking the team logos in the panel header.
 *
 * Reads config from window.IBL_TRADE_CONFIG:
 *   {
 *     rosterPreviewApiBaseUrl: 'modules.php?name=Trading&op=roster-preview-api',
 *     userTeamId:              1,
 *     partnerTeamId:           2,
 *     switchCounter:           12
 *   }
 */
(function () {
    'use strict';

    var config = window.IBL_TRADE_CONFIG;
    if (!config || !config.rosterPreviewApiBaseUrl) {
        return;
    }

    var panel = document.getElementById('trade-roster-preview');
    if (!panel) {
        return;
    }

    var form = document.querySelector('form[name="Trade_Offer"]');
    if (!form) {
        return;
    }

    // State
    var currentTeamId = config.userTeamId;
    var currentDisplay = 'ratings';
    var debounceTimer = null;
    var abortController = null;

    // Tab elements
    var tabBar = panel.querySelector('.trade-roster-preview__tabs');
    var tabs = panel.querySelectorAll('.trade-roster-preview__tabs .ibl-tab');

    // ========================================================================
    // PID COLLECTION (same logic as trade-comparison.js)
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

    // ========================================================================
    // TAB STATE
    // ========================================================================

    function updateActiveTab() {
        for (var t = 0; t < tabs.length; t++) {
            if (tabs[t].getAttribute('data-display') === currentDisplay) {
                tabs[t].classList.add('ibl-tab--active');
            } else {
                tabs[t].classList.remove('ibl-tab--active');
            }
        }
    }

    // ========================================================================
    // CAP WARNING — over hard cap indicators
    // ========================================================================

    var contractsTab = null;
    for (var ct = 0; ct < tabs.length; ct++) {
        if (tabs[ct].getAttribute('data-display') === 'contracts') {
            contractsTab = tabs[ct];
            break;
        }
    }

    /**
     * Collect the total salary of checked players per side.
     * Returns { userSalary: number, partnerSalary: number }
     */
    function getCheckedSalaries() {
        var switchCounter = config.switchCounter;
        var userSalary = 0;
        var partnerSalary = 0;
        var k = 0;

        while (true) {
            var checkbox = form.elements['check' + k];
            if (!checkbox) {
                break;
            }

            var typeField = form.elements['type' + k];
            var isPlayer = typeField && typeField.value === '1';

            if (isPlayer && checkbox.type === 'checkbox' && checkbox.checked) {
                var contractField = form.elements['contract' + k];
                var salary = contractField ? parseInt(contractField.value, 10) || 0 : 0;
                if (k < switchCounter) {
                    userSalary += salary;
                } else {
                    partnerSalary += salary;
                }
            }

            k++;
        }

        return { userSalary: userSalary, partnerSalary: partnerSalary };
    }

    /**
     * Get current-season cash exchange amounts from the form inputs.
     * Returns { userSendsCash: number, partnerSendsCash: number }
     */
    function getCurrentSeasonCash() {
        var yr = config.cashStartYear;
        var userInput = form.elements['userSendsCash' + yr];
        var partnerInput = form.elements['partnerSendsCash' + yr];
        return {
            userSendsCash: userInput ? parseInt(userInput.value, 10) || 0 : 0,
            partnerSendsCash: partnerInput ? parseInt(partnerInput.value, 10) || 0 : 0,
        };
    }

    /**
     * Check if post-trade cap totals exceed the hard cap and toggle warnings.
     */
    function updateCapWarnings() {
        var hardCap = config.hardCap;
        var userBaseCap = config.userFutureSalary[0] || 0;
        var partnerBaseCap = config.partnerFutureSalary[0] || 0;

        var salaries = getCheckedSalaries();
        var cash = getCurrentSeasonCash();

        // Post-trade cap: base - outgoing salaries + incoming salaries +/- cash
        var userPostCap = userBaseCap - salaries.userSalary + salaries.partnerSalary
            + cash.partnerSendsCash - cash.userSendsCash;
        var partnerPostCap = partnerBaseCap - salaries.partnerSalary + salaries.userSalary
            + cash.userSendsCash - cash.partnerSendsCash;

        var userOver = userPostCap > hardCap;
        var partnerOver = partnerPostCap > hardCap;

        // 1) Red glow on preview panel logos
        var userLogo = panel.querySelector(
            '.trade-roster-preview__logo[data-team-id="' + config.userTeamId + '"]');
        var partnerLogo = panel.querySelector(
            '.trade-roster-preview__logo[data-team-id="' + config.partnerTeamId + '"]');
        if (userLogo) {
            userLogo.classList.toggle('cap-warning-logo', userOver);
        }
        if (partnerLogo) {
            partnerLogo.classList.toggle('cap-warning-logo', partnerOver);
        }

        // 2) Red glow + tint on roster checklist logo banners
        var userBanner = form.querySelector(
            '.trading-roster[data-team-id="' + config.userTeamId + '"] thead tr:first-child th');
        var partnerBanner = form.querySelector(
            '.trading-roster[data-team-id="' + config.partnerTeamId + '"] thead tr:first-child th');
        if (userBanner) {
            userBanner.classList.toggle('cap-warning-banner', userOver);
        }
        if (partnerBanner) {
            partnerBanner.classList.toggle('cap-warning-banner', partnerOver);
        }

        // 3) Contracts tab — only red when the currently viewed team is over cap
        var viewedTeamOverCap = currentTeamId === config.userTeamId
            ? userOver : partnerOver;
        if (contractsTab) {
            contractsTab.classList.toggle('cap-warning-tab', viewedTeamOverCap);
        }

        // 4) Cap total cells — first row (current season) in each team's table
        var userCapCell = form.querySelector(
            '.trading-cap-totals[data-side="user"] tbody tr:first-child td');
        var partnerCapCell = form.querySelector(
            '.trading-cap-totals[data-side="partner"] tbody tr:first-child td');
        if (userCapCell) {
            userCapCell.classList.toggle('cap-warning-cell', userOver);
        }
        if (partnerCapCell) {
            partnerCapCell.classList.toggle('cap-warning-cell', partnerOver);
        }
    }

    // ========================================================================
    // FETCH AND RENDER
    // ========================================================================

    function fetchRosterPreview() {
        var container = panel.querySelector('.table-scroll-container');
        if (!container) {
            return;
        }

        var checked = getCheckedPids();
        var anyChecked = checked.user.length > 0 || checked.partner.length > 0;

        panel.style.display = anyChecked ? '' : 'none';

        if (!anyChecked) {
            container.innerHTML = '<div class="trade-roster-preview__empty">Select players to preview roster changes</div>';
            return;
        }

        // Determine addPids and removePids based on which team is being viewed
        var addPids, removePids;
        if (currentTeamId === config.userTeamId) {
            removePids = checked.user;   // user's players being sent away
            addPids = checked.partner;   // partner's players being received
        } else {
            removePids = checked.partner; // partner's players being sent away
            addPids = checked.user;       // user's players being received
        }

        // Cancel previous request
        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();

        container.innerHTML = '<div class="trade-roster-preview__loading">Loading</div>';

        var url = config.rosterPreviewApiBaseUrl
            + '&teamID=' + encodeURIComponent(currentTeamId)
            + '&addPids=' + encodeURIComponent(addPids.join(','))
            + '&removePids=' + encodeURIComponent(removePids.join(','));

        url += '&display=' + encodeURIComponent(currentDisplay);

        // Add cash params for contracts view
        if (currentDisplay === 'contracts') {
            url += '&userTeam=' + encodeURIComponent(config.userTeam)
                 + '&partnerTeam=' + encodeURIComponent(config.partnerTeam)
                 + '&userTeamId=' + encodeURIComponent(config.userTeamId)
                 + '&cashStartYear=' + encodeURIComponent(config.cashStartYear)
                 + '&cashEndYear=' + encodeURIComponent(config.cashEndYear);

            for (var yr = config.cashStartYear; yr <= config.cashEndYear; yr++) {
                var uInput = form.elements['userSendsCash' + yr];
                var pInput = form.elements['partnerSendsCash' + yr];
                url += '&userCash' + yr + '=' + (uInput ? parseInt(uInput.value, 10) || 0 : 0);
                url += '&partnerCash' + yr + '=' + (pInput ? parseInt(pInput.value, 10) || 0 : 0);
            }
        }

        fetch(url, { signal: abortController.signal })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to fetch roster preview');
                }
                return response.json();
            })
            .then(function (data) {
                if (data.html) {
                    // Clear inline width constraints
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

                    // Classify and reorder trade-involved player rows
                    classifyAndReorderTradeRows(container, addPids, removePids);

                    // Highlight cash rows with incoming style
                    var cashRows = container.querySelectorAll('tr[data-cash-row]');
                    for (var c = 0; c < cashRows.length; c++) {
                        cashRows[c].classList.add('trade-incoming-row');
                    }
                } else {
                    container.innerHTML = '<div class="trade-roster-preview__empty">No data available</div>';
                }
            })
            .catch(function (err) {
                if (err.name !== 'AbortError') {
                    container.innerHTML = '<div class="trade-roster-preview__empty">Error loading roster preview</div>';
                }
            });
    }

    /**
     * Classify trade rows as incoming/outgoing, then reorder so that
     * outgoing rows sink to the bottom and incoming rows sit below them.
     */
    function classifyAndReorderTradeRows(container, incomingPids, outgoingPids) {
        if (incomingPids.length === 0 && outgoingPids.length === 0) {
            return;
        }

        var tbodies = container.querySelectorAll('tbody');
        for (var t = 0; t < tbodies.length; t++) {
            var tbody = tbodies[t];
            var rows = tbody.querySelectorAll('tr');
            var outgoingRows = [];
            var incomingRows = [];

            for (var r = 0; r < rows.length; r++) {
                var pid = getRowPid(rows[r]);
                if (pid === null) {
                    continue;
                }

                if (outgoingPids.indexOf(pid) !== -1) {
                    rows[r].classList.add('trade-outgoing-row');
                    outgoingRows.push(rows[r]);
                } else if (incomingPids.indexOf(pid) !== -1) {
                    rows[r].classList.add('trade-incoming-row');
                    incomingRows.push(rows[r]);
                }
            }

            // Move outgoing rows to the bottom, then incoming rows below them
            for (var o = 0; o < outgoingRows.length; o++) {
                tbody.appendChild(outgoingRows[o]);
            }
            for (var i = 0; i < incomingRows.length; i++) {
                tbody.appendChild(incomingRows[i]);
            }
        }
    }

    /**
     * Extract PID from a table row's player link (href containing pid=).
     */
    function getRowPid(row) {
        var links = row.querySelectorAll('a[href*="pid="]');
        for (var l = 0; l < links.length; l++) {
            var href = links[l].getAttribute('href') || '';
            var match = href.match(/pid=(\d+)/);
            if (match) {
                return parseInt(match[1], 10);
            }
        }
        return null;
    }

    // ========================================================================
    // LOGO TOGGLE
    // ========================================================================

    function updateActiveLogoState() {
        var logos = panel.querySelectorAll('.trade-roster-preview__logo');
        for (var i = 0; i < logos.length; i++) {
            var teamId = parseInt(logos[i].getAttribute('data-team-id'), 10);
            if (teamId === currentTeamId) {
                logos[i].classList.add('trade-roster-preview__logo--active');
            } else {
                logos[i].classList.remove('trade-roster-preview__logo--active');
            }
        }
    }

    /**
     * Update tab bar colors to match the currently selected team.
     */
    function updateTabBarColors() {
        if (!tabBar) {
            return;
        }
        var color = currentTeamId === config.userTeamId
            ? config.userTeamColor1
            : config.partnerTeamColor1;
        tabBar.style.setProperty('--team-tab-bg-color', '#' + color);
        tabBar.style.setProperty('--team-tab-active-color', '#' + color);
    }

    // ========================================================================
    // DEBOUNCE
    // ========================================================================

    function debouncedRefresh() {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(fetchRosterPreview, 300);
    }

    // ========================================================================
    // EVENT LISTENERS
    // ========================================================================

    // Checkbox changes on the form
    form.addEventListener('change', function (e) {
        if (e.target.type === 'checkbox') {
            updateCapWarnings();
            debouncedRefresh();
        }
    });

    // Cash exchange input changes
    form.addEventListener('input', function (e) {
        if (e.target.name && (e.target.name.indexOf('userSendsCash') === 0
            || e.target.name.indexOf('partnerSendsCash') === 0)) {
            updateCapWarnings();
            debouncedRefresh();
        }
    });

    // Logo clicks to switch team
    var logos = panel.querySelectorAll('.trade-roster-preview__logo');
    for (var i = 0; i < logos.length; i++) {
        logos[i].addEventListener('click', function () {
            var teamId = parseInt(this.getAttribute('data-team-id'), 10);
            if (teamId && teamId !== currentTeamId) {
                currentTeamId = teamId;
                updateActiveLogoState();
                updateTabBarColors();
                updateCapWarnings();
                fetchRosterPreview();
            }
        });
    }

    // Tab clicks
    for (var t = 0; t < tabs.length; t++) {
        tabs[t].addEventListener('click', function () {
            var display = this.getAttribute('data-display');
            if (display && display !== currentDisplay) {
                currentDisplay = display;
                updateActiveTab();
                fetchRosterPreview();
            }
        });
    }

    // Session-restored checkboxes on page load
    document.addEventListener('DOMContentLoaded', function () {
        var checked = getCheckedPids();
        if (checked.user.length > 0 || checked.partner.length > 0) {
            updateCapWarnings();
            fetchRosterPreview();
        }
    });
})();
