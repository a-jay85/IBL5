<?php

declare(strict_types=1);

namespace Updater\Steps;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\DraFileParser;
use JsbParser\HofFileParser;
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
 *
 * Each file has its own "already imported?" check against its own table, so a
 * file missing from the archive on one night is retried on a later night.
 * Every import is an upsert, so a re-run writes no duplicate rows.
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

        $result = new JsbImportResult();
        /** @var list<string> $messages */
        $messages = [];

        $draDone = $this->importDra($result, $messages);
        $retDone = $this->importRet($result, $messages);
        $hofDone = $this->importHof($result, $messages);
        $awaDone = $this->importAwa($result, $messages);

        if ($draDone && $retDone && $hofDone && $awaDone) {
            return StepResult::skipped($this->getLabel(), 'Prior-season imports already ran for ' . $this->priorYear);
        }

        return StepResult::success(
            $this->getLabel(),
            $result->summary(),
            messages: $messages,
            messageErrorCount: $result->errors,
        );
    }

    /**
     * The .dra file holds every draft, and its newest draft year is not the
     * prior season's year, so the check keys on the newest year in the file.
     *
     * @param list<string> $messages
     * @return bool True when the newest draft in the archive is already stored
     */
    private function importDra(JsbImportResult $result, array &$messages): bool
    {
        $data = $this->fetchArchived('dra', $messages);
        if ($data === null) {
            return false;
        }

        $newestYear = self::newestYear(array_column(DraFileParser::parse($data), 'draft_year'));
        if ($newestYear !== null && $this->jsbRepo->hasDraftResultsForYear($newestYear)) {
            return true;
        }

        $draResult = $this->jsbService->processDraData($data);
        $result->merge($draResult);
        array_push($messages, 'DRA: ' . $draResult->summary(), ...$draResult->messages);

        return false;
    }

    /**
     * @param list<string> $messages
     * @return bool True when the prior season's retirees are already stored
     */
    private function importRet(JsbImportResult $result, array &$messages): bool
    {
        if ($this->jsbRepo->hasRetiredPlayersForYear($this->priorYear)) {
            return true;
        }

        $data = $this->fetchArchived('ret', $messages);
        if ($data === null) {
            return false;
        }

        $retResult = $this->jsbService->processRetData($data, $this->priorYear);
        $result->merge($retResult);
        array_push($messages, 'RET: ' . $retResult->summary(), ...$retResult->messages);

        return false;
    }

    /**
     * The .hof file holds every induction and some years have none, so the
     * check keys on the newest induction year in the file.
     *
     * @param list<string> $messages
     * @return bool True when the newest induction in the archive is already stored
     */
    private function importHof(JsbImportResult $result, array &$messages): bool
    {
        $data = $this->fetchArchived('hof', $messages);
        if ($data === null) {
            return false;
        }

        try {
            $newestYear = self::newestYear(array_column(HofFileParser::parse($data), 'induction_year'));
        } catch (\RuntimeException) {
            // Unparseable: let processHofData run and report the parse error.
            $newestYear = null;
        }

        if ($newestYear !== null && $this->jsbRepo->hasHofInducteesForYear($newestYear)) {
            return true;
        }

        $hofResult = $this->jsbService->processHofData($data);
        $result->merge($hofResult);
        array_push($messages, 'HOF: ' . $hofResult->summary(), ...$hofResult->messages);

        return false;
    }

    /**
     * @param list<string> $messages
     * @return bool True when the prior season's stat-leader awards are already stored
     */
    private function importAwa(JsbImportResult $result, array &$messages): bool
    {
        if ($this->jsbRepo->hasStatLeaderAwardsForYear($this->priorYear)) {
            return true;
        }

        $awaData = $this->fetchArchived('awa', $messages);
        $carData = $this->fetchArchived('car', $messages);
        if ($awaData === null || $carData === null) {
            return false;
        }

        $awaResult = $this->jsbService->processAwaData($awaData, $carData, $this->priorYear);
        $result->merge($awaResult);
        array_push($messages, 'AWA: ' . $awaResult->summary(), ...$awaResult->messages);

        return false;
    }

    /**
     * @param list<int> $years
     */
    private static function newestYear(array $years): ?int
    {
        return $years === [] ? null : max($years);
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
            $messages[] = strtoupper($extension) . ': not found in the ' . $this->priorYear . ' archive, will retry next run';
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
