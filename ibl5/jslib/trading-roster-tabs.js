/**
 * Trading Roster Tabs
 *
 * Client-side tab switching for the Players/Picks/Cash tabs
 * inside each team's trading-roster-details card.
 *
 * Uses data-panel on buttons and data-panel-id on panels.
 * Hidden panels remain in DOM so form fields still submit.
 */
(function () {
    'use strict';

    var cards = document.querySelectorAll('.trading-roster-details');

    for (var c = 0; c < cards.length; c++) {
        (function (details) {
            var tabBar = details.querySelector('.trading-roster-details__tabs');
            if (!tabBar) return;

            tabBar.addEventListener('click', function (e) {
                var btn = e.target;
                while (btn && btn !== tabBar && !btn.classList.contains('ibl-tab')) {
                    btn = btn.parentElement;
                }
                if (!btn || !btn.classList.contains('ibl-tab')) return;

                var panelId = btn.getAttribute('data-panel');
                if (!panelId) return;

                // Deactivate all tabs
                var tabs = tabBar.querySelectorAll('.ibl-tab');
                for (var t = 0; t < tabs.length; t++) {
                    tabs[t].classList.remove('ibl-tab--active');
                }
                btn.classList.add('ibl-tab--active');

                // Show target panel, hide others
                var panels = details.querySelectorAll('.trading-roster-details__panel');
                for (var p = 0; p < panels.length; p++) {
                    if (panels[p].getAttribute('data-panel-id') === panelId) {
                        panels[p].classList.add('trading-roster-details__panel--active');
                    } else {
                        panels[p].classList.remove('trading-roster-details__panel--active');
                    }
                }
            });
        })(cards[c]);
    }
})();
