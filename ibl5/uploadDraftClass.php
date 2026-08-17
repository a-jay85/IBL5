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
    <link rel="stylesheet" href="design/components/import-demands.css">
</head>
<body>
<div class="import-demands">
    <h1>Upload Draft Class</h1>

<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-error"><?= HtmlSanitizer::e($errorMessage) ?></div>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="alert alert-error">
        <strong>This file was not imported. Fix the following and upload it again:</strong>
        <ul>
        <?php foreach ($errors as $error): ?>
            <li><?= HtmlSanitizer::e($error) ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($view === 'imported'): ?>
    <div class="alert alert-success">
        Imported <?= $importedCount ?> player<?= $importedCount === 1 ? '' : 's' ?>.
    </div>
    <p><a href="modules.php?name=Draft">Go to the Draft module</a></p>
    <p><a href="<?= DRAFT_CLASS_PAGE_URL ?>">Upload another draft class</a></p>

<?php elseif ($view === 'preview'): ?>
    <p>
        This will delete <?= $deleteCount ?> existing row<?= $deleteCount === 1 ? '' : 's' ?>
        and insert <?= count($rows) ?> new one<?= count($rows) === 1 ? '' : 's' ?>.
    </p>
    <p>Nothing has been written yet. Check the rows below, then confirm.</p>

    <table class="skipped-table">
        <thead>
            <tr>
            <?php foreach (DRAFT_CLASS_COLUMNS as $column): ?>
                <th><?= HtmlSanitizer::e($column) ?></th>
            <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
            <?php foreach (DRAFT_CLASS_COLUMNS as $column): ?>
                <td><?= HtmlSanitizer::e((string) ($row[$column] ?? '')) ?></td>
            <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post">
        <?= HtmlSanitizer::trusted($csrfField) ?>
        <button type="submit" name="action" value="commit">Commit Import</button>
        <a href="<?= DRAFT_CLASS_PAGE_URL ?>?action=cancel">Cancel</a>
    </form>

<?php else: ?>
    <p>Upload the draft-class export as a CSV. The importer reads the first 27 columns and replaces the entire draft class.</p>
    <p>Every existing row in <code>ibl_draft_class</code> is deleted and replaced. You will see a preview and a row count before anything is written.</p>

    <form method="post" enctype="multipart/form-data">
        <?= HtmlSanitizer::trusted($csrfField) ?>
        <label for="draftClassFile">Select CSV file:</label>
        <input type="file" name="draftClassFile" id="draftClassFile" accept=".csv" required>
        <button type="submit" name="action" value="upload">Upload and Preview</button>
    </form>
<?php endif; ?>
</div>
</body>
</html>
