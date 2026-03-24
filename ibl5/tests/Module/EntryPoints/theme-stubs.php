<?php

declare(strict_types=1);

/**
 * Theme function stubs for module entry point tests.
 *
 * These functions are normally defined in themes/IBL/theme.php which is loaded
 * by mainfile.php. Since tests don't load mainfile.php, we provide minimal stubs.
 * This file intentionally has no namespace so functions are defined globally.
 */

if (!function_exists('themeheader')) {
    function themeheader(): void
    {
        echo '<body>';
    }
}

if (!function_exists('themefooter')) {
    function themefooter(): void
    {
    }
}

if (!function_exists('themecenterbox')) {
    function themecenterbox(string $title, string $content): void
    {
    }
}
