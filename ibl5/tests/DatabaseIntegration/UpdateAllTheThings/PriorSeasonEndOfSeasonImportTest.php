<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\UpdateAllTheThings;

use BulkImport\Contracts\ArchiveExtractorInterface;
use BulkImport\Contracts\BackupArchiveLocatorInterface;
use JsbParser\JsbImportRepository;
use JsbParser\JsbImportService;
use JsbParser\PlayerIdResolver;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\JsbSourceResolver;
use Updater\SourceProvenance;
use Updater\Steps\PriorSeasonEndOfSeasonImportStep;

/**
 * Database coverage for PriorSeasonEndOfSeasonImportStep against the real
 * repository, service and tables.
 */
#[Group('database')]
class PriorSeasonEndOfSeasonImportTest extends DatabaseTestCase
{
    private const PRIOR_YEAR = 2095;
    private const JSB_PID = 200000301;
    private const RET_DATA = "Prior Ret 200000301\n";

    private JsbImportRepository $repo;
    private JsbImportService $service;
    private string $tempDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new JsbImportRepository($this->db);
        $resolver = new PlayerIdResolver($this->db);
        $this->service = new JsbImportService($this->repo, $resolver);
    }

    protected function tearDown(): void
    {
        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files === false ? [] : $files as $file) {
                unlink($file);
            }
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function testPriorSeasonImportWritesRetireesForPriorYearAndFlagsPlayer(): void
    {
        $this->insertTestPlayer(self::JSB_PID, 'Prior Ret');
        $this->insertChampion(self::PRIOR_YEAR);

        $result = $this->buildStep($this->archiveResolver())->execute();

        self::assertTrue($result->success);
        self::assertSame(1, $this->countRetiredRows(self::JSB_PID, self::PRIOR_YEAR));
        self::assertSame(1, $this->fetchRetiredFlag(self::JSB_PID));
    }

    public function testSecondRunSkipsOnceRetireesExistForPriorYear(): void
    {
        $this->insertTestPlayer(self::JSB_PID, 'Prior Ret');
        $this->insertChampion(self::PRIOR_YEAR);

        $first = $this->buildStep($this->archiveResolver())->execute();
        self::assertFalse($first->skipped);

        $second = $this->buildStep($this->archiveResolver())->execute();

        self::assertTrue($second->skipped);
        self::assertSame('Prior-season imports already ran for 2095', $second->detail);
    }

    public function testRealResolverDiskFallbackWritesNothing(): void
    {
        $this->insertTestPlayer(self::JSB_PID, 'Prior Ret');
        $this->insertChampion(self::PRIOR_YEAR);

        $this->tempDir = sys_get_temp_dir() . '/prior-season-eos-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->tempDir));
        self::assertNotFalse(file_put_contents($this->tempDir . '/IBL5.ret', self::RET_DATA));

        $locator = self::createStub(BackupArchiveLocatorInterface::class);
        $locator->method('describeSelection')->willReturn(null);
        $extractor = self::createStub(ArchiveExtractorInterface::class);

        $resolver = new JsbSourceResolver($locator, $extractor, 'no-such-backup-dir', $this->tempDir, 'IBL5');

        $this->buildStep($resolver)->execute();

        self::assertSame(0, $this->countRetiredRows(self::JSB_PID, self::PRIOR_YEAR));
        self::assertSame(0, $this->fetchRetiredFlag(self::JSB_PID));
    }

    private function buildStep(JsbSourceResolverInterface $resolver): PriorSeasonEndOfSeasonImportStep
    {
        return new PriorSeasonEndOfSeasonImportStep($this->repo, $this->service, self::PRIOR_YEAR, $resolver);
    }

    private function archiveResolver(): JsbSourceResolverInterface
    {
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturnCallback(
            static fn (string $extension): ?string => $extension === 'ret' ? self::RET_DATA : null
        );
        $resolver->method('describeLastSource')->willReturn(new SourceProvenance(
            kind: SourceProvenance::KIND_ARCHIVE,
            name: 'IBL5-2094-95.zip',
            declaredSeasonEndingYear: self::PRIOR_YEAR,
        ));

        return $resolver;
    }

    private function insertChampion(int $seasonYear): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ibl_jsb_history (season_year, team_name, won_championship) VALUES (?, ?, ?)'
        );
        self::assertNotFalse($stmt);
        $teamName = 'Prior Champs';
        $won = 1;
        $stmt->bind_param('isi', $seasonYear, $teamName, $won);
        $stmt->execute();
        $stmt->close();
    }

    private function countRetiredRows(int $jsbPid, int $retirementYear): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM ibl_jsb_retired_players WHERE jsb_pid = ? AND retirement_year = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('ii', $jsbPid, $retirementYear);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row);

        return (int) $row['cnt'];
    }

    private function fetchRetiredFlag(int $pid): int
    {
        $stmt = $this->db->prepare('SELECT retired FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row);

        return (int) $row['retired'];
    }
}
