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
    public function testTurnoversIsTheOnlyPlayerOnlyStat(): void
    {
        $playerOnly = array_keys(array_filter(
            RecordStatDefinitions::STATS,
            static fn (array $def): bool => $def['teamKey'] === null
        ));

        $this->assertSame(['turnovers'], $playerOnly);
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
