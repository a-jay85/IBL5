// Converts <time class="local-time"> elements from UTC to the user's local timezone.
// Fallback: if JS is disabled, the server-rendered UTC string remains visible.
(function () {
  function formatLocalTimes() {
    var times = document.querySelectorAll('time.local-time');
    for (var i = 0; i < times.length; i++) {
      var el = times[i];
      var iso = el.getAttribute('datetime');
      if (!iso) continue;
      var d = new Date(iso);
      if (isNaN(d.getTime())) continue;
      var options = {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
        timeZoneName: 'short'
      };
      el.textContent = d.toLocaleString(undefined, options);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', formatLocalTimes);
  } else {
    formatLocalTimes();
  }

  // Re-run after HTMX swaps new content in
  document.addEventListener('htmx:afterSettle', formatLocalTimes);
})();
