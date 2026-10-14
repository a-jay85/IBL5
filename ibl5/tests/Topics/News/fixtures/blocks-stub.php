<?php

declare(strict_types=1);

// Loaded only by NewsControllerGoldenTest::testGoldenHomeFileBranch (separate process),
// before ModuleEntryPointTestCase::setUp() loads theme-stubs.php. The real blocks() in
// LegacyFunctions.php is always loaded by that setUp and renders each block through
// themecenterbox(), whose theme stub echoes nothing. This marker makes the call visible.
if (!function_exists('themecenterbox')) {
    function themecenterbox(string $title, string $content): void
    {
        echo "<!-- blocks:$title:$content -->";
    }
}
