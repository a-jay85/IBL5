<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Waivers\WaiversController;
use Waivers\Contracts\WaiversControllerInterface;

class WaiversInputBoundaryTest extends TestCase
{
    /** Superglobals the Waivers input boundary must never touch directly. */
    private const FORBIDDEN_SUPERGLOBALS = ['$_REQUEST', '$_GET', '$_POST', '$_COOKIE', '$_SERVER', '$_FILES'];

    /**
     * Token-level scan: only T_VARIABLE tokens count, so a mention inside a comment
     * or string literal is neither a hit nor a way to hide one.
     *
     * @return list<string>
     */
    private static function superglobalReads(string $source): array
    {
        $hits = [];
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_VARIABLE
                && in_array($token[1], self::FORBIDDEN_SUPERGLOBALS, true)) {
                $hits[] = $token[1];
            }
        }
        return $hits;
    }

    private static function moduleEntryPointPath(): string
    {
        return dirname(__DIR__, 2) . '/modules/Waivers/index.php';
    }

    public function testWaiversControllerSourceReadsNoSuperglobal(): void
    {
        $path = (new \ReflectionClass(WaiversController::class))->getFileName();
        self::assertIsString($path);
        $source = file_get_contents($path);
        self::assertIsString($source);
        self::assertSame([], self::superglobalReads($source));
    }

    public function testWaiversModuleEntryPointReadsNoSuperglobal(): void
    {
        self::assertFileExists(self::moduleEntryPointPath());
        $source = file_get_contents(self::moduleEntryPointPath());
        self::assertIsString($source);
        self::assertSame([], self::superglobalReads($source));
    }

    public function testHandleWaiverRequestTakesOnlyTheUserParameter(): void
    {
        foreach ([WaiversControllerInterface::class, WaiversController::class] as $fqcn) {
            $m = new \ReflectionMethod($fqcn, 'handleWaiverRequest');
            self::assertSame(1, $m->getNumberOfParameters(), "$fqcn::handleWaiverRequest must have exactly 1 parameter");
            self::assertSame('user', $m->getParameters()[0]->getName(), "parameter must be named 'user'");
        }
    }

    public function testSuperglobalScannerFlagsAForbiddenRead(): void
    {
        self::assertSame(
            ['$_REQUEST'],
            self::superglobalReads("<?php\n\$a = \$_REQUEST['action'] ?? null;\n")
        );
        self::assertSame(
            [],
            self::superglobalReads("<?php\n// mentions \$_REQUEST only in a comment\n\$b = 1;\n")
        );
    }
}
