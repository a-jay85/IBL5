/**
 * Depth Chart Change Detection
 *
 * Captures the live (original) values of every form field in the depth chart
 * on page load, then applies an orange glow class (dc-glow-1 through dc-glow-5)
 * to any field whose current value differs from the original.
 *
 * Tracked fields:
 *   - <select>          role slots BH/DI/OI/DF/OF (+ saved-DC dropdown is excluded)
 *   - <input type=number> minutes target (min<N>)
 *   - <input type=checkbox> active toggle (canPlayInGame<N>)
 *
 * Glow intensity scales with the magnitude of the change:
 *   - Role slot fields (BH/DI/OI/DF/OF): inversely proportional to value
 *   - Categorical (canPlayInGame, minutes): always level 1
 *
 * Exposes window.IBL_recalculateDepthChartGlows() for use after loading a saved
 * depth chart or resetting the form.
 */
(function () {
    'use strict';

    var GLOW_CLASSES = ['dc-glow-1', 'dc-glow-2', 'dc-glow-3', 'dc-glow-4', 'dc-glow-5'];
    var ROLE_SLOT_PREFIXES = { BH: 1, DI: 1, OI: 1, OF: 1, DF: 1 };

    /** @type {Object.<string, string>} Original (live) values keyed by field name */
    var originalValues = {};

    /**
     * Strip trailing digits from a field name to determine field type.
     * "BH3" → "BH", "canPlayInGame5" → "canPlayInGame", "min2" → "min"
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

        // Role slot fields (BH, DI, OI, OF, DF): intensity inversely proportional
        // to value — #1 (highest bonus) gets strongest glow, #3 gets weakest
        if (ROLE_SLOT_PREFIXES[fieldPrefix]) {
            var val = parseInt(current, 10);
            if (val === 0) {
                // Changed from assigned to unassigned
                return 1;
            }
            // val=1 → glow 3 (strongest), val=2 → glow 2, val=3 → glow 1 (weakest)
            return Math.max(4 - val, 1);
        }

        // Categorical fields (canPlayInGame, min): always level 1
        return 1;
    }

    /**
     * Return true if the element is a field we track for change glow.
     */
    function isTrackedField(el) {
        if (!el || !el.name) return false;
        if (el.tagName === 'SELECT') return true;
        if (el.tagName !== 'INPUT') return false;
        return el.type === 'number' || el.type === 'checkbox';
    }

    /**
     * Normalize a field's current value to the same string shape used when
     * capturing the baseline. Checkboxes → '1' or '0'; number/select → .value.
     */
    function readCurrentValue(el) {
        if (el.type === 'checkbox') {
            return el.checked ? '1' : '0';
        }
        return el.value;
    }

    /**
     * Read the server-rendered default value for a field. Uses default*
     * properties so that browser form restoration (back button) doesn't
     * pollute the baseline.
     */
    function readDefaultValue(el) {
        if (el.tagName === 'SELECT') {
            for (var i = 0; i < el.options.length; i++) {
                if (el.options[i].defaultSelected) {
                    return el.options[i].value;
                }
            }
            return el.options.length > 0 ? el.options[0].value : '';
        }
        if (el.type === 'checkbox') {
            return el.defaultChecked ? '1' : '0';
        }
        // number input
        return el.defaultValue;
    }

    /**
     * Remove all glow classes from a tracked element.
     */
    function clearGlow(el) {
        for (var i = 0; i < GLOW_CLASSES.length; i++) {
            el.classList.remove(GLOW_CLASSES[i]);
        }
    }

    /**
     * Compare a field's current value against its original and apply/remove glow.
     */
    function updateGlow(el) {
        clearGlow(el);

        var name = el.name;
        if (!name || !(name in originalValues)) {
            return;
        }

        var original = originalValues[name];
        var current = readCurrentValue(el);
        var fieldPrefix = getFieldPrefix(name);
        var intensity = calculateIntensity(fieldPrefix, original, current);

        if (intensity > 0) {
            el.classList.add('dc-glow-' + intensity);
        }
    }

    /**
     * Return all tracked fields within the form: role slot selects, minutes
     * number inputs, and canPlayInGame checkboxes. Hidden inputs (including
     * the canPlayInGame unchecked-fallback) are excluded.
     */
    function getTrackedFields(form) {
        var fields = [];
        var selects = form.querySelectorAll('select[name]');
        for (var i = 0; i < selects.length; i++) {
            fields.push(selects[i]);
        }
        var numInputs = form.querySelectorAll('input[type="number"][name]');
        for (var j = 0; j < numInputs.length; j++) {
            fields.push(numInputs[j]);
        }
        var checkboxes = form.querySelectorAll('input[type="checkbox"][name]');
        for (var k = 0; k < checkboxes.length; k++) {
            fields.push(checkboxes[k]);
        }
        return fields;
    }

    /**
     * Capture the server-rendered (live DB) values as the baseline.
     */
    function captureOriginalValues(form) {
        originalValues = {};
        var fields = getTrackedFields(form);
        for (var i = 0; i < fields.length; i++) {
            var el = fields[i];
            // For canPlayInGame, the desktop and mobile markup both have a
            // checkbox plus a sibling hidden input. We only want the checkbox
            // recorded — but the baseline is keyed by name, and both mobile +
            // desktop checkboxes share the name. The last write wins; they're
            // initialized to the same defaultChecked value from PHP, so this
            // is deterministic.
            originalValues[el.name] = readDefaultValue(el);
        }
    }

    /**
     * Recalculate glows on every tracked field in the form.
     * Called after loading a saved depth chart or resetting.
     */
    function recalculateAll() {
        var form = document.forms['DepthChartEntry'];
        if (!form) {
            return;
        }
        var fields = getTrackedFields(form);
        for (var i = 0; i < fields.length; i++) {
            updateGlow(fields[i]);
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

        // Delegate change events at the form level so we catch all tracked
        // field types with a single listener.
        form.addEventListener('change', function (e) {
            if (isTrackedField(e.target)) {
                updateGlow(e.target);
            }
        });
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
