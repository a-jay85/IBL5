/**
 * HTMX Post-Swap Initialization
 *
 * Re-runs page-level JS initializers after HTMX swaps new content into
 * the page. Each initializer is exposed as a window.IBL_refresh* function
 * by its respective script file.
 *
 * Also handles:
 * - Dropdown value decomposition for tab switching (split:key → display + split)
 * - Width constraint clearing before table content swaps
 * - Static file link interception to prevent hx-boost from breaking non-PHP links
 * - Submit button disable/enable during boosted form submissions
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

    // Decompose dropdown compound values (e.g. "split:home") into separate
    // display and split query parameters for the HTMX API request.
    // The <select> has no name attribute so HTMX won't auto-include its value.
    document.addEventListener('htmx:configRequest', function (evt) {
        var elt = evt.detail.elt;
        if (!elt || !elt.classList.contains('ibl-view-select')) return;
        var val = elt.value;
        if (val.indexOf('split:') === 0) {
            evt.detail.parameters['display'] = 'split';
            evt.detail.parameters['split'] = val.substring(6);
        } else {
            evt.detail.parameters['display'] = val;
        }
    });

    // Clear inline width constraints set by responsive-tables.js before
    // HTMX swaps new table content — otherwise the container starts at
    // the wrong size for the new table.
    document.addEventListener('htmx:beforeSwap', function (evt) {
        var target = evt.detail.target;
        if (!target) return;
        if (target.classList.contains('table-scroll-container') ||
            target.classList.contains('nextsim-tab-container')) {
            target.style.width = '';
            target.style.maxWidth = '';
            var wrapper = target.closest('.table-scroll-wrapper');
            if (wrapper) {
                wrapper.style.maxWidth = '';
            }
        }
    });

    // Disable submit buttons during boosted form submissions to prevent
    // double-submit. Re-enable after the request completes (success or error).
    // Scoped to FORM elements only — does not affect tab/dropdown hx-get.
    document.addEventListener('htmx:beforeRequest', function (evt) {
        var elt = evt.detail.elt;
        if (!elt || elt.tagName !== 'FORM') return;
        var btns = elt.querySelectorAll('button[type="submit"], input[type="submit"]');
        for (var i = 0; i < btns.length; i++) {
            btns[i].disabled = true;
            if (btns[i].tagName === 'BUTTON') {
                btns[i].dataset.originalText = btns[i].textContent;
                btns[i].textContent = 'Submitting\u2026';
            }
        }
    });

    document.addEventListener('htmx:afterRequest', function (evt) {
        var elt = evt.detail.elt;
        if (!elt || elt.tagName !== 'FORM') return;
        var btns = elt.querySelectorAll('button[type="submit"], input[type="submit"]');
        for (var i = 0; i < btns.length; i++) {
            btns[i].disabled = false;
            if (btns[i].tagName === 'BUTTON' && btns[i].dataset.originalText) {
                btns[i].textContent = btns[i].dataset.originalText;
            }
        }
    });

    // Re-initialize JS features after HTMX restores a page from its
    // history cache (browser Back/Forward). htmx:afterSwap does NOT fire
    // on history restore — only htmx:historyRestore does.
    document.addEventListener('htmx:historyRestore', function () {
        if (window.sorttable) {
            // HTMX serializes the DOM (including data-sorttable attributes)
            // into its history cache, but JS event listeners are lost on
            // restore. Clear the init guard so listeners get reattached.
            var cachedTables = document.querySelectorAll('table.sortable[data-sorttable]');
            for (var t = 0; t < cachedTables.length; t++) {
                cachedTables[t].removeAttribute('data-sorttable');
            }
            window.sorttable.init();
        }
        if (typeof window.IBL_refreshResponsiveTables === 'function') {
            window.IBL_refreshResponsiveTables();
        }
        if (typeof window.IBL_refreshUserTeamHighlighter === 'function') {
            window.IBL_refreshUserTeamHighlighter();
        }
        if (typeof window.IBL_refreshNameAbbreviations === 'function') {
            window.IBL_refreshNameAbbreviations();
        }
        if (typeof window.IBL_refreshStickyPageHeaders === 'function') {
            window.IBL_refreshStickyPageHeaders();
        }
        if (typeof window.IBL_sizeContractHintLinks === 'function') {
            window.IBL_sizeContractHintLinks();
        }
    });

    document.addEventListener('htmx:afterSwap', function (evt) {
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

        // Re-size contract hint links for new content
        if (typeof window.IBL_sizeContractHintLinks === 'function') {
            window.IBL_sizeContractHintLinks();
        }

        // Re-initialize NextSim column highlights (scoped to nextsim swaps)
        if (evt.detail && evt.detail.target &&
            evt.detail.target.classList.contains('nextsim-tab-container') &&
            typeof window.IBL_initNextSimHighlight === 'function') {
            window.IBL_initNextSimHighlight(evt.detail.target);
        }
    });
})();
