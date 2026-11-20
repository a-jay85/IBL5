<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\RequireLeagueContextInFactoryRule;

/**
 * @extends RuleTestCase<RequireLeagueContextInFactoryRule>
 */
final class RequireLeagueContextInFactoryRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new RequireLeagueContextInFactoryRule(self::createReflectionProvider());
    }

    public function testFlagsOnThisDayShapeWithNullableLeagueContextParam(): void
    {
        $this->analyse(
            [__DIR__ . '/../../phpstan-rules/Fixtures/LeagueContextParamOmittedFactory.php'],
            [
                [
                    'OnThisDay\OnThisDayRepository accepts a LeagueContext, but this factory method builds it '
                    . 'without one. Pass the LeagueContext in scope as $leagueContext.',
                    11,
                ],
            ],
        );
    }

    public function testFlagsInheritedConstructorWhenLeagueContextCalledLaterInMethod(): void
    {
        $this->analyse(
            [__DIR__ . '/../../phpstan-rules/Fixtures/LeagueContextCallOmittedFactory.php'],
            [
                [
                    'Voting\VotingRepository accepts a LeagueContext, but this factory method builds it '
                    . 'without one. Pass the LeagueContext in scope as $leagueContext.',
                    12,
                ],
            ],
        );
    }

    public function testFlagsLocalLeagueContextVariable(): void
    {
        $this->analyse(
            [__DIR__ . '/../../phpstan-rules/Fixtures/LeagueContextVariableOmittedFactory.php'],
            [
                [
                    'Team\TeamRepository accepts a LeagueContext, but this factory method builds it '
                    . 'without one. Pass the LeagueContext in scope as $leagueContext.',
                    13,
                ],
            ],
        );
    }

    public function testAllowsLeagueContextPassedPositionallyOrByName(): void
    {
        $this->analyse(
            [__DIR__ . '/../../phpstan-rules/Fixtures/LeagueContextPassedFactory.php'],
            [],
        );
    }

    public function testIgnoresMethodWithoutLeagueContextInScope(): void
    {
        $this->analyse(
            [__DIR__ . '/../../phpstan-rules/Fixtures/LeagueContextAbsentFactory.php'],
            [],
        );
    }

    public function testIgnoresNearMissLeagueContextNames(): void
    {
        $this->analyse(
            [__DIR__ . '/../../phpstan-rules/Fixtures/LeagueContextNearMissFactory.php'],
            [],
        );
    }

    public function testIgnoresConstructorWithoutLeagueContextParam(): void
    {
        $this->analyse(
            [__DIR__ . '/../../phpstan-rules/Fixtures/LeagueContextUnsupportedCtorFactory.php'],
            [],
        );
    }

    public function testIgnoresNonFactoryFile(): void
    {
        $this->analyse(
            [__DIR__ . '/../../phpstan-rules/Fixtures/LeagueContextNonFactoryBuilder.php'],
            [],
        );
    }
}
