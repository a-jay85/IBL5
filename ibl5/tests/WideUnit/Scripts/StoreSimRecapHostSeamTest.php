<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * Source-shape pins for the host-seam additions to scripts/storeSimRecap.php.
 *
 * These assertions are separate from StoreSimRecapGuardTest so the security
 * pins in that file are never touched when the host-seam code changes.
 */
final class StoreSimRecapHostSeamTest extends TestCase
{
    private string $src;

    protected function setUp(): void
    {
        $this->src = (string) file_get_contents(__DIR__ . '/../../../scripts/storeSimRecap.php');
    }

    public function testScriptReadsCanonicalHostConstant(): void
    {
        self::assertStringContainsString('IBL5_CANONICAL_HOST', $this->src);
    }

    public function testScriptInitialisesDiscordWithHost(): void
    {
        self::assertStringContainsString('Discord::init(', $this->src);
    }

    public function testScriptUsesSimSummaryLinkForViewerUrl(): void
    {
        self::assertStringContainsString('SimSummaryLink', $this->src);
    }

    public function testScriptContainsNoHardcodedHostname(): void
    {
        self::assertStringNotContainsString('iblhoops.net', $this->src);
    }

    public function testScriptDoesNotAcceptHostFromArgv(): void
    {
        // The host is never accepted from argv — only from IBL5_CANONICAL_HOST.
        // A '--host' token in the source would indicate a security regression
        // where the production routing decision could be spoofed by the caller.
        self::assertStringNotContainsString('--host', $this->src);
    }
}
