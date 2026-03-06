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

    draggableRows.forEach(function (row) {
        row.addEventListener('dragstart', function (e) {
            draggedRow = row;
            row.classList.add('draft-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', '');
        });

        row.addEventListener('dragend', function () {
            row.classList.remove('draft-dragging');
            clearDropIndicators();
            draggedRow = null;
        });

        row.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (draggedRow && row !== draggedRow && row.hasAttribute('draggable')) {
                clearDropIndicators();
                row.classList.add('draft-drag-over');
            }
        });

        row.addEventListener('dragleave', function () {
            row.classList.remove('draft-drag-over');
        });

        row.addEventListener('drop', function (e) {
            e.preventDefault();
            clearDropIndicators();
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

    function clearDropIndicators() {
        draggableRows.forEach(function (r) {
            r.classList.remove('draft-drag-over');
        });
    }

    function renumberPicks() {
        var currentRows = table.querySelectorAll('tr[draggable="true"]');
        currentRows.forEach(function (row, index) {
            var pickCell = row.querySelector('td:first-child');
            if (pickCell) {
                var handle = pickCell.querySelector('.draft-drag-handle');
                var handleHtml = handle ? handle.outerHTML + ' ' : '';
                pickCell.innerHTML = handleHtml + (index + 1);
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
