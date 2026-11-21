<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\RequireNativeTypeDeclarationsRule;

/**
 * @extends RuleTestCase<RequireNativeTypeDeclarationsRule>
 */
final class RequireNativeTypeDeclarationsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new RequireNativeTypeDeclarationsRule();
    }

    public function testFlagsMissingNativeTypesOnMethodsFunctionsAndProperties(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/NativeTypesMissing.php'],
            [
                [self::missingProperty('MissingTypesView', 'db'), 9],
                [self::missingProperty('MissingTypesView', 'legacy'), 10],
                [self::missingParam('repo', 'Method MissingTypesView::__construct()'), 12],
                [self::missingParam('team', 'Method MissingTypesView::render()'), 16],
                [self::missingReturn('Method MissingTypesView::noReturn()'), 21],
                [self::missingParam('items', 'Method MissingTypesView::spread()'), 25],
                [self::missingParam('id', 'Method MissingTypesContract::load()'), 32],
                [self::missingReturn('Method MissingTypesContract::load()'), 32],
                [self::missingReturn('Method MissingTypesAbstract::build()'), 37],
                [self::missingReturn('Method MissingTypesTrait::untypedTraitMethod()'), 46],
                [self::missingReturn('Method MissingTypesEnum::label()'), 55],
                [self::missingParam('value', 'Function missingTypesFunction()'), 61],
                [self::missingReturn('Function missingTypesFunction()'), 61],
                [self::missingReturn('Method class@anonymous::run()'), 66],
            ],
        );
    }

    public function testFlagsBareObjectTypeInAllPositions(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/NativeTypesBareObject.php'],
            [
                [self::bareObject('Property BareObjectMock::$handle'), 9],
                [self::bareObject('Parameter $user of Method BareObjectMock::__construct()'), 11],
                [self::bareObject('Parameter $stmt of Method BareObjectMock::bind()'), 15],
                [self::bareObject('Return type of Method BareObjectMock::fetch()'), 19],
                [self::bareObject('Return type of Method BareObjectMock::maybeFetch()'), 24],
                [self::bareObject('Parameter $value of Method BareObjectMock::mixedInput()'), 29],
            ],
        );
    }

    public function testFlagsPhpDocOnlyTypes(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/NativeTypesPhpDocOnly.php'],
            [
                [self::missingProperty('PhpDocOnlyTypes', 'mockDb'), 10],
                [self::missingParam('x', 'Method PhpDocOnlyTypes::f()'), 13],
                [self::missingReturn('Method PhpDocOnlyTypes::g()'), 18],
            ],
        );
    }

    public function testAcceptsFullyTypedDeclarations(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/NativeTypesCompliant.php'],
            [],
        );
    }

    private static function missingParam(string $name, string $label): string
    {
        return 'Parameter $' . $name . ' of ' . $label . ' has no native type declaration. '
            . 'A PHPDoc @param does not count; declare a native type (mixed when nothing narrower fits).';
    }

    private static function missingReturn(string $label): string
    {
        return $label . ' has no native return type declaration. '
            . 'A PHPDoc @return does not count; declare a native type (void, mixed, or narrower).';
    }

    private static function missingProperty(string $class, string $name): string
    {
        return 'Property ' . $class . '::$' . $name . ' has no native type declaration. '
            . 'A PHPDoc @var does not count; declare a native type (\Closure for a stored callable).';
    }

    private static function bareObject(string $subject): string
    {
        return $subject . ' uses the bare object type. Declare the class, interface, or union it accepts.';
    }
}
