/**
 * Name Abbreviation
 *
 * Abbreviates player names on mobile devices to save horizontal space,
 * and shortens long team names in stat tables for compact display.
 *
 * Player names: "John Paul Jones" -> "J.P. Jones"
 * Team names:   "Timberwolves" -> "T-Wolves", "Trailblazers" -> "Blazers"
 */
(function() {
    'use strict';

    const MOBILE_BREAKPOINT = 768;
    /** Tables where names are always abbreviated (then selectively restored if they fit) */
    const COMPACT_TABLE_SELECTOR = '.stat-table, .fa-table';

    /** Long team names mapped to shorter display forms */
    const TEAM_ABBREVIATIONS = {
        'Timberwolves': 'T-Wolves',
        'Trailblazers': 'Blazers',
        'Mavericks': 'Mavs'
    };

    /**
     * Abbreviate a full name to initials + last name.
     * "John Paul Jones" -> "J.P. Jones"
     * "Michael Jordan" -> "M. Jordan"
     * "Nene" -> "Nene" (unchanged)
     * "John Smith Jr." -> "J. Smith Jr."
     */
    function abbreviateName(fullName) {
        var parts = fullName.trim().split(/\s+/);
        if (parts.length < 2) return fullName;

        var suffixes = ['Jr.', 'Jr', 'Sr.', 'Sr', 'II', 'III', 'IV', 'V'];
        var suffix = '';
        while (parts.length > 1 && suffixes.indexOf(parts[parts.length - 1]) !== -1) {
            suffix = ' ' + parts.pop() + suffix;
        }

        if (parts.length < 2) return fullName;

        var lastName = parts.pop();
        var initials = parts.map(function(p) { return p.charAt(0) + '.'; }).join('');

        return initials + ' ' + lastName + suffix;
    }

    /**
     * Canvas-based text measurement — zero reflows.
     * Caches the canvas context and font string per unique font.
     */
    var measureCanvas = null;
    var fontCache = {};

    function getTextWidth(text, fontKey) {
        if (!measureCanvas) {
            measureCanvas = document.createElement('canvas').getContext('2d');
        }
        measureCanvas.font = fontKey;
        return measureCanvas.measureText(text).width;
    }

    /**
     * Build a CSS font string from a representative element.
     * Called once per table to avoid repeated getComputedStyle calls.
     */
    function getFontKey(element) {
        var s = window.getComputedStyle(element);
        return s.fontStyle + ' ' + s.fontWeight + ' ' + s.fontSize + ' ' + s.fontFamily;
    }

    /** Find the first non-empty text node child of a link. */
    function findTextNode(link) {
        for (var i = 0; i < link.childNodes.length; i++) {
            if (link.childNodes[i].nodeType === Node.TEXT_NODE && link.childNodes[i].textContent.trim()) {
                return link.childNodes[i];
            }
        }
        return null;
    }

    /** Set text on a link, preserving img children when possible. */
    function setLinkText(link, textNode, name) {
        if (textNode) {
            textNode.textContent = name;
        } else if (!link.querySelector('img')) {
            link.textContent = name;
        }
    }

    function processPlayerNames() {
        var isMobile = window.innerWidth <= MOBILE_BREAKPOINT;

        var nameLinks = document.querySelectorAll(
            '.ibl-data-table a[href*="pid="]:not([data-no-abbreviate]), .dc-card__name[href*="pid="]'
        );

        // First pass: abbreviate names (compact tables always, others on mobile only)
        // and collect links that need overflow checking
        var compactLinks = [];

        for (var i = 0; i < nameLinks.length; i++) {
            var link = nameLinks[i];
            var textNode = findTextNode(link);

            if (!link.dataset.fullName) {
                link.dataset.fullName = textNode ? textNode.textContent.trim() : link.textContent.trim();
            }

            var inCompactTable = link.closest(COMPACT_TABLE_SELECTOR) !== null;
            var shouldAbbreviate = isMobile || inCompactTable;
            var newName = shouldAbbreviate ? abbreviateName(link.dataset.fullName) : link.dataset.fullName;

            setLinkText(link, textNode, newName);

            if (inCompactTable) {
                compactLinks.push(link);
            }
        }

        // Second pass: selectively restore full names in compact tables.
        // Batch-read all cell widths first (one forced layout), then do text
        // measurement via canvas (zero layouts), then batch-write text changes.
        if (compactLinks.length === 0) return;

        // Determine font key from the first link (all player cells share the same font)
        var fontKey = null;
        var restoreCandidates = [];

        for (var j = 0; j < compactLinks.length; j++) {
            var cl = compactLinks[j];
            var nameEl = findTextNode(cl) || cl;
            var fullName = cl.dataset.fullName;
            if (nameEl.textContent.trim() === fullName) continue; // already full

            var cell = cl.closest('td');
            if (!cell) continue;

            if (!fontKey) {
                var textEl = nameEl.nodeType === Node.TEXT_NODE ? nameEl.parentElement : nameEl;
                if (textEl) fontKey = getFontKey(textEl);
            }

            restoreCandidates.push({ link: cl, nameEl: nameEl, cell: cell, fullName: fullName });
        }

        if (restoreCandidates.length === 0 || !fontKey) return;

        // Batch-read: measure all cell widths in one pass (single layout)
        for (var k = 0; k < restoreCandidates.length; k++) {
            restoreCandidates[k].cellWidth = restoreCandidates[k].cell.getBoundingClientRect().width;
            var img = restoreCandidates[k].cell.querySelector('img');
            restoreCandidates[k].nonTextWidth = img ? (img.offsetWidth + 6 + 16) : 16;
        }

        // Batch-write: restore names that fit (canvas measurement, no reflows)
        for (var m = 0; m < restoreCandidates.length; m++) {
            var c = restoreCandidates[m];
            var fullTextWidth = getTextWidth(c.fullName, fontKey);
            if (fullTextWidth <= (c.cellWidth - c.nonTextWidth)) {
                setLinkText(c.link, findTextNode(c.link), c.fullName);
            }
        }
    }

    function processTeamNames() {
        var spans = document.querySelectorAll('.stat-table .ibl-team-cell__text, .sticky-table .ibl-team-cell__text');

        // First pass: abbreviate all long team names
        var statSpans = [];
        for (var i = 0; i < spans.length; i++) {
            var span = spans[i];
            if (!span.dataset.fullName) {
                span.dataset.fullName = span.textContent.trim();
            }

            var original = span.dataset.fullName;
            var display = TEAM_ABBREVIATIONS[original] || original;
            span.textContent = display;

            if (span.closest('.stat-table') !== null && span.textContent !== span.dataset.fullName) {
                statSpans.push(span);
            }
        }

        // Second pass: restore full names in stat tables where they fit
        if (statSpans.length === 0) return;

        var fontKey = null;
        for (var j = 0; j < statSpans.length; j++) {
            var sp = statSpans[j];
            if (!fontKey) fontKey = getFontKey(sp);

            var availableWidth = sp.getBoundingClientRect().width;
            var fullTextWidth = getTextWidth(sp.dataset.fullName, fontKey);
            if (fullTextWidth <= availableWidth) {
                sp.textContent = sp.dataset.fullName;
            }
        }
    }

    function processScheduleTeamNames() {
        var isMobile = window.innerWidth <= MOBILE_BREAKPOINT;
        var spans = document.querySelectorAll('.schedule-game__team-text');

        for (var i = 0; i < spans.length; i++) {
            var span = spans[i];
            if (!span.dataset.fullName) {
                span.dataset.fullName = span.textContent.trim();
            }

            var original = span.dataset.fullName;
            span.textContent = (isMobile && TEAM_ABBREVIATIONS[original]) || original;
        }
    }

    function processAll() {
        processPlayerNames();
        processTeamNames();
        processScheduleTeamNames();
    }

    // Debounce resize handling
    var resizeTimer;
    function handleResize() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(processAll, 150);
    }

    window.IBL_refreshNameAbbreviations = processAll;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', processAll);
    } else {
        processAll();
    }
    window.addEventListener('resize', handleResize);
    window.addEventListener('orientationchange', handleResize);
})();
