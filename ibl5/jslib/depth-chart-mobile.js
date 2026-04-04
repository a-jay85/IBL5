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
     * Sync select values from source container to target container.
     * Handles select↔select and select↔checkbox mapping for canPlayInGame.
     */
    function syncValues(source, target) {
        if (!source || !target) return;

        // Sync all selects by name
        var sourceSelects = source.querySelectorAll('select[name]');
        for (var i = 0; i < sourceSelects.length; i++) {
            var sel = sourceSelects[i];
            var targetSel = target.querySelector('select[name="' + sel.name + '"]');
            if (targetSel) {
                targetSel.value = sel.value;
            }
        }

        // Sync desktop canPlayInGame selects → mobile checkboxes
        var checkboxes = target.querySelectorAll('.dc-card__active-cb');
        for (var j = 0; j < checkboxes.length; j++) {
            var cb = checkboxes[j];
            var srcSel = source.querySelector('select[name="' + cb.name + '"]');
            if (srcSel) {
                cb.checked = (srcSel.value === '1');
                updateCardOpacity(cb);
            }
        }

        // Sync mobile checkboxes → desktop canPlayInGame selects
        var sourceCheckboxes = source.querySelectorAll('.dc-card__active-cb');
        for (var k = 0; k < sourceCheckboxes.length; k++) {
            var srcCb = sourceCheckboxes[k];
            var targetSelForCb = target.querySelector('select[name="' + srcCb.name + '"]');
            if (targetSelForCb) {
                targetSelForCb.value = srcCb.checked ? '1' : '0';
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

        // Active checkbox toggle listeners
        var checkboxes = mobileEl.querySelectorAll('.dc-card__active-cb');
        for (var i = 0; i < checkboxes.length; i++) {
            (function (cb) {
                updateCardOpacity(cb);
                cb.addEventListener('change', function () {
                    updateCardOpacity(cb);
                    // Sync to desktop select
                    var desktop = getDesktopContainer();
                    if (desktop) {
                        var sel = desktop.querySelector('select[name="' + cb.name + '"]');
                        if (sel) sel.value = cb.checked ? '1' : '0';
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

        // Card select change listeners — sync to desktop immediately
        mobileEl.addEventListener('change', function (e) {
            var target = e.target;
            if (target.tagName !== 'SELECT') return;
            var desktop = getDesktopContainer();
            if (desktop) {
                var sel = desktop.querySelector('select[name="' + target.name + '"]');
                if (sel) sel.value = target.value;
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
