/**
 * HTMX Post-Swap Initialization
 *
 * Re-runs page-level JS initializers after HTMX swaps new content into
 * the page. Each initializer is exposed as a window.IBL_refresh* function
 * by its respective script file.
 */
(function () {
    'use strict';

    document.addEventListener('htmx:afterSwap', function () {
        // Re-run sorttable on new .sortable tables
        if (window.sorttable) {
            var tables = document.querySelectorAll('table.sortable:not(.sorttable_done)');
            for (var i = 0; i < tables.length; i++) {
                window.sorttable.makeSortable(tables[i]);
                tables[i].classList.add('sorttable_done');
            }
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
