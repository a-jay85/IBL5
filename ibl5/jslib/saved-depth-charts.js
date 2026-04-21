/**
 * Saved Depth Charts - AJAX dropdown, form population, rename, and stats display
 *
 * Reads configuration from window.IBL_DEPTH_CHART_CONFIG:
 *   { teamId, apiBaseUrl, currentRosterPids }
 */
(function () {
    'use strict';

    function initSavedDepthCharts() {
        var config = window.IBL_DEPTH_CHART_CONFIG;
        if (!config || !config.teamId || !config.apiBaseUrl) {
            return;
        }

        var select = document.getElementById('saved-dc-select');
        var renameBtn = document.getElementById('saved-dc-rename-btn');
        var loadingEl = document.getElementById('saved-dc-loading');
        var loadedDcInput = document.getElementById('loaded_dc_id');

        if (!select) {
            return;
        }

        // Show pencil on initial page load (Current (Live) is selected by default)
        if (renameBtn) {
            renameBtn.style.display = 'inline-block';
        }

        // Show/hide rename button based on selection
        select.addEventListener('change', function () {
            var dcId = parseInt(select.value, 10);

            if (renameBtn) {
                renameBtn.style.display = 'inline-block';
            }

            if (dcId === 0) {
                // "Current" selected - reload page to restore live DB values
                window.location.href = 'modules.php?name=DepthChartEntry';
                return;
            }

            loadDepthChart(dcId);
        });

        // Rename button handler
        if (renameBtn) {
            renameBtn.addEventListener('click', function () {
                var dcId = parseInt(select.value, 10);

                if (dcId === 0) {
                    // Naming the current (live) depth chart
                    var liveName = prompt('Enter a name for the current depth chart:');
                    if (liveName === null || liveName.trim() === '') return;

                    renameActiveDepthChart(liveName.trim());
                    return;
                }

                var currentText = select.options[select.selectedIndex].text;
                var newName = prompt('Enter a name for this depth chart:', currentText);
                if (newName === null || newName.trim() === '') return;

                renameDepthChart(dcId, newName.trim());
            });
        }

        function loadDepthChart(dcId) {
            if (loadingEl) loadingEl.style.display = 'block';

            var url = config.apiBaseUrl + '&action=load&id=' + encodeURIComponent(String(dcId));

            fetch(url, { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) throw new Error('Failed to load depth chart');
                    return response.json();
                })
                .then(function (data) {
                    if (loadingEl) loadingEl.style.display = 'none';
                    populateForm(data);
                    showStats(data);
                    if (typeof window.IBL_recalculateDepthChartGlows === 'function') {
                        window.IBL_recalculateDepthChartGlows();
                    }
                    if (typeof window.IBL_recalculateLineupPreview === 'function') {
                        window.IBL_recalculateLineupPreview();
                    }
                })
                .catch(function (err) {
                    if (loadingEl) loadingEl.style.display = 'none';
                    console.error('Error loading saved depth chart:', err);
                    alert('Failed to load saved depth chart. Please try again.');
                });
        }

        function populateForm(data) {
            // Set hidden loaded_dc_id
            if (loadedDcInput) {
                loadedDcInput.value = String(data.depthChart.id);
            }

            var form = document.forms['DepthChartEntry'];
            if (!form) return;

            // Clear any previous traded player styling (desktop)
            var allRows = form.querySelectorAll('tr[data-pid]');
            for (var i = 0; i < allRows.length; i++) {
                allRows[i].classList.remove('depth-chart-traded-player');
                var badge = allRows[i].querySelector('.traded-badge');
                if (badge) badge.remove();

                // Re-enable all selects
                var selects = allRows[i].querySelectorAll('select');
                for (var s = 0; s < selects.length; s++) {
                    selects[s].disabled = false;
                }
            }

            // Clear previous traded player styling (mobile cards)
            var allCards = form.querySelectorAll('.dc-card[data-pid]');
            for (var ci = 0; ci < allCards.length; ci++) {
                allCards[ci].classList.remove('dc-card--traded');
                var cardBadge = allCards[ci].querySelector('.traded-badge');
                if (cardBadge) cardBadge.remove();
            }

            // Populate player settings
            var players = data.players || [];
            for (var p = 0; p < players.length; p++) {
                var player = players[p];
                var pid = player.pid;
                var row = form.querySelector('tr[data-pid="' + pid + '"]');

                if (!row) continue;

                // Find the depthCount for this row by reading the hidden pid input
                var pidInput = row.querySelector('input[name^="pid"]');
                if (!pidInput) continue;
                var depthCount = pidInput.name.replace('pid', '');

                // Active status (checkbox) + minutes (number input, clamped to 0-40)
                setFieldValue(form, 'canPlayInGame' + depthCount, player.dc_canPlayInGame);
                setFieldValue(form, 'min' + depthCount, Math.max(0, Math.min(40, player.dc_minutes)));

                // Position depth values (clamp to 0-5)
                setFieldValue(form, 'pg' + depthCount, Math.max(0, Math.min(5, player.dc_PGDepth)));
                setFieldValue(form, 'sg' + depthCount, Math.max(0, Math.min(5, player.dc_SGDepth)));
                setFieldValue(form, 'sf' + depthCount, Math.max(0, Math.min(5, player.dc_SFDepth)));
                setFieldValue(form, 'pf' + depthCount, Math.max(0, Math.min(5, player.dc_PFDepth)));
                setFieldValue(form, 'c' + depthCount, Math.max(0, Math.min(5, player.dc_CDepth)));

                // Mark traded players
                if (!player.isOnCurrentRoster) {
                    row.classList.add('depth-chart-traded-player');

                    // Add TRADED badge if not already present
                    if (!row.querySelector('.traded-badge')) {
                        var nameCell = row.querySelector('td:nth-child(2)');
                        if (nameCell) {
                            var badge = document.createElement('span');
                            badge.className = 'traded-badge';
                            badge.textContent = 'TRADED';
                            nameCell.appendChild(badge);
                        }
                    }

                    // Disable form fields for traded players (selects, minutes
                    // number input, active checkbox)
                    var tradedFields = row.querySelectorAll('select, input[type="number"], input[type="checkbox"]');
                    for (var ts = 0; ts < tradedFields.length; ts++) {
                        tradedFields[ts].disabled = true;
                    }
                }

                // Update mobile card for this player
                var card = form.querySelector('.dc-card[data-pid="' + pid + '"]');
                if (card) {
                    // Sync canPlayInGame checkbox
                    var cb = card.querySelector('.dc-card__active-cb');
                    if (cb) {
                        cb.checked = (player.dc_canPlayInGame === 1);
                        if (cb.checked) {
                            card.classList.remove('dc-card--inactive');
                        } else {
                            card.classList.add('dc-card--inactive');
                        }
                    }

                    // Mark traded players on mobile
                    if (!player.isOnCurrentRoster) {
                        card.classList.add('dc-card--traded');
                        if (!card.querySelector('.traded-badge')) {
                            var nameEl = card.querySelector('.dc-card__name');
                            if (nameEl) {
                                var cardBadge2 = document.createElement('span');
                                cardBadge2.className = 'traded-badge';
                                cardBadge2.textContent = 'TRADED';
                                nameEl.parentNode.insertBefore(cardBadge2, nameEl.nextSibling);
                            }
                        }
                    }
                }
            }

            // Re-apply mobile view state after populating
            if (typeof window.IBL_applyDepthChartMobileView === 'function') {
                window.IBL_applyDepthChartMobileView();
            }
        }

        /**
         * Apply a value to every form field sharing the given name. Handles
         * SELECT (position depth), INPUT[type=number] (minutes), and
         * INPUT[type=checkbox] (canPlayInGame). Hidden inputs sharing the
         * canPlayInGame name are intentionally left alone — they always carry
         * "0" so the form still posts 0 when the checkbox is unchecked.
         */
        function setFieldValue(form, name, value) {
            var el = form.elements[name];
            if (!el) return;

            // RadioNodeList when multiple elements share the name (desktop +
            // mobile selects/checkboxes, or checkbox + its sibling hidden field)
            var items = (typeof el.length === 'number' && !el.tagName) ? el : [el];
            for (var i = 0; i < items.length; i++) {
                applyFieldValue(items[i], value);
            }
        }

        function applyFieldValue(field, value) {
            if (field.tagName === 'SELECT') {
                field.value = String(value);
                return;
            }
            if (field.tagName !== 'INPUT') {
                return;
            }
            if (field.type === 'checkbox') {
                field.checked = (Number(value) === 1);
                return;
            }
            if (field.type === 'number' || field.type === 'text') {
                field.value = String(value);
            }
            // Intentionally skip type="hidden" — those are the canPlayInGame
            // unchecked-fallback fields and must stay at "0".
        }

        function showStats(data) {
            // Inject period averages into Sim Averages tab content area
            if (data.statsHtml) {
                var statsContainer = document.getElementById('saved-dc-stats-panel');
                if (statsContainer) {
                    statsContainer.innerHTML = data.statsHtml;
                }
            }

            // Update "Sim Averages" tab label with date range if applicable
            var dc = data.depthChart;
            if (dc && dc.simNumberStart && dc.simNumberEnd && dc.simNumberStart !== dc.simNumberEnd) {
                updateSimAveragesTabLabel(dc.simStartDate, dc.simEndDate);
            }
        }

        function updateSimAveragesTabLabel(startDate, endDate) {
            // Find the Sim Averages tab link
            var links = document.querySelectorAll('.table-view-switcher a, .table-view-switcher button');
            for (var i = 0; i < links.length; i++) {
                if (links[i].textContent.trim() === 'Sim Averages') {
                    var start = formatShortDate(startDate);
                    var end = formatShortDate(endDate);
                    if (start && end) {
                        links[i].textContent = start + ' - ' + end;
                    }
                    break;
                }
            }
        }

        function formatShortDate(dateStr) {
            if (!dateStr) return '';
            var d = new Date(dateStr + 'T00:00:00');
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return months[d.getMonth()] + ' ' + d.getDate();
        }

        function renameDepthChart(dcId, newName) {
            var url = config.apiBaseUrl + '&action=rename';

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: dcId, name: newName })
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('Failed to rename');
                    return response.json();
                })
                .then(function (data) {
                    if (data.success && select) {
                        // Refresh the dropdown to show the updated name
                        var option = select.querySelector('option[value="' + dcId + '"]');
                        if (option) {
                            // Re-fetch list to get properly formatted label
                            refreshDropdown(dcId);
                        }
                    }
                })
                .catch(function (err) {
                    console.error('Error renaming depth chart:', err);
                    alert('Failed to rename. Please try again.');
                });
        }

        function renameActiveDepthChart(newName) {
            var url = config.apiBaseUrl + '&action=rename-active';

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: newName })
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('Failed to name active depth chart');
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        refreshDropdown(data.id);
                    } else if (data.error) {
                        alert(data.error);
                    }
                })
                .catch(function (err) {
                    console.error('Error naming active depth chart:', err);
                    alert('Failed to name depth chart. Please try again.');
                });
        }

        function refreshDropdown(selectedId) {
            var url = config.apiBaseUrl + '&action=list';

            fetch(url, { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) throw new Error('Failed to refresh list');
                    return response.json();
                })
                .then(function (data) {
                    if (!select) return;

                    // Update "Current (Live)" option label if provided
                    if (data.currentLiveLabel && select.options.length > 0) {
                        select.options[0].textContent = data.currentLiveLabel;
                    }

                    // Preserve the "Current (Live)" option
                    while (select.options.length > 1) {
                        select.remove(1);
                    }

                    var options = data.options || [];
                    for (var i = 0; i < options.length; i++) {
                        var opt = document.createElement('option');
                        opt.value = String(options[i].id);
                        opt.textContent = options[i].label;
                        select.appendChild(opt);
                    }

                    // Re-select the previously selected item
                    if (selectedId) {
                        select.value = String(selectedId);
                        // If option wasn't found (e.g., active DC hidden because it matches live), fall back to Current (Live)
                        if (select.selectedIndex === -1) {
                            select.value = '0';
                        }
                    }
                })
                .catch(function (err) {
                    console.error('Error refreshing dropdown:', err);
                });
        }

    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSavedDepthCharts);
    } else {
        initSavedDepthCharts();
    }
})();
