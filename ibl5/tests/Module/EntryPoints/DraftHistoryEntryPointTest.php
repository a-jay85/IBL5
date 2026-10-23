<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * Tests for modules/DraftHistory/index.php — now a redirect stub.
 *
 * The stub serves op=api directly and redirects everything else to
 * modules.php?name=DraftInfo&tab=history via ModuleRedirect::sendWithPassthrough.
 * Behavior is fully asserted by DraftInfoEntryPointTest (15 ported methods) and
 * DraftInfoRedirectTest (passthroughUrl unit tests).
 *
 * File-content assertions here verify the stub structure cannot silently regress
 * (wrong module literal, leftover PageLayout, header() instead of sendWithPassthrough).
 */
class DraftHistoryEntryPointTest extends ModuleEntryPointTestCase
{
    public function testStubOpApiServesFragmentDirectly(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory', ['op' => 'api']);

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testStubRedirectProducesNoPageOutput(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftHistory');

        $this->assertSame('', $output);
    }

    public function testStubFileStructure(): void
    {
        $path = __DIR__ . '/../../../modules/DraftHistory/index.php';
        $content = file_get_contents($path);
        $this->assertIsString($content);

        $this->assertStringContainsString('sendWithPassthrough', $content);
        $this->assertStringContainsString("'modules.php?name=DraftInfo&tab=history'", $content);
        $this->assertStringNotContainsString('PageLayout', $content);
        $this->assertStringNotContainsString("header('Location", $content);
    }
}
