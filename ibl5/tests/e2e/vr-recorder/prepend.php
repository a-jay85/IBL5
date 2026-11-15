<?php

declare(strict_types=1);

// CI-only VR rendered-View recorder. docker-compose.ci.yml mounts vr-recorder.ini, which
// names this file as auto_prepend_file. Production never mounts it.
(static function (): void {
    if (PHP_SAPI === 'cli') {
        return;
    }
    require_once __DIR__ . '/functions.php';
    if (!ibl_vr_recorder_enabled(getenv('IBL_VR_RECORD_VIEWS'))) {
        return;
    }
    $cell = ibl_vr_parse_cell_cookie($_COOKIE['_vr_cell'] ?? null);
    if ($cell === null) {
        return;
    }
    $root = dirname(__DIR__, 3);
    $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '';
    register_shutdown_function(static function () use ($root, $cell, $uri): void {
        $files = ibl_vr_filter_view_files(get_included_files(), $root);
        if (!ibl_vr_write_record($root . '/vr-rendered', $cell, $uri, $files)) {
            error_log('[vr-recorder] could not write a record for cell ' . $cell['cell']);
        }
    });
})();
