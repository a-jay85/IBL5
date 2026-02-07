/**
 * Team Page AJAX Tab Switching
 *
 * Intercepts tab clicks to fetch table HTML via API and swap content in place.
 * Progressive enhancement: falls back to full page reload without JS.
 *
 * Reads config from window.IBL_TEAM_CONFIG:
 *   { teamId, apiBaseUrl, yr }
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var config = window.IBL_TEAM_CONFIG;
        if (!config || config.teamId === undefined || !config.apiBaseUrl) {
            return;
        }

        var container = document.querySelector('.table-scroll-container');
        if (!container) {
            return;
        }

        var isLoading = false;

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

            var url = config.apiBaseUrl + '&teamID=' + config.teamId + '&display=' + encodeURIComponent(display);
            if (config.yr) {
                url += '&yr=' + encodeURIComponent(config.yr);
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
                        container.innerHTML = data.html;
                    }
                })
                .catch(function (err) {
                    isLoading = false;
                    console.error('Error fetching team tab:', err);
                    // On error, fall back to full page navigation
                    var fallbackUrl = 'modules.php?name=Team&op=team&teamID=' + config.teamId + '&display=' + encodeURIComponent(display);
                    if (config.yr) {
                        fallbackUrl += '&yr=' + encodeURIComponent(config.yr);
                    }
                    window.location.href = fallbackUrl;
                });
        }
    });
})();
