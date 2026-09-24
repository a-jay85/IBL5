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
        $plrPath = $this->buildPlrFile([
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
        $plrPath = $this->buildPlrFile([
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
}
