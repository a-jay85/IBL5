<?php

namespace Tests\Integration\Mocks;

/**
 * Mock UI class for testing
 */
class UI
{
    public static function displayDebugOutput($content, $title = 'Debug Output')
    {
        // In test mode, don't output anything
        // This prevents test output pollution
        if (defined('PHPUNIT_RUNNING') || php_sapi_name() === 'cli') {
            return;
        }
        
        // Otherwise, output normally (though this shouldn't happen in tests)
        static $debugId = 0;
        $debugId++;
        
        echo "<div style='margin: 10px 0; border: 1px solid #ccc; border-radius: 4px;'>
            <div style='padding: 8px; background-color: #f5f5f5; border-bottom: 1px solid #ccc; cursor: pointer;'
                 onclick='toggleDebug$debugId()'>
                <span id='debugIcon$debugId'>▶</span> $title
            </div>
            <pre id='debugContent$debugId' style='display: none; margin: 0; padding: 8px; background-color: #fff; overflow: auto;'>$content</pre>
        </div>
        <script>
            function toggleDebug$debugId() {
                var content = document.getElementById('debugContent$debugId');
                var icon = document.getElementById('debugIcon$debugId');
                if (content.style.display === 'none') {
                    content.style.display = 'block';
                    icon.textContent = '▼';
                } else {
                    content.style.display = 'none';
                    icon.textContent = '▶';
                }
            }
        </script>";
    }
}
