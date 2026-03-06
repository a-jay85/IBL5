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
    var rowHeight = 0;

    draggableRows.forEach(function (row) {
        row.addEventListener('dragstart', function (e) {
            draggedRow = row;
            rowHeight = row.offsetHeight;

            // Create a custom drag image from the row to suppress Chrome's link preview
            var clone = row.cloneNode(true);
            clone.style.width = row.offsetWidth + 'px';
            clone.style.backgroundColor = '#fff';
            clone.style.opacity = '0.9';
            clone.style.position = 'absolute';
            clone.style.top = '-9999px';
            clone.style.left = '-9999px';
            clone.style.display = 'table-row';

            // Wrap in a table so the row renders properly
            var wrapTable = document.createElement('table');
            wrapTable.style.borderCollapse = 'collapse';
            wrapTable.style.width = row.offsetWidth + 'px';
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

            // Clean up the off-screen clone after drag starts
            requestAnimationFrame(function () {
                document.body.removeChild(wrapTable);
            });

            row.classList.add('draft-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', '');
        });

        row.addEventListener('dragend', function () {
            row.classList.remove('draft-dragging');
            clearDisplacement();
            draggedRow = null;
        });

        row.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (!draggedRow || row === draggedRow || !row.hasAttribute('draggable')) return;

            applyDisplacement(row);
        });

        row.addEventListener('drop', function (e) {
            e.preventDefault();
            clearDisplacement();
            if (!draggedRow || row === draggedRow || !row.hasAttribute('draggable')) return;

            // Move the dragged row before or after the target
            var tbody = row.parentNode;
            var allRows = Array.from(tbody.children);
            var draggedIdx = allRows.indexOf(draggedRow);
            var targetIdx = allRows.indexOf(row);

            if (draggedIdx < targetIdx) {
                tbody.insertBefore(draggedRow, row.nextSibling);
            } else {
                tbody.insertBefore(draggedRow, row);
            }

            renumberPicks();
            checkForChanges();
        });
    });

    // Also handle dragover on separator rows (non-draggable) to keep displacement visible
    var allTbodyRows = table.querySelectorAll('tbody tr');
    allTbodyRows.forEach(function (row) {
        if (row.hasAttribute('draggable')) return; // already handled
        row.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });
    });

    function applyDisplacement(targetRow) {
        var rows = Array.from(table.querySelectorAll('tr[draggable="true"]'));
        var draggedIdx = rows.indexOf(draggedRow);
        var targetIdx = rows.indexOf(targetRow);

        rows.forEach(function (r, i) {
            r.classList.remove('draft-drag-placeholder');
            if (r === draggedRow) {
                r.style.transform = '';
                return;
            }
            if (draggedIdx < targetIdx) {
                // Dragging down: rows between dragged and target shift up
                if (i > draggedIdx && i <= targetIdx) {
                    r.style.transform = 'translateY(-' + rowHeight + 'px)';
                } else {
                    r.style.transform = '';
                }
            } else {
                // Dragging up: rows between target and dragged shift down
                if (i >= targetIdx && i < draggedIdx) {
                    r.style.transform = 'translateY(' + rowHeight + 'px)';
                } else {
                    r.style.transform = '';
                }
            }
        });

        targetRow.classList.add('draft-drag-placeholder');
    }

    function clearDisplacement() {
        var rows = table.querySelectorAll('tr[draggable="true"]');
        rows.forEach(function (r) {
            r.style.transform = '';
            r.classList.remove('draft-drag-placeholder');
        });
    }

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
