<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\Player;

class PlayerPublicApiSurfaceTest extends TestCase
{
    /**
     * Frozen public API surface of Player, generated pre-refactor.
     * 138 caller files depend on these exact signatures. Any diff here is
     * a source-compatibility break, not a test that needs updating.
     *
     * @var list<string>
     */
    private const array EXPECTED_PUBLIC_API = [
        '__construct(): mixed',
        'canRenegotiateContract(?Season\Season $season = default): bool',
        'canRookieOption(string $seasonPhase): bool',
        'decoratePlayerName(): string',
        'getAge(): ?int',
        'getBirdYears(): ?int',
        'getCollegeName(): ?string',
        'getContractCurrentYear(): ?int',
        'getContractTotalYears(): ?int',
        'getContractYear1Salary(): ?int',
        'getContractYear2Salary(): ?int',
        'getContractYear3Salary(): ?int',
        'getContractYear4Salary(): ?int',
        'getContractYear5Salary(): ?int',
        'getContractYear6Salary(): ?int',
        'getCurrentSeasonSalary(): int',
        'getDaysRemainingForInjury(): ?int',
        'getDecoratedName(): ?string',
        'getDraftPickNumber(): ?int',
        'getDraftRound(): ?int',
        'getDraftTeamCurrentName(): ?string',
        'getDraftTeamOriginalName(): ?string',
        'getDraftYear(): ?int',
        'getFinalYearRookieContractSalary(): int',
        'getFreeAgencyDemands(): array',
        'getFreeAgencyLoyalty(): ?int',
        'getFreeAgencyPlayForWinner(): ?int',
        'getFreeAgencyPlayingTime(): ?int',
        'getFreeAgencySecurity(): ?int',
        'getFreeAgencyTradition(): ?int',
        'getFutureSalaries(): array',
        'getHeightFeet(): ?int',
        'getHeightInches(): ?int',
        'getHistoricalYear(): ?int',
        'getInjuryReturnDate(string $rawLastSimEndDate): string',
        'getIsRetired(): ?int',
        'getLongBuyoutArray(): array',
        'getName(): ?string',
        'getNameStatusClass(): string',
        'getNextSeasonSalary(): int',
        'getNickname(): ?string',
        'getOrdinal(): ?int',
        'getPlayerID(): ?int',
        'getPlrRow(): ?array',
        'getPosition(): ?string',
        'getRatingAssists(): ?int',
        'getRatingBlocks(): ?int',
        'getRatingClutch(): ?int',
        'getRatingConsistency(): ?int',
        'getRatingDefensiveRebounds(): ?int',
        'getRatingDriveDefense(): ?int',
        'getRatingDriveOffense(): ?int',
        'getRatingFieldGoalAttempts(): ?int',
        'getRatingFieldGoalPercentage(): ?int',
        'getRatingFouls(): ?int',
        'getRatingFreeThrowAttempts(): ?int',
        'getRatingFreeThrowPercentage(): ?int',
        'getRatingIntangibles(): ?int',
        'getRatingOffensiveRebounds(): ?int',
        'getRatingOutsideDefense(): ?int',
        'getRatingOutsideOffense(): ?int',
        'getRatingPostDefense(): ?int',
        'getRatingPostOffense(): ?int',
        'getRatingSkill(): ?int',
        'getRatingSteals(): ?int',
        'getRatingTalent(): ?int',
        'getRatingThreePointAttempts(): ?int',
        'getRatingThreePointPercentage(): ?int',
        'getRatingTransitionDefense(): ?int',
        'getRatingTransitionOffense(): ?int',
        'getRatingTurnovers(): ?int',
        'getRemainingContractArray(): array',
        'getSalaryJSB(): ?int',
        'getShortBuyoutArray(): array',
        'getTeamColor1(): ?string',
        'getTeamColor2(): ?string',
        'getTeamName(): ?string',
        'getTeamid(): ?int',
        'getTimeDroppedOnWaivers(): ?int',
        'getTotalRemainingSalary(): int',
        'getWeightPounds(): ?int',
        'getYearsOfExperience(): ?int',
        'isPlayerFreeAgent(Season\Season $season): bool',
        'isSalaryPlaceholder(): bool',
        'static withHistoricalPlrRow(mysqli $db, array $plrRow): Player\Player',
        'static withPlayerID(mysqli $db, int $playerID): Player\Player',
        'static withPlrRow(mysqli $db, array $plrRow): Player\Player',
        'wasRookieOptioned(): bool',
    ];

    private static function typeToString(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionNamedType) {
            $nullable = $type->allowsNull() && $type->getName() !== 'null' && $type->getName() !== 'mixed';
            return ($nullable ? '?' : '') . $type->getName();
        }
        return 'mixed';
    }

    /** @return list<string> */
    private static function publicApiOf(string $class): array
    {
        $reflection = new \ReflectionClass($class);
        $signatures = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $params = [];
            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                $params[] = ($type !== null ? self::typeToString($type) : 'mixed')
                    . ' $' . $param->getName()
                    . ($param->isDefaultValueAvailable() ? ' = default' : '');
            }
            $returnType = $method->getReturnType();
            $signatures[] = ($method->isStatic() ? 'static ' : '')
                . $method->getName()
                . '(' . implode(', ', $params) . '): '
                . ($returnType !== null ? self::typeToString($returnType) : 'mixed');
        }
        sort($signatures, SORT_STRING);
        return $signatures;
    }

    public function testPlayerPublicApiSurfaceIsUnchanged(): void
    {
        self::assertSame(
            self::EXPECTED_PUBLIC_API,
            self::publicApiOf(Player::class),
            'Player public API drifted — 138 caller files depend on these signatures'
        );
    }

    public function testPinDetectsARemovedMethod(): void
    {
        $withOneRemoved = self::EXPECTED_PUBLIC_API;
        array_pop($withOneRemoved);

        self::assertNotSame(
            $withOneRemoved,
            self::publicApiOf(Player::class),
            'pin is vacuous: it cannot tell a dropped method from the real surface'
        );
    }

    public function testPinDetectsADriftedReturnType(): void
    {
        $withDriftedReturn = array_map(
            static fn (string $sig): string => str_replace('): ?int', '): int', $sig),
            self::EXPECTED_PUBLIC_API
        );

        self::assertNotSame(
            $withDriftedReturn,
            self::publicApiOf(Player::class),
            'pin is vacuous: it cannot tell ?int from int'
        );
    }

    public function testEveryPureFieldGetterIsStillReachableOnPlayer(): void
    {
        $reflection = new \ReflectionClass(Player::class);
        foreach (['getName', 'getRatingClutch', 'getContractYear1Salary'] as $method) {
            self::assertTrue($reflection->hasMethod($method), "Player lost $method()");
            self::assertTrue($reflection->getMethod($method)->isPublic(), "$method() is no longer public");
        }
    }
}
