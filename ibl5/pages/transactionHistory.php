<?php

declare(strict_types=1);

use Utilities\HtmlSanitizer;

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $mysqli_db;

// Transaction category mapping
$categories = [
    1 => 'Waiver Pool Moves',
    2 => 'Trades',
    3 => 'Contract Extensions',
    8 => 'Free Agency',
    10 => 'Rookie Extension',
    14 => 'Position Changes',
];

// Get filter parameters
$selectedCategory = isset($_GET['cat']) ? (int) $_GET['cat'] : 0;
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : 0;
$selectedMonth = isset($_GET['month']) ? (int) $_GET['month'] : 0;

// Get available years from database
$yearQuery = "SELECT DISTINCT YEAR(time) as year FROM nuke_stories WHERE catid IN (1, 2, 3, 8, 10, 14) ORDER BY year DESC";
$yearResult = $mysqli_db->query($yearQuery);
$availableYears = [];
if ($yearResult instanceof mysqli_result) {
    while ($row = $yearResult->fetch_assoc()) {
        $availableYears[] = (int) $row['year'];
    }
}

// Build query
$whereConditions = ["catid IN (1, 2, 3, 8, 10, 14)"];
$params = [];
$types = '';

if ($selectedCategory > 0 && isset($categories[$selectedCategory])) {
    $whereConditions[] = "catid = ?";
    $params[] = $selectedCategory;
    $types .= 'i';
}

if ($selectedYear > 0) {
    $whereConditions[] = "YEAR(time) = ?";
    $params[] = $selectedYear;
    $types .= 'i';
}

if ($selectedMonth > 0 && $selectedMonth <= 12) {
    $whereConditions[] = "MONTH(time) = ?";
    $params[] = $selectedMonth;
    $types .= 'i';
}

$whereClause = implode(' AND ', $whereConditions);
$query = "SELECT sid, catid, title, time FROM nuke_stories WHERE $whereClause ORDER BY time DESC LIMIT 500";

if (count($params) > 0) {
    $stmt = $mysqli_db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $mysqli_db->query($query);
}

// Month names for display
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Transaction History - IBL</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: navy; }
        .filters { margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 5px; }
        .filters form { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .filters select { padding: 5px 10px; }
        .filters button { padding: 5px 15px; background: navy; color: white; border: none; cursor: pointer; }
        .filters button:hover { background: #000080; }
        table { border-collapse: collapse; width: 100%; max-width: 1000px; }
        th { background: navy; color: white; padding: 10px; text-align: left; }
        td { padding: 8px 10px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) { background: #f9f9f9; }
        tr:hover { background: #e6e7e2; }
        .category-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        .cat-1 { background: #e3f2fd; color: #1565c0; } /* Waiver Pool Moves */
        .cat-2 { background: #fff3e0; color: #e65100; } /* Trades */
        .cat-3 { background: #e8f5e9; color: #2e7d32; } /* Contract Extensions */
        .cat-8 { background: #fce4ec; color: #c2185b; } /* Free Agency */
        .cat-10 { background: #f3e5f5; color: #7b1fa2; } /* Rookie Extension */
        .cat-14 { background: #e0f7fa; color: #00838f; } /* Position Changes */
        .no-results { padding: 20px; text-align: center; color: #666; }
        .reset-link { margin-left: 10px; }
    </style>
</head>
<body>
    <h1>Transaction History</h1>

    <div class="filters">
        <form method="get">
            <label>
                Category:
                <select name="cat">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $catId => $catName): ?>
                        <option value="<?= $catId ?>" <?= $selectedCategory === $catId ? 'selected' : '' ?>>
                            <?= HtmlSanitizer::safeHtmlOutput($catName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Year:
                <select name="year">
                    <option value="0">All Years</option>
                    <?php foreach ($availableYears as $year): ?>
                        <option value="<?= $year ?>" <?= $selectedYear === $year ? 'selected' : '' ?>>
                            <?= $year ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Month:
                <select name="month">
                    <option value="0">All Months</option>
                    <?php foreach ($monthNames as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $selectedMonth === $num ? 'selected' : '' ?>>
                            <?= $name ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button type="submit">Filter</button>
            <a href="transactionHistory.php" class="reset-link">Reset</a>
        </form>
    </div>

    <?php if ($result instanceof mysqli_result && $result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Transaction</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $catId = (int) $row['catid'];
                    $catName = $categories[$catId] ?? 'Unknown';
                    $title = HtmlSanitizer::safeHtmlOutput($row['title']);
                    $date = date('M j, Y', strtotime($row['time']));
                    ?>
                    <tr>
                        <td><?= $date ?></td>
                        <td><span class="category-badge cat-<?= $catId ?>"><?= HtmlSanitizer::safeHtmlOutput($catName) ?></span></td>
                        <td><?= $title ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-results">
            No transactions found for the selected filters.
        </div>
    <?php endif; ?>
</body>
</html>
