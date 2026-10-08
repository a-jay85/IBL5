<?php

declare(strict_types=1);

namespace Tests\Database;

use Boxscore\BoxscoreAuditRepository;
use Boxscore\BoxscoreRepository;
use Database\BaseMysqliRepository;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @covers \Database\BaseMysqliRepository
 */
final class BaseMysqliRepositoryScalarHelpersTest extends TestCase
{
    private \Closure $toInt;
    private \Closure $toString;

    protected function setUp(): void
    {
        $probe = new class (new MockDatabase()) extends BaseMysqliRepository {
            public static function exposeInt(mixed $value): int
            {
                return self::scalarToInt($value);
            }

            public static function exposeString(mixed $value): string
            {
                return self::scalarToString($value);
            }
        };
        $this->toInt = $probe::exposeInt(...);
        $this->toString = $probe::exposeString(...);
    }

    public function testScalarToIntPassesThroughIntsAndCastsNumericValues(): void
    {
        $cases = [
            [42, 42],
            [-7, -7],
            [0, 0],
            [12.9, 12],
            [-3.7, -3],
            ['5', 5],
            ['3.9', 3],
            ['1e3', 1000],
        ];

        foreach ($cases as [$input, $expected]) {
            $this->assertSame($expected, ($this->toInt)($input), 'input: ' . var_export($input, true));
        }
    }

    public function testScalarToIntReturnsZeroForNonNumericAndNonScalar(): void
    {
        $inputs = [null, true, false, [], [1], '', 'abc', '12abc'];

        foreach ($inputs as $input) {
            $this->assertSame(0, ($this->toInt)($input), 'input: ' . var_export($input, true));
        }
    }

    public function testScalarToStringPassesThroughStringsAndStringifiesNumbers(): void
    {
        $cases = [
            ['2025-01-03', '2025-01-03'],
            ['', ''],
            ['abc', 'abc'],
            [7, '7'],
            [0, '0'],
            [1.5, '1.5'],
        ];

        foreach ($cases as [$input, $expected]) {
            $this->assertSame($expected, ($this->toString)($input), 'input: ' . var_export($input, true));
        }
    }

    public function testScalarToStringReturnsEmptyForNonScalar(): void
    {
        $inputs = [null, true, false, [], ['x']];

        foreach ($inputs as $input) {
            $this->assertSame('', ($this->toString)($input), 'input: ' . var_export($input, true));
        }
    }

    public function testScalarHelpersAreProtectedStaticAndInheritedByBoxscoreRepositories(): void
    {
        foreach (['scalarToInt', 'scalarToString'] as $name) {
            $base = new \ReflectionMethod(BaseMysqliRepository::class, $name);
            $this->assertTrue($base->isProtected(), $name . ' must be protected');
            $this->assertTrue($base->isStatic(), $name . ' must be static');

            foreach ([BoxscoreRepository::class, BoxscoreAuditRepository::class] as $class) {
                $declaring = (new \ReflectionMethod($class, $name))->getDeclaringClass()->getName();
                $this->assertSame(BaseMysqliRepository::class, $declaring, $class . '::' . $name);
            }
        }
    }
}
