/**
 * NextSim Position Tab Switching (AJAX)
 *
 * Handles position tab clicks (PG, SG, SF, PF, C) within the
 * .nextsim-tab-container on the DepthChartEntry page. Fetches
 * the new position table via the nextsim-api endpoint and swaps
 * the container's innerHTML.
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

    document.addEventListener('DOMContentLoaded', function () {
        var config = window.IBL_NEXTSIM_TABS_CONFIG;
        if (!config || !config.apiBaseUrl || !config.params) {
            return;
        }

        var container = document.querySelector('.nextsim-tab-container');
        if (!container) {
            return;
        }

        var isLoading = false;

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

            if (isLoading) {
                return;
            }

            // Optimistic UI: update active tab immediately
            var tabs = container.querySelectorAll('.ibl-tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('ibl-tab--active');
            }
            tab.classList.add('ibl-tab--active');

            isLoading = true;

            var url = config.apiBaseUrl
                + '&position=' + encodeURIComponent(position)
                + '&teamID=' + encodeURIComponent(config.params.teamID);

            fetch(url)
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Failed to fetch NextSim tab content');
                    }
                    return response.json();
                })
                .then(function (data) {
                    isLoading = false;
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
                    isLoading = false;
                    console.error('Error fetching NextSim tab:', err);
                });
        });
    });
})();
