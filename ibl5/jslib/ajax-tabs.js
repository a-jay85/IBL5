/**
 * Reusable AJAX Tab/Dropdown Switching
 *
 * Intercepts tab clicks and dropdown changes to fetch table HTML via API
 * and swap content in place.
 * Progressive enhancement: falls back to full page reload without JS.
 *
 * Reads config from window.IBL_AJAX_TABS_CONFIG:
 *   {
 *     apiBaseUrl:       'modules.php?name=Team&op=api',
 *     params:           { teamID: 5, yr: '2024' },
 *     fallbackBaseUrl:  'modules.php?name=Team&op=team&teamID=5',
 *   }
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var config = window.IBL_AJAX_TABS_CONFIG;
        if (!config || !config.apiBaseUrl || !config.params) {
            return;
        }

        var container = document.querySelector('.table-scroll-container');
        if (!container) {
            return;
        }

        var isLoading = false;

        function buildQueryString(params) {
            var parts = [];
            for (var key in params) {
                if (Object.prototype.hasOwnProperty.call(params, key) && params[key] !== null && params[key] !== undefined) {
                    parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[key]));
                }
            }
            return parts.join('&');
        }

        /**
         * Parse a select option value into display + optional split
         * "ratings" => { display: "ratings", split: null }
         * "split:home" => { display: "split", split: "home" }
         */
        function parseSelectValue(value) {
            if (value.indexOf('split:') === 0) {
                return { display: 'split', split: value.substring(6) };
            }
            return { display: value, split: null };
        }

        /**
         * Build the fallback URL for a given display/split combo
         */
        function buildFallbackUrl(display, split) {
            var url = config.fallbackBaseUrl + '&display=' + encodeURIComponent(display);
            if (split) {
                url += '&split=' + encodeURIComponent(split);
            }
            return url;
        }

        // Event delegation on the container for tab clicks (legacy tabs)
        container.addEventListener('click', function (e) {
            var tab = e.target.closest('.ibl-tab');
            if (!tab) {
                return;
            }

            var display = tab.getAttribute('data-display');
            if (!display) {
                return;
            }

            e.preventDefault();

            if (isLoading) {
                return;
            }

            // Optimistic UI: update active tab immediately
            updateActiveTab(tab);

            // Fetch new table content
            fetchTab(display, null);

            // Update browser URL
            var newUrl = tab.getAttribute('href');
            if (newUrl) {
                history.pushState({ display: display, split: null }, '', newUrl);
            }
        });

        // Event delegation for dropdown changes
        container.addEventListener('change', function (e) {
            var select = e.target.closest('.ibl-view-select');
            if (!select) {
                return;
            }

            if (isLoading) {
                return;
            }

            var parsed = parseSelectValue(select.value);
            fetchTab(parsed.display, parsed.split);

            // Update browser URL
            var newUrl = buildFallbackUrl(parsed.display, parsed.split);
            history.pushState({ display: parsed.display, split: parsed.split }, '', newUrl);
        });

        // Handle back/forward navigation
        window.addEventListener('popstate', function (e) {
            var display = 'ratings';
            var split = null;
            if (e.state && e.state.display) {
                display = e.state.display;
                split = e.state.split || null;
            } else {
                // Parse display and split from current URL
                var params = new URLSearchParams(window.location.search);
                display = params.get('display') || 'ratings';
                split = params.get('split') || null;
            }

            fetchTab(display, split);
        });

        // Set initial state so back button from first AJAX nav works
        var initialParams = new URLSearchParams(window.location.search);
        var initialDisplay = initialParams.get('display') || 'ratings';
        var initialSplit = initialParams.get('split') || null;
        history.replaceState({ display: initialDisplay, split: initialSplit }, '', window.location.href);

        function updateActiveTab(clickedTab) {
            var tabs = container.querySelectorAll('.ibl-tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('ibl-tab--active');
            }
            clickedTab.classList.add('ibl-tab--active');
        }

        function fetchTab(display, split) {
            isLoading = true;

            var url = config.apiBaseUrl + '&display=' + encodeURIComponent(display) + '&' + buildQueryString(config.params);
            if (split) {
                url += '&split=' + encodeURIComponent(split);
            }

            fetch(url)
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Failed to fetch tab content');
                    }
                    return response.json();
                })
                .then(function (data) {
                    isLoading = false;
                    if (data.html) {
                        // Clear inline width constraints set by responsive-tables.js
                        // so the container can resize to fit the new table content
                        container.style.width = '';
                        container.style.maxWidth = '';
                        var wrapper = container.closest('.table-scroll-wrapper');
                        if (wrapper) {
                            wrapper.style.maxWidth = '';
                        }

                        container.innerHTML = data.html;

                        // Re-initialize sorting on the new table
                        var tables = container.querySelectorAll('table.sortable');
                        for (var i = 0; i < tables.length; i++) {
                            sorttable.makeSortable(tables[i]);
                        }

                        // Restore the dropdown selection after content swap
                        var newSelect = container.querySelector('.ibl-view-select');
                        if (newSelect) {
                            var selectValue = split ? 'split:' + split : display;
                            newSelect.value = selectValue;
                        }

                        // Re-run responsive table logic to re-measure and re-constrain
                        if (typeof window.IBL_refreshResponsiveTables === 'function') {
                            window.IBL_refreshResponsiveTables();
                        }
                    }
                })
                .catch(function (err) {
                    isLoading = false;
                    console.error('Error fetching tab:', err);
                    // On error, fall back to full page navigation
                    window.location.href = buildFallbackUrl(display, split);
                });
        }
    });
})();
