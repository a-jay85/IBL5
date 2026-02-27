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
        const parts = fullName.trim().split(/\s+/);
        if (parts.length < 2) return fullName; // Single name unchanged

        // Check for suffixes (Jr., Sr., III, etc.)
        const suffixes = ['Jr.', 'Jr', 'Sr.', 'Sr', 'II', 'III', 'IV', 'V'];
        let suffix = '';
        while (parts.length > 1 && suffixes.includes(parts[parts.length - 1])) {
            suffix = ' ' + parts.pop() + suffix;
        }

        if (parts.length < 2) return fullName; // Only suffix + one name

        // Last part is the last name
        const lastName = parts.pop();

        // Remaining parts get abbreviated to initials
        const initials = parts.map(p => p.charAt(0) + '.').join('');

        return initials + ' ' + lastName + suffix;
    }

    /**
     * Check whether showing the full name would cause the stat table to
     * overflow its wrapper. Returns true if the name should be abbreviated.
     *
     * The table has `width: 100%` and `overflow: hidden`, so normal
     * scrollWidth measurements are clamped. We temporarily set
     * `width: auto` on the table and check wrapper.scrollWidth.
     */
    /**
     * Measure the rendered pixel width of text using the same font as element.
     * Creates a temporary off-screen DOM element for pixel-perfect accuracy.
     */
    function getTextWidth(text, element) {
        const temp = document.createElement('span');
        const s = window.getComputedStyle(element);
        temp.style.fontFamily = s.fontFamily;
        temp.style.fontSize = s.fontSize;
        temp.style.fontWeight = s.fontWeight;
        temp.style.fontStyle = s.fontStyle;
        temp.style.letterSpacing = s.letterSpacing;
        temp.style.whiteSpace = 'nowrap';
        temp.style.position = 'absolute';
        temp.style.visibility = 'hidden';
        temp.style.left = '-9999px';
        temp.textContent = text;
        document.body.appendChild(temp);
        const width = temp.getBoundingClientRect().width;
        document.body.removeChild(temp);
        return width;
    }

    function wouldOverflow(element, fullName) {
        // element may be a Text node (from findTextNode) — resolve to Element
        const el = element.nodeType === Node.TEXT_NODE ? element.parentElement : element;
        if (!el) return false;

        // For team name cells: the <a> has overflow:hidden and flex layout,
        // so the span shrinks to fit and DOM measurements never show overflow.
        // Compare the DOM-measured text width against the span's rendered width.
        const cellLink = el.closest('.ibl-team-cell__name');
        if (cellLink) {
            const availableWidth = element.getBoundingClientRect().width;
            const fullTextWidth = getTextWidth(fullName, element);
            return fullTextWidth > availableWidth;
        }

        // For player names: temporarily let the table size naturally.
        const table = el.closest('.stat-table');
        if (!table) return false;
        const wrapper = table.closest('.stat-table-wrapper');
        if (!wrapper) return false;

        const originalText = element.textContent;
        element.textContent = fullName;
        table.style.width = 'auto';
        table.style.overflow = 'visible';

        const overflows = table.offsetWidth > wrapper.clientWidth;

        table.style.width = '';
        table.style.overflow = '';
        element.textContent = originalText;

        return overflows;
    }

    function processPlayerNames() {
        const isMobile = window.innerWidth <= MOBILE_BREAKPOINT;

        // Target player name links in data tables
        // Look for links with pid= parameter (player pages)
        const nameLinks = document.querySelectorAll(
            '.ibl-data-table a[href*="pid="]'
        );

        // First pass: abbreviate names (stat tables always, others on mobile)
        nameLinks.forEach(link => {
            const textNode = findTextNode(link);

            if (!link.dataset.fullName) {
                link.dataset.fullName = textNode ? textNode.textContent.trim() : link.textContent.trim();
            }

            const inStatTable = link.closest('.stat-table') !== null;
            const shouldAbbreviate = isMobile || inStatTable;
            const newName = shouldAbbreviate ? abbreviateName(link.dataset.fullName) : link.dataset.fullName;

            setLinkText(link, textNode, newName);
        });

        // Second pass: in stat tables, try restoring full names where they fit
        nameLinks.forEach(link => {
            if (link.closest('.stat-table') === null) return;
            const nameEl = findTextNode(link) || link;
            const fullName = link.dataset.fullName;
            if (nameEl.textContent.trim() === fullName) return; // already full

            if (!wouldOverflow(nameEl, fullName)) {
                setLinkText(link, findTextNode(link), fullName);
            }
        });
    }

    /** Find the first non-empty text node child of a link. */
    function findTextNode(link) {
        for (let i = 0; i < link.childNodes.length; i++) {
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

    function processTeamNames() {
        const spans = document.querySelectorAll('.stat-table .ibl-team-cell__text, .draft-pick-table .ibl-team-cell__text');

        // First pass: abbreviate all long team names
        spans.forEach(span => {
            if (!span.dataset.fullName) {
                span.dataset.fullName = span.textContent.trim();
            }

            const original = span.dataset.fullName;
            let display = original;

            for (const [long, short] of Object.entries(TEAM_ABBREVIATIONS)) {
                if (original === long) {
                    display = short;
                    break;
                }
            }

            span.textContent = display;
        });

        // Second pass: in stat tables, try restoring full names where they fit
        spans.forEach(span => {
            if (span.closest('.stat-table') === null) return;
            const fullName = span.dataset.fullName;
            if (span.textContent === fullName) return; // already full

            if (!wouldOverflow(span, fullName)) {
                span.textContent = fullName;
            }
        });
    }

    function processDraftPickCells() {
        const cells = document.querySelectorAll('.draft-pick-table .draft-pick-traded, .draft-pick-table .draft-pick-own');

        cells.forEach(cell => {
            const link = cell.querySelector('a');
            const target = link || cell;

            if (!target.dataset.fullName) {
                target.dataset.fullName = target.textContent.trim();
            }

            const original = target.dataset.fullName;
            let display = original;

            for (const [long, short] of Object.entries(TEAM_ABBREVIATIONS)) {
                if (original === long) {
                    display = short;
                    break;
                }
            }

            target.textContent = display;
        });
    }

    function processScheduleTeamNames() {
        const isMobile = window.innerWidth <= MOBILE_BREAKPOINT;
        const spans = document.querySelectorAll('.schedule-game__team-text');

        spans.forEach(span => {
            if (!span.dataset.fullName) {
                span.dataset.fullName = span.textContent.trim();
            }

            const original = span.dataset.fullName;
            let display = original;

            if (isMobile) {
                for (const [long, short] of Object.entries(TEAM_ABBREVIATIONS)) {
                    if (original === long) {
                        display = short;
                        break;
                    }
                }
            }

            span.textContent = display;
        });
    }

    function processAll() {
        processPlayerNames();
        processTeamNames();
        processDraftPickCells();
        processScheduleTeamNames();
    }

    // Debounce resize handling
    let resizeTimer;
    function handleResize() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(processAll, 150);
    }

    document.addEventListener('DOMContentLoaded', processAll);
    window.addEventListener('resize', handleResize);
    window.addEventListener('orientationchange', handleResize);
})();
