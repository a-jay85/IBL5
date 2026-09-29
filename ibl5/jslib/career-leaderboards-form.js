/**
 * Career Leaderboards filter form — progressive enhancement.
 *
 * The form works without JS (the server renders the same state). This keeps
 * the controls consistent while the user edits them:
 *  - phases without an averages table (rookie, sophomore) disable Averages
 *  - the PPG option reads "PTS" on Totals and "PPG" on Averages
 *  - FG% / FT% / TG% only exist on averages tables, so they are disabled on Totals
 */
(function () {
    'use strict';

    var form = document.querySelector('form[name="CareerLeaderboards"]');
    if (!form) return;

    var phase = form.querySelector('select[name="phase"]');
    var sortby = form.querySelector('select[name="sortby"]');
    var totals = form.querySelector('input[name="mode"][value="totals"]');
    var averages = form.querySelector('input[name="mode"][value="averages"]');
    if (!phase || !sortby || !totals || !averages) return;

    var PERCENT_KEYS = ['FGP', 'FTP', 'TGP'];

    function syncSortOptions() {
        var isTotals = !averages.checked;

        for (var i = 0; i < sortby.options.length; i++) {
            var option = sortby.options[i];

            if (option.value === 'PPG') {
                option.textContent = isTotals ? 'PTS' : 'PPG';
            }
            if (PERCENT_KEYS.indexOf(option.value) !== -1) {
                option.disabled = isTotals;
            }
        }

        var selected = sortby.options[sortby.selectedIndex];
        if (selected && selected.disabled) {
            sortby.value = 'PPG';
        }
    }

    function syncPhase() {
        var chosen = phase.options[phase.selectedIndex];
        var hasAverages = !chosen || chosen.getAttribute('data-has-averages') !== '0';

        averages.disabled = !hasAverages;
        if (!hasAverages && averages.checked) {
            totals.checked = true;
        }
        syncSortOptions();
    }

    phase.addEventListener('change', syncPhase);
    totals.addEventListener('change', syncSortOptions);
    averages.addEventListener('change', syncSortOptions);
    syncPhase();
})();
