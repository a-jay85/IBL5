<?php

declare(strict_types=1);

namespace Tests\Updater\Steps;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;
use JsbParser\JsbImportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\SourceProvenance;
use Updater\Steps\PriorSeasonEndOfSeasonImportStep;

/**
 * @covers \Updater\Steps\PriorSeasonEndOfSeasonImportStep
 */
final class PriorSeasonEndOfSeasonImportStepTest extends TestCase
{
    private const PRIOR_YEAR = 2008;

    private JsbImportRepositoryInterface $stubRepo;
    private JsbImportService $stubService;
    private JsbSourceResolverInterface $stubResolver;

    protected function setUp(): void
    {
        $this->stubRepo = self::createStub(JsbImportRepositoryInterface::class);
        $this->stubService = self::createStub(JsbImportService::class);
        $this->stubResolver = self::createStub(JsbSourceResolverInterface::class);
    }

    public function testSkipsWhenPriorSeasonHasNoChampion(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('hasChampionForSeason')->willReturnMap([[2008, false]]);

        $result = $this->buildStep(repo: $repo)->execute();

        self::assertTrue($result->success);
        self::assertTrue($result->skipped);
        self::assertSame('No champion recorded for 2008', $result->detail);
    }

    public function testRunsImportsWhenPriorYearChampionExists(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('hasChampionForSeason')->willReturnMap([[2008, true], [2009, false]]);
        $repo->method('hasRetiredPlayersForYear')->willReturn(false);

        $service = $this->serviceMock();
        $service->expects(self::once())->method('processDraData');

        $result = $this->buildStep(repo: $repo, service: $service, resolver: $this->archiveResolver(2008))->execute();

        self::assertTrue($result->success);
        self::assertFalse($result->skipped);
    }

    public function testSkipsWhenEveryPriorSeasonImportAlreadyRan(): void
    {
        $service = self::createMock(JsbImportService::class);
        $service->expects(self::never())->method('processDraData');
        $service->expects(self::never())->method('processRetData');
        $service->expects(self::never())->method('processHofData');
        $service->expects(self::never())->method('processAwaData');

        $result = $this->buildStep(
            repo: $this->allImportedRepo(),
            service: $service,
            resolver: $this->fileResolver($this->realFiles()),
        )->execute();

        self::assertTrue($result->skipped);
        self::assertSame('Prior-season imports already ran for 2008', $result->detail);
    }

    /**
     * The draft check keys on the newest draft in the file (2006 here), not on
     * the prior season's year.
     */
    public function testImportsDraftWhenNewestArchivedDraftYearIsMissing(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('hasChampionForSeason')->willReturn(true);
        $repo->method('hasDraftResultsForYear')->willReturnMap([[2005, true], [2006, false]]);
        $repo->method('hasRetiredPlayersForYear')->willReturn(true);
        $repo->method('hasHofInducteesForYear')->willReturnMap([[2007, true]]);
        $repo->method('hasStatLeaderAwardsForYear')->willReturn(true);

        $service = $this->serviceMock();
        $service->expects(self::once())->method('processDraData');
        $service->expects(self::never())->method('processHofData');

        $this->buildStep(repo: $repo, service: $service, resolver: $this->fileResolver($this->realFiles()))->execute();
    }

    /**
     * The Hall of Fame check keys on the newest induction in the file (2007
     * here), so a year with no inductees does not force a nightly re-import.
     */
    public function testImportsHofWhenNewestArchivedInductionYearIsMissing(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('hasChampionForSeason')->willReturn(true);
        $repo->method('hasDraftResultsForYear')->willReturnMap([[2006, true]]);
        $repo->method('hasRetiredPlayersForYear')->willReturn(true);
        $repo->method('hasHofInducteesForYear')->willReturnMap([[2005, true], [2007, false]]);
        $repo->method('hasStatLeaderAwardsForYear')->willReturn(true);

        $service = $this->serviceMock();
        $service->expects(self::never())->method('processDraData');
        $service->expects(self::once())->method('processHofData');

        $this->buildStep(repo: $repo, service: $service, resolver: $this->fileResolver($this->realFiles()))->execute();
    }

    /**
     * A file missing from the archive is skipped without failing the step, and
     * the next run imports it once it is there.
     */
    public function testMissingArchiveFileIsSkippedThenRetriedNextRun(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('hasChampionForSeason')->willReturn(true);
        $repo->method('hasRetiredPlayersForYear')->willReturn(true);
        $repo->method('hasStatLeaderAwardsForYear')->willReturn(false);

        $files = $this->realFiles();
        $withoutAwa = $files;
        $withoutAwa['awa'] = null;

        $firstNight = $this->serviceMock();
        $firstNight->expects(self::never())->method('processAwaData');
        $first = $this->buildStep(repo: $repo, service: $firstNight, resolver: $this->fileResolver($withoutAwa))->execute();

        self::assertTrue($first->success);
        self::assertFalse($first->skipped);
        self::assertContains('AWA: not found in the 2008 archive, will retry next run', $first->messages);

        $secondNight = $this->serviceMock();
        $secondNight->expects(self::once())->method('processAwaData')->with($files['awa'], $files['car'], 2008);
        $second = $this->buildStep(repo: $repo, service: $secondNight, resolver: $this->fileResolver($files))->execute();

        self::assertTrue($second->success);
    }

    /**
     * Retirees landed on an earlier night but the awards file was missing then.
     * The awards import must still run.
     */
    public function testImportsAwardsWhenRetireesPresentButAwardsAbsent(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('hasChampionForSeason')->willReturn(true);
        $repo->method('hasRetiredPlayersForYear')->willReturnMap([[2008, true]]);

        $service = $this->serviceMock();
        $service->expects(self::never())->method('processRetData');
        $service->expects(self::once())->method('processAwaData')->with('data', 'data', 2008);

        $result = $this->buildStep(repo: $repo, service: $service, resolver: $this->archiveResolver(2008))->execute();

        self::assertFalse($result->skipped);
    }

    public function testForwardsPriorYearToRetAndAwaImports(): void
    {
        $service = $this->serviceMock();
        $service->expects(self::once())->method('processRetData')->with('data', 2008);
        $service->expects(self::once())->method('processAwaData')->with('data', 'data', 2008);

        $this->buildStep(
            repo: $this->championRepo(),
            service: $service,
            resolver: $this->archiveResolver(2008),
        )->execute();
    }

    public function testRejectsDiskFallbackSource(): void
    {
        $service = self::createMock(JsbImportService::class);
        $service->expects(self::never())->method('processDraData');
        $service->expects(self::never())->method('processRetData');
        $service->expects(self::never())->method('processHofData');
        $service->expects(self::never())->method('processAwaData');

        $this->buildStep(
            repo: $this->championRepo(),
            service: $service,
            resolver: $this->diskResolver(),
        )->execute();
    }

    public function testRejectsWhenProvenanceIsNull(): void
    {
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('data');
        $resolver->method('describeLastSource')->willReturn(null);

        $service = $this->serviceMock();
        $service->expects(self::never())->method('processRetData');

        $this->buildStep(repo: $this->championRepo(), service: $service, resolver: $resolver)->execute();
    }

    public function testRejectsArchiveDeclaringDifferentSeasonYear(): void
    {
        $service = $this->serviceMock();
        $service->expects(self::never())->method('processRetData');

        $this->buildStep(
            repo: $this->championRepo(),
            service: $service,
            resolver: $this->archiveResolver(2009),
        )->execute();
    }

    public function testImportsArchiveWithUnparsedSeasonYear(): void
    {
        $service = $this->serviceMock();
        $service->expects(self::once())->method('processRetData')->with('data', 2008);

        $this->buildStep(
            repo: $this->championRepo(),
            service: $service,
            resolver: $this->archiveResolver(null),
        )->execute();
    }

    /**
     * A rejected source surfaces its reason in StepResult::$messages.
     */
    public function testReportsRejectedSourceInMessages(): void
    {
        $result = $this->buildStep(
            repo: $this->championRepo(),
            resolver: $this->diskResolver(),
        )->execute();

        self::assertContains('RET: skipped, source is not the 2008 archive', $result->messages);
    }

    /**
     * An import error keeps its text, so the updater page shows it under the
     * file's summary line instead of a bare count.
     */
    public function testSurfacesImportErrorTextInMessages(): void
    {
        $hofResult = new JsbImportResult();
        $hofResult->addError('HOF parse failed: bad data');

        $service = self::createStub(JsbImportService::class);
        $service->method('processDraData')->willReturn(new JsbImportResult());
        $service->method('processRetData')->willReturn(new JsbImportResult());
        $service->method('processHofData')->willReturn($hofResult);
        $service->method('processAwaData')->willReturn(new JsbImportResult());

        $result = $this->buildStep(
            repo: $this->championRepo(),
            service: $service,
            resolver: $this->archiveResolver(2008),
        )->execute();

        $hofLine = array_search('HOF: 1 errors', $result->messages, true);
        self::assertIsInt($hofLine);
        self::assertSame('ERROR: HOF parse failed: bad data', $result->messages[$hofLine + 1]);
        self::assertSame(1, $result->messageErrorCount);
    }

    private function buildStep(
        ?JsbImportRepositoryInterface $repo = null,
        ?JsbImportService $service = null,
        ?JsbSourceResolverInterface $resolver = null,
    ): PriorSeasonEndOfSeasonImportStep {
        return new PriorSeasonEndOfSeasonImportStep(
            $repo ?? $this->stubRepo,
            $service ?? $this->stubService,
            self::PRIOR_YEAR,
            $resolver ?? $this->stubResolver,
        );
    }

    private function championRepo(): JsbImportRepositoryInterface
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('hasChampionForSeason')->willReturn(true);
        $repo->method('hasRetiredPlayersForYear')->willReturn(false);

        return $repo;
    }

    /**
     * Mock whose four import methods each return an empty result, so a test
     * adds expects() only for the call it cares about.
     *
     * @return JsbImportService&MockObject
     */
    private function serviceMock(): JsbImportService&MockObject
    {
        $service = self::createMock(JsbImportService::class);
        $service->method('processDraData')->willReturn(new JsbImportResult());
        $service->method('processRetData')->willReturn(new JsbImportResult());
        $service->method('processHofData')->willReturn(new JsbImportResult());
        $service->method('processAwaData')->willReturn(new JsbImportResult());

        return $service;
    }

    private function archiveResolver(?int $declaredYear): JsbSourceResolverInterface
    {
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('data');
        $resolver->method('describeLastSource')->willReturn(new SourceProvenance(
            kind: SourceProvenance::KIND_ARCHIVE,
            name: 'IBL5-2007-08-finals.zip',
            declaredSeasonEndingYear: $declaredYear,
        ));

        return $resolver;
    }

    private function allImportedRepo(): JsbImportRepositoryInterface
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('hasChampionForSeason')->willReturn(true);
        $repo->method('hasDraftResultsForYear')->willReturnMap([[2006, true]]);
        $repo->method('hasRetiredPlayersForYear')->willReturnMap([[2008, true]]);
        $repo->method('hasHofInducteesForYear')->willReturnMap([[2007, true]]);
        $repo->method('hasStatLeaderAwardsForYear')->willReturnMap([[2008, true]]);

        return $repo;
    }

    /**
     * Archive contents shaped like a real 2007-08 archive: the newest draft is
     * 2006 and the newest Hall of Fame induction is 2007.
     *
     * @return array<string, ?string>
     */
    private function realFiles(): array
    {
        $dra = "***** 2005 rookie draft *****\nRound 1 Pick 1\nLakers: SF John Doe\n"
            . "***** 2006 rookie draft *****\nRound 1 Pick 1\nCeltics: PG Jim Roe\n";
        $hof = str_pad("SF Grant Hill 614 2005 \r\n C Arvydas Sabonis 327 2007 \r\n", 8000);

        return ['dra' => $dra, 'ret' => 'ret-data', 'hof' => $hof, 'awa' => 'awa-data', 'car' => 'car-data'];
    }

    /**
     * Archive resolver that serves each extension's contents; null means the
     * file is missing from the archive.
     *
     * @param array<string, ?string> $files
     */
    private function fileResolver(array $files): JsbSourceResolverInterface
    {
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturnCallback(
            static fn (string $extension): ?string => $files[$extension] ?? null,
        );
        $resolver->method('describeLastSource')->willReturn(new SourceProvenance(
            kind: SourceProvenance::KIND_ARCHIVE,
            name: 'IBL5-2007-08-finals.zip',
            declaredSeasonEndingYear: 2008,
        ));

        return $resolver;
    }

    private function diskResolver(): JsbSourceResolverInterface
    {
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn('data');
        $resolver->method('describeLastSource')->willReturn(new SourceProvenance(
            kind: SourceProvenance::KIND_DISK,
            name: 'IBL5.ret',
        ));

        return $resolver;
    }
}
