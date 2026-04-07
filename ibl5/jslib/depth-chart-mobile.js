/**
 * Depth Chart Mobile Card View
 *
 * Toggles between desktop table (>768px) and mobile cards (<=768px).
 * Enables inputs in the active view, disables inputs in the hidden view.
 * Syncs values between views on viewport breakpoint crossing.
 */
(function () {
    'use strict';

    var MOBILE_BREAKPOINT = 768;

    function getForm() {
        return document.forms['DepthChartEntry'];
    }

    function getDesktopContainer() {
        var form = getForm();
        return form ? form.querySelector('.text-center') : null;
    }

    function getMobileContainer() {
        return document.getElementById('dc-mobile-cards');
    }

    function isMobile() {
        return window.innerWidth <= MOBILE_BREAKPOINT;
    }

    /**
     * Enable or disable all inputs/selects within a container.
     */
    function setInputsDisabled(container, disabled) {
        if (!container) return;
        var inputs = container.querySelectorAll('input, select');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].disabled = disabled;
        }
    }

    /**
     * Sync form values from source container to target container.
     * Fields in both views share names, so we sync:
     *   - <select> role slots (BH/DI/OI/DF/OF)
     *   - <input type="number"> minutes (min<N>)
     *   - <input type="checkbox"> canPlayInGame<N>
     * The sibling hidden <input> for canPlayInGame is untouched — it always
     * carries "0" as the unchecked-fallback value.
     */
    function syncValues(source, target) {
        if (!source || !target) return;

        // Role slot selects
        var sourceSelects = source.querySelectorAll('select[name]');
        for (var i = 0; i < sourceSelects.length; i++) {
            var sel = sourceSelects[i];
            var targetSel = target.querySelector('select[name="' + sel.name + '"]');
            if (targetSel) {
                targetSel.value = sel.value;
            }
        }

        // Minutes number inputs
        var sourceNumInputs = source.querySelectorAll('input[type="number"][name]');
        for (var n = 0; n < sourceNumInputs.length; n++) {
            var numInput = sourceNumInputs[n];
            var targetNumInput = target.querySelector('input[type="number"][name="' + numInput.name + '"]');
            if (targetNumInput) {
                targetNumInput.value = numInput.value;
            }
        }

        // Active checkboxes
        var sourceCheckboxes = source.querySelectorAll('input[type="checkbox"][name^="canPlayInGame"]');
        for (var k = 0; k < sourceCheckboxes.length; k++) {
            var srcCb = sourceCheckboxes[k];
            var targetCb = target.querySelector('input[type="checkbox"][name="' + srcCb.name + '"]');
            if (targetCb) {
                targetCb.checked = srcCb.checked;
                updateCardOpacity(targetCb);
            }
        }
    }

    /**
     * Toggle card opacity based on active checkbox state.
     */
    function updateCardOpacity(checkbox) {
        var card = checkbox.closest('.dc-card');
        if (!card) return;
        if (checkbox.checked) {
            card.classList.remove('dc-card--inactive');
        } else {
            card.classList.add('dc-card--inactive');
        }
    }

    var lastMobile = null;

    /**
     * Apply the correct view based on viewport width.
     * Syncs values from the previously active view to the newly active one.
     */
    function applyView(mobile) {
        var desktop = getDesktopContainer();
        var mobileEl = getMobileContainer();

        if (mobile) {
            syncValues(desktop, mobileEl);
            setInputsDisabled(desktop, true);
            setInputsDisabled(mobileEl, false);
            if (mobileEl) mobileEl.removeAttribute('aria-hidden');
            if (desktop) desktop.setAttribute('aria-hidden', 'true');
        } else {
            syncValues(mobileEl, desktop);
            setInputsDisabled(mobileEl, true);
            setInputsDisabled(desktop, false);
            if (desktop) desktop.removeAttribute('aria-hidden');
            if (mobileEl) mobileEl.setAttribute('aria-hidden', 'true');
        }

        if (typeof window.IBL_recalculateDepthChartGlows === 'function') {
            window.IBL_recalculateDepthChartGlows();
        }
        if (typeof window.IBL_recalculateLineupPreview === 'function') {
            window.IBL_recalculateLineupPreview();
        }
    }

    function onResize() {
        var mobile = isMobile();
        if (mobile !== lastMobile) {
            lastMobile = mobile;
            applyView(mobile);
        }
    }

    function initMobileCards() {
        var form = getForm();
        if (!form) return;

        var mobileEl = getMobileContainer();
        if (!mobileEl) return;

        // Set initial view based on viewport
        lastMobile = isMobile();
        applyView(lastMobile);

        // Active checkbox toggle listeners — sync mobile cb → desktop cb
        var checkboxes = mobileEl.querySelectorAll('.dc-card__active-cb');
        for (var i = 0; i < checkboxes.length; i++) {
            (function (cb) {
                updateCardOpacity(cb);
                cb.addEventListener('change', function () {
                    updateCardOpacity(cb);
                    var desktop = getDesktopContainer();
                    if (desktop) {
                        var deskCb = desktop.querySelector('input[type="checkbox"][name="' + cb.name + '"]');
                        if (deskCb) deskCb.checked = cb.checked;
                    }
                    if (typeof window.IBL_recalculateDepthChartGlows === 'function') {
                        window.IBL_recalculateDepthChartGlows();
                    }
                    if (typeof window.IBL_recalculateLineupPreview === 'function') {
                        window.IBL_recalculateLineupPreview();
                    }
                });
            })(checkboxes[i]);
        }

        // Card field change listeners — sync role slot selects and minutes
        // number inputs to desktop immediately. Checkboxes are handled by
        // the dedicated loop above.
        mobileEl.addEventListener('change', function (e) {
            var target = e.target;
            if (!target.name) return;
            var desktop = getDesktopContainer();
            if (!desktop) return;
            if (target.tagName === 'SELECT') {
                var sel = desktop.querySelector('select[name="' + target.name + '"]');
                if (sel) sel.value = target.value;
            } else if (target.tagName === 'INPUT' && target.type === 'number') {
                var numInput = desktop.querySelector('input[type="number"][name="' + target.name + '"]');
                if (numInput) numInput.value = target.value;
            }
        });

        // Debounced resize handler
        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(onResize, 100);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileCards);
    } else {
        initMobileCards();
    }

    // Expose for external use (saved DC integration)
    window.IBL_applyDepthChartMobileView = function () {
        lastMobile = isMobile();
        applyView(lastMobile);
    };
})();
