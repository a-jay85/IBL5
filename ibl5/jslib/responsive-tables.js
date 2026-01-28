/**
 * Responsive Tables - Automatic overflow detection and responsive features
 *
 * Detects when data tables overflow horizontally and conditionally applies:
 * - Scroll containers (table-scroll-wrapper + table-scroll-container)
 * - Sticky first column via .responsive-table + .sticky-col classes
 * - Scroll shadow indicators
 * - iOS-compatible width management
 *
 * Tables that fit within the viewport retain rounded corners and standard styling.
 * Tables with hardcoded .responsive-table class (Standings, Draft, Leaderboards,
 * SeasonLeaders) are preserved and enhanced with scroll indicators.
 */
(function () {
    "use strict";

    /**
     * Main entry point: process all data tables on the page.
     */
    function initResponsiveTables() {
        var tables = document.querySelectorAll(
            ".ibl-data-table, .ibl-table, .league-stats-table, .compare-players-table"
        );
        for (var i = 0; i < tables.length; i++) {
            processTable(tables[i]);
        }
    }

    /**
     * Determine if a table overflows and apply/remove responsive features.
     */
    function processTable(table) {
        // Mark tables that already have hardcoded responsive-table class on first run
        if (table.dataset.responsiveInit === undefined) {
            table.dataset.responsiveInit = "1";
            if (table.classList.contains("responsive-table")) {
                table.dataset.hardcodedResponsive = "1";
            }
        }

        // Measure overflow: compare table's actual rendered width to available space.
        // Always cap available width at viewport width because parent elements
        // (e.g. <td> in PHP-Nuke layout tables) can expand beyond the viewport,
        // causing wrappers/containers to also be oversized.
        var wrapper = table.closest(".table-scroll-wrapper");
        var container = table.closest(".table-scroll-container");
        var viewportWidth = document.documentElement.clientWidth;
        var availableWidth = viewportWidth;

        if (wrapper) {
            availableWidth = Math.min(wrapper.clientWidth, viewportWidth);
        } else if (container) {
            availableWidth = Math.min(container.clientWidth, viewportWidth);
        }

        // Use getBoundingClientRect for accurate table width
        var tableWidth = table.getBoundingClientRect().width;

        // For tables inside scroll containers, scrollWidth gives the full content width
        if (container) {
            tableWidth = Math.max(tableWidth, container.scrollWidth);
        }

        var overflows = tableWidth > availableWidth + 2; // 2px tolerance

        if (overflows) {
            makeResponsive(table);
        } else {
            removeResponsive(table);
        }
    }

    /**
     * Apply responsive features to an overflowing table.
     */
    function makeResponsive(table) {
        // Skip tables that opt out of responsive treatment (sticky columns, scroll wrapper, shadows)
        if (table.dataset.noResponsive !== undefined) return;

        // Add responsive-table class for CSS sticky column support
        table.classList.add("responsive-table");

        // Ensure scroll wrapper structure exists
        ensureScrollWrappers(table);

        // Add sticky columns if the table doesn't already have them
        if (
            !table.querySelector(".sticky-col") &&
            !table.querySelector(".sticky-col-1") &&
            !table.querySelector(".sticky-col-2")
        ) {
            addStickyColumns(table);
        }

        // Set up scroll indicator and iOS width fix
        setupScrollIndicator(table);
        setContainerWidth(table);
    }

    /**
     * Remove JS-added responsive features when table fits.
     */
    function removeResponsive(table) {
        // Don't remove from hardcoded tables
        if (table.dataset.hardcodedResponsive === "1") {
            // Still manage scroll indicators for hardcoded tables
            setupScrollIndicator(table);
            setContainerWidth(table);
            return;
        }

        table.classList.remove("responsive-table");

        // Remove JS-added sticky classes
        if (table.dataset.jsStickyAdded === "1") {
            removeStickyColumns(table);
        }

        // Don't unwrap - leave the structure in place for future resizes
        // Just let the CSS handle it (no responsive-table = normal overflow:hidden)
    }

    /**
     * Ensure table is wrapped in .table-scroll-wrapper > .table-scroll-container.
     * Handles 3 cases:
     * 1. Already fully wrapped (Standings) - no-op
     * 2. Has container but no wrapper (Draft, Leaderboards, SeasonLeaders)
     * 3. Bare table (all other tables) - inject both
     */
    function ensureScrollWrappers(table) {
        var container = table.closest(".table-scroll-container");
        var wrapper = table.closest(".table-scroll-wrapper");

        // Case 1: Already fully wrapped
        if (container && wrapper) {
            constrainWrapper(wrapper);
            return;
        }

        // Case 2: Has container but no wrapper - wrap container in wrapper
        if (container && !wrapper) {
            wrapper = document.createElement("div");
            wrapper.className = "table-scroll-wrapper";
            container.parentNode.insertBefore(wrapper, container);
            wrapper.appendChild(container);
            constrainWrapper(wrapper);
            return;
        }

        // Case 3: Bare table - inject both wrapper and container
        wrapper = document.createElement("div");
        wrapper.className = "table-scroll-wrapper";
        container = document.createElement("div");
        container.className = "table-scroll-container";

        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(container);
        container.appendChild(table);
        constrainWrapper(wrapper);
    }

    /**
     * Constrain wrapper to fit within the viewport.
     * Accounts for the wrapper's left offset (e.g. PHP-Nuke layout table
     * padding/cellspacing) so the wrapper's right edge doesn't exceed
     * the viewport, which would cause a horizontal page "wiggle".
     */
    function constrainWrapper(wrapper) {
        // Temporarily clear max-width so getBoundingClientRect reflects
        // the natural position (important during resize)
        wrapper.style.maxWidth = "none";

        var viewportWidth = document.documentElement.clientWidth;
        var leftOffset = wrapper.getBoundingClientRect().left;
        var availableWidth = viewportWidth - Math.max(0, leftOffset);
        wrapper.style.maxWidth = availableWidth + "px";
        wrapper.style.overflow = "hidden";
    }

    /**
     * Add sticky column class to first-column cells.
     * Uses single sticky column (first column) for auto-detected tables.
     */
    function addStickyColumns(table) {
        var headerCells = table.querySelectorAll("thead th:first-child");
        var bodyCells = table.querySelectorAll("tbody td:first-child");

        for (var i = 0; i < headerCells.length; i++) {
            headerCells[i].classList.add("sticky-col");
        }
        for (var j = 0; j < bodyCells.length; j++) {
            bodyCells[j].classList.add("sticky-col");
        }

        table.dataset.jsStickyAdded = "1";
    }

    /**
     * Remove JS-added sticky column classes.
     */
    function removeStickyColumns(table) {
        var stickyCells = table.querySelectorAll(".sticky-col");
        for (var i = 0; i < stickyCells.length; i++) {
            stickyCells[i].classList.remove("sticky-col");
        }
        table.dataset.jsStickyAdded = "0";
    }

    /**
     * Set up scroll shadow indicator on the wrapper.
     * Shows a shadow on the right edge that fades when scrolled to end.
     */
    function setupScrollIndicator(table) {
        var container = table.closest(".table-scroll-container");
        var wrapper = table.closest(".table-scroll-wrapper");

        if (!container || !wrapper) {
            return;
        }

        // Avoid attaching duplicate listeners
        if (container.dataset.scrollListenerAttached === "1") {
            // Just update the indicator state
            updateScrollIndicator(container, wrapper);
            return;
        }

        container.dataset.scrollListenerAttached = "1";

        container.addEventListener("scroll", function () {
            updateScrollIndicator(container, wrapper);
        });

        // Initial state
        updateScrollIndicator(container, wrapper);
    }

    /**
     * Toggle .scrolled-end class based on scroll position.
     */
    function updateScrollIndicator(container, wrapper) {
        var isAtEnd =
            container.scrollLeft + container.clientWidth >=
            container.scrollWidth - 5;
        wrapper.classList.toggle("scrolled-end", isAtEnd);
    }

    /**
     * Set explicit width on scroll container for iOS compatibility.
     * iOS Safari needs explicit width to properly constrain the scroll area.
     */
    function setContainerWidth(table) {
        var container = table.closest(".table-scroll-container");
        var wrapper = table.closest(".table-scroll-wrapper");

        if (!container || !wrapper) {
            return;
        }

        var availableWidth = wrapper.clientWidth;
        container.style.width = availableWidth + "px";
        container.style.maxWidth = availableWidth + "px";
    }

    /**
     * Update all container widths (called on resize).
     */
    function updateAllContainerWidths() {
        var containers = document.querySelectorAll(".table-scroll-container");
        for (var i = 0; i < containers.length; i++) {
            var wrapper = containers[i].closest(".table-scroll-wrapper");
            if (wrapper) {
                var availableWidth = wrapper.clientWidth;
                containers[i].style.width = availableWidth + "px";
                containers[i].style.maxWidth = availableWidth + "px";
            }
        }
    }

    // Debounced resize handler
    var resizeTimer;
    window.addEventListener("resize", function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            initResponsiveTables();
            updateAllContainerWidths();
        }, 150);
    });

    window.addEventListener("orientationchange", function () {
        setTimeout(function () {
            initResponsiveTables();
            updateAllContainerWidths();
        }, 200);
    });

    // Run on DOM ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initResponsiveTables);
    } else {
        initResponsiveTables();
    }
})();
