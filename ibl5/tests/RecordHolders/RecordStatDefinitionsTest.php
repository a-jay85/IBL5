<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use PHPUnit\Framework\TestCase;
use RecordHolders\Contracts\RecordHoldersRepositoryInterface;
use RecordHolders\RecordBreakingDetector;
use RecordHolders\RecordHoldersService;
use RecordHolders\RecordStatDefinitions;

final class RecordStatDefinitionsTest extends TestCase
{
    /**
     * Locks the canonical registry exactly — every field (teamKey, expression,
     * unit, recordLabel) and the iteration order both consumers depend on.
     */
    public function testStatsRegistryIsTheCanonicalDefinition(): void
    {
        $expected = [
            'points'    => ['teamKey' => 'team_points',   'expression' => 'bs.calc_points',   'unit' => 'points',         'recordLabel' => 'Most Points in a Single Game'],
            'rebounds'  => ['teamKey' => 'team_rebounds', 'expression' => 'bs.calc_rebounds', 'unit' => 'rebounds',       'recordLabel' => 'Most Rebounds in a Single Game'],
            'assists'   => ['teamKey' => 'team_assists',  'expression' => 'bs.game_ast',      'unit' => 'assists',        'recordLabel' => 'Most Assists in a Single Game'],
            'steals'    => ['teamKey' => 'team_steals',   'expression' => 'bs.game_stl',      'unit' => 'steals',         'recordLabel' => 'Most Steals in a Single Game'],
            'blocks'    => ['teamKey' => 'team_blocks',   'expression' => 'bs.game_blk',      'unit' => 'blocks',         'recordLabel' => 'Most Blocks in a Single Game'],
            'turnovers' => ['teamKey' => null,            'expression' => 'bs.game_tov',      'unit' => 'turnovers',      'recordLabel' => 'Most Turnovers in a Single Game'],
            'fg_made'   => ['teamKey' => 'team_fg_made',  'expression' => 'bs.calc_fg_made',  'unit' => 'field goals',    'recordLabel' => 'Most Field Goals in a Single Game'],
            'ft_made'   => ['teamKey' => 'team_ft_made',  'expression' => 'bs.game_ftm',      'unit' => 'free throws',    'recordLabel' => 'Most Free Throws in a Single Game'],
            '3pt_made'  => ['teamKey' => 'team_3pt_made', 'expression' => 'bs.game_3gm',      'unit' => 'three pointers', 'recordLabel' => 'Most Three Pointers in a Single Game'],
        ];

        // @phpstan-ignore method.alreadyNarrowedType (const value is statically known; assertion still guards against future edits)
        $this->assertSame($expected, RecordStatDefinitions::STATS);
    }

    public function testTurnoversIsTheOnlyPlayerOnlyStat(): void
    {
        $playerOnly = array_keys(array_filter(
            RecordStatDefinitions::STATS,
            static fn (array $def): bool => $def['teamKey'] === null
        ));

        $this->assertSame(['turnovers'], $playerOnly);
    }

    public function testDateFiltersMapGameTypesToBoxScoreGameType(): void
    {
        // @phpstan-ignore method.alreadyNarrowedType (const value is statically known; assertion still guards against future edits)
        $this->assertSame([
            'regularSeason' => 'bs.game_type = 1',
            'playoffs'      => 'bs.game_type = 2',
            'heat'          => 'bs.game_type = 3',
        ], RecordStatDefinitions::DATE_FILTERS);
    }

    // --- Single-source propagation guards ---
    //
    // Expected maps are DERIVED from RecordStatDefinitions::STATS, not
    // re-hardcoded. If a registry expression changes, the derived expectation
    // changes with it — and these tests fail unless the consumer also tracks the
    // registry. That is what proves both consumers share one source.

    public function testServiceDerivesPlayerExpressionsFromRegistry(): void
    {
        $expected = [];
        foreach (RecordStatDefinitions::STATS as $def) {
            $expected[$def['recordLabel']] = $def['expression'];
        }

        /** @var array<string, string>|null $captured */
        $captured = null;
        $repo = self::createStub(RecordHoldersRepositoryInterface::class);
        $repo->method('getTopPlayerSingleGameBatch')
            ->willReturnCallback(function (array $expressions) use (&$captured): array {
                $captured ??= $expressions;
                return [];
            });
        $service = new RecordHoldersService($repo);

        $service->getAllRecords();

        $this->assertSame($expected, $captured);
    }

    public function testDetectorDerivesPlayerExpressionsFromRegistry(): void
    {
        $expected = [];
        foreach (RecordStatDefinitions::STATS as $key => $def) {
            $expected[$key] = $def['expression'];
        }

        /** @var array<string, string>|null $captured */
        $captured = null;
        $repo = self::createStub(RecordHoldersRepositoryInterface::class);
        $repo->method('getTopPlayerSingleGameBatch')
            ->willReturnCallback(function (array $expressions) use (&$captured): array {
                $captured ??= $expressions;
                return [];
            });
        $detector = new RecordBreakingDetector($repo);

        $detector->detectAndAnnounce(['2007-01-15']);

        $this->assertSame($expected, $captured);
    }
}
