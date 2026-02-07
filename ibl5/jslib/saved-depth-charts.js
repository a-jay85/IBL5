/**
 * Saved Depth Charts - AJAX dropdown, form population, rename, and stats display
 *
 * Reads configuration from window.IBL_DEPTH_CHART_CONFIG:
 *   { teamId, apiBaseUrl, currentRosterPids }
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
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

        // Show/hide rename button based on selection
        select.addEventListener('change', function () {
            var dcId = parseInt(select.value, 10);

            if (renameBtn) {
                renameBtn.style.display = dcId > 0 ? 'inline-block' : 'none';
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
                if (dcId <= 0) return;

                var currentText = select.options[select.selectedIndex].text;
                var newName = prompt('Enter a name for this depth chart:', currentText);
                if (newName === null || newName.trim() === '') return;

                renameDepthChart(dcId, newName.trim());
            });
        }

        function loadDepthChart(dcId) {
            if (loadingEl) loadingEl.style.display = 'block';

            var url = config.apiBaseUrl + '&action=load&id=' + dcId;

            fetch(url, { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) throw new Error('Failed to load depth chart');
                    return response.json();
                })
                .then(function (data) {
                    if (loadingEl) loadingEl.style.display = 'none';
                    populateForm(data);
                    showStats(data);
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

            // Clear any previous traded player styling
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

                setSelectValue(form, 'pg' + depthCount, player.dc_PGDepth);
                setSelectValue(form, 'sg' + depthCount, player.dc_SGDepth);
                setSelectValue(form, 'sf' + depthCount, player.dc_SFDepth);
                setSelectValue(form, 'pf' + depthCount, player.dc_PFDepth);
                setSelectValue(form, 'c' + depthCount, player.dc_CDepth);
                setSelectValue(form, 'active' + depthCount, player.dc_active);
                setSelectValue(form, 'min' + depthCount, player.dc_minutes);
                setSelectValue(form, 'OF' + depthCount, player.dc_of);
                setSelectValue(form, 'DF' + depthCount, player.dc_df);
                setSelectValue(form, 'OI' + depthCount, player.dc_oi);
                setSelectValue(form, 'DI' + depthCount, player.dc_di);
                setSelectValue(form, 'BH' + depthCount, player.dc_bh);

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

                    // Disable selects for traded players
                    var tradedSelects = row.querySelectorAll('select');
                    for (var ts = 0; ts < tradedSelects.length; ts++) {
                        tradedSelects[ts].disabled = true;
                    }
                }
            }
        }

        function setSelectValue(form, name, value) {
            var sel = form.elements[name];
            if (sel && sel.tagName === 'SELECT') {
                sel.value = String(value);
            }
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
                    }
                })
                .catch(function (err) {
                    console.error('Error refreshing dropdown:', err);
                });
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    });
})();
