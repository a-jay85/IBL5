/**
 * Depth Chart Change Detection
 *
 * Captures the live (original) values of every <select> in the depth chart form
 * on page load, then applies an orange glow class (dc-glow-1 through dc-glow-5)
 * to any select whose current value differs from the original.
 *
 * Glow intensity scales with the magnitude of the change:
 *   - Position fields (pg, sg, sf, pf, c): distance along 1st→2nd→3rd→4th→ok→No spectrum
 *   - Intensity fields (OI, DI, BH): absolute numeric difference (max 4)
 *   - Minutes (min): proportional to difference, ceil(diff/8) capped at 5
 *   - Categorical (OF, DF, active): always level 1
 *
 * Exposes window.IBL_recalculateDepthChartGlows() for use after loading a saved
 * depth chart or resetting the form.
 */
(function () {
    'use strict';

    /** Map of position form values to ordinal positions for distance calculation */
    var POSITION_ORDINALS = {
        '1': 0,  // 1st
        '2': 1,  // 2nd
        '3': 2,  // 3rd
        '4': 3,  // 4th
        '5': 4,  // ok
        '0': 5   // No
    };

    var GLOW_CLASSES = ['dc-glow-1', 'dc-glow-2', 'dc-glow-3', 'dc-glow-4', 'dc-glow-5'];

    /** @type {Object.<string, string>} Original (live) values keyed by select name */
    var originalValues = {};

    /**
     * Strip trailing digits from a select name to determine field type.
     * "pg12" → "pg", "OI3" → "OI", "active5" → "active", "min7" → "min"
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

        // Position fields: distance along ordinal spectrum
        if (fieldPrefix === 'pg' || fieldPrefix === 'sg' || fieldPrefix === 'sf' ||
            fieldPrefix === 'pf' || fieldPrefix === 'c') {
            var origOrd = POSITION_ORDINALS[original];
            var currOrd = POSITION_ORDINALS[current];
            if (origOrd === undefined || currOrd === undefined) {
                return 1;
            }
            var distance = Math.abs(origOrd - currOrd);
            return Math.min(distance, 5);
        }

        // Intensity fields (OI, DI, BH): absolute numeric difference (range -2 to +2, max diff = 4)
        if (fieldPrefix === 'OI' || fieldPrefix === 'DI' || fieldPrefix === 'BH') {
            var diff = Math.abs(parseInt(current, 10) - parseInt(original, 10));
            return Math.min(diff, 5);
        }

        // Minutes: proportional, ceil(diff/8) capped at 5
        if (fieldPrefix === 'min') {
            var minDiff = Math.abs(parseInt(current, 10) - parseInt(original, 10));
            if (minDiff === 0) {
                return 0;
            }
            return Math.min(Math.ceil(minDiff / 8), 5);
        }

        // Categorical fields (OF, DF, active): always level 1
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
     * Capture all select values in the form as the "original" (live) baseline.
     */
    function captureOriginalValues(form) {
        originalValues = {};
        var selects = form.querySelectorAll('select');
        for (var i = 0; i < selects.length; i++) {
            var sel = selects[i];
            if (sel.name) {
                originalValues[sel.name] = sel.value;
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

    document.addEventListener('DOMContentLoaded', function () {
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
    });
})();
