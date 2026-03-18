/**
 * Trade Review Roster Preview
 *
 * Shows roster preview panels on the trade review page (op=reviewtrade).
 * Each trade offer card gets an independent preview panel that can be
 * toggled via a "Preview" button.
 *
 * Reads config from window.IBL_TRADE_REVIEW_CONFIGS:
 *   { [offerId]: { rosterPreviewApiBaseUrl, fromTeam, toTeam, fromTeamId,
 *     toTeamId, fromPids, toPids, fromCash, toCash, cashStartYear,
 *     cashEndYear, seasonEndingYear, fromColor1, toColor1, userTeamId } }
 */
(function () {
    'use strict';

    var configs = window.IBL_TRADE_REVIEW_CONFIGS;
    if (!configs) {
        return;
    }

    // Per-panel state keyed by offerId
    var panelState = {};

    /**
     * Initialize state for each offer panel.
     */
    function initPanels() {
        var offerIds = Object.keys(configs);
        for (var i = 0; i < offerIds.length; i++) {
            var offerId = offerIds[i];
            var cfg = configs[offerId];
            var panel = document.getElementById('trade-review-preview-' + offerId);
            if (!panel) {
                continue;
            }

            // Default to user's team
            var initialTeamId = cfg.userTeamId === cfg.fromTeamId
                ? cfg.fromTeamId : cfg.toTeamId;

            panelState[offerId] = {
                panel: panel,
                config: cfg,
                currentTeamId: initialTeamId,
                currentDisplay: 'ratings',
                abortController: null,
                initialized: false
            };

            bindPanelEvents(offerId);
        }

        // Bind preview toggle buttons
        var buttons = document.querySelectorAll('[data-preview-offer]');
        for (var b = 0; b < buttons.length; b++) {
            buttons[b].addEventListener('click', handlePreviewToggle);
        }
    }

    /**
     * Toggle preview panel visibility.
     */
    function handlePreviewToggle() {
        var offerId = this.getAttribute('data-preview-offer');
        var state = panelState[offerId];
        if (!state) {
            return;
        }

        var isVisible = state.panel.style.display !== 'none';

        if (isVisible) {
            state.panel.style.display = 'none';
            this.textContent = 'Preview';
        } else {
            state.panel.style.display = '';
            this.textContent = 'Hide Preview';

            if (!state.initialized) {
                state.initialized = true;
                fetchRosterPreview(offerId);
            }

            // Scroll preview into view on mobile
            if (window.innerWidth <= 768) {
                state.panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    }

    /**
     * Bind logo click and tab click events for a specific panel.
     */
    function bindPanelEvents(offerId) {
        var state = panelState[offerId];
        var panel = state.panel;

        // Logo clicks
        var logos = panel.querySelectorAll('.trade-roster-preview__logo');
        for (var i = 0; i < logos.length; i++) {
            logos[i].addEventListener('click', function (oid) {
                return function () {
                    var teamId = parseInt(this.getAttribute('data-team-id'), 10);
                    var st = panelState[oid];
                    if (teamId && teamId !== st.currentTeamId) {
                        st.currentTeamId = teamId;
                        updateActiveLogoState(oid);
                        updateTabBarColors(oid);
                        fetchRosterPreview(oid);
                    }
                };
            }(offerId));
        }

        // Tab clicks
        var tabs = panel.querySelectorAll('.trade-roster-preview__tabs .ibl-tab');
        for (var t = 0; t < tabs.length; t++) {
            tabs[t].addEventListener('click', function (oid) {
                return function () {
                    var display = this.getAttribute('data-display');
                    var st = panelState[oid];
                    if (display && display !== st.currentDisplay) {
                        st.currentDisplay = display;
                        updateActiveTab(oid);
                        fetchRosterPreview(oid);
                    }
                };
            }(offerId));
        }
    }

    /**
     * Update active logo visual state for a panel.
     */
    function updateActiveLogoState(offerId) {
        var state = panelState[offerId];
        var logos = state.panel.querySelectorAll('.trade-roster-preview__logo');
        for (var i = 0; i < logos.length; i++) {
            var teamId = parseInt(logos[i].getAttribute('data-team-id'), 10);
            if (teamId === state.currentTeamId) {
                logos[i].classList.add('trade-roster-preview__logo--active');
            } else {
                logos[i].classList.remove('trade-roster-preview__logo--active');
            }
        }
    }

    /**
     * Update tab bar colors to match the currently selected team.
     */
    function updateTabBarColors(offerId) {
        var state = panelState[offerId];
        var cfg = state.config;
        var tabBar = state.panel.querySelector('.trade-roster-preview__tabs');
        if (!tabBar) {
            return;
        }
        var color = state.currentTeamId === cfg.fromTeamId
            ? cfg.fromColor1 : cfg.toColor1;
        tabBar.style.setProperty('--team-tab-bg-color', '#' + color);
        tabBar.style.setProperty('--team-tab-active-color', '#' + color);
    }

    /**
     * Update active tab visual state for a panel.
     */
    function updateActiveTab(offerId) {
        var state = panelState[offerId];
        var tabs = state.panel.querySelectorAll('.trade-roster-preview__tabs .ibl-tab');
        for (var t = 0; t < tabs.length; t++) {
            if (tabs[t].getAttribute('data-display') === state.currentDisplay) {
                tabs[t].classList.add('ibl-tab--active');
            } else {
                tabs[t].classList.remove('ibl-tab--active');
            }
        }
    }

    /**
     * Fetch and render roster preview for a specific offer panel.
     */
    function fetchRosterPreview(offerId) {
        var state = panelState[offerId];
        var cfg = state.config;
        var container = state.panel.querySelector('.table-scroll-container');
        if (!container) {
            return;
        }

        // Determine addPids/removePids based on which team is being viewed
        var addPids, removePids;
        if (state.currentTeamId === cfg.fromTeamId) {
            // Viewing the "from" team: they lose fromPids, gain toPids
            removePids = cfg.fromPids;
            addPids = cfg.toPids;
        } else {
            // Viewing the "to" team: they lose toPids, gain fromPids
            removePids = cfg.toPids;
            addPids = cfg.fromPids;
        }

        // Cancel previous request
        if (state.abortController) {
            state.abortController.abort();
        }
        state.abortController = new AbortController();

        container.innerHTML = '<div class="trade-roster-preview__loading">Loading</div>';

        var url = cfg.rosterPreviewApiBaseUrl
            + '&teamID=' + encodeURIComponent(state.currentTeamId)
            + '&addPids=' + encodeURIComponent(addPids.join(','))
            + '&removePids=' + encodeURIComponent(removePids.join(','))
            + '&display=' + encodeURIComponent(state.currentDisplay);

        // Add cash params for contracts view
        if (state.currentDisplay === 'contracts') {
            url += '&userTeam=' + encodeURIComponent(cfg.fromTeam)
                 + '&partnerTeam=' + encodeURIComponent(cfg.toTeam)
                 + '&userTeamId=' + encodeURIComponent(cfg.fromTeamId)
                 + '&cashStartYear=' + encodeURIComponent(cfg.cashStartYear)
                 + '&cashEndYear=' + encodeURIComponent(cfg.cashEndYear);

            for (var yr = cfg.cashStartYear; yr <= cfg.cashEndYear; yr++) {
                // Map cash based on which team is being viewed
                if (state.currentTeamId === cfg.fromTeamId) {
                    url += '&userCash' + yr + '=' + (cfg.fromCash[yr] || 0);
                    url += '&partnerCash' + yr + '=' + (cfg.toCash[yr] || 0);
                } else {
                    url += '&userCash' + yr + '=' + (cfg.toCash[yr] || 0);
                    url += '&partnerCash' + yr + '=' + (cfg.fromCash[yr] || 0);
                }
            }
        }

        fetch(url, { signal: state.abortController.signal })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to fetch roster preview');
                }
                return response.json();
            })
            .then(function (data) {
                if (data.html) {
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
     * Classify trade rows as incoming/outgoing/cash, then reorder:
     *   1. Non-traded players (original order)
     *   2. Existing cash rows (data-cash-row without trade-incoming-row)
     *   3. Outgoing players (trade-outgoing-row)
     *   4. Incoming players (trade-incoming-row)
     *   5. New cash rows (data-cash-row with trade-incoming-row)
     */
    function classifyAndReorderTradeRows(container, incomingPids, outgoingPids) {
        var tbodies = container.querySelectorAll('tbody');
        for (var t = 0; t < tbodies.length; t++) {
            var tbody = tbodies[t];
            var rows = tbody.querySelectorAll('tr');
            var existingCashRows = [];
            var outgoingRows = [];
            var incomingRows = [];
            var newCashRows = [];

            for (var r = 0; r < rows.length; r++) {
                var isCashRow = rows[r].hasAttribute('data-cash-row');
                var pid = getRowPid(rows[r]);

                if (isCashRow) {
                    if (pid === null || pid === 0) {
                        rows[r].classList.add('trade-incoming-row');
                        newCashRows.push(rows[r]);
                    } else {
                        existingCashRows.push(rows[r]);
                    }
                } else if (pid !== null && outgoingPids.indexOf(pid) !== -1) {
                    rows[r].classList.add('trade-outgoing-row');
                    outgoingRows.push(rows[r]);
                } else if (pid !== null && incomingPids.indexOf(pid) !== -1) {
                    rows[r].classList.add('trade-incoming-row');
                    incomingRows.push(rows[r]);
                }
            }

            for (var ec = 0; ec < existingCashRows.length; ec++) {
                tbody.appendChild(existingCashRows[ec]);
            }
            for (var o = 0; o < outgoingRows.length; o++) {
                tbody.appendChild(outgoingRows[o]);
            }
            for (var i = 0; i < incomingRows.length; i++) {
                tbody.appendChild(incomingRows[i]);
            }
            for (var nc = 0; nc < newCashRows.length; nc++) {
                tbody.appendChild(newCashRows[nc]);
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

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPanels);
    } else {
        initPanels();
    }
})();
