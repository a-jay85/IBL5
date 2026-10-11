<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\TestCase;

/**
 * Characterization test for modules/DebugMenu/index.php.
 *
 * Every DebugMenu path ends in HtmxHelper::redirect(), which calls exit and
 * would kill the PHPUnit process. The dispatch is therefore pinned at the
 * source level, and DebugController's behaviour is covered by its own tests.
 */
class DebugMenuEntryPointTest extends TestCase
{
    public function testDebugMenuRendersDefaultView(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/modules/DebugMenu/index.php');
        $this->assertIsString($source);

        $this->assertStringContainsString("case 'toggle_extensions':", $source);
        $this->assertStringContainsString('->handleToggle()', $source);
        $this->assertStringContainsString("HtmxHelper::redirect('/ibl5/')", $source);
    }
}
