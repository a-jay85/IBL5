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
        echo '<!-- THEMEHEADER_CALLED --><body>';
    }
}

if (!function_exists('themefooter')) {
    function themefooter(): void
    {
        echo '<!-- THEMEFOOTER_CALLED -->';
    }
}

if (!function_exists('themecenterbox')) {
    function themecenterbox(string $title, string $content): void
    {
    }
}

if (!function_exists('OpenTable')) {
    function OpenTable(): void
    {
        echo '<div class="stub-table">';
    }
}

if (!function_exists('OpenTable2')) {
    function OpenTable2(): void
    {
        echo '<div class="stub-table">';
    }
}

if (!function_exists('CloseTable')) {
    function CloseTable(): void
    {
        echo '</div>';
    }
}

if (!function_exists('themeindex')) {
    function themeindex(mixed $aid, mixed $informant, mixed $time, mixed $title, mixed $counter, mixed $topic, mixed $thetext, mixed $notes, mixed $morelink, mixed $topicname, mixed $topicimage, mixed $topictext): void
    {
        echo (string) $title . ' ' . (string) $morelink;
    }
}

if (!function_exists('themearticle')) {
    function themearticle(mixed $aid, mixed $informant, mixed $datetime, mixed $title, mixed $thetext, mixed $topic, mixed $topicname, mixed $topicimage, mixed $topictext): void
    {
        echo (string) $title . ' ' . (string) $thetext;
    }
}
