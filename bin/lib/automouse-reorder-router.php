<?php
// Router for `php -S 127.0.0.1:<port> bin/lib/automouse-reorder-router.php`,
// spawned by bin/automouse-queue-reorder-ui. Serves a self-contained
// drag-and-drop page (GET /) and applies a reorder (POST /apply) by shelling
// out to the tested `bin/automouse-queue reorder` engine — this layer holds no
// validation of its own. Inputs arrive via inherited env from the launcher:
//   AUTOMOUSE_QUEUE_ORDER  newline-separated queue basenames, ls -1tr order
//   AUTOMOUSE_QUEUE_CMD    absolute path to bin/automouse-queue
//   AUTOMOUSE_SENTINEL     file the router writes ONLY on a successful apply
//   NIGHTLY_DIR / PLANS_DIR  inherited so the shelled-out engine sees the same queue
//
// NOTE: this is a local-only dev-ops tool served on 127.0.0.1, outside the
// ibl5/ PHPStan scope — the app's CsrfGuard / XSS rules do not apply here.

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($method === 'POST' && $uri === '/apply') {
    apply_reorder();
    return;
}
serve_page();

function queue_slugs(): array
{
    $raw = getenv('AUTOMOUSE_QUEUE_ORDER');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode("\n", $raw)), 'strlen'));
}

function apply_reorder(): void
{
    header('Content-Type: application/json');
    $body = json_decode(file_get_contents('php://input'), true);
    $order = is_array($body) && isset($body['order']) ? $body['order'] : null;
    if (!is_array($order) || $order === []) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'output' => 'Bad request: expected {order:[...]}.']);
        return;
    }
    foreach ($order as $slug) {
        if (!is_string($slug) || $slug === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'output' => 'Bad request: order must be non-empty strings.']);
            return;
        }
    }

    $queueCmd = getenv('AUTOMOUSE_QUEUE_CMD') ?: 'automouse-queue';
    // escapeshellarg every token (defense-in-depth on top of the engine's own
    // permutation validation); 2>&1 so the engine's error text flows back.
    $cmd = escapeshellarg($queueCmd) . ' reorder '
         . implode(' ', array_map('escapeshellarg', $order)) . ' 2>&1';
    exec($cmd, $out, $ret);
    $output = implode("\n", $out);

    if ($ret === 0) {
        $sentinel = getenv('AUTOMOUSE_SENTINEL');
        if ($sentinel !== false && $sentinel !== '') {
            file_put_contents($sentinel, $output);   // signal the launcher to tear down
        }
        echo json_encode(['ok' => true, 'exit' => 0, 'output' => $output]);
        return;
    }
    // Rejection: no sentinel, server stays up so the user can retry.
    echo json_encode(['ok' => false, 'exit' => $ret, 'output' => $output]);
}

function serve_page(): void
{
    $slugs = queue_slugs();
    header('Content-Type: text/html; charset=utf-8');

    $items = '';
    foreach ($slugs as $i => $slug) {
        $safe  = htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');           // data-slug (engine accepts .md or not)
        $label = htmlspecialchars(preg_replace('/\.md$/', '', $slug), ENT_QUOTES, 'UTF-8');  // human-facing, no .md
        $pos   = $i + 1;
        $items .= <<<LI
      <li class="row" draggable="true" data-slug="{$safe}">
        <span class="handle" aria-hidden="true">⠿</span>
        <span class="pos">{$pos}</span>
        <span class="slug">{$label}</span>
      </li>

LI;
    }

    $empty = $slugs === [];
    $emptyNote = $empty
        ? '<p class="empty">The queue is empty — nothing to reorder.</p>'
        : '';
    $saveDisabled = $empty ? 'disabled' : '';

    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Automouse queue — reorder</title>
<style>
  :root { color-scheme: light; }
  * { box-sizing: border-box; }
  body {
    font: 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    margin: 0; padding: 2rem 1rem; background: #f4f5f7; color: #1c1e21;
  }
  main { max-width: 720px; margin: 0 auto; }
  h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
  p.sub { margin: 0 0 1.25rem; color: #606770; }
  ol#queue { list-style: none; margin: 0; padding: 0; }
  li.row {
    display: flex; align-items: center; gap: .75rem;
    background: #fff; border: 1px solid #dcdfe3; border-radius: 8px;
    padding: .7rem .9rem; margin: 0 0 .5rem;
    cursor: grab; user-select: none;
    box-shadow: 0 1px 2px rgba(0,0,0,.04);
  }
  li.row:active { cursor: grabbing; }
  li.row.dragging { opacity: .4; border-style: dashed; }
  .handle { color: #b0b3b8; font-size: 1.1rem; }
  .pos {
    min-width: 1.6rem; height: 1.6rem; display: inline-flex;
    align-items: center; justify-content: center;
    background: #eef0f2; border-radius: 50%; font-size: .8rem;
    color: #606770; font-variant-numeric: tabular-nums;
  }
  li.row:first-child .pos { background: #d6f0d8; color: #1a7f37; }
  .slug { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .92rem; }
  .actions { margin-top: 1.25rem; display: flex; align-items: center; gap: 1rem; }
  button {
    font: inherit; font-weight: 600; color: #fff; background: #1a7f37;
    border: 0; border-radius: 8px; padding: .55rem 1.1rem; cursor: pointer;
  }
  button:disabled { background: #9db0a3; cursor: default; }
  #msg { font-size: .9rem; }
  #msg.ok  { color: #1a7f37; }
  #msg.err { color: #c0392b; white-space: pre-wrap; font-family: ui-monospace, Menlo, monospace; }
  .hint { color: #8a8d91; font-size: .82rem; margin-top: 1.5rem; }
  .empty { color: #606770; font-style: italic; }
</style>
</head>
<body>
<main>
  <h1>Automouse queue — drag to reorder</h1>
  <p class="sub">Top of the list runs <strong>next</strong>. Drag rows, then Save.</p>
  {$emptyNote}
  <ol id="queue">
{$items}  </ol>
  <div class="actions">
    <button id="save" {$saveDisabled}>Save order</button>
    <span id="msg"></span>
  </div>
  <p class="hint">Save applies the new order and shuts this server down. Close the tab or press Ctrl-C in the terminal to cancel with no changes.</p>
</main>
<script>
const list = document.getElementById('queue');
let dragEl = null;

list.addEventListener('dragstart', e => {
  dragEl = e.target.closest('li');
  if (dragEl) dragEl.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
});
list.addEventListener('dragend', () => {
  if (dragEl) dragEl.classList.remove('dragging');
  dragEl = null;
  renumber();
});
list.addEventListener('dragover', e => {
  e.preventDefault();
  if (!dragEl) return;
  const after = afterElement(list, e.clientY);
  if (after == null) list.appendChild(dragEl);
  else list.insertBefore(dragEl, after);
});
function afterElement(container, y) {
  const els = [...container.querySelectorAll('li:not(.dragging)')];
  return els.reduce((closest, child) => {
    const box = child.getBoundingClientRect();
    const offset = y - box.top - box.height / 2;
    return (offset < 0 && offset > closest.offset) ? { offset, element: child } : closest;
  }, { offset: -Infinity }).element;
}
function renumber() {
  [...list.children].forEach((li, i) => { li.querySelector('.pos').textContent = (i + 1); });
}

const saveBtn = document.getElementById('save');
saveBtn && saveBtn.addEventListener('click', async () => {
  const order = [...list.querySelectorAll('li')].map(li => li.dataset.slug);
  const msg = document.getElementById('msg');
  msg.className = ''; msg.textContent = 'Applying…';
  saveBtn.disabled = true;
  try {
    const res = await fetch('/apply', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order })
    });
    const data = await res.json();
    if (data.ok) {
      msg.className = 'ok';
      msg.textContent = 'Queue reordered. You can close this tab — the server is shutting down.';
    } else {
      msg.className = 'err';
      msg.textContent = data.output || 'Reorder rejected.';
      saveBtn.disabled = false;   // let the user fix and retry
    }
  } catch (err) {
    msg.className = 'err';
    msg.textContent = 'Request failed: ' + err;
    saveBtn.disabled = false;
  }
});
</script>
</body>
</html>
HTML;
}
