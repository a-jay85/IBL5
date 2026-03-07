/**
 * Sticky Page Header — clones a table's thead into a fixed overlay when
 * the original header scrolls past the fixed nav bar. Syncs horizontal
 * scroll position and column widths with the source wrapper.
 *
 * Targets: .sticky-scroll-wrapper.page-sticky on desktop (>= 1024px).
 * On mobile the wrapper's own max-height + CSS sticky handles this.
 */
(function () {
  'use strict';

  var NAV_HEIGHT = 72;
  var MQ = window.matchMedia('(min-width: 1024px)');

  function init() {
    if (!MQ.matches) return;
    var wrappers = document.querySelectorAll('.sticky-scroll-wrapper.page-sticky');
    for (var i = 0; i < wrappers.length; i++) {
      setup(wrappers[i]);
    }
  }

  function setup(wrapper) {
    var table = wrapper.querySelector('table');
    var thead = table && table.querySelector('thead');
    if (!table || !thead) return;

    var clone = null;
    var cloneTable = null;

    function createClone() {
      clone = document.createElement('div');
      clone.className = 'page-sticky-header-clone';
      clone.setAttribute('aria-hidden', 'true');

      cloneTable = document.createElement('table');
      cloneTable.className = table.className;
      cloneTable.appendChild(thead.cloneNode(true));
      clone.appendChild(cloneTable);

      document.body.appendChild(clone);
      syncWidths();
      syncScroll();
    }

    function destroyClone() {
      if (clone) {
        clone.remove();
        clone = null;
        cloneTable = null;
      }
    }

    function syncWidths() {
      if (!cloneTable) return;
      var origCells = thead.querySelectorAll('th');
      var cloneCells = cloneTable.querySelectorAll('th');
      for (var i = 0; i < origCells.length; i++) {
        if (cloneCells[i]) {
          var w = origCells[i].getBoundingClientRect().width + 'px';
          cloneCells[i].style.width = w;
          cloneCells[i].style.minWidth = w;
          cloneCells[i].style.maxWidth = w;
        }
      }
      cloneTable.style.width = table.getBoundingClientRect().width + 'px';
      cloneTable.style.tableLayout = 'fixed';
    }

    function syncScroll() {
      if (!clone) return;
      var rect = wrapper.getBoundingClientRect();
      clone.style.left = rect.left + 'px';
      clone.style.width = rect.width + 'px';
      cloneTable.style.transform = 'translateX(' + -wrapper.scrollLeft + 'px)';
    }

    function onPageScroll() {
      var theadRect = thead.getBoundingClientRect();
      var tableRect = table.getBoundingClientRect();
      var shouldShow = theadRect.top < NAV_HEIGHT &&
        tableRect.bottom > NAV_HEIGHT + theadRect.height;

      if (shouldShow && !clone) {
        createClone();
      } else if (!shouldShow && clone) {
        destroyClone();
      }

      if (clone) syncScroll();
    }

    window.addEventListener('scroll', onPageScroll, { passive: true });
    wrapper.addEventListener('scroll', function () {
      if (clone) syncScroll();
    }, { passive: true });

    var resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        if (!MQ.matches) {
          destroyClone();
          return;
        }
        if (clone) {
          syncWidths();
          syncScroll();
        }
      }, 100);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
