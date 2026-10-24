/**
 * Smooth scrolling for the league and team schedule pages.
 *
 * Wired by data attributes on the schedule anchors:
 *   data-scroll-target="<element id>"  scroll that element under the fixed header
 *   data-scroll-next                   (with data-scroll-target) centre the element instead
 *
 * The schedule pages are hx-boost navigated, so htmx re-executes this script
 * on every swap. The window flag keeps it to ONE document-level listener,
 * which also covers anchors inside content swapped in later.
 */
(function () {
    'use strict';

    if (window.iblScheduleScrollBound === true) {
        return;
    }
    window.iblScheduleScrollBound = true;

    var HEADER_OFFSET = 70;

    document.addEventListener('click', function (event) {
        var origin = event.target;
        if (!(origin instanceof Element)) {
            return;
        }
        var trigger = origin.closest('[data-scroll-target]');
        if (trigger === null) {
            return;
        }

        event.preventDefault();
        var target = document.getElementById(trigger.getAttribute('data-scroll-target') || '');
        if (target === null) {
            return;
        }

        var rect = target.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var targetY = trigger.hasAttribute('data-scroll-next')
            ? scrollTop + rect.top - (window.innerHeight / 2) + (rect.height / 2)
            : scrollTop + rect.top - HEADER_OFFSET;

        window.scrollTo({ top: targetY, behavior: 'smooth' });
    });
})();
