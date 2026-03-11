/**
 * NextSim Position Tab Switching (AJAX)
 *
 * Handles position tab clicks (PG, SG, SF, PF, C) within the
 * .nextsim-tab-container on the DepthChartEntry page. Fetches
 * the new position table via the nextsim-api endpoint and swaps
 * the container's innerHTML.
 *
 * Runs immediately (DOM elements exist above this script) so it works
 * both on initial page load and after HTMX content swaps.
 *
 * Progressive enhancement: falls back to NextSim module page without JS.
 *
 * Reads config from window.IBL_NEXTSIM_TABS_CONFIG:
 *   {
 *     apiBaseUrl: 'modules.php?name=DepthChartEntry&op=nextsim-api',
 *     params:     { teamID: 5 }
 *   }
 */
(function () {
    'use strict';

    var config = window.IBL_NEXTSIM_TABS_CONFIG;
    if (!config || !config.apiBaseUrl || !config.params) {
        return;
    }

    var container = document.querySelector('.nextsim-tab-container');
    if (!container) {
        return;
    }

    var currentController = null;

    container.addEventListener('click', function (e) {
        var tab = e.target.closest('.ibl-tab');
        if (!tab) {
            return;
        }

        var position = tab.getAttribute('data-display');
        if (!position) {
            return;
        }

        e.preventDefault();

        // Optimistic UI: update active tab immediately
        var tabs = container.querySelectorAll('.ibl-tab');
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove('ibl-tab--active');
        }
        tab.classList.add('ibl-tab--active');

        // Abort any in-flight request so the new selection takes priority
        if (currentController) {
            currentController.abort();
        }
        currentController = new AbortController();

        container.classList.add('ajax-loading');

        var url = config.apiBaseUrl
            + '&position=' + encodeURIComponent(position)
            + '&teamID=' + encodeURIComponent(config.params.teamID);

        fetch(url, { signal: currentController.signal })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to fetch NextSim tab content');
                }
                return response.json();
            })
            .then(function (data) {
                currentController = null;
                container.classList.remove('ajax-loading');
                if (data.html) {
                    container.innerHTML = data.html;

                    // Re-initialize column highlights on the new table
                    if (typeof window.IBL_initNextSimHighlight === 'function') {
                        window.IBL_initNextSimHighlight(container);
                    }

                    // Re-run responsive table logic to re-measure
                    if (typeof window.IBL_refreshResponsiveTables === 'function') {
                        window.IBL_refreshResponsiveTables();
                    }
                }
            })
            .catch(function (err) {
                // Ignore aborted requests — a newer request superseded this one
                if (err.name === 'AbortError') {
                    return;
                }
                currentController = null;
                container.classList.remove('ajax-loading');
                console.error('Error fetching NextSim tab:', err);
            });
    });
})();
