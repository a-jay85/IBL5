<?php

declare(strict_types=1);

namespace UI;

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
        $safeContent = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Restore <br> tags after escaping (they're safe and commonly used)
        $safeContent = str_replace(['&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;'], '<br>', $safeContent);

        ob_start();
        ?>
<div style="margin: 10px 0; border: 1px solid #ccc; border-radius: 4px;">
    <div style="padding: 8px; background-color: #f5f5f5; border-bottom: 1px solid #ccc; cursor: pointer;"
         onclick="toggleDebug<?= $id ?>()">
        <span id="debugIcon<?= $id ?>">&#9654;</span> <?= htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
    </div>
    <pre id="debugContent<?= $id ?>" style="display: none; margin: 0; padding: 8px; background-color: #fff; overflow: auto;"><?= $safeContent ?></pre>
</div>
<script>
    function toggleDebug<?= $id ?>() {
        var content = document.getElementById('debugContent<?= $id ?>');
        var icon = document.getElementById('debugIcon<?= $id ?>');
        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.textContent = '\u25BC';
        } else {
            content.style.display = 'none';
            icon.textContent = '\u25B6';
        }
    }
</script>
        <?php
        echo ob_get_clean();
    }
}
