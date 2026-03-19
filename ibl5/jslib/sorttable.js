/**
 * SortTable — Modern replacement for sorttable.js v2 (Stuart Langridge, 2007).
 *
 * Preserves the original API surface:
 *   - class="sortable" on <table> for auto-init
 *   - sorttable_customkey attribute on <td> for custom sort keys
 *   - sorttable.init() and sorttable.makeSortable(table)
 *   - CSS classes: sorttable_sorted, sorttable_sorted_reverse
 *   - Sort indicator spans: #sorttable_sortfwdind, #sorttable_sortrevind
 *
 * Drops: IE6/Safari 2 hacks, date sorting, shaker_sort, dean_addEvent,
 *        forEach polyfills, Function.prototype extension, arguments.callee.
 *
 * Adds: aria-sort attributes on <th> elements.
 */
window.sorttable = {
  init: function () {
    var tables = document.querySelectorAll('table.sortable');
    for (var i = 0; i < tables.length; i++) {
      sorttable.makeSortable(tables[i]);
    }
  },

  makeSortable: function (table) {
    if (table.getAttribute('data-sorttable')) return;
    table.setAttribute('data-sorttable', 'true');

    if (!table.tHead) {
      var thead = document.createElement('thead');
      thead.appendChild(table.rows[0]);
      table.insertBefore(thead, table.firstChild);
    }

    if (!table.tHead || table.tHead.rows.length === 0) return;

    // Move sortbottom rows to tfoot for backwards compat
    var sortbottomRows = [];
    for (var i = 0; i < table.rows.length; i++) {
      if (table.rows[i].className.indexOf('sortbottom') !== -1) {
        sortbottomRows.push(table.rows[i]);
      }
    }
    if (sortbottomRows.length > 0) {
      var tfoot = table.tFoot || table.appendChild(document.createElement('tfoot'));
      for (var i = 0; i < sortbottomRows.length; i++) {
        tfoot.appendChild(sortbottomRows[i]);
      }
    }

    var headerCells = table.tHead.rows[0].cells;
    for (var i = 0; i < headerCells.length; i++) {
      var th = headerCells[i];
      th.setAttribute('role', 'columnheader');
      th.setAttribute('aria-sort', 'none');
      th.setAttribute('data-sort-col', String(i));
      th.addEventListener('click', sorttable._handleClick);
    }
  },

  _handleClick: function () {
    var th = this;
    var table = th.closest('table');
    if (!table || !table.tBodies[0]) return;
    var tbody = table.tBodies[0];
    var col = parseInt(th.getAttribute('data-sort-col'), 10);

    // Already sorted descending — reverse to ascending
    if (th.classList.contains('sorttable_sorted_reverse')) {
      sorttable._reverse(tbody);
      th.classList.remove('sorttable_sorted_reverse');
      th.classList.add('sorttable_sorted');
      th.setAttribute('aria-sort', 'ascending');
      sorttable._removeIndicator('sorttable_sortrevind');
      sorttable._addIndicator(th, 'sorttable_sortfwdind', '\u25BE');
      return;
    }

    // Already sorted ascending — reverse to descending
    if (th.classList.contains('sorttable_sorted')) {
      sorttable._reverse(tbody);
      th.classList.remove('sorttable_sorted');
      th.classList.add('sorttable_sorted_reverse');
      th.setAttribute('aria-sort', 'descending');
      sorttable._removeIndicator('sorttable_sortfwdind');
      sorttable._addIndicator(th, 'sorttable_sortrevind', '\u25B4');
      return;
    }

    // New column — clear previous sort state from all headers
    var allHeaders = th.parentNode.cells;
    for (var i = 0; i < allHeaders.length; i++) {
      allHeaders[i].classList.remove('sorttable_sorted', 'sorttable_sorted_reverse');
      allHeaders[i].setAttribute('aria-sort', 'none');
    }
    sorttable._removeIndicator('sorttable_sortfwdind');
    sorttable._removeIndicator('sorttable_sortrevind');

    // Schwartzian transform: decorate, sort, undecorate
    var cmp = sorttable._detectType(tbody, col) === 'numeric'
      ? sorttable._cmpNumeric
      : sorttable._cmpAlpha;

    var rows = [];
    for (var j = 0; j < tbody.rows.length; j++) {
      rows.push([sorttable._getKey(tbody.rows[j].cells[col]), tbody.rows[j]]);
    }
    rows.sort(cmp);
    // Reverse for descending (highest first) on initial click
    for (var j = rows.length - 1; j >= 0; j--) {
      tbody.appendChild(rows[j][1]);
    }

    th.classList.add('sorttable_sorted_reverse');
    th.setAttribute('aria-sort', 'descending');
    sorttable._addIndicator(th, 'sorttable_sortrevind', '\u25B4');
  },

  _getKey: function (td) {
    if (!td) return '';
    var custom = td.getAttribute('sorttable_customkey');
    if (custom !== null) return custom;
    return (td.textContent || '').trim();
  },

  _detectType: function (tbody, col) {
    for (var i = 0; i < tbody.rows.length; i++) {
      var text = sorttable._getKey(tbody.rows[i].cells[col]);
      if (text !== '') {
        return /^-?[\u00a3$\u00a4]?[\d,.]+%?$/.test(text) ? 'numeric' : 'alpha';
      }
    }
    return 'alpha';
  },

  _cmpNumeric: function (a, b) {
    var aa = parseFloat(a[0].replace(/[^0-9.\-]/g, '')) || 0;
    var bb = parseFloat(b[0].replace(/[^0-9.\-]/g, '')) || 0;
    return aa - bb;
  },

  _cmpAlpha: function (a, b) {
    return a[0].localeCompare(b[0]);
  },

  _reverse: function (tbody) {
    var rows = [];
    for (var i = 0; i < tbody.rows.length; i++) {
      rows.push(tbody.rows[i]);
    }
    for (var i = rows.length - 1; i >= 0; i--) {
      tbody.appendChild(rows[i]);
    }
  },

  _addIndicator: function (th, id, symbol) {
    var span = document.createElement('span');
    span.id = id;
    span.textContent = ' ' + symbol;
    th.appendChild(span);
  },

  _removeIndicator: function (id) {
    var el = document.getElementById(id);
    if (el) el.parentNode.removeChild(el);
  }
};

document.addEventListener('DOMContentLoaded', function () {
  sorttable.init();
});
