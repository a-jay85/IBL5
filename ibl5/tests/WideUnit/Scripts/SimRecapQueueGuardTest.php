<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * Source-content guard tests for the prod-side queue CLI. Mirrors
 * StoreSimRecapGuardTest: it asserts on the script's SOURCE and never shells
 * out to the real script, because a real run happens outside PHPUnit (so the
 * isPhpUnit short-circuits do not engage) and could fire live side effects.
 * The queue behavior itself is covered by SimSummaryRepositoryTest.
 */
final class SimRecapQueueGuardTest extends TestCase
{
    private string $src;

    protected function setUp(): void
    {
        $this->src = (string) file_get_contents(__DIR__ . '/../../../scripts/simRecapQueue.php');
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

        $requirePos = strpos($this->src, 'require_once');
        self::assertNotFalse($requirePos);

        $newPos = strpos($this->src, 'new ');
        self::assertNotFalse($newPos);

        self::assertLessThan($requirePos, $guardPos, 'SAPI guard must appear before any require_once');
        self::assertLessThan($newPos, $guardPos, 'SAPI guard must appear before any object construction');
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

    public function testScriptComposesNoSql(): void
    {
        self::assertSame(0, preg_match('/SELECT /i', $this->src), 'Script must not compose SELECT');
        self::assertSame(0, preg_match('/INSERT /i', $this->src), 'Script must not compose INSERT');
        self::assertSame(0, preg_match('/UPDATE /i', $this->src), 'Script must not compose UPDATE');
        self::assertSame(0, preg_match('/DELETE /i', $this->src), 'Script must not compose DELETE');
    }

    public function testScriptCastsOnlyTheSimArgument(): void
    {
        self::assertSame(1, substr_count($this->src, '(int)'), 'Exactly one (int) cast expected (only --sim)');
    }

    public function testSnowflakesLeaveAsStrings(): void
    {
        // The mention-map path emits each id through (string), never (int).
        self::assertTrue(
            str_contains($this->src, '(string) $id'),
            'Mention-map must cast the snowflake to string before it enters the JSON'
        );
    }

    // ── Htaccess tests ─────────────────────────────────────────────────────────

    public function testHtaccessDeniesSimRecapQueue(): void
    {
        $htaccess = (string) file_get_contents(__DIR__ . '/../../../scripts/.htaccess');

        self::assertMatchesRegularExpression('/<Files\s+"simRecapQueue\.php">/', $htaccess);
        self::assertStringContainsString('Require all denied', $htaccess);
    }

    public function testHtaccessDenyStaysFileScoped(): void
    {
        $htaccess = (string) file_get_contents(__DIR__ . '/../../../scripts/.htaccess');

        // No directory-wide deny — updateAllTheThings.php and siblings stay web-reachable.
        self::assertFalse(str_contains($htaccess, '<Directory'), 'Must not use a directory-wide block');

        // Unit 1's storeSimRecap.php block must remain intact.
        self::assertMatchesRegularExpression('/<Files\s+"storeSimRecap\.php">/', $htaccess);

        // Every "Require all denied" occurrence must live inside a <Files> block.
        $offset = 0;
        while (($denyPos = strpos($htaccess, 'Require all denied', $offset)) !== false) {
            $openPos = strrpos(substr($htaccess, 0, $denyPos), '<Files');
            $closePos = strpos($htaccess, '</Files>', $denyPos);
            self::assertNotFalse($openPos, 'Require all denied must be preceded by a <Files open');
            self::assertNotFalse($closePos, 'Require all denied must be followed by a </Files close');
            $offset = $denyPos + 1;
        }
    }

    public function testHtaccessUsesApache24Syntax(): void
    {
        $htaccess = (string) file_get_contents(__DIR__ . '/../../../scripts/.htaccess');

        self::assertFalse(str_contains($htaccess, 'Order deny'), 'Must use Apache 2.4 syntax, not Order deny');
        self::assertFalse(str_contains($htaccess, 'Deny from'), 'Must use Apache 2.4 syntax, not Deny from');
    }
}
