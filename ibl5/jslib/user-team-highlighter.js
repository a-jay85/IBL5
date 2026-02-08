/**
 * User Team Row Highlighter
 *
 * Highlights table rows and schedule game cards that match the logged-in
 * user's team. Reads the user's team ID from the <body data-user-team-id>
 * attribute (set by theme.php) and applies CSS classes to matching elements:
 *
 * - .user-team-row on <tr data-team-id> or <tr data-team-ids> elements
 * - .user-team-game on .schedule-game[data-home-team-id] or
 *   .schedule-game[data-visitor-team-id] elements
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var userTeamId = document.body.getAttribute('data-user-team-id');
        if (!userTeamId) {
            return;
        }

        // Highlight <tr data-team-id="X"> rows
        var rows = document.querySelectorAll('tr[data-team-id]');
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].getAttribute('data-team-id') === userTeamId) {
                rows[i].classList.add('user-team-row');
            }
        }

        // Highlight <tr data-team-ids="X,Y"> rows (comma-separated, e.g. Player Movement)
        var multiRows = document.querySelectorAll('tr[data-team-ids]');
        for (var j = 0; j < multiRows.length; j++) {
            var ids = multiRows[j].getAttribute('data-team-ids').split(',');
            for (var k = 0; k < ids.length; k++) {
                if (ids[k] === userTeamId) {
                    multiRows[j].classList.add('user-team-row');
                    break;
                }
            }
        }

        // Highlight .schedule-game cards where home or visitor matches
        var games = document.querySelectorAll('.schedule-game[data-home-team-id], .schedule-game[data-visitor-team-id]');
        for (var g = 0; g < games.length; g++) {
            var home = games[g].getAttribute('data-home-team-id');
            var visitor = games[g].getAttribute('data-visitor-team-id');
            if (home === userTeamId || visitor === userTeamId) {
                games[g].classList.add('user-team-game');
            }
        }
    });
})();
