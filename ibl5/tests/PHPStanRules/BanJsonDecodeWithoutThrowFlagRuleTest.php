<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanJsonDecodeWithoutThrowFlagRule;

/**
 * @extends RuleTestCase<BanJsonDecodeWithoutThrowFlagRule>
 */
final class BanJsonDecodeWithoutThrowFlagRuleTest extends RuleTestCase
{
    private const ERROR_MESSAGE = 'json_decode() must include JSON_THROW_ON_ERROR in the $flags argument (4th positional). '
        . 'Malformed JSON returns null silently, causing downstream failures far from the parse site. '
        . 'Use: json_decode($x, true, 512, JSON_THROW_ON_ERROR).';

    protected function getRule(): Rule
    {
        return new BanJsonDecodeWithoutThrowFlagRule();
    }

    public function testFlagsJsonDecodeWithoutFlag(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/JsonDecodeNoFlag.php'],
            [
                [self::ERROR_MESSAGE, 5],
            ],
        );
    }

    public function testAllowsJsonDecodeWithFlag(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/JsonDecodeWithFlag.php'],
            [],
        );
    }

    public function testAllowsJsonDecodeWithCompositeFlag(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/JsonDecodeWithCompositeFlag.php'],
            [],
        );
    }

    public function testFlagsJsonDecodeWithWrongFlag(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/JsonDecodeWithWrongFlag.php'],
            [
                [self::ERROR_MESSAGE, 5],
            ],
        );
    }

    public function testAllowsJsonDecodeOutsideClassesDirectory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/OutsideClassesJsonDecode.php'],
            [],
        );
    }
}
