<?php

declare(strict_types=1);

namespace Tests\League;

use PHPUnit\Framework\TestCase;
use Repositories\PlayerTeamJoinQuery;

/**
 * Pins the security invariant of the two player-to-team JOIN fragments
 * (backlog 13.12): each is a private, zero-parameter method whose body is one
 * return of a constant single-quoted literal, and whose PHPDoc declares that
 * same literal as its return type. The PHPDoc is what lets PHPStan's
 * BanSqlStringConcatenationRule accept the fragment after a SQL literal.
 */
class PlayerTeamJoinQueryContractTest extends TestCase
{
    private const FRAGMENTS = [
        'playerTeamLeftJoin' => 'LEFT JOIN `ibl_team_info` t ON p.teamid = t.teamid',
        'playerTeamInnerJoin' => 'JOIN `ibl_team_info` t ON p.teamid = t.teamid',
    ];

    public function testJoinFragmentMethodsTakeNoParameters(): void
    {
        foreach (array_keys(self::FRAGMENTS) as $name) {
            $method = new \ReflectionMethod(PlayerTeamJoinQuery::class, $name);

            $this->assertSame(0, $method->getNumberOfParameters(), "$name must take no parameters");
            $this->assertTrue($method->isPrivate(), "$name must be private");
            $this->assertFalse($method->isStatic(), "$name must not be static");
            $this->assertSame('string', (string) $method->getReturnType(), "$name must return string");
        }
    }

    public function testJoinFragmentMethodsReturnExactConstantClauses(): void
    {
        $probe = new class {
            use PlayerTeamJoinQuery;

            public function left(): string
            {
                return $this->playerTeamLeftJoin();
            }

            public function inner(): string
            {
                return $this->playerTeamInnerJoin();
            }
        };

        $this->assertSame(self::FRAGMENTS['playerTeamLeftJoin'], $probe->left());
        $this->assertSame(self::FRAGMENTS['playerTeamLeftJoin'], $probe->left());
        $this->assertSame(self::FRAGMENTS['playerTeamInnerJoin'], $probe->inner());
        $this->assertSame(self::FRAGMENTS['playerTeamInnerJoin'], $probe->inner());
    }

    public function testJoinFragmentPhpDocDeclaresTheReturnedLiteral(): void
    {
        foreach (self::FRAGMENTS as $name => $literal) {
            $doc = (new \ReflectionMethod(PlayerTeamJoinQuery::class, $name))->getDocComment();

            $this->assertIsString($doc, "$name must carry a PHPDoc");
            $this->assertStringContainsString("@return '" . $literal . "'", $doc);
        }
    }

    public function testJoinFragmentMethodBodiesAreSingleConstantReturn(): void
    {
        foreach (array_keys(self::FRAGMENTS) as $name) {
            $this->assertTrue(
                self::isConstantReturnBody(self::methodBody($name)),
                "$name body must be exactly one return of a single-quoted constant literal"
            );
        }
    }

    public function testConstantReturnBodyCheckRejectsNonConstantBodies(): void
    {
        $rejected = [
            'interpolation' => 'return "JOIN $table t ON p.teamid = t.teamid";',
            'concatenation' => "return 'JOIN ' . \$table;",
            'variable return' => 'return $this->join;',
            'function-call return' => "return sprintf('JOIN %s', \$t);",
            'two statements' => "\$x = 'a'; return \$x;",
        ];
        foreach ($rejected as $label => $body) {
            $this->assertFalse(self::isConstantReturnBody($body), "$label must be rejected");
        }

        $this->assertTrue(
            self::isConstantReturnBody("return 'LEFT JOIN `ibl_team_info` t ON p.teamid = t.teamid';")
        );
    }

    private static function methodBody(string $name): string
    {
        $method = new \ReflectionMethod(PlayerTeamJoinQuery::class, $name);
        $file = $method->getFileName();
        self::assertIsString($file);
        $lines = file($file);
        self::assertIsArray($lines);

        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
        $open = strpos($source, '{');
        $close = strrpos($source, '}');
        self::assertIsInt($open);
        self::assertIsInt($close);

        return substr($source, $open + 1, $close - $open - 1);
    }

    /**
     * Accepts exactly one single-quoted literal return: no `$`, no backslash,
     * no concatenation, no second statement.
     */
    private static function isConstantReturnBody(string $body): bool
    {
        return preg_match('/^return \'[^\'$\\\\]*\';$/', trim($body)) === 1;
    }
}
