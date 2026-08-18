<?php

declare(strict_types=1);

require __DIR__ . '/mainfile.php';

use DraftClassImport\DraftClassCsvParser;
use Security\CsrfGuard;
use Security\HtmlSanitizer;

/** @var mysqli $mysqli_db */

// Admin-only access
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}

/** Session key carrying the parsed-but-uncommitted rows between the upload and commit requests. */
const DRAFT_CLASS_SESSION_KEY = 'draft_class_import_rows';

/** CSRF form name — distinct from import-demands.php's 'import_demands'. */
const DRAFT_CLASS_CSRF_FORM = 'draft_class_upload';

/** Upload size cap: this importer expects a few hundred rows, never a megabyte. */
const DRAFT_CLASS_MAX_UPLOAD_BYTES = 1048576;

const DRAFT_CLASS_PAGE_URL = '/ibl5/uploadDraftClass.php';

/**
 * The 27 columns this importer writes, in CSV field order (shared-context decision #1).
 * `drafted` and `stamina` are deliberately absent — they take their DB defaults.
 */
const DRAFT_CLASS_COLUMNS = [
    'name', 'pos', 'age', 'team',
    'fga', 'fgp', 'fta', 'ftp', 'r_3ga', 'r_3gp', 'orb', 'drb', 'ast', 'stl',
    'tvr', 'blk', 'oo', 'r_drive_off', 'po', 'r_trans_off', 'od', 'dd', 'pd', 'td',
    'talent', 'skill', 'intangibles',
];

/**
 * Right-hand column-group separators for the preview table, mirroring the ratings
 * table's grouping (classes/UI/Tables/Ratings.php): identity | shooting | rebound
 * and defence counts | offensive and defensive ratings | intangibles, with the
 * light rule inside the shooting and offence groups.
 */
const DRAFT_CLASS_COLUMN_SEPARATORS = [
    'team' => 'sep-r-team',
    'fgp' => 'sep-r-weak',
    'ftp' => 'sep-r-weak',
    'r_3gp' => 'sep-r-team',
    'blk' => 'sep-r-team',
    'r_trans_off' => 'sep-r-weak',
    'td' => 'sep-r-team',
];

/** Columns bound as strings; every other column is bound as an integer. */
const DRAFT_CLASS_STRING_COLUMNS = ['name', 'pos', 'team'];

/** @var 'form'|'preview'|'imported' $view */
$view = 'form';
/** @var list<string> $errors Aggregated per-row parse/validation errors. */
$errors = [];
/** @var string $errorMessage Single page-level error (upload guards, DB failure). */
$errorMessage = '';
/** @var list<array<string, int|string>> $rows */
$rows = [];
/** @var int $deleteCount */
$deleteCount = 0;
/** @var int $importedCount */
$importedCount = 0;
/** @var string $csrfField Rendered hidden-input markup; generated once per rendering request. */
$csrfField = '';

$method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])
    ? $_SERVER['REQUEST_METHOD']
    : 'GET';

$action = '';
if (isset($_POST['action']) && is_string($_POST['action'])) {
    $action = $_POST['action'];
} elseif (isset($_GET['action']) && is_string($_GET['action'])) {
    $action = $_GET['action'];
}

if ($method === 'POST' && $action === 'upload') {
    // CSRF first: validateSubmittedToken() is also what starts the PHP session.
    if (!CsrfGuard::validateSubmittedToken(DRAFT_CLASS_CSRF_FORM)) {
        http_response_code(403);
        die('Invalid or expired form submission. Reload the page and try again.');
    }

    /** @var array{name?: string, tmp_name?: string, error?: int, size?: int}|null $file */
    $file = isset($_FILES['draftClassFile']) && is_array($_FILES['draftClassFile'])
        ? $_FILES['draftClassFile']
        : null;

    if ($file === null || !isset($file['error'], $file['tmp_name'], $file['size'])) {
        $errorMessage = 'No file was uploaded. Choose a CSV file and try again.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'That file is too large.'
            : 'The upload did not complete. Try again.';
    } elseif (!is_uploaded_file($file['tmp_name'])) {
        $errorMessage = 'That upload could not be read. Try again.';
    } elseif ($file['size'] > DRAFT_CLASS_MAX_UPLOAD_BYTES) {
        $errorMessage = 'That file is larger than 1 MB — this importer expects a draft-class export of a few hundred rows.';
    } else {
        $raw = file_get_contents($file['tmp_name']);
        if ($raw === false) {
            $errorMessage = 'That upload could not be read. Try again.';
        } else {
            $result = (new DraftClassCsvParser())->parse($raw);
            if ($result['errors'] !== []) {
                // Nothing is stored: the commissioner fixes the file and uploads again.
                $errors = $result['errors'];
            } else {
                $_SESSION[DRAFT_CLASS_SESSION_KEY] = $result['rows'];
                header('Location: ' . DRAFT_CLASS_PAGE_URL);
                exit;
            }
        }
    }
} elseif ($method === 'POST' && $action === 'commit') {
    if (!CsrfGuard::validateSubmittedToken(DRAFT_CLASS_CSRF_FORM)) {
        http_response_code(403);
        die('Invalid or expired form submission. Reload the page and try again.');
    }

    $pending = $_SESSION[DRAFT_CLASS_SESSION_KEY] ?? [];
    if (!is_array($pending) || $pending === []) {
        // Back-button re-submit, or a session that expired between preview and commit.
        unset($_SESSION[DRAFT_CLASS_SESSION_KEY]);
        header('Location: ' . DRAFT_CLASS_PAGE_URL . '?expired=1');
        exit;
    }

    /** @var list<array<string, int|string>> $rows */
    $rows = array_values($pending);
    $errors = (new DraftClassCsvParser())->validateRows($rows);

    if ($errors !== []) {
        unset($_SESSION[DRAFT_CLASS_SESSION_KEY]);
        $rows = [];
    } else {
        $mysqli_db->begin_transaction();
        try {
            $mysqli_db->query('DELETE FROM ibl_draft_class');

            $stmt = $mysqli_db->prepare(
                'INSERT INTO ibl_draft_class (' . implode(', ', DRAFT_CLASS_COLUMNS) . ') '
                . 'VALUES (' . rtrim(str_repeat('?, ', count(DRAFT_CLASS_COLUMNS)), ', ') . ')'
            );
            if ($stmt === false) {
                throw new RuntimeException('prepare failed');
            }

            // bind_param binds by reference, so the statement is prepared and bound once
            // and only the referenced slots change per row.
            $bound = array_fill(0, count(DRAFT_CLASS_COLUMNS), null);
            $refs = [];
            foreach (array_keys($bound) as $slot) {
                $refs[] = &$bound[$slot];
            }
            // 's' name, 's' pos, 'i' age, 's' team, then 'i' for fga…intangibles.
            $stmt->bind_param('ssis' . str_repeat('i', count(DRAFT_CLASS_COLUMNS) - 4), ...$refs);

            foreach ($rows as $row) {
                foreach (DRAFT_CLASS_COLUMNS as $slot => $column) {
                    $value = $row[$column] ?? '';
                    $bound[$slot] = in_array($column, DRAFT_CLASS_STRING_COLUMNS, true)
                        ? (string) $value
                        : (int) $value;
                }
                if (!$stmt->execute()) {
                    throw new RuntimeException('insert failed');
                }
            }
            $stmt->close();

            $mysqli_db->commit();
            $importedCount = count($rows);
        } catch (Throwable $e) {
            $mysqli_db->rollback();
            // Never surface the driver message — the commissioner is non-technical.
            $errorMessage = 'The import could not be saved. Nothing was changed. Send the file to A-Jay.';
        }

        if ($errorMessage === '') {
            unset($_SESSION[DRAFT_CLASS_SESSION_KEY]);
            header('Location: ' . DRAFT_CLASS_PAGE_URL . '?imported=' . $importedCount);
            exit;
        }

        $rows = [];
    }
} else {
    // GET. Generating the CSRF field first also starts the PHP session
    // (CsrfGuard::ensureSessionStarted), which the branches below need before they
    // can read the pending-import payload.
    $csrfField = CsrfGuard::generateToken(DRAFT_CLASS_CSRF_FORM);

    if ($action === 'cancel') {
        unset($_SESSION[DRAFT_CLASS_SESSION_KEY]);
        header('Location: ' . DRAFT_CLASS_PAGE_URL);
        exit;
    }

    $pending = $_SESSION[DRAFT_CLASS_SESSION_KEY] ?? [];
    if (is_array($pending) && $pending !== []) {
        /** @var list<array<string, int|string>> $rows */
        $rows = array_values($pending);
        $view = 'preview';

        $countResult = $mysqli_db->query('SELECT COUNT(*) AS c FROM ibl_draft_class');
        if ($countResult instanceof mysqli_result) {
            $countRow = $countResult->fetch_assoc();
            $deleteCount = is_array($countRow) ? (int) ($countRow['c'] ?? 0) : 0;
            $countResult->free();
        }
    } elseif (isset($_GET['expired'])) {
        $errorMessage = 'Your upload expired. Choose the file again.';
    } elseif (isset($_GET['imported'])) {
        $importedCount = max(0, (int) $_GET['imported']);
        $view = 'imported';
    }
}

if ($csrfField === '') {
    $csrfField = CsrfGuard::generateToken(DRAFT_CLASS_CSRF_FORM);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Upload Draft Class</title>
    <link rel="stylesheet" href="themes/IBL/style/style.css">
<?php /* The stylesheet is served with no Cache-Control, so a browser that visited
         import-demands.php (which links the same file) before the picker button
         landed keeps the stale copy and renders the raw file input. The mtime
         stamp retires that cached copy on the next edit. */ ?>
    <link rel="stylesheet" href="design/components/import-demands.css?v=<?= HtmlSanitizer::e((string) (filemtime(__DIR__ . '/design/components/import-demands.css') ?: 0)) ?>">
</head>
<body>
<?php /* Preview drops the 700px prose measure so the 27-column table can use the
         full viewport width; see import-demands.css .import-demands--preview. */ ?>
<div class="import-demands<?= $view === 'preview' ? ' import-demands--preview' : '' ?>">
    <h1 class="ibl-title">Upload Draft Class</h1>

<?php if ($errorMessage !== ''): ?>
    <div class="ibl-alert ibl-alert--error"><?= HtmlSanitizer::e($errorMessage) ?></div>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="ibl-alert ibl-alert--error">
        <strong>This file was not imported. Fix the following and upload it again:</strong>
        <ul>
        <?php foreach ($errors as $error): ?>
            <li><?= HtmlSanitizer::e($error) ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($view === 'imported'): ?>
    <div class="ibl-alert ibl-alert--success">
        Imported <?= $importedCount ?> player<?= $importedCount === 1 ? '' : 's' ?>.
    </div>
    <p><a href="modules.php?name=Draft">Go to the Draft module</a></p>
    <p><a href="<?= DRAFT_CLASS_PAGE_URL ?>">Upload another draft class</a></p>

<?php elseif ($view === 'preview'): ?>
    <?php /* One source line so the rendered sentence has no interior newlines to match around. */ ?>
    <p><?= HtmlSanitizer::e(sprintf('This will delete %d existing row%s and insert %d new one%s.', $deleteCount, $deleteCount === 1 ? '' : 's', count($rows), count($rows) === 1 ? '' : 's')) ?></p>
    <p>Nothing has been written yet. Check the rows below, then confirm.</p>

    <?php /* 27 columns never fit the page container, so the design system's scroll
             wrapper pair carries the table (css-architecture.md, table pattern 1). */ ?>
    <div class="table-scroll-wrapper" id="draftClassTableWrapper"><div class="table-scroll-container">
    <table class="ibl-data-table">
        <thead>
            <tr>
            <?php foreach (DRAFT_CLASS_COLUMNS as $column): ?>
                <?php /* Header carries only the strong rules, as Ratings.php does. */ ?>
                <?php $sep = DRAFT_CLASS_COLUMN_SEPARATORS[$column] ?? ''; ?>
                <th<?= $sep === 'sep-r-team' ? ' class="sep-r-team"' : '' ?>><?= HtmlSanitizer::e(DraftClassCsvParser::COLUMN_LABELS[$column] ?? $column) ?></th>
            <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
            <?php foreach (DRAFT_CLASS_COLUMNS as $column): ?>
                <?php $sep = DRAFT_CLASS_COLUMN_SEPARATORS[$column] ?? ''; ?>
                <td<?= $sep === '' ? '' : ' class="' . $sep . '"' ?>><?= HtmlSanitizer::e((string) ($row[$column] ?? '')) ?></td>
            <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div></div>

    <?php /* The wrapper's right-edge scroll shadow is toggled by jslib/responsive-tables.js,
             which this standalone page does not load — so without this it hangs there
             advertising columns that are not off-screen. Same end test as that file's
             updateScrollIndicator(), which also covers a table wide enough to scroll. */ ?>
    <script>
    (function () {
        var wrapper = document.getElementById('draftClassTableWrapper');
        var container = wrapper.querySelector('.table-scroll-container');

        function updateScrollIndicator() {
            var isAtEnd = container.scrollLeft + container.clientWidth >= container.scrollWidth - 5;
            wrapper.classList.toggle('scrolled-end', isAtEnd);
        }

        container.addEventListener('scroll', updateScrollIndicator);
        window.addEventListener('resize', updateScrollIndicator);
        updateScrollIndicator();
    })();
    </script>

    <form method="post">
        <?= HtmlSanitizer::trusted($csrfField) ?>
        <?php /* Destructive: commit deletes every existing row before inserting. */ ?>
        <button type="submit" name="action" value="commit" class="ibl-btn ibl-btn--danger">Commit Import</button>
        <a href="<?= DRAFT_CLASS_PAGE_URL ?>?action=cancel" class="ibl-btn ibl-btn--ghost">Cancel</a>
    </form>

<?php else: ?>
    <p>Upload the draft-class export as a CSV. The importer reads the first 27 columns and replaces the entire draft class.</p>
    <p>Every existing row in <code>ibl_draft_class</code> is deleted and replaced. You will see a preview and a row count before anything is written.</p>

    <?php /* The file input wears .sr-only: its native control reads as a text box, not a
             button, but it must stay focusable for keyboard users. The <label> is the
             .ibl-btn the commissioner sees, and choosing a file submits the form — no
             second click. `action` rides as a hidden input so the JS submit carries it
             (a submit button's name/value would not). */ ?>
    <form method="post" enctype="multipart/form-data" id="draftClassForm">
        <?= HtmlSanitizer::trusted($csrfField) ?>
        <input type="hidden" name="action" value="upload">
        <input type="file" name="draftClassFile" id="draftClassFile" accept=".csv" class="sr-only" required>
        <label for="draftClassFile" class="ibl-btn ibl-btn--primary" id="draftClassLabel">Click to choose .csv</label>
        <noscript><button type="submit" class="ibl-btn ibl-btn--primary">Upload and Preview</button></noscript>
    </form>
    <p class="ibl-alert ibl-alert--error" id="draftClassClientError" hidden>That is not a .csv file. Choose the draft-class export again.</p>

    <script>
    (function () {
        var input = document.getElementById('draftClassFile');
        var form = document.getElementById('draftClassForm');
        var label = document.getElementById('draftClassLabel');
        var clientError = document.getElementById('draftClassClientError');
        var LABEL_IDLE = label.textContent;

        // Back-button/bfcache restore hands back a form that still holds the last
        // file. Re-picking the same file fires no `change` event, so clear it and
        // restore the idle label — otherwise the button reads "Uploading…" forever.
        window.addEventListener('pageshow', function () {
            input.value = '';
            label.textContent = LABEL_IDLE;
        });

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) {
                return;
            }
            if (!/\.csv$/i.test(file.name)) {
                // Only a filename check — the server still parses and validates every row.
                clientError.hidden = false;
                input.value = '';
                return;
            }
            clientError.hidden = true;
            label.textContent = 'Uploading ' + file.name + '…';
            form.submit();
        });
    })();
    </script>
<?php endif; ?>
</div>
</body>
</html>
