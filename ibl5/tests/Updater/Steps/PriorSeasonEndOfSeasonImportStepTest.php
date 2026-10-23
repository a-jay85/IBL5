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

    public function testSkipsWhenPriorSeasonImportsAlreadyRan(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('hasChampionForSeason')->willReturn(true);
        $repo->method('hasRetiredPlayersForYear')->willReturnMap([[2008, true]]);

        $service = $this->serviceMock();
        $service->expects(self::never())->method('processRetData');

        $result = $this->buildStep(repo: $repo, service: $service, resolver: $this->archiveResolver(2008))->execute();

        self::assertTrue($result->skipped);
        self::assertSame('Prior-season imports already ran for 2008', $result->detail);
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

    public function testReportsRejectedSourceInMessages(): void
    {
        $result = $this->buildStep(
            repo: $this->championRepo(),
            resolver: $this->diskResolver(),
        )->execute();

        self::assertContains('RET: skipped, source is not the 2008 archive', $result->messages);
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
