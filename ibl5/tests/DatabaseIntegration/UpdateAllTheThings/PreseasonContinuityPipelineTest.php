<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\UpdateAllTheThings;

use PHPUnit\Framework\Attributes\Group;
use PlrParser\PlrParserRepository;
use PlrParser\PlrParserService;
use JsbParser\JsbImportRepository;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Steps\SnapshotPlrStep;
use Updater\Steps\PreseasonContinuityCheckStep;

#[Group('database')]
class PreseasonContinuityPipelineTest extends PipelineIntegrationTestCase
{
    public function testUnchangedFileAcrossPreseasonAndHeatFlagsNothing(): void
    {
        $plrPath = $this->buildDistinctFieldPlrFile([
            ['pid' => 900101, 'name' => 'Pipeline Player A', 'teamid' => 1, 'ordinal' => 1],
            ['pid' => 900102, 'name' => 'Pipeline Player B', 'teamid' => 1, 'ordinal' => 2],
        ]);
        $plrData = file_get_contents($plrPath);
        self::assertIsString($plrData);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn($plrData);

        $plrRepo = new PlrParserRepository($this->db);
        $plrService = new PlrParserService($plrRepo, $this->buildSeason('Preseason', 2099));
        $jsbRepo = new JsbImportRepository($this->db);

        // Write the preseason snapshot
        $snapshotStep = new SnapshotPlrStep($plrService, $jsbRepo, 2099, $resolver, 'Preseason');
        $snapshotStep->execute();

        // Run the continuity check on the same bytes at HEAT
        $checkStep = new PreseasonContinuityCheckStep($plrRepo, $resolver, 2099, 'HEAT');
        $result = $checkStep->execute();

        self::assertStringNotContainsString('No preseason snapshot', $result->detail);
        self::assertSame(0, $result->messageErrorCount);
        self::assertStringContainsString('2 players match their Preseason snapshot', $result->detail);
    }

    public function testRatingEditLostAtHeatIsFlagged(): void
    {
        $plrPath = $this->buildDistinctFieldPlrFile([
            ['pid' => 900101, 'name' => 'Pipeline Player A', 'teamid' => 1, 'ordinal' => 1],
            ['pid' => 900102, 'name' => 'Pipeline Player B', 'teamid' => 1, 'ordinal' => 2],
        ]);
        $plrData = file_get_contents($plrPath);
        self::assertIsString($plrData);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn($plrData);

        $plrRepo = new PlrParserRepository($this->db);
        $plrService = new PlrParserService($plrRepo, $this->buildSeason('Preseason', 2099));
        $jsbRepo = new JsbImportRepository($this->db);

        // Write preseason snapshot
        $snapshotStep = new SnapshotPlrStep($plrService, $jsbRepo, 2099, $resolver, 'Preseason');
        $snapshotStep->execute();

        // Modify pid 900101's ratingOO (offset 591, width 2) in the PLR data
        $lines = explode("\r\n", $plrData);
        foreach ($lines as &$line) {
            $pid = (int) substr($line, 38, 6);
            if ($pid === 900101) {
                // Write a different value at ratingOO offset 591 width 2
                $line = substr_replace($line, ' 9', 591, 2);
                break;
            }
        }
        unset($line);
        $modifiedData = implode("\r\n", $lines);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $modifiedResolver = self::createStub(JsbSourceResolverInterface::class);
        $modifiedResolver->method('getContents')->willReturn($modifiedData);

        $checkStep = new PreseasonContinuityCheckStep($plrRepo, $modifiedResolver, 2099, 'HEAT');
        $result = $checkStep->execute();

        self::assertSame(1, $result->messageErrorCount);
        self::assertCount(1, $result->messages);
        self::assertStringContainsString('oo', $result->messages[0]);
        self::assertStringContainsString('900101', $result->messages[0]);
    }

    public function testSnapshotRoundTripsEachFieldToItsColumn(): void
    {
        $plrPath = $this->buildDistinctFieldPlrFile([
            ['pid' => 900101, 'name' => 'Pipeline Player A', 'teamid' => 1, 'ordinal' => 1],
        ]);
        $plrData = file_get_contents($plrPath);
        self::assertIsString($plrData);

        /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn($plrData);

        $plrRepo = new PlrParserRepository($this->db);
        $plrService = new PlrParserService($plrRepo, $this->buildSeason('Preseason', 2099));
        $jsbRepo = new JsbImportRepository($this->db);

        (new SnapshotPlrStep($plrService, $jsbRepo, 2099, $resolver, 'Preseason'))->execute();

        $snapshots = $plrRepo->getSnapshotsByPhase(2099, 'preseason');
        self::assertArrayHasKey(900101, $snapshots);
        $row = $snapshots[900101];

        self::assertSame('SF', $row['pos'], 'ibl_plr_snapshots.pos did not round-trip');
        foreach (self::DISTINCT_FIELD_VALUES as $column => [, , $value]) {
            self::assertSame(
                $value,
                (int) $row[$column],
                "ibl_plr_snapshots.{$column} did not round-trip its distinct .plr value",
            );
        }
    }

    /**
     * Snapshot column => [.plr byte offset, field width, distinct value].
     *
     * Covers every column PreseasonContinuityCheckStep compares plus the stat ratings
     * it excludes (r_fga, r_fgp, r_fta, r_ftp, r_3ga, r_3gp). Every value is unique
     * across the whole map and in range for its field, so a swapped column-to-parser-key
     * pair changes the round-trip result instead of hiding behind an equal value.
     * Positional ratings (oo..td) are 1-9; stat ratings are 0-99; rl_* are 4-wide.
     *
     * @var array<string, array{int, int, int}>
     */
    private const DISTINCT_FIELD_VALUES = [
        'rl_gp' => [52, 4, 61],
        'rl_min' => [56, 4, 2007],
        'rl_fgm' => [60, 4, 413],
        'rl_fga' => [64, 4, 877],
        'rl_ftm' => [68, 4, 305],
        'rl_fta' => [72, 4, 391],
        'rl_3gm' => [76, 4, 127],
        'rl_3ga' => [80, 4, 331],
        'rl_orb' => [84, 4, 91],
        'rl_drb' => [88, 4, 253],
        'rl_ast' => [92, 4, 337],
        'rl_stl' => [96, 4, 83],
        'rl_tvr' => [100, 4, 149],
        'rl_blk' => [104, 4, 47],
        'rl_pf' => [108, 4, 171],
        'r_fga' => [555, 3, 21],
        'r_fgp' => [558, 3, 22],
        'r_fta' => [561, 3, 23],
        'r_ftp' => [564, 3, 24],
        'r_3ga' => [567, 3, 25],
        'r_3gp' => [570, 3, 26],
        'r_orb' => [573, 3, 31],
        'r_drb' => [576, 3, 32],
        'r_ast' => [579, 3, 33],
        'r_stl' => [582, 3, 34],
        'r_tvr' => [585, 3, 35],
        'r_blk' => [588, 3, 36],
        'oo' => [591, 2, 1],
        'r_drive_off' => [593, 2, 3],
        'po' => [595, 2, 5],
        'r_trans_off' => [597, 2, 7],
        'od' => [599, 2, 2],
        'dd' => [601, 2, 4],
        'pd' => [603, 2, 6],
        'td' => [605, 2, 8],
    ];

    /**
     * buildPlrFile() leaves most fields blank, so every compared column would round-trip
     * as 0 and a swapped column-to-parser-key pair would go unnoticed. This overlays a
     * distinct value on each field and a non-default position.
     *
     * @param list<array{pid: int, name: string, teamid: int, ordinal: int}> $players
     */
    private function buildDistinctFieldPlrFile(array $players): string
    {
        $path = $this->buildPlrFile($players);
        $data = file_get_contents($path);
        self::assertIsString($data);

        $lines = explode("\r\n", $data);
        foreach ($lines as &$line) {
            $line = substr_replace($line, 'SF', 50, 2);
            foreach (self::DISTINCT_FIELD_VALUES as [$offset, $width, $value]) {
                $line = substr_replace($line, str_pad((string) $value, $width, ' ', STR_PAD_LEFT), $offset, $width);
            }
        }
        unset($line);

        file_put_contents($path, implode("\r\n", $lines));

        return $path;
    }
}
