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

    function processPlayerNames() {
        const isMobile = window.innerWidth <= MOBILE_BREAKPOINT;

        // Target player name links in data tables
        // Look for links with pid= parameter (player pages)
        const nameLinks = document.querySelectorAll(
            '.ibl-data-table a[href*="pid="]'
        );

        nameLinks.forEach(link => {
            // Find the text node containing the player name (skip img elements)
            let textNode = null;
            for (let i = 0; i < link.childNodes.length; i++) {
                if (link.childNodes[i].nodeType === Node.TEXT_NODE && link.childNodes[i].textContent.trim()) {
                    textNode = link.childNodes[i];
                    break;
                }
            }

            // Store original name on first encounter
            if (!link.dataset.fullName) {
                link.dataset.fullName = textNode ? textNode.textContent.trim() : link.textContent.trim();
            }

            // Always abbreviate in stat tables (compact grid layout), otherwise only on mobile
            const inStatTable = link.closest('.stat-table') !== null;
            const shouldAbbreviate = isMobile || inStatTable;
            const newName = shouldAbbreviate ? abbreviateName(link.dataset.fullName) : link.dataset.fullName;

            if (textNode) {
                // Preserve images/other elements, only update the text node
                textNode.textContent = newName;
            } else if (!link.querySelector('img')) {
                // Only use textContent if there are no images to preserve
                link.textContent = newName;
            }
        });
    }

    function processTeamNames() {
        const spans = document.querySelectorAll('.stat-table .ibl-team-cell__text, .draft-pick-table .ibl-team-cell__text');

        spans.forEach(span => {
            // Store original name on first encounter
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
