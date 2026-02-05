<?php

declare(strict_types=1);

/**
 * Free Agency Admin Processing Page
 *
 * Admin-only page for processing free agency day results.
 * All database operations use prepared statements.
 *
 * SECURITY NOTES:
 * - Admin authentication required
 * - CSRF protection on all forms
 * - No arbitrary SQL execution
 * - All user input sanitized
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $mysqli_db, $admin;

use FreeAgency\FreeAgencyAdminProcessor;
use Utilities\CsrfGuard;
use Utilities\HtmlSanitizer;

// Admin authentication check
if (!is_admin($admin)) {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Admin access required.</p>';
    exit;
}

// Cast day parameter to int to prevent XSS
$day = isset($_GET['day']) ? (int) $_GET['day'] : 1;

// Validate day range (1-10 for standard free agency)
if ($day < 1 || $day > 12) {
    $day = 1;
}

$processor = new FreeAgencyAdminProcessor($mysqli_db);
$actionMessage = '';
$actionCompleted = false;

// Handle POST requests for button actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validate CSRF token
    if (!CsrfGuard::validateSubmittedToken('free_agency_admin')) {
        $actionMessage = 'Security validation failed. Please try again.';
    } else {
        $action = $_POST['action'];

        if ($action === 'assign_free_agents') {
            // Get the signing data from the hidden field (JSON-encoded)
            $signingsJson = $_POST['signings_data'] ?? '';
            $signings = [];

            if ($signingsJson !== '') {
                $decoded = json_decode($signingsJson, true);
                if (is_array($decoded)) {
                    $signings = $decoded;
                }
            }

            if ($signings !== []) {
                $newsHomeText = $_POST['news_hometext'] ?? '';
                $newsBodyText = $_POST['news_bodytext'] ?? '';
                $newsTitle = '2006 IBL Free Agency, Days ' . ($day - 1) . '-' . $day;

                $result = $processor->executeSignings(
                    $day,
                    $signings,
                    $newsTitle,
                    $newsHomeText,
                    $newsBodyText
                );

                $actionMessage = $result['message'];
                $actionCompleted = $result['success'];
            } else {
                $actionMessage = 'No signings found to execute.';
            }
        } elseif ($action === 'clear_offers') {
            $result = $processor->clearOffers();
            $actionMessage = $result['message'];
        }
    }
}

// Process the day to get offer data
$dayData = $processor->processDay($day);

$signings = $dayData['signings'];
$allOffers = $dayData['allOffers'];
$autoRejections = $dayData['autoRejections'];
$discordText = $dayData['discordText'];
$newsHomeText = $dayData['newsHomeText'];
$newsBodyText = $dayData['newsBodyText'];
$numOffers = count($allOffers);

// Build auto-rejected text for Discord
$autoRejectedText = "These offers have been **auto-rejected** for being under half of the player's demands:";
foreach ($autoRejections as $rejection) {
    $offers = $rejection['offers'];
    $autoRejectedText .= "\n" . HtmlSanitizer::safeHtmlOutput($rejection['teamName']) . "'s offer for " . HtmlSanitizer::safeHtmlOutput($rejection['playerName']) . ": ";
    $autoRejectedText .= $offers['offer1'];
    if ($offers['offer2'] !== 0) {
        $autoRejectedText .= '/' . $offers['offer2'];
    }
    if ($offers['offer3'] !== 0) {
        $autoRejectedText .= '/' . $offers['offer3'];
    }
    if ($offers['offer4'] !== 0) {
        $autoRejectedText .= '/' . $offers['offer4'];
    }
    if ($offers['offer5'] !== 0) {
        $autoRejectedText .= '/' . $offers['offer5'];
    }
    if ($offers['offer6'] !== 0) {
        $autoRejectedText .= '/' . $offers['offer6'];
    }
}

// Encode signings data as JSON for the form
$signingsDataJson = json_encode($signings, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);

// Generate CSRF token for forms
$csrfToken = CsrfGuard::generateRawToken('free_agency_admin');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Free Agent Processing</title>
    <style>
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .modal-buttons {
            margin-top: 20px;
        }
        .btn-run {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 0 10px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-cancel {
            background: white;
            color: black;
            border: 1px solid #ccc;
            padding: 10px 20px;
            margin: 0 10px;
            border-radius: 5px;
            cursor: pointer;
        }
        .action-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            margin-top: 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .action-button:hover {
            background: #0056b3;
        }
        .action-button-red {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            margin-top: 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .action-button-red:hover {
            background: #c82333;
        }
        table {
            border-collapse: collapse;
            margin: 10px 0;
        }
        td, th {
            border: 1px solid #000;
            padding: 5px;
        }
        .message-success {
            color: #007bff;
            font-weight: bold;
        }
        .message-error {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>You are viewing <span style="color:red">Day <?= $day ?></span> results!</h1>
    <h2>Total number of offers: <?= $numOffers ?></h2>

    <?php if ($actionMessage !== ''): ?>
        <p id="actionMessage" class="<?= $actionCompleted ? 'message-success' : 'message-error' ?>">
            <?= HtmlSanitizer::safeHtmlOutput($actionMessage) ?>
        </p>
    <?php endif; ?>

    <table>
        <tr style="font-weight:bold">
            <td colspan="8">Free Agent Signings</td>
            <td>Bird</td>
            <td>MLE</td>
            <td>LLE</td>
        </tr>
        <?php foreach ($signings as $signing): ?>
        <tr>
            <td><?= HtmlSanitizer::safeHtmlOutput($signing['playerName']) ?></td>
            <td><?= HtmlSanitizer::safeHtmlOutput($signing['teamName']) ?></td>
            <td><?= (int) $signing['offers']['offer1'] ?></td>
            <td><?= (int) $signing['offers']['offer2'] ?></td>
            <td><?= (int) $signing['offers']['offer3'] ?></td>
            <td><?= (int) $signing['offers']['offer4'] ?></td>
            <td><?= (int) $signing['offers']['offer5'] ?></td>
            <td><?= (int) $signing['offers']['offer6'] ?></td>
            <td>-</td>
            <td><?= $signing['usedMle'] ? '1' : '0' ?></td>
            <td><?= $signing['usedLle'] ? '1' : '0' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <table>
        <tr style="font-weight:bold">
            <td colspan="8">ALL OFFERS MADE</td>
            <td>Bird</td>
            <td>MLE</td>
            <td>LLE</td>
            <td>RANDOM</td>
            <td>VALUE</td>
        </tr>
        <?php foreach ($allOffers as $offer): ?>
        <tr>
            <td><?= HtmlSanitizer::safeHtmlOutput($offer['playerName']) ?></td>
            <td><?= HtmlSanitizer::safeHtmlOutput($offer['teamName']) ?></td>
            <td><?= (int) $offer['offers']['offer1'] ?></td>
            <td><?= (int) $offer['offers']['offer2'] ?></td>
            <td><?= (int) $offer['offers']['offer3'] ?></td>
            <td><?= (int) $offer['offers']['offer4'] ?></td>
            <td><?= (int) $offer['offers']['offer5'] ?></td>
            <td><?= (int) $offer['offers']['offer6'] ?></td>
            <td><?= (int) $offer['birdYears'] ?></td>
            <td><?= (int) $offer['mle'] ?></td>
            <td><?= (int) $offer['lle'] ?></td>
            <td><?= (int) $offer['random'] ?></td>
            <td><?= number_format($offer['perceivedValue'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <hr>

    <?php if ($day < 12): ?>
    <h2 style="color:red">AUTO-REJECTED OFFERS</h2>
    <textarea cols="85" rows="20"><?= HtmlSanitizer::safeHtmlOutput($autoRejectedText) ?></textarea>
    <hr>
    <?php endif; ?>

    <h2 style="color:#7289da">ALL REMAINING OFFERS IN DISCORD FORMAT (FOR <a href="https://discord.com/channels/666986450889474053/682990441641279531">#live-sims</a>)</h2>
    <textarea style="font-size: 24px" cols="85" rows="20"><?= HtmlSanitizer::safeHtmlOutput($discordText) ?></textarea>
    <hr>

    <h2 id="signingsHeader">SIGNINGS TO EXECUTE (<?= count($signings) ?> players)</h2>
    <textarea id="signingsDisplay" cols="125" rows="10" readonly><?php
        foreach ($signings as $signing) {
            echo HtmlSanitizer::safeHtmlOutput($signing['playerName']) . ' -> ' . HtmlSanitizer::safeHtmlOutput($signing['teamName']);
            echo ' (' . $signing['offers']['offer1'];
            if ($signing['offers']['offer2'] !== 0) echo '/' . $signing['offers']['offer2'];
            if ($signing['offers']['offer3'] !== 0) echo '/' . $signing['offers']['offer3'];
            if ($signing['offers']['offer4'] !== 0) echo '/' . $signing['offers']['offer4'];
            if ($signing['offers']['offer5'] !== 0) echo '/' . $signing['offers']['offer5'];
            if ($signing['offers']['offer6'] !== 0) echo '/' . $signing['offers']['offer6'];
            echo ")\n";
        }
    ?></textarea>

    <br>
    <?php if ($actionCompleted): ?>
        <button type="button" class="action-button-red" onclick="showClearOffersModal()">Clear All Free Agency Offers</button>
    <?php else: ?>
        <button type="button" class="action-button" onclick="showAssignFreeAgentsModal()">Assign Free Agents to Teams and Insert News Story</button>
    <?php endif; ?>

    <hr>
    <h2>ACCEPTED OFFERS IN HTML FORMAT (FOR NEWS ARTICLE)</h2>
    <textarea id="newsHometextArea" cols="125" rows="20"><?= HtmlSanitizer::safeHtmlOutput($newsHomeText) ?></textarea>

    <hr>
    <h2>ALL OFFERS IN HTML FORMAT (FOR NEWS ARTICLE EXTENDED TEXT)</h2>
    <textarea id="newsBodytextArea" cols="125" rows="20"><?= HtmlSanitizer::safeHtmlOutput($newsBodyText) ?></textarea>

    <!-- Modal for Assign Free Agents -->
    <div id="assignFreeAgentsModal" class="modal-overlay">
        <div class="modal-content">
            <h2>WARNING</h2>
            <p>Are you sure?</p>
            <p>Winning offers will be applied to players and teams.</p>
            <p>A news story will be inserted into the database.</p>
            <p><b>Please double-check everything before proceeding.</b></p>
            <div class="modal-buttons">
                <form method="POST" id="assignFreeAgentsForm">
                    <input type="hidden" name="_csrf_token" value="<?= HtmlSanitizer::safeHtmlOutput($csrfToken) ?>">
                    <input type="hidden" name="action" value="assign_free_agents">
                    <input type="hidden" name="signings_data" id="signingsDataInput" value="<?= HtmlSanitizer::safeHtmlOutput($signingsDataJson) ?>">
                    <input type="hidden" name="news_hometext" id="newsHometextInput" value="">
                    <input type="hidden" name="news_bodytext" id="newsBodytextInput" value="">
                    <button type="submit" class="btn-run">Yes</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('assignFreeAgentsModal')">No</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Clear Offers -->
    <div id="clearOffersModal" class="modal-overlay">
        <div class="modal-content">
            <h2>WARNING</h2>
            <p>Are you sure you want to clear all Free Agency Offers?</p>
            <p>This will remove all offers from the database.</p>
            <p><b>Please double-check everything before proceeding.</b></p>
            <div class="modal-buttons">
                <form method="POST">
                    <input type="hidden" name="_csrf_token" value="<?= HtmlSanitizer::safeHtmlOutput($csrfToken) ?>">
                    <input type="hidden" name="action" value="clear_offers">
                    <button type="submit" class="btn-run">Yes</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('clearOffersModal')">No</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showAssignFreeAgentsModal() {
            // Get the news text from the textareas
            var newsHometext = document.getElementById('newsHometextArea') ? document.getElementById('newsHometextArea').value : '';
            var newsBodytext = document.getElementById('newsBodytextArea') ? document.getElementById('newsBodytextArea').value : '';
            document.getElementById('newsHometextInput').value = newsHometext;
            document.getElementById('newsBodytextInput').value = newsBodytext;

            // Show the modal
            document.getElementById('assignFreeAgentsModal').classList.add('active');
        }

        function showClearOffersModal() {
            document.getElementById('clearOffersModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            var modals = document.getElementsByClassName('modal-overlay');
            for (var i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].classList.remove('active');
                }
            }
        }

        // Handle Enter/Return key press in modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' || event.keyCode === 13) {
                // Check if assign free agents modal is active
                var assignModal = document.getElementById('assignFreeAgentsModal');
                if (assignModal && assignModal.classList.contains('active')) {
                    event.preventDefault();
                    document.getElementById('assignFreeAgentsForm').submit();
                    return;
                }

                // Check if clear offers modal is active
                var clearModal = document.getElementById('clearOffersModal');
                if (clearModal && clearModal.classList.contains('active')) {
                    event.preventDefault();
                    // Find and submit the form in the clear offers modal
                    var form = clearModal.querySelector('form');
                    if (form) {
                        form.submit();
                    }
                    return;
                }
            }
        });

        // Scroll to action message if present
        window.addEventListener('load', function() {
            var message = document.getElementById('actionMessage');
            if (message) {
                message.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    </script>
</body>
</html>
