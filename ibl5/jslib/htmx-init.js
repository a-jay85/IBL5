/**
 * HTMX Post-Swap Initialization
 *
 * Re-runs page-level JS initializers after HTMX swaps new content into
 * the page. Each initializer is exposed as a window.IBL_refresh* function
 * by its respective script file.
 */
(function () {
    'use strict';

    // Prevent hx-boost from intercepting links to non-PHP pages (static
    // .htm files, directory listings).  By the time htmx:beforeRequest fires
    // the original click's default action is already prevented, so we must
    // navigate manually when cancelling the htmx request.
    document.addEventListener('htmx:beforeRequest', function (evt) {
        var elt = evt.detail.elt;
        if (!elt || elt.tagName !== 'A') return;
        var href = elt.getAttribute('href');
        if (!href || href === '' || href === '/') return;
        // Allow PHP endpoints and root index
        if (href.indexOf('.php') !== -1) return;
        // Everything else (static files, directories, etc.) — fall back to
        // normal browser navigation so the full page loads correctly.
        evt.preventDefault();
        window.location.href = href;
    });

    document.addEventListener('htmx:afterSwap', function () {
        // Re-run sorttable on new .sortable tables (skips already-initialized
        // tables via data-sorttable attribute guard in makeSortable)
        if (window.sorttable) {
            window.sorttable.init();
        }

        // Re-initialize responsive table wrappers
        if (typeof window.IBL_refreshResponsiveTables === 'function') {
            window.IBL_refreshResponsiveTables();
        }

        // Re-highlight user team rows
        if (typeof window.IBL_refreshUserTeamHighlighter === 'function') {
            window.IBL_refreshUserTeamHighlighter();
        }

        // Re-abbreviate names
        if (typeof window.IBL_refreshNameAbbreviations === 'function') {
            window.IBL_refreshNameAbbreviations();
        }

        // Re-initialize sticky page headers
        if (typeof window.IBL_refreshStickyPageHeaders === 'function') {
            window.IBL_refreshStickyPageHeaders();
        }
    });
})();
