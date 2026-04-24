<?php

declare(strict_types=1);

namespace UI;

use Utilities\HtmlSanitizer;

/**
 * DebugOutput - Displays debug information in a collapsible panel
 *
 * SECURITY: Debug output is only shown to admin users to prevent
 * information disclosure to regular users.
 */
class DebugOutput
{
    private static int $debugId = 0;

    /**
     * Check if current user is an admin via AuthService
     *
     * @return bool True if user is admin
     */
    private static function isAdmin(): bool
    {
        global $authService;
        if ($authService instanceof \Auth\AuthService) {
            return $authService->isAdmin();
        }
        return false;
    }

    /**
     * Display debug output in a collapsible panel
     *
     * SECURITY: Only displays to admin users. Content is HTML-escaped
     * to prevent XSS attacks.
     *
     * @param string $content The content to display
     * @param string $title The title of the debug panel
     * @return void
     */
    public static function display(string $content, string $title = 'Debug Output'): void
    {
        // SECURITY: Only show debug output to admins
        if (!self::isAdmin()) {
            return;
        }

        self::$debugId++;
        $id = self::$debugId;

        // SECURITY: Escape content to prevent XSS
        // Allow only basic HTML tags for formatting (br tags are common in debug output)
        // deliberate: keep htmlspecialchars here — the str_replace below restores <br> tags
        // after escaping, a two-step pattern that can't be collapsed into HtmlSanitizer::e()
        $safeContent = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Restore <br> tags after escaping (they're safe and commonly used)
        $safeContent = str_replace(['&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;'], '<br>', $safeContent);

        ob_start();
        ?>
<div class="debug-panel">
    <div class="debug-panel__header"
         onclick="toggleDebug<?= $id ?>()">
        <span id="debugIcon<?= $id ?>">&#9654;</span> <?= HtmlSanitizer::e($title) ?>
    </div>
    <pre id="debugContent<?= $id ?>" class="debug-panel__content" hidden><?= $safeContent ?></pre>
</div>
<script>
    function toggleDebug<?= $id ?>() {
        var content = document.getElementById('debugContent<?= $id ?>');
        var icon = document.getElementById('debugIcon<?= $id ?>');
        content.hidden = !content.hidden;
        icon.textContent = content.hidden ? '▶' : '▼';
    }
</script>
        <?php
        echo ob_get_clean();
    }
}
