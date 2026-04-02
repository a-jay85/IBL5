/**
 * offer-salary-hints.js
 *
 * Shows placeholder hints on contract offer year 2-6 inputs based on
 * the year 1 value. The hint shows the maximum allowed salary for each
 * year, calculated as: year1 + floor(year1 * raisePercentage) * (yearNum - 1).
 *
 * Uses Math.floor() to match the server-side ContractRules::calculateMaxRaise().
 *
 * Activated by a container with class "offer-salary-row--inputs" and
 * data-raise-percentage attribute.
 */
(function () {
    function updateHints(container) {
        var raisePercentage = parseFloat(container.dataset.raisePercentage);
        if (isNaN(raisePercentage)) return;

        var inputs = container.querySelectorAll('input[type="number"]');
        if (inputs.length < 2) return;

        var year1Value = parseInt(inputs[0].value, 10);

        for (var i = 1; i < inputs.length; i++) {
            if (year1Value > 0) {
                var maxRaise = Math.floor(year1Value * raisePercentage);
                inputs[i].placeholder = (year1Value + maxRaise * i).toString();
            } else {
                inputs[i].placeholder = '';
            }
        }
    }

    function init() {
        var containers = document.querySelectorAll('.offer-salary-row--inputs[data-raise-percentage]');
        for (var i = 0; i < containers.length; i++) {
            var container = containers[i];
            var firstInput = container.querySelector('input[type="number"]');
            if (!firstInput) continue;

            firstInput.addEventListener('input', (function (c) {
                return function () { updateHints(c); };
            })(container));

            // Run on load for pre-filled values (e.g. validation error redirect)
            updateHints(container);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
