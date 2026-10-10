<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * Tests for modules/ProjectedDraftOrder/index.php — now a redirect stub.
 *
 * Behavior ported to DraftInfoEntryPointTest::testRendersProjectedOrderWhenNotFinalized
 * and ::testRendersFinalizedOrderWhenFinalized.
 */
class ProjectedDraftOrderEntryPointTest extends ModuleEntryPointTestCase
{
    public function testStubRedirectProducesNoPageOutput(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('ProjectedDraftOrder');

        $this->assertSame('', $output);
    }

    public function testSaveOrderStubProducesNoPageOutput(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('ProjectedDraftOrder', ['op' => 'save_order']);

        $this->assertSame('', $output);
    }

    public function testStubFileStructure(): void
    {
        $path = __DIR__ . '/../../../modules/ProjectedDraftOrder/index.php';
        $content = file_get_contents($path);
        $this->assertIsString($content);

        $this->assertStringContainsString('sendWithPassthrough', $content);
        $this->assertStringContainsString("'modules.php?name=DraftInfo&tab=order'", $content);
        $this->assertStringContainsString("'modules.php?name=DraftInfo&op=save_order'", $content);
        $this->assertStringNotContainsString('PageLayout', $content);
        $this->assertStringNotContainsString("header('Location", $content);
    }
}
