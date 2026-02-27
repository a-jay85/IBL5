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
    var headerSelectPopulated = false;

    // Header dropdown (replaces static title)
    var headerSelect = panel.querySelector('.trade-roster-preview__select');

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
    // HEADER DROPDOWN POPULATION
    // ========================================================================

    /**
     * Populate the header <select> from the API response's dropdown options.
     * Only runs once — subsequent responses just restore the value.
     */
    function populateHeaderSelect(container) {
        if (headerSelectPopulated || !headerSelect) {
            return;
        }

        var responseSelect = container.querySelector('.ibl-view-select');
        if (!responseSelect) {
            return;
        }

        // Clone all optgroups and options into the header select
        headerSelect.innerHTML = responseSelect.innerHTML;
        headerSelect.value = currentDisplay;
        headerSelectPopulated = true;
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

        if (currentDisplay.indexOf('split:') === 0) {
            url += '&display=split&split=' + encodeURIComponent(currentDisplay.substring(6));
        } else {
            url += '&display=' + encodeURIComponent(currentDisplay);
        }

        // Collect all trade-involved PIDs for highlighting
        var tradePids = addPids.concat(removePids);

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

                    // Populate the header dropdown from response (first time only)
                    populateHeaderSelect(container);

                    // Restore header select value
                    if (headerSelect) {
                        headerSelect.value = currentDisplay;
                    }

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

                    // Highlight trade-involved player rows
                    highlightTradeRows(container, tradePids);
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
     * Add .trade-involved-row class to rows containing trade-involved player links.
     */
    function highlightTradeRows(container, tradePids) {
        if (tradePids.length === 0) {
            return;
        }

        var rows = container.querySelectorAll('tr');
        for (var r = 0; r < rows.length; r++) {
            var links = rows[r].querySelectorAll('a[href*="pid="]');
            for (var l = 0; l < links.length; l++) {
                var href = links[l].getAttribute('href') || '';
                var match = href.match(/pid=(\d+)/);
                if (match) {
                    var linkPid = parseInt(match[1], 10);
                    if (tradePids.indexOf(linkPid) !== -1) {
                        rows[r].classList.add('trade-involved-row');
                        break;
                    }
                }
            }
        }
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
                fetchRosterPreview();
            }
        });
    }

    // Header dropdown change
    if (headerSelect) {
        headerSelect.addEventListener('change', function () {
            currentDisplay = headerSelect.value;
            fetchRosterPreview();
        });
    }

    // Session-restored checkboxes on page load
    document.addEventListener('DOMContentLoaded', function () {
        var checked = getCheckedPids();
        if (checked.user.length > 0 || checked.partner.length > 0) {
            fetchRosterPreview();
        }
    });
})();
