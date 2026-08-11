<?php

declare(strict_types=1);

namespace Tests\SimRecap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimRecap\RecapPhasePolicy;

/**
 * @covers \SimRecap\RecapPhasePolicy
 */
class RecapPhasePolicyTest extends TestCase
{
    public function testEnabledPhasesConstantContainsRegularSeason(): void
    {
        self::assertContains('Regular Season', RecapPhasePolicy::ENABLED_PHASES);
    }

    public function testRegularSeasonIsEnabled(): void
    {
        self::assertTrue(RecapPhasePolicy::isEnabled('Regular Season'));
    }

    /**
     * @param non-empty-string $phase
     */
    #[DataProvider('disabledPhaseProvider')]
    public function testNonRegularSeasonPhasesAreDisabled(string $phase): void
    {
        self::assertFalse(RecapPhasePolicy::isEnabled($phase));
    }

    /**
     * @return array<string, array{non-empty-string}>
     */
    public static function disabledPhaseProvider(): array
    {
        return [
            'Preseason'   => ['Preseason'],
            'HEAT'        => ['HEAT'],
            'Playoffs'    => ['Playoffs'],
            'Draft'       => ['Draft'],
            'Free Agency' => ['Free Agency'],
        ];
    }

    public function testEmptyStringIsDisabled(): void
    {
        self::assertFalse(RecapPhasePolicy::isEnabled(''));
    }

    public function testCaseSensitive(): void
    {
        // The check is strict — 'regular season' (lower) must not pass.
        self::assertFalse(RecapPhasePolicy::isEnabled('regular season'));
    }
}
