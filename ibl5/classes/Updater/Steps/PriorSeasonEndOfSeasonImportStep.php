<?php

declare(strict_types=1);

namespace Updater\Steps;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;
use JsbParser\JsbImportService;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\SourceProvenance;
use Updater\StepResult;

/**
 * Imports the prior season's end-of-season JSB files (.dra, .ret, .hof, .awa)
 * from that season's backup archive once its champion is recorded. Runs after
 * EndOfSeasonImportStep. IBL-only; registered inside the non-Olympics block.
 */
final class PriorSeasonEndOfSeasonImportStep implements PipelineStepInterface
{
    public function __construct(
        private readonly JsbImportRepositoryInterface $jsbRepo,
        private readonly JsbImportService $jsbService,
        private readonly int $priorYear,
        private readonly JsbSourceResolverInterface $priorSourceResolver,
    ) {
    }

    public function getLabel(): string
    {
        return 'Prior-season end-of-season imports';
    }

    public function execute(): StepResult
    {
        if (!$this->jsbRepo->hasChampionForSeason($this->priorYear)) {
            return StepResult::skipped($this->getLabel(), 'No champion recorded for ' . $this->priorYear);
        }

        if ($this->jsbRepo->hasRetiredPlayersForYear($this->priorYear)) {
            return StepResult::skipped($this->getLabel(), 'Prior-season imports already ran for ' . $this->priorYear);
        }

        $result = new JsbImportResult();
        /** @var list<string> $messages */
        $messages = [];

        $this->importDra($result, $messages);
        $this->importRet($result, $messages);
        $this->importHof($result, $messages);
        $this->importAwa($result, $messages);

        return StepResult::success(
            $this->getLabel(),
            $result->summary(),
            messages: $messages,
            messageErrorCount: $result->errors,
        );
    }

    /**
     * @param list<string> $messages
     */
    private function importDra(JsbImportResult $result, array &$messages): void
    {
        $data = $this->fetchArchived('dra', $messages);
        if ($data === null) {
            return;
        }

        $draResult = $this->jsbService->processDraData($data);
        $result->merge($draResult);
        $messages[] = 'DRA: ' . $draResult->summary();
    }

    /**
     * @param list<string> $messages
     */
    private function importRet(JsbImportResult $result, array &$messages): void
    {
        $data = $this->fetchArchived('ret', $messages);
        if ($data === null) {
            return;
        }

        $retResult = $this->jsbService->processRetData($data, $this->priorYear);
        $result->merge($retResult);
        $messages[] = 'RET: ' . $retResult->summary();
    }

    /**
     * @param list<string> $messages
     */
    private function importHof(JsbImportResult $result, array &$messages): void
    {
        $data = $this->fetchArchived('hof', $messages);
        if ($data === null) {
            return;
        }

        $hofResult = $this->jsbService->processHofData($data);
        $result->merge($hofResult);
        $messages[] = 'HOF: ' . $hofResult->summary();
    }

    /**
     * @param list<string> $messages
     */
    private function importAwa(JsbImportResult $result, array &$messages): void
    {
        $awaData = $this->fetchArchived('awa', $messages);
        $carData = $this->fetchArchived('car', $messages);
        if ($awaData === null || $carData === null) {
            return;
        }

        $awaResult = $this->jsbService->processAwaData($awaData, $carData, $this->priorYear);
        $result->merge($awaResult);
        $messages[] = 'AWA: ' . $awaResult->summary();
    }

    /**
     * Read a JSB file only when it came from the prior season's archive.
     *
     * The resolver is archive-first but falls back to the live disk file, which
     * after the Finals holds the new season's state. Importing that under the
     * prior year would write wrong-season rows, so anything but a prior-season
     * archive is rejected.
     *
     * @param list<string> $messages
     */
    private function fetchArchived(string $extension, array &$messages): ?string
    {
        $contents = $this->priorSourceResolver->getContents($extension);
        if ($contents === null) {
            return null;
        }

        $source = $this->priorSourceResolver->describeLastSource();
        if ($source === null || $source->kind !== SourceProvenance::KIND_ARCHIVE) {
            $messages[] = strtoupper($extension) . ': skipped, source is not the ' . $this->priorYear . ' archive';
            return null;
        }

        if ($source->declaredSeasonEndingYear !== null
            && $source->declaredSeasonEndingYear !== $this->priorYear) {
            $messages[] = strtoupper($extension) . ': skipped, archive declares season ' . $source->declaredSeasonEndingYear;
            return null;
        }

        return $contents;
    }
}
