/**
 * Trade Submit Guard
 *
 * Disables the "Make Trade Offer" button until both teams have at least one
 * checkbox checked or cash field with a value > 0.
 *
 * Reads config from window.IBL_TRADE_CONFIG:
 *   { switchCounter, cashStartYear, cashEndYear }
 */
(function () {
    'use strict';

    var config = window.IBL_TRADE_CONFIG;
    if (!config) return;

    var form = document.querySelector('form[name="Trade_Offer"]');
    var btn = document.getElementById('trade-submit-btn');
    if (!form || !btn) return;

    var switchCounter = config.switchCounter;
    var cashStart = config.cashStartYear;
    var cashEnd = config.cashEndYear;

    function hasUserItems() {
        for (var i = 0; i < switchCounter; i++) {
            var cb = form.elements['check' + i];
            if (cb && cb.type === 'checkbox' && cb.checked) return true;
        }
        for (var y = cashStart; y <= cashEnd; y++) {
            var input = form.elements['userSendsCash' + y];
            if (input && Number(input.value) > 0) return true;
        }
        return false;
    }

    function hasPartnerItems() {
        var total = Number(form.elements['fieldsCounter'].value);
        for (var i = switchCounter; i <= total; i++) {
            var cb = form.elements['check' + i];
            if (cb && cb.type === 'checkbox' && cb.checked) return true;
        }
        for (var y = cashStart; y <= cashEnd; y++) {
            var input = form.elements['partnerSendsCash' + y];
            if (input && Number(input.value) > 0) return true;
        }
        return false;
    }

    function update() {
        btn.disabled = !(hasUserItems() && hasPartnerItems());
    }

    form.addEventListener('change', update);
    form.addEventListener('input', update);
    update();
})();
