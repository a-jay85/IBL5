<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Waivers\Contracts\WaiversTeamLockInterface;
use Waivers\WaiversTeamLockRepository;

/**
 * Structural invariants for the team-lock collaborator (backlog#1570).
 *
 * The lock receives only a server-resolved team id and a callable. It must carry no actor
 * identity (no own property, no constructor) and hold exactly one fixed, parameterised
 * SQL statement.
 */
class WaiversTeamLockStructureTest extends TestCase
{
    /**
     * One message per violation, properties first, then the constructor.
     *
     * @param \ReflectionClass<object> $class
     * @return list<string>
     */
    private static function ownStateViolations(\ReflectionClass $class): array
    {
        $violations = [];
        foreach ($class->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() === $class->getName()) {
                $violations[] = 'property:' . $property->getName();
            }
        }

        $constructor = $class->getConstructor();
        if ($constructor !== null && $constructor->getDeclaringClass()->getName() === $class->getName()) {
            $violations[] = 'constructor';
        }

        return $violations;
    }

    public function testTeamLockRepositoryHoldsNoOwnState(): void
    {
        $this->assertSame([], self::ownStateViolations(new \ReflectionClass(WaiversTeamLockRepository::class)));
    }

    public function testOwnStateScannerFlagsAForbiddenProperty(): void
    {
        $fixture = new class () {
            private string $teamName = 'x';

            public function __construct()
            {
            }

            public function teamName(): string
            {
                return $this->teamName;
            }
        };

        $this->assertSame(
            ['property:teamName', 'constructor'],
            self::ownStateViolations(new \ReflectionClass($fixture))
        );
    }

    public function testWithTeamLockTakesOnlyATeamIdAndACallable(): void
    {
        foreach ([WaiversTeamLockInterface::class, WaiversTeamLockRepository::class] as $className) {
            $method = (new \ReflectionClass($className))->getMethod('withTeamLock');
            $parameters = $method->getParameters();

            $this->assertCount(2, $parameters, $className);
            $this->assertSame('teamId', $parameters[0]->getName(), $className);
            $this->assertSame('int', (string) $parameters[0]->getType(), $className);
            $this->assertSame('fn', $parameters[1]->getName(), $className);
            $this->assertSame('callable', (string) $parameters[1]->getType(), $className);
        }
    }

    public function testTeamLockSourceHoldsOneFixedParameterisedStatement(): void
    {
        $file = (new \ReflectionClass(WaiversTeamLockRepository::class))->getFileName();
        $this->assertIsString($file);

        // phpunit-hygiene-allow: the security invariant is about the literal SQL text, which reflection cannot expose
        $src = file_get_contents($file);
        $this->assertIsString($src);

        $this->assertSame(1, substr_count($src, 'FOR UPDATE'));
        $this->assertStringContainsString("'SELECT teamid FROM `ibl_team_info` WHERE teamid = ? FOR UPDATE'", $src);

        foreach (['$_SESSION', '$_POST', '$_GET', '$_COOKIE', '->query(', '->real_query(', '->multi_query(', 'sprintf('] as $token) {
            $this->assertStringNotContainsString($token, $src, "forbidden token $token");
        }
    }
}
