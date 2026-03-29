<?php

declare(strict_types=1);

require __DIR__ . '/mainfile.php';

use Utilities\HtmlSanitizer;

/** @var mysqli $mysqli_db */

// Admin-only access
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}

/**
 * Strip accents/diacriticals from a string for loose name matching.
 *
 * @return string ASCII-folded, lowercased string
 */
function stripAccents(string $str): string
{
    $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $str);
    return $transliterated !== false ? $transliterated : mb_strtolower($str);
}

/** @var list<array{csv_name: string, reason: string}> $skipped */
$skipped = [];
/** @var int $importedCount */
$importedCount = 0;
/** @var string $errorMessage */
$errorMessage = '';
/** @var bool $submitted */
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['demands_csv'])) {
    $submitted = true;
    $file = $_FILES['demands_csv'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = 'File upload failed (error code: ' . $file['error'] . ').';
    } elseif (!is_uploaded_file($file['tmp_name'])) {
        $errorMessage = 'Invalid upload.';
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            $errorMessage = 'Could not open uploaded file.';
        } else {
            // Build a lookup of normalized name -> {pid, name} from ibl_plr
            $plrResult = $mysqli_db->query('SELECT pid, name FROM ibl_plr WHERE retired = 0');
            /** @var array<string, array{pid: int, name: string}> $playerLookup */
            $playerLookup = [];
            if ($plrResult instanceof mysqli_result) {
                while (($row = $plrResult->fetch_assoc()) !== null) {
                    /** @var array{pid: int, name: string} $row */
                    $normalized = stripAccents($row['name']);
                    $playerLookup[$normalized] = ['pid' => $row['pid'], 'name' => $row['name']];
                }
                $plrResult->free();
            }

            // Read header row
            $header = fgetcsv($handle);
            if ($header === false || $header === [null]) {
                $errorMessage = 'CSV file is empty or unreadable.';
            } else {
                // Normalize header names
                $header = array_map(fn (string $col): string => strtolower(trim($col)), $header);

                // Validate expected columns
                $requiredCols = ['name', 'dem1', 'dem2', 'dem3', 'dem4', 'dem5', 'dem6'];
                $missing = array_diff($requiredCols, $header);
                if ($missing !== []) {
                    $errorMessage = 'CSV is missing required columns: ' . implode(', ', $missing);
                } else {
                    // Map column positions
                    /** @var array<string, int> $colIndex */
                    $colIndex = array_flip($header);

                    // Parse all rows first, then import in a transaction
                    /** @var list<array{name: string, pid: int, dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int}> $rows */
                    $rows = [];
                    $lineNum = 1; // header was line 1

                    while (($csvRow = fgetcsv($handle)) !== false) {
                        $lineNum++;
                        if ($csvRow === [null] || $csvRow === []) {
                            continue; // skip blank lines
                        }

                        $csvName = trim($csvRow[$colIndex['name']] ?? '');
                        if ($csvName === '') {
                            $skipped[] = ['csv_name' => '(empty)', 'reason' => "Blank name on line {$lineNum}"];
                            continue;
                        }

                        $normalizedCsvName = stripAccents($csvName);
                        if (!isset($playerLookup[$normalizedCsvName])) {
                            $skipped[] = ['csv_name' => $csvName, 'reason' => 'No matching player found in ibl_plr'];
                            continue;
                        }

                        $player = $playerLookup[$normalizedCsvName];
                        $rows[] = [
                            'name' => $player['name'], // use canonical DB name
                            'pid'  => $player['pid'],
                            'dem1' => (int) ($csvRow[$colIndex['dem1']] ?? 0),
                            'dem2' => (int) ($csvRow[$colIndex['dem2']] ?? 0),
                            'dem3' => (int) ($csvRow[$colIndex['dem3']] ?? 0),
                            'dem4' => (int) ($csvRow[$colIndex['dem4']] ?? 0),
                            'dem5' => (int) ($csvRow[$colIndex['dem5']] ?? 0),
                            'dem6' => (int) ($csvRow[$colIndex['dem6']] ?? 0),
                        ];
                    }

                    if ($rows === [] && $skipped === []) {
                        $errorMessage = 'CSV contained no data rows.';
                    } elseif ($rows !== []) {
                        // Import inside a transaction: truncate + insert
                        $mysqli_db->begin_transaction();
                        try {
                            $mysqli_db->query('DELETE FROM ibl_demands');

                            $stmt = $mysqli_db->prepare(
                                'INSERT INTO ibl_demands (name, pid, dem1, dem2, dem3, dem4, dem5, dem6) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                            );
                            if ($stmt === false) {
                                throw new RuntimeException('Failed to prepare insert statement: ' . $mysqli_db->error);
                            }

                            foreach ($rows as $row) {
                                $stmt->bind_param(
                                    'siiiiiiii',
                                    $row['name'],
                                    $row['pid'],
                                    $row['dem1'],
                                    $row['dem2'],
                                    $row['dem3'],
                                    $row['dem4'],
                                    $row['dem5'],
                                    $row['dem6']
                                );
                                $stmt->execute();
                            }
                            $stmt->close();

                            $mysqli_db->commit();
                            $importedCount = count($rows);
                        } catch (Throwable $e) {
                            $mysqli_db->rollback();
                            $errorMessage = 'Database error: ' . $e->getMessage();
                        }
                    }
                }
            }
            fclose($handle);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Import Free Agent Demands</title>
    <link rel="stylesheet" href="design/components/import-demands.css">
</head>
<body>
<div class="import-demands">
    <h1>Import Free Agent Demands</h1>
    <p>Upload a CSV with columns: <code>name, dem1, dem2, dem3, dem4, dem5, dem6</code>.</p>
    <p>The <code>pid</code> column will be resolved automatically from the player name. The <code>ibl_demands</code> table will be truncated before import.</p>

    <form method="post" enctype="multipart/form-data">
        <label for="demands_csv">Select CSV file:</label>
        <input type="file" name="demands_csv" id="demands_csv" accept=".csv" required>
        <button type="submit">Import Demands</button>
    </form>

<?php if ($submitted): ?>
    <div class="results">
    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-error"><?= HtmlSanitizer::e($errorMessage) ?></div>
    <?php endif; ?>

    <?php if ($importedCount > 0): ?>
        <div class="alert alert-success">
            Successfully imported <?= $importedCount ?> player demand<?= $importedCount !== 1 ? 's' : '' ?>.
        </div>
    <?php endif; ?>

    <?php if ($skipped !== []): ?>
        <div class="alert alert-warning">
            <strong><?= count($skipped) ?> row<?= count($skipped) !== 1 ? 's' : '' ?> skipped:</strong>
            <table class="skipped-table">
                <tr><th>CSV Name</th><th>Reason</th></tr>
                <?php foreach ($skipped as $skip): ?>
                <tr>
                    <td><?= HtmlSanitizer::e($skip['csv_name']) ?></td>
                    <td><?= HtmlSanitizer::e($skip['reason']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
    </div>
<?php endif; ?>
</div>
</body>
</html>
