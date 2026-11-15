<?php

declare(strict_types=1);

/**
 * CI-only VR rendered-View recorder helpers. prepend.php loads this file; prepend.php
 * runs only when docker-compose.ci.yml mounts vr-recorder.ini. Production never loads it.
 */

if (!function_exists('ibl_vr_recorder_enabled')) {
    function ibl_vr_recorder_enabled(string|false $env): bool
    {
        return $env === '1';
    }

    /** @return array{row: string, cell: string}|null */
    function ibl_vr_parse_cell_cookie(mixed $raw): ?array
    {
        if (!is_string($raw)) {
            return null;
        }
        if (preg_match('/^([A-Za-z0-9][A-Za-z0-9_-]{0,119})~([A-Za-z0-9][A-Za-z0-9_-]{0,159})$/D', $raw, $m) !== 1) {
            return null;
        }
        return ['row' => $m[1], 'cell' => $m[2]];
    }

    /**
     * @param list<string> $included
     * @return list<string>
     */
    function ibl_vr_filter_view_files(array $included, string $root): array
    {
        $prefix = rtrim($root, '/') . '/';
        $keep = [];
        foreach ($included as $path) {
            if (!str_starts_with($path, $prefix)) { continue; }
            $rel = 'ibl5/' . substr($path, strlen($prefix));
            if (preg_match('#^ibl5/classes/.+(View|Renderer|Formatter)\.php$#D', $rel) === 1) {
                $keep[$rel] = true;
            }
        }
        $keys = array_map('strval', array_keys($keep));
        sort($keys, SORT_STRING);
        return $keys;
    }

    /**
     * @param array{row: string, cell: string} $cell
     * @param list<string> $files
     */
    function ibl_vr_write_record(string $dir, array $cell, string $uri, array $files): bool
    {
        if (!is_dir($dir) || !is_writable($dir)) { return false; }
        $json = json_encode(['row' => $cell['row'], 'cell' => $cell['cell'], 'uri' => $uri, 'files' => $files], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $name = $cell['cell'] . '.' . (int) getmypid() . '-' . hrtime(true) . '-' . bin2hex(random_bytes(4)) . '.json';
        $tmp = $dir . '/.' . $name . '.tmp';
        if (file_put_contents($tmp, $json) === false) { return false; }
        return rename($tmp, $dir . '/' . $name);
    }
}
