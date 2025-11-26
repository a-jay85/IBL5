<?php

namespace UI;

/**
 * DebugOutput - Displays debug information in a collapsible panel
 */
class DebugOutput
{
    private static int $debugId = 0;

    /**
     * Display debug output in a collapsible panel
     *
     * @param string $content The content to display
     * @param string $title The title of the debug panel
     * @return void
     */
    public static function display(string $content, string $title = 'Debug Output'): void
    {
        self::$debugId++;
        $id = self::$debugId;

        ob_start();
        ?>
<div style="margin: 10px 0; border: 1px solid #ccc; border-radius: 4px;">
    <div style="padding: 8px; background-color: #f5f5f5; border-bottom: 1px solid #ccc; cursor: pointer;"
         onclick="toggleDebug<?= $id ?>()">
        <span id="debugIcon<?= $id ?>">▶</span> <?= htmlspecialchars($title) ?>
    </div>
    <pre id="debugContent<?= $id ?>" style="display: none; margin: 0; padding: 8px; background-color: #fff; overflow: auto;"><?= htmlspecialchars($content) ?></pre>
</div>
<script>
    function toggleDebug<?= $id ?>() {
        var content = document.getElementById('debugContent<?= $id ?>');
        var icon = document.getElementById('debugIcon<?= $id ?>');
        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.textContent = '▼';
        } else {
            content.style.display = 'none';
            icon.textContent = '▶';
        }
    }
</script>
        <?php
        echo ob_get_clean();
    }
}
