/**
 * contract-hint.js
 *
 * Sizes each .contract-hint-link to span the full width of the 5
 * .contract-hint-cell siblings in its row.  Runs on load and resize.
 */
(function () {
    function sizeLinks() {
        var links = document.querySelectorAll('.contract-hint-link');
        for (var i = 0; i < links.length; i++) {
            var link = links[i];
            var cell = link.closest('.contract-hint-cell');
            if (!cell || !cell.parentElement) continue;
            var cells = cell.parentElement.querySelectorAll('.contract-hint-cell');
            if (cells.length === 0) continue;

            var first = cells[0];
            var last = cells[cells.length - 1];
            var width = last.offsetLeft + last.offsetWidth - first.offsetLeft;
            link.style.width = width + 'px';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sizeLinks);
    } else {
        sizeLinks();
    }
    window.addEventListener('resize', sizeLinks);
})();
