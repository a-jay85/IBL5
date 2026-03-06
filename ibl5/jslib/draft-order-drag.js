(function () {
    'use strict';

    var table = document.getElementById('draft-order-round1');
    if (!table) return;

    var saveBtn = document.getElementById('draft-order-save-btn');
    var draggableRows = table.querySelectorAll('tr[draggable="true"]');
    if (draggableRows.length === 0) return;

    // Store original order for comparison
    var originalOrder = [];
    draggableRows.forEach(function (row) {
        originalOrder.push(row.getAttribute('data-team-id'));
    });

    var draggedRow = null;
    var placeholder = null;

    draggableRows.forEach(function (row) {
        row.addEventListener('dragstart', function (e) {
            draggedRow = row;

            // Create a custom drag image from the row to suppress Chrome's link preview
            var clone = row.cloneNode(true);
            var wrapTable = document.createElement('table');
            wrapTable.style.cssText = 'border-collapse:collapse;width:' + row.offsetWidth + 'px;position:absolute;top:-9999px;left:-9999px;';
            var wrapBody = document.createElement('tbody');
            wrapBody.appendChild(clone);
            wrapTable.appendChild(wrapBody);

            // Copy cell widths so columns align
            var origCells = row.querySelectorAll('td');
            var cloneCells = clone.querySelectorAll('td');
            origCells.forEach(function (cell, i) {
                if (cloneCells[i]) {
                    cloneCells[i].style.width = cell.offsetWidth + 'px';
                }
            });

            document.body.appendChild(wrapTable);
            var rect = row.getBoundingClientRect();
            e.dataTransfer.setDragImage(wrapTable, e.clientX - rect.left, e.clientY - rect.top);
            requestAnimationFrame(function () {
                if (wrapTable.parentNode) wrapTable.parentNode.removeChild(wrapTable);
            });

            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', '');

            // Replace the dragged row with a placeholder after a tick
            // (browser needs the row in the DOM during dragstart)
            requestAnimationFrame(function () {
                placeholder = document.createElement('tr');
                placeholder.className = 'draft-drag-placeholder';
                placeholder.innerHTML = '<td colspan="4">&nbsp;</td>';
                placeholder.addEventListener('dragover', function (ev) {
                    ev.preventDefault();
                    ev.dataTransfer.dropEffect = 'move';
                });
                placeholder.addEventListener('drop', function (ev) {
                    ev.preventDefault();
                    if (!draggedRow || !placeholder) return;
                    placeholder.parentNode.insertBefore(draggedRow, placeholder);
                    placeholder.parentNode.removeChild(placeholder);
                    draggedRow.classList.remove('draft-dragging');
                    placeholder = null;
                    draggedRow = null;
                    renumberPicks();
                    checkForChanges();
                });
                row.parentNode.insertBefore(placeholder, row);
                row.classList.add('draft-dragging');
            });
        });

        row.addEventListener('dragend', function () {
            // Remove placeholder if it exists and put the row back
            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.insertBefore(row, placeholder);
                placeholder.parentNode.removeChild(placeholder);
            }
            row.classList.remove('draft-dragging');
            placeholder = null;
            draggedRow = null;
            renumberPicks();
            checkForChanges();
        });

        row.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (!draggedRow || !placeholder || row === draggedRow || row === placeholder) return;
            if (!row.hasAttribute('draggable')) return;

            // Move the placeholder to the target position
            var tbody = row.parentNode;
            var allChildren = Array.from(tbody.children);
            var placeholderIdx = allChildren.indexOf(placeholder);
            var targetIdx = allChildren.indexOf(row);

            if (placeholderIdx < targetIdx) {
                tbody.insertBefore(placeholder, row.nextSibling);
            } else {
                tbody.insertBefore(placeholder, row);
            }
        });

        row.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!draggedRow || !placeholder) return;

            // Insert the dragged row where the placeholder is
            placeholder.parentNode.insertBefore(draggedRow, placeholder);
            placeholder.parentNode.removeChild(placeholder);
            draggedRow.classList.remove('draft-dragging');
            placeholder = null;
            draggedRow = null;
            renumberPicks();
            checkForChanges();
        });
    });

    function renumberPicks() {
        var currentRows = table.querySelectorAll('tr[draggable="true"]');
        currentRows.forEach(function (row, index) {
            var pickCell = row.querySelector('td:first-child');
            if (pickCell) {
                pickCell.textContent = String(index + 1);
            }
        });
    }

    function checkForChanges() {
        if (!saveBtn) return;
        var currentRows = table.querySelectorAll('tr[draggable="true"]');
        var changed = false;
        currentRows.forEach(function (row, index) {
            if (row.getAttribute('data-team-id') !== originalOrder[index]) {
                changed = true;
            }
        });
        saveBtn.style.display = changed ? '' : 'none';
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            var currentRows = table.querySelectorAll('tr[draggable="true"]');
            var order = [];
            currentRows.forEach(function (row) {
                order.push(parseInt(row.getAttribute('data-team-id'), 10));
            });

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            fetch('modules.php?name=ProjectedDraftOrder&file=save-order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order: order })
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save Draft Order';
                    }
                })
                .catch(function () {
                    alert('Network error. Please try again.');
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Draft Order';
                });
        });
    }
})();
