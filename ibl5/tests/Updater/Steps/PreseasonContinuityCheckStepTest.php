<?php

declare(strict_types=1);

namespace Tests\Updater\Steps;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrParserRepositoryInterface;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\PreseasonContinuityCheckStep;

/**
 * @covers \Updater\Steps\PreseasonContinuityCheckStep
 */
class PreseasonContinuityCheckStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        $this->assertTrue(
            (new \ReflectionClass(PreseasonContinuityCheckStep::class))
                ->implementsInterface(PipelineStepInterface::class)
        );
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $step = $this->buildStep();

        $this->assertSame('Preseason continuity check', $step->getLabel());
    }

    #[DataProvider('nonCheckedPhaseProvider')]
    public function testSkipsOutsideHeatAndRegularSeason(string $phase): void
    {
        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockRepo = self::createMock(PlrParserRepositoryInterface::class);
        $mockRepo->expects($this->never())->method('getSnapshotsByPhase');

        $step = $this->buildStep(repo: $mockRepo, phase: $phase);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Not HEAT or Regular Season phase', $result->detail);
    }

    public static function nonCheckedPhaseProvider(): array
    {
        return [
            'Preseason'  => ['Preseason'],
            'Playoffs'   => ['Playoffs'],
            'Draft'      => ['Draft'],
            'Free Agency' => ['Free Agency'],
            'empty'      => [''],
        ];
    }

    public function testSkipsWhenNoPreseasonSnapshot(): void
    {
        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $repoStub = self::createStub(PlrParserRepositoryInterface::class);
        $repoStub->method('getSnapshotsByPhase')->willReturn([]);

        $step = $this->buildStep(repo: $repoStub, phase: 'HEAT');
        $result = $step->execute();

        $this->assertSame('No preseason snapshot for 2099', $result->detail);
        $this->assertSame(0, $result->messageErrorCount);
    }

    public function testSkipsWhenPlrFileMissing(): void
    {
        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $repoStub = self::createStub(PlrParserRepositoryInterface::class);
        $repoStub->method('getSnapshotsByPhase')->willReturn([$this->snapshotRow(101, 'Player')]);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $resolverStub = self::createStub(JsbSourceResolverInterface::class);
        $resolverStub->method('getContents')->willReturn(null);

        $step = $this->buildStep(repo: $repoStub, resolver: $resolverStub, phase: 'HEAT');
        $result = $step->execute();

        $this->assertSame('PLR file not found', $result->detail);
    }

    public function testFlagsLostPreseasonEdit(): void
    {
        $snapshot = $this->snapshotRow(101, 'Test Player', ['oo' => 5]);

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $repoStub = self::createStub(PlrParserRepositoryInterface::class);
        $repoStub->method('getSnapshotsByPhase')->willReturn([101 => $snapshot]);

        $plrLine = $this->plrLine(101, 'Test Player', ['ratingOO' => 3]);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $resolverStub = self::createStub(JsbSourceResolverInterface::class);
        $resolverStub->method('getContents')->willReturn($plrLine);

        $step = $this->buildStep(repo: $repoStub, resolver: $resolverStub, phase: 'HEAT');
        $result = $step->execute();

        $this->assertSame(['ERROR: Test Player (pid 101): oo preseason=5 current=3'], $result->messages);
        $this->assertSame(1, $result->messageErrorCount);
        $this->assertTrue($result->success);
    }

    public function testDoesNotFlagCarriedOverEdit(): void
    {
        $snapshot = $this->snapshotRow(101, 'Test Player');

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $repoStub = self::createStub(PlrParserRepositoryInterface::class);
        $repoStub->method('getSnapshotsByPhase')->willReturn([101 => $snapshot]);

        $plrLine = $this->plrLine(101, 'Test Player');

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $resolverStub = self::createStub(JsbSourceResolverInterface::class);
        $resolverStub->method('getContents')->willReturn($plrLine);

        $step = $this->buildStep(repo: $repoStub, resolver: $resolverStub, phase: 'HEAT');
        $result = $step->execute();

        $this->assertSame([], $result->messages);
        $this->assertSame(0, $result->messageErrorCount);
    }

    public function testIgnoresShootingPercentageRebuild(): void
    {
        $snapshot = $this->snapshotRow(101, 'Test Player', ['r_fgp' => 50, 'r_ftp' => 80, 'r_3gp' => 35]);

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $repoStub = self::createStub(PlrParserRepositoryInterface::class);
        $repoStub->method('getSnapshotsByPhase')->willReturn([101 => $snapshot]);

        $plrLine = $this->plrLine(101, 'Test Player', ['rating2GP' => 58, 'ratingFTP' => 44, 'rating3GP' => 43]);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $resolverStub = self::createStub(JsbSourceResolverInterface::class);
        $resolverStub->method('getContents')->willReturn($plrLine);

        $step = $this->buildStep(repo: $repoStub, resolver: $resolverStub, phase: 'HEAT');
        $result = $step->execute();

        $this->assertSame(0, $result->messageErrorCount);
    }

    public function testFlagsStatRatingDiffDuringHeat(): void
    {
        $snapshot = $this->snapshotRow(101, 'Test Player', ['r_orb' => 41]);

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $repoStub = self::createStub(PlrParserRepositoryInterface::class);
        $repoStub->method('getSnapshotsByPhase')->willReturn([101 => $snapshot]);

        $plrLine = $this->plrLine(101, 'Test Player', ['ratingORB' => 60]);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $resolverStub = self::createStub(JsbSourceResolverInterface::class);
        $resolverStub->method('getContents')->willReturn($plrLine);

        $step = $this->buildStep(repo: $repoStub, resolver: $resolverStub, phase: 'HEAT');
        $result = $step->execute();

        $this->assertCount(1, $result->messages);
        $this->assertStringContainsString('r_orb', $result->messages[0]);
    }

    public function testRegularSeasonSkipsStatRatingsButComparesOdpt(): void
    {
        // r_orb differs (should be ignored in Regular Season), td differs (should be flagged)
        $snapshot = $this->snapshotRow(101, 'Test Player', ['r_orb' => 99, 'td' => 99]);

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $repoStub = self::createStub(PlrParserRepositoryInterface::class);
        $repoStub->method('getSnapshotsByPhase')->willReturn([101 => $snapshot]);

        // PLR line: ratingORB = default (41), ratingTD = default (8); both differ from snapshot values above
        $plrLine = $this->plrLine(101, 'Test Player');

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $resolverStub = self::createStub(JsbSourceResolverInterface::class);
        $resolverStub->method('getContents')->willReturn($plrLine);

        $step = $this->buildStep(repo: $repoStub, resolver: $resolverStub, phase: 'Regular Season');
        $result = $step->execute();

        $this->assertCount(1, $result->messages);
        $this->assertStringContainsString('td', $result->messages[0]);
        $this->assertStringNotContainsString('r_orb', $result->messages[0]);
    }

    public function testFlagsPositionChange(): void
    {
        $snapshot = $this->snapshotRow(101, 'Test Player', ['pos' => 'PG']);

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $repoStub = self::createStub(PlrParserRepositoryInterface::class);
        $repoStub->method('getSnapshotsByPhase')->willReturn([101 => $snapshot]);

        $plrLine = $this->plrLine(101, 'Test Player', ['pos' => 'SG']);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $resolverStub = self::createStub(JsbSourceResolverInterface::class);
        $resolverStub->method('getContents')->willReturn($plrLine);

        $step = $this->buildStep(repo: $repoStub, resolver: $resolverStub, phase: 'HEAT');
        $result = $step->execute();

        $this->assertCount(1, $result->messages);
        $this->assertStringContainsString('pos preseason=PG current=SG', $result->messages[0]);
    }

    public function testCapsListedLinesAtTwentyFive(): void
    {
        $snapshots = [];
        $lines = [];
        for ($i = 1; $i <= 30; $i++) {
            $pid = 10000 + $i;
            $snapshots[$pid] = $this->snapshotRow($pid, "Player {$i}", ['oo' => 5]);
            $lines[] = $this->plrLine($pid, "Player {$i}", ['ratingOO' => 3]);
        }

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $repoStub = self::createStub(PlrParserRepositoryInterface::class);
        $repoStub->method('getSnapshotsByPhase')->willReturn($snapshots);

        $plrData = implode("\r\n", $lines);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $resolverStub = self::createStub(JsbSourceResolverInterface::class);
        $resolverStub->method('getContents')->willReturn($plrData);

        $step = $this->buildStep(repo: $repoStub, resolver: $resolverStub, phase: 'HEAT');
        $result = $step->execute();

        $this->assertCount(26, $result->messages);
        $this->assertSame('...and 5 more', $result->messages[25]);
        $this->assertSame(30, $result->messageErrorCount);
    }

    public function testIgnoresPidsPresentOnOnlyOneSide(): void
    {
        $snapshots = [
            101 => $this->snapshotRow(101, 'Player One'),
            102 => $this->snapshotRow(102, 'Player Two'),
        ];

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $repoStub = self::createStub(PlrParserRepositoryInterface::class);
        $repoStub->method('getSnapshotsByPhase')->willReturn($snapshots);

        // PLR has pid 101 (identical defaults) and pid 103 (not in snapshot)
        $plrData = $this->plrLine(101, 'Player One') . "\r\n" . $this->plrLine(103, 'Player Three');

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $resolverStub = self::createStub(JsbSourceResolverInterface::class);
        $resolverStub->method('getContents')->willReturn($plrData);

        $step = $this->buildStep(repo: $repoStub, resolver: $resolverStub, phase: 'HEAT');
        $result = $step->execute();

        $this->assertSame(0, $result->messageErrorCount);
    }

    public function testComparesStringSnapshotValuesAgainstParsedInts(): void
    {
        // Snapshot values as numeric strings
        $snapshot = $this->snapshotRow(101, 'Test Player', [
            'oo' => '1',
            'od' => '2',
            'r_drive_off' => '3',
            'dd' => '4',
            'po' => '5',
            'pd' => '6',
            'r_trans_off' => '7',
            'td' => '8',
        ]);

        /** @var PlrParserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
        $repoStub = self::createStub(PlrParserRepositoryInterface::class);
        $repoStub->method('getSnapshotsByPhase')->willReturn([101 => $snapshot]);

        $plrLine = $this->plrLine(101, 'Test Player');

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $resolverStub = self::createStub(JsbSourceResolverInterface::class);
        $resolverStub->method('getContents')->willReturn($plrLine);

        $step = $this->buildStep(repo: $repoStub, resolver: $resolverStub, phase: 'HEAT');
        $result = $step->execute();

        $this->assertSame(0, $result->messageErrorCount);
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function buildStep(
        ?PlrParserRepositoryInterface $repo = null,
        ?JsbSourceResolverInterface $resolver = null,
        int $year = 2099,
        string $phase = 'HEAT',
    ): PreseasonContinuityCheckStep {
        return new PreseasonContinuityCheckStep(
            $repo ?? self::createStub(PlrParserRepositoryInterface::class),
            $resolver ?? self::createStub(JsbSourceResolverInterface::class),
            $year,
            $phase,
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function snapshotRow(int $pid, string $name, array $overrides = []): array
    {
        $defaults = [
            'pid' => $pid, 'name' => $name, 'season_year' => 2099,
            'snapshot_phase' => 'preseason', 'source_archive' => 'test.zip', 'ordinal' => 1,
            'pos' => 'PG',
            'rl_gp' => 101, 'rl_min' => 102, 'rl_fgm' => 103, 'rl_fga' => 104,
            'rl_ftm' => 105, 'rl_fta' => 106, 'rl_3gm' => 107, 'rl_3ga' => 108,
            'rl_orb' => 109, 'rl_drb' => 110, 'rl_ast' => 111, 'rl_stl' => 112,
            'rl_tvr' => 113, 'rl_blk' => 114, 'rl_pf' => 115,
            'r_orb' => 41, 'r_drb' => 42, 'r_ast' => 43,
            'r_stl' => 44, 'r_tvr' => 45, 'r_blk' => 46,
            'oo' => 1, 'od' => 2, 'r_drive_off' => 3, 'dd' => 4,
            'po' => 5, 'pd' => 6, 'r_trans_off' => 7, 'td' => 8,
            'r_fgp' => 50, 'r_ftp' => 51, 'r_3gp' => 52,
        ];
        return array_merge($defaults, $overrides);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function plrLine(int $pid, string $name, array $fields = []): string
    {
        $defaults = [
            'pos' => 'PG',
            'realLifeGP' => 101, 'realLifeMIN' => 102, 'realLifeFGM' => 103, 'realLifeFGA' => 104,
            'realLifeFTM' => 105, 'realLifeFTA' => 106, 'realLife3GM' => 107, 'realLife3GA' => 108,
            'realLifeORB' => 109, 'realLifeDRB' => 110, 'realLifeAST' => 111, 'realLifeSTL' => 112,
            'realLifeTVR' => 113, 'realLifeBLK' => 114, 'realLifePF' => 115,
            'ratingORB' => 41, 'ratingDRB' => 42, 'ratingAST' => 43,
            'ratingSTL' => 44, 'ratingTVR' => 45, 'ratingBLK' => 46,
            'ratingOO' => 1, 'ratingOD' => 2, 'ratingDO' => 3, 'ratingDD' => 4,
            'ratingPO' => 5, 'ratingPD' => 6, 'ratingTO' => 7, 'ratingTD' => 8,
            'rating2GP' => 50, 'ratingFTP' => 51, 'rating3GP' => 52,
        ];
        $fields = array_merge($defaults, $fields);

        $line = str_repeat(' ', 607);
        $line = substr_replace($line, str_pad('1', 4, ' ', STR_PAD_LEFT), 0, 4);
        $line = substr_replace($line, str_pad($name, 32), 4, 32);
        $line = substr_replace($line, str_pad((string) $pid, 6, '0', STR_PAD_LEFT), 38, 6);
        $line = substr_replace($line, str_pad((string) $fields['pos'], 2), 50, 2);

        // rl_* fields, width 4 right-aligned
        foreach ([
            'realLifeGP' => 52, 'realLifeMIN' => 56, 'realLifeFGM' => 60, 'realLifeFGA' => 64,
            'realLifeFTM' => 68, 'realLifeFTA' => 72, 'realLife3GM' => 76, 'realLife3GA' => 80,
            'realLifeORB' => 84, 'realLifeDRB' => 88, 'realLifeAST' => 92, 'realLifeSTL' => 96,
            'realLifeTVR' => 100, 'realLifeBLK' => 104, 'realLifePF' => 108,
        ] as $key => $offset) {
            $line = substr_replace($line, str_pad((string) $fields[$key], 4, ' ', STR_PAD_LEFT), $offset, 4);
        }

        // Stat ratings, width 3 right-aligned
        foreach ([
            'rating2GP' => 558, 'ratingFTP' => 564, 'rating3GP' => 570,
            'ratingORB' => 573, 'ratingDRB' => 576, 'ratingAST' => 579,
            'ratingSTL' => 582, 'ratingTVR' => 585, 'ratingBLK' => 588,
        ] as $key => $offset) {
            $line = substr_replace($line, str_pad((string) $fields[$key], 3, ' ', STR_PAD_LEFT), $offset, 3);
        }

        // ODPT ratings, width 2 right-aligned
        foreach ([
            'ratingOO' => 591, 'ratingDO' => 593, 'ratingPO' => 595, 'ratingTO' => 597,
            'ratingOD' => 599, 'ratingDD' => 601, 'ratingPD' => 603, 'ratingTD' => 605,
        ] as $key => $offset) {
            $line = substr_replace($line, str_pad((string) $fields[$key], 2, ' ', STR_PAD_LEFT), $offset, 2);
        }

        return $line;
    }
}
