/**
 * Last-Sim Recap tabs — WAI-ARIA tabs pattern.
 *
 * Click + keyboard (Arrow{Left,Right}, Home, End, Enter, Space) switch tabs.
 * Active tab carries aria-selected="true" + tabindex="0"; others
 * aria-selected="false" + tabindex="-1" (roving tabindex). Panels are
 * hidden via the `hidden` attribute when inactive.
 */
(function () {
    'use strict';

    var cards = document.querySelectorAll('.last-sim-recap');

    for (var c = 0; c < cards.length; c++) {
        (function (card) {
            var tabList = card.querySelector('.last-sim-recap__tabs');
            if (!tabList) return;

            var tabs = card.querySelectorAll('.last-sim-recap__tab');
            var panels = card.querySelectorAll('.last-sim-recap__panel');
            if (tabs.length === 0) return;

            function activate(idx, focus) {
                if (idx < 0) idx = tabs.length - 1;
                if (idx >= tabs.length) idx = 0;

                for (var i = 0; i < tabs.length; i++) {
                    var isActive = i === idx;
                    tabs[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
                    tabs[i].setAttribute('tabindex', isActive ? '0' : '-1');
                    if (isActive) {
                        tabs[i].classList.add('last-sim-recap__tab--active');
                    } else {
                        tabs[i].classList.remove('last-sim-recap__tab--active');
                    }
                }
                for (var p = 0; p < panels.length; p++) {
                    var matches = parseInt(panels[p].getAttribute('data-panel-index'), 10) === idx;
                    if (matches) {
                        panels[p].removeAttribute('hidden');
                    } else {
                        panels[p].setAttribute('hidden', '');
                    }
                }

                if (focus && tabs[idx]) {
                    tabs[idx].focus();
                }
            }

            function indexOfTab(el) {
                while (el && el !== tabList && !el.classList.contains('last-sim-recap__tab')) {
                    el = el.parentElement;
                }
                if (!el || !el.classList.contains('last-sim-recap__tab')) return -1;
                return parseInt(el.getAttribute('data-tab-index'), 10);
            }

            tabList.addEventListener('click', function (e) {
                var idx = indexOfTab(e.target);
                if (idx >= 0) {
                    activate(idx, false);
                }
            });

            tabList.addEventListener('keydown', function (e) {
                var current = -1;
                for (var i = 0; i < tabs.length; i++) {
                    if (tabs[i].getAttribute('aria-selected') === 'true') {
                        current = i;
                        break;
                    }
                }
                if (current < 0) return;

                switch (e.key) {
                    case 'ArrowLeft':
                        e.preventDefault();
                        activate(current - 1, true);
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        activate(current + 1, true);
                        break;
                    case 'Home':
                        e.preventDefault();
                        activate(0, true);
                        break;
                    case 'End':
                        e.preventDefault();
                        activate(tabs.length - 1, true);
                        break;
                    case 'Enter':
                    case ' ':
                        var idx = indexOfTab(e.target);
                        if (idx >= 0) {
                            e.preventDefault();
                            activate(idx, true);
                        }
                        break;
                }
            });
        })(cards[c]);
    }
})();
