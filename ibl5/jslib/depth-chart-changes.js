/**
 * Depth Chart Change Detection
 *
 * Captures the live (original) values of every <select> in the depth chart form
 * on page load, then applies an orange glow class (dc-glow-1 through dc-glow-5)
 * to any select whose current value differs from the original.
 *
 * Glow intensity scales with the magnitude of the change:
 *   - Role slot fields (BH, DI, OI, OF, DF): absolute numeric difference (max 2 or 3)
 *   - Categorical (canPlayInGame): always level 1
 *
 * Exposes window.IBL_recalculateDepthChartGlows() for use after loading a saved
 * depth chart or resetting the form.
 */
(function () {
    'use strict';

    var GLOW_CLASSES = ['dc-glow-1', 'dc-glow-2', 'dc-glow-3', 'dc-glow-4', 'dc-glow-5'];

    /** @type {Object.<string, string>} Original (live) values keyed by select name */
    var originalValues = {};

    /**
     * Strip trailing digits from a select name to determine field type.
     * "BH3" → "BH", "canPlayInGame5" → "canPlayInGame"
     */
    function getFieldPrefix(name) {
        return name.replace(/\d+$/, '');
    }

    /**
     * Calculate glow intensity (0 = no glow, 1-5 = intensity level).
     */
    function calculateIntensity(fieldPrefix, original, current) {
        if (original === current) {
            return 0;
        }

        // Role slot fields (BH, DI, OI, OF, DF): absolute numeric difference
        if (fieldPrefix === 'BH' || fieldPrefix === 'DI' || fieldPrefix === 'OI' ||
            fieldPrefix === 'OF' || fieldPrefix === 'DF') {
            var diff = Math.abs(parseInt(current, 10) - parseInt(original, 10));
            // Scale: max diff is 3 (for OF/DF), map to glow 1-3
            return Math.min(Math.max(diff, 1), 5);
        }

        // Categorical fields (canPlayInGame): always level 1
        return 1;
    }

    /**
     * Remove all glow classes from a select element.
     */
    function clearGlow(selectEl) {
        for (var i = 0; i < GLOW_CLASSES.length; i++) {
            selectEl.classList.remove(GLOW_CLASSES[i]);
        }
    }

    /**
     * Compare a select's current value against its original and apply/remove glow.
     */
    function updateGlow(selectEl) {
        clearGlow(selectEl);

        var name = selectEl.name;
        if (!name || !(name in originalValues)) {
            return;
        }

        var original = originalValues[name];
        var current = selectEl.value;
        var fieldPrefix = getFieldPrefix(name);
        var intensity = calculateIntensity(fieldPrefix, original, current);

        if (intensity > 0) {
            selectEl.classList.add('dc-glow-' + intensity);
        }
    }

    /**
     * Get the server-rendered default value for a <select> element.
     * Reads option.defaultSelected (the HTML "selected" attribute), which is
     * unaffected by browser form restoration on back-navigation.
     */
    function getServerRenderedValue(selectEl) {
        for (var i = 0; i < selectEl.options.length; i++) {
            if (selectEl.options[i].defaultSelected) {
                return selectEl.options[i].value;
            }
        }
        // No option had the SELECTED attribute — use the first option's value
        return selectEl.options.length > 0 ? selectEl.options[0].value : '';
    }

    /**
     * Capture the server-rendered (live DB) values as the baseline.
     * Uses defaultSelected rather than the current value so that browser
     * form restoration (back button) doesn't pollute the baseline.
     */
    function captureOriginalValues(form) {
        originalValues = {};
        var selects = form.querySelectorAll('select');
        for (var i = 0; i < selects.length; i++) {
            var sel = selects[i];
            if (sel.name) {
                originalValues[sel.name] = getServerRenderedValue(sel);
            }
        }
    }

    /**
     * Recalculate glows on every select in the form.
     * Called after loading a saved depth chart or resetting.
     */
    function recalculateAll() {
        var form = document.forms['DepthChartEntry'];
        if (!form) {
            return;
        }
        var selects = form.querySelectorAll('select');
        for (var i = 0; i < selects.length; i++) {
            updateGlow(selects[i]);
        }
    }

    // Expose for external callers (saved-depth-charts.js, resetDepthChart)
    window.IBL_recalculateDepthChartGlows = recalculateAll;

    function initDepthChartChanges() {
        var form = document.forms['DepthChartEntry'];
        if (!form) {
            return;
        }

        // Capture live values as the baseline
        captureOriginalValues(form);

        // Attach change listeners to all selects
        var selects = form.querySelectorAll('select');
        for (var i = 0; i < selects.length; i++) {
            selects[i].addEventListener('change', function () {
                updateGlow(this);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDepthChartChanges);
    } else {
        initDepthChartChanges();
    }

    // Recalculate glows on every pageshow (fires after DOMContentLoaded).
    window.addEventListener('pageshow', function () {
        recalculateAll();
    });
})();
