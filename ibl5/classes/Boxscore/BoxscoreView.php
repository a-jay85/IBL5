<?php

declare(strict_types=1);

namespace Boxscore;

use Utilities\HtmlSanitizer;

/**
 * BoxscoreView - Renders .sco file upload form and parse log
 *
 * Handles all HTML rendering for the scoParser page.
 *
 * @see BoxscoreProcessor For the processing logic
 */
class BoxscoreView
{
    /**
     * Render the .sco file upload form
     *
     * @return string HTML form markup
     */
    public function renderUploadForm(): string
    {
        ob_start();
        ?>
<h1>JSB .sco File Parser</h1>
<h2>Uploader</h2>
<form enctype="multipart/form-data" action="/ibl5/scripts/scoParser.php" method="POST">
    <input type="hidden" name="MAX_FILE_SIZE" value="14000000" />
    <label for="scoFiles">Upload Old Season's .sco: </label><input name="scoFiles[]" type="file" multiple /><p>
    <label for="seasonPhase">Season Phase for Uploaded .sco: </label><select name="seasonPhase">
        <option value="Preseason">Preseason</option>
        <option value="HEAT">HEAT</option>
        <option value="Regular Season/Playoffs">Regular Season</option>
    </select><p>
    <label for="seasonEndingYear">Season <strong><span style="text-decoration: underline;">Ending</span></strong> Year for Uploaded .sco: </label><input type="text" name="seasonEndingYear" maxlength="4" minlength="4" size="4" /><br>
    <em>e.g. HEAT before the 1990-1991 season</em> = <code>1991</code><br>
    <em>e.g. 1984-1985 Preseason or Regular Season</em> = <code>1985</code><p>
    <input type="submit" value="Parse Uploaded .sco Files" />
</form>
<hr>
<br>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render file upload error message
     *
     * @param int $errorCode PHP file upload error code
     * @return string HTML error message
     */
    public function renderUploadError(int $errorCode): string
    {
        return '<p>' . $errorCode . '</p>';
    }

    /**
     * Render parse results log
     *
     * @param array{success: bool, gamesInserted: int, gamesUpdated: int, gamesSkipped: int, linesProcessed: int, messages: list<string>, error?: string} $result
     * @return string HTML parse log
     */
    public function renderParseLog(array $result): string
    {
        ob_start();
        ?>
<h2>Parse Log</h2>
        <?php foreach ($result['messages'] as $message): ?>
        <?php /** @var string $safeMessage */ $safeMessage = HtmlSanitizer::safeHtmlOutput($message); ?>
<p><?= $safeMessage ?></p>
        <?php endforeach; ?>
        <?php if (isset($result['error']) && $result['error'] !== ''): ?>
        <?php /** @var string $safeError */ $safeError = HtmlSanitizer::safeHtmlOutput($result['error']); ?>
<p><strong>Error:</strong> <?= $safeError ?></p>
        <?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }
}
