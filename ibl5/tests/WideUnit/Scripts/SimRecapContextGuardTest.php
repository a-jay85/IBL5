<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * Source-content guard tests for the prod-side roster-context CLI. Mirrors
 * SimRecapQueueGuardTest: it asserts on the script's SOURCE and never shells
 * out to the real script, because a real run happens outside PHPUnit (so the
 * isPhpUnit short-circuits do not engage) and could fire live side effects.
 */
final class SimRecapContextGuardTest extends TestCase
{
    private string $src;

    protected function setUp(): void
    {
        $this->src = (string) file_get_contents(__DIR__ . '/../../../scripts/simRecapContext.php');
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

    public function testScriptTakesNoCredentialFromArgvOrEnv(): void
    {
        self::assertSame(
            0,
            preg_match('/--(password|passwd|user|db[-_]?pass)/i', $this->src),
            'Script must not accept a credential flag'
        );
        self::assertFalse(
            str_contains($this->src, 'SIM_RECAP_DB_'),
            'Script must not read SIM_RECAP_DB_* env vars'
        );
        self::assertSame(
            0,
            preg_match('/getenv\s*\(/', $this->src),
            'Script must not call getenv()'
        );
    }

    public function testScriptOpensNoConnectionOfItsOwn(): void
    {
        self::assertSame(
            0,
            preg_match('/new\s+\\\\?mysqli\s*\(/', $this->src),
            'Script must not open its own mysqli connection'
        );
        self::assertFalse(str_contains($this->src, 'mysqli_connect'), 'Script must not call mysqli_connect');
        self::assertFalse(str_contains($this->src, 'real_connect'), 'Script must not call real_connect');
        self::assertSame(
            0,
            preg_match('/PDO\s*\(/', $this->src),
            'Script must not open a PDO connection'
        );
        // The connection must be provided via db/db.php
        self::assertTrue(str_contains($this->src, 'db/db.php'), 'Script must load db/db.php for its connection');
    }

    // ── Htaccess tests ─────────────────────────────────────────────────────────

    public function testHtaccessDeniesWebAccessToTheScript(): void
    {
        $htaccess = (string) file_get_contents(__DIR__ . '/../../../scripts/.htaccess');

        $pos = strpos($htaccess, '<Files "simRecapContext.php">');
        self::assertNotFalse($pos, '.htaccess must contain a <Files "simRecapContext.php"> block');
        self::assertStringContainsString(
            'Require all denied',
            substr($htaccess, $pos),
            '"Require all denied" must appear inside the simRecapContext.php Files block'
        );
    }
}
