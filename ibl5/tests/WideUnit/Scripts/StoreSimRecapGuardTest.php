<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\TestCase;

final class StoreSimRecapGuardTest extends TestCase
{
    private string $src;

    protected function setUp(): void
    {
        $this->src = (string) file_get_contents(__DIR__ . '/../../../scripts/storeSimRecap.php');
    }

    // ── Script guard tests ─────────────────────────────────────────────────────

    public function testGuardIsPresent(): void
    {
        self::assertTrue(str_contains($this->src, "PHP_SAPI !== 'cli'"));
    }

    public function testGuardIsTheFirstExecutableStatement(): void
    {
        $guardPos = strpos($this->src, "PHP_SAPI !== 'cli'");
        self::assertNotFalse($guardPos);

        $autoloadPos = strpos($this->src, 'vendor/autoload.php');
        self::assertNotFalse($autoloadPos);

        $dbPos = strpos($this->src, 'db/db.php');
        self::assertNotFalse($dbPos);

        self::assertLessThan($autoloadPos, $guardPos, 'SAPI guard must appear before vendor/autoload.php');
        self::assertLessThan($dbPos, $guardPos, 'SAPI guard must appear before db/db.php');
    }

    public function testGuardReturns403(): void
    {
        self::assertTrue(str_contains($this->src, 'http_response_code(403)'));
    }

    public function testScriptDoesNotHardcodeAWebhookOrSnowflake(): void
    {
        self::assertSame(
            0,
            preg_match('/discord\.com\/api\/webhooks/i', $this->src),
            'Script must not hardcode a Discord webhook URL'
        );
        self::assertSame(
            0,
            preg_match('/\b\d{17,19}\b/', $this->src),
            'Script must not hardcode a Discord snowflake ID'
        );
    }

    public function testScriptCastsOnlyTheSimArgument(): void
    {
        self::assertSame(1, substr_count($this->src, '(int)'), 'Exactly one (int) cast expected');
    }

    public function testScriptWritesBeforeItPosts(): void
    {
        $writePos = strpos($this->src, 'markDone');
        $postPos = strpos($this->src, 'postToChannel');

        self::assertNotFalse($writePos, 'markDone not found in script');
        self::assertNotFalse($postPos, 'postToChannel not found in script');
        self::assertLessThan($postPos, $writePos, 'markDone must appear before postToChannel');
    }

    public function testScriptComposesNoSql(): void
    {
        self::assertSame(0, preg_match('/SELECT /i', $this->src), 'Script must not compose SELECT');
        self::assertSame(0, preg_match('/INSERT /i', $this->src), 'Script must not compose INSERT');
        self::assertSame(0, preg_match('/UPDATE /i', $this->src), 'Script must not compose UPDATE');
        self::assertSame(0, preg_match('/DELETE /i', $this->src), 'Script must not compose DELETE');
    }

    public function testScriptDelegatesParsingToThePayloadDto(): void
    {
        self::assertTrue(
            str_contains($this->src, 'SimRecapPayload'),
            'Script must reference the SimRecapPayload DTO class'
        );
        self::assertTrue(
            str_contains($this->src, 'fromJson'),
            'Script must call SimRecapPayload::fromJson to delegate parsing'
        );
    }

    public function testScriptParsesBeforeItWrites(): void
    {
        $fromJsonPos = strpos($this->src, 'fromJson');
        $catchPos = strpos($this->src, 'catch');
        $markDonePos = strpos($this->src, 'markDone');

        self::assertNotFalse($fromJsonPos, 'fromJson not found in script');
        self::assertNotFalse($catchPos, 'catch not found in script');
        self::assertNotFalse($markDonePos, 'markDone not found in script');

        self::assertLessThan($markDonePos, $fromJsonPos, 'fromJson must appear before markDone');
        self::assertLessThan($markDonePos, $catchPos, 'catch must appear before markDone');
    }

    // ── Htaccess tests ─────────────────────────────────────────────────────────

    public function testHtaccessExists(): void
    {
        $htaccess = file_get_contents(__DIR__ . '/../../../scripts/.htaccess');
        self::assertNotFalse($htaccess);
        self::assertNotEmpty($htaccess);
    }

    public function testHtaccessDeniesStoreSimRecap(): void
    {
        $htaccess = (string) file_get_contents(__DIR__ . '/../../../scripts/.htaccess');

        self::assertMatchesRegularExpression('/<Files\s+"storeSimRecap\.php">/', $htaccess);
        self::assertStringContainsString('Require all denied', $htaccess);

        $filesOpenPos = strpos($htaccess, '<Files');
        $filesClosePos = strpos($htaccess, '</Files>');
        $denyPos = strpos($htaccess, 'Require all denied');

        self::assertNotFalse($filesOpenPos);
        self::assertNotFalse($filesClosePos);
        self::assertNotFalse($denyPos);
        self::assertGreaterThan($filesOpenPos, $denyPos, 'Require all denied must appear after <Files');
        self::assertLessThan($filesClosePos, $denyPos, 'Require all denied must appear before </Files>');
    }

    public function testHtaccessUsesApache24Syntax(): void
    {
        $htaccess = (string) file_get_contents(__DIR__ . '/../../../scripts/.htaccess');

        self::assertFalse(str_contains($htaccess, 'Order deny'), 'Must use Apache 2.4 syntax, not Order deny');
        self::assertFalse(str_contains($htaccess, 'Deny from'), 'Must use Apache 2.4 syntax, not Deny from');
    }

    public function testHtaccessDoesNotDenySiblingScripts(): void
    {
        $htaccess = (string) file_get_contents(__DIR__ . '/../../../scripts/.htaccess');

        $siblings = [
            'updateAllTheThings.php',
            'allStarRename.php',
            'generate_api_key.php',
            'jsbExport.php',
            'plrScratchpad.php',
            'build-engine-bundle.php',
        ];

        foreach ($siblings as $sibling) {
            self::assertFalse(
                str_contains($htaccess, $sibling),
                "Sibling script {$sibling} must not appear in .htaccess"
            );
        }

        // Every "Require all denied" must be inside a <Files> block
        $filesOpenPos = strpos($htaccess, '<Files');
        $filesClosePos = strpos($htaccess, '</Files>');
        $denyPos = strpos($htaccess, 'Require all denied');

        if ($denyPos !== false) {
            self::assertNotFalse($filesOpenPos);
            self::assertNotFalse($filesClosePos);
            self::assertGreaterThan($filesOpenPos, $denyPos, 'Require all denied must be inside a <Files> block');
            self::assertLessThan($filesClosePos, $denyPos, 'Require all denied must be inside a <Files> block');
        }
    }
}
