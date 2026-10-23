<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * Tests for modules/DraftPickLocator/index.php — now a redirect stub.
 *
 * Behavior ported to DraftInfoEntryPointTest::testRendersDraftPickLocator.
 */
class DraftPickLocatorEntryPointTest extends ModuleEntryPointTestCase
{
    public function testStubRedirectProducesNoPageOutput(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftPickLocator');

        $this->assertSame('', $output);
    }

    public function testStubFileStructure(): void
    {
        $path = __DIR__ . '/../../../modules/DraftPickLocator/index.php';
        $content = file_get_contents($path);
        $this->assertIsString($content);

        $this->assertStringContainsString('sendWithPassthrough', $content);
        $this->assertStringContainsString("'modules.php?name=DraftInfo&tab=picks'", $content);
        $this->assertStringNotContainsString('PageLayout', $content);
        $this->assertStringNotContainsString("header('Location", $content);
    }
}
