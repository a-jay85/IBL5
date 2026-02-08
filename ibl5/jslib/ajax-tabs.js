/**
 * Reusable AJAX Tab Switching
 *
 * Intercepts tab clicks to fetch table HTML via API and swap content in place.
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

        // Event delegation on the container for tab clicks
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
            fetchTab(display);

            // Update browser URL
            var newUrl = tab.getAttribute('href');
            if (newUrl) {
                history.pushState({ display: display }, '', newUrl);
            }
        });

        // Handle back/forward navigation
        window.addEventListener('popstate', function (e) {
            var display = 'ratings';
            if (e.state && e.state.display) {
                display = e.state.display;
            } else {
                // Parse display from current URL
                var params = new URLSearchParams(window.location.search);
                display = params.get('display') || 'ratings';
            }

            fetchTab(display);
        });

        // Set initial state so back button from first AJAX nav works
        var initialParams = new URLSearchParams(window.location.search);
        var initialDisplay = initialParams.get('display') || 'ratings';
        history.replaceState({ display: initialDisplay }, '', window.location.href);

        function updateActiveTab(clickedTab) {
            var tabs = container.querySelectorAll('.ibl-tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('ibl-tab--active');
            }
            clickedTab.classList.add('ibl-tab--active');
        }

        function fetchTab(display) {
            isLoading = true;

            var url = config.apiBaseUrl + '&display=' + encodeURIComponent(display) + '&' + buildQueryString(config.params);

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
                        container.innerHTML = data.html;
                    }
                })
                .catch(function (err) {
                    isLoading = false;
                    console.error('Error fetching tab:', err);
                    // On error, fall back to full page navigation
                    window.location.href = config.fallbackBaseUrl + '&display=' + encodeURIComponent(display);
                });
        }
    });
})();
