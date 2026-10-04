<?php

declare(strict_types=1);

namespace Tests\Unit\Updater\SeasonRollover;

use PHPUnit\Framework\TestCase;
use Updater\SeasonRollover\CashConsiderationsYearAdvancer;
use Updater\SeasonRollover\SeasonRolloverApplier;
use Updater\SeasonRollover\SeasonRolloverDetector;
use Updater\SeasonRollover\SeasonRolloverRunner;

/**
 * Pins the extracted runner as state-free: it holds only its three rollover
 * collaborators and touches no request, session, DB handle or output. The
 * admin, POST and CSRF guards and all rendering stay in
 * scripts/updateAllTheThings.php.
 */
final class SeasonRolloverRunnerStateFreeTest extends TestCase
{
    private const FORBIDDEN = ['$_POST', '$_GET', '$_REQUEST', '$_COOKIE', '$_SESSION', 'is_admin', 'mysqli', 'echo'];

    /**
     * Scans the executable code of SeasonRolloverRunner::run() and the rest of
     * the class, with comments stripped.
     */
    public function testRunnerSourceContainsNoForbiddenToken(): void
    {
        $hits = $this->forbiddenTokensIn($this->runnerSource());

        self::assertSame([], $hits, 'SeasonRolloverRunner must stay state-free; found: ' . implode(', ', $hits));
    }

    public function testForbiddenTokenScanFlagsInjectedToken(): void
    {
        $source = $this->runnerSource();
        $end = strrpos($source, '}');
        self::assertNotFalse($end);
        $injected = substr($source, 0, $end) . "\$x = \$_POST['a']; echo \$x;\n}\n";

        $hits = $this->forbiddenTokensIn($injected);

        self::assertContains('$_POST', $hits);
        self::assertContains('echo', $hits);
        self::assertSame([], $this->forbiddenTokensIn("<?php\n/** reads \$_POST */\n"));
    }

    public function testRunnerConstructorTakesOnlyRolloverCollaborators(): void
    {
        $constructor = (new \ReflectionClass(SeasonRolloverRunner::class))->getConstructor();
        self::assertNotNull($constructor);

        $types = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            self::assertFalse($type->allowsNull());
            $types[] = $type->getName();
        }

        self::assertSame(
            [SeasonRolloverDetector::class, SeasonRolloverApplier::class, CashConsiderationsYearAdvancer::class],
            $types
        );
    }

    public function testRunnerDeclaresNoPropertyBeyondCollaborators(): void
    {
        $names = [];
        foreach ((new \ReflectionClass(SeasonRolloverRunner::class))->getProperties() as $property) {
            $names[] = $property->getName();
            $type = $property->getType();
            if ($type instanceof \ReflectionNamedType) {
                self::assertNotSame('mysqli', ltrim($type->getName(), '\\'));
            }
        }

        self::assertSame(['detector', 'applier', 'cashAdvancer'], $names);
    }

    private function runnerSource(): string
    {
        $file = (new \ReflectionClass(SeasonRolloverRunner::class))->getFileName();
        self::assertNotFalse($file);
        $source = file_get_contents($file);
        self::assertNotFalse($source);

        return $source;
    }

    private function codeWithoutComments(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    /**
     * @return list<string>
     */
    private function forbiddenTokensIn(string $source): array
    {
        $code = $this->codeWithoutComments($source);
        $hits = [];
        foreach (self::FORBIDDEN as $forbidden) {
            if (str_contains($code, $forbidden)) {
                $hits[] = $forbidden;
            }
        }

        return $hits;
    }
}
