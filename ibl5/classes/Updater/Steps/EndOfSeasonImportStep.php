<?php

declare(strict_types=1);

namespace Updater\Steps;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;
use JsbParser\JsbImportService;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Step 12: End-of-season imports (.dra, .ret, .hof, .awa).
 *
 * Runs after ParseJsbFilesStep. Checks if a champion has been determined
 * for the current season. If so, processes additional JSB files that are
 * only meaningful at season end. Skipped when no champion exists yet.
 *
 * PLR snapshot creation was moved to SnapshotPlrStep (ADR-0006).
 *
 * IBL-only — Olympics league does not use this step.
 */
final class EndOfSeasonImportStep implements PipelineStepInterface
{
    public function __construct(
        private readonly JsbImportRepositoryInterface $jsbRepo,
        private readonly JsbImportService $jsbService,
        private readonly int $seasonEndingYear,
        private readonly JsbSourceResolverInterface $sourceResolver,
        private readonly bool $hasFinalsMvp = true,
    ) {
    }

    public function getLabel(): string
    {
        return 'End-of-season imports';
    }

    public function execute(): StepResult
    {
        if (!$this->jsbRepo->hasChampionForSeason($this->seasonEndingYear)) {
            return StepResult::skipped($this->getLabel(), 'No champion determined yet');
        }

        $result = new JsbImportResult();
        /** @var list<string> $messages */
        $messages = [];

        $this->importDra($result, $messages);
        $this->importRet($result, $messages);
        $this->importHof($result, $messages);
        $this->importAwa($result, $messages);

        $inlineHtml = $this->renderFinalsMvpCard();

        return StepResult::success(
            $this->getLabel(),
            $result->summary(),
            inlineHtml: $inlineHtml,
            messages: $messages,
            messageErrorCount: $result->errors,
        );
    }

    /**
     * @param list<string> $messages
     */
    private function importDra(JsbImportResult $result, array &$messages): void
    {
        $data = $this->sourceResolver->getContents('dra');
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
        $data = $this->sourceResolver->getContents('ret');
        if ($data === null) {
            return;
        }

        $retResult = $this->jsbService->processRetData($data, $this->seasonEndingYear);
        $result->merge($retResult);
        $messages[] = 'RET: ' . $retResult->summary();
    }

    /**
     * @param list<string> $messages
     */
    private function importHof(JsbImportResult $result, array &$messages): void
    {
        $data = $this->sourceResolver->getContents('hof');
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
        $awaData = $this->sourceResolver->getContents('awa');
        $carData = $this->sourceResolver->getContents('car');
        if ($awaData === null || $carData === null) {
            return;
        }

        $awaResult = $this->jsbService->processAwaData($awaData, $carData, $this->seasonEndingYear);
        $result->merge($awaResult);
        $messages[] = 'AWA: ' . $awaResult->summary();
    }

    private function renderFinalsMvpCard(): string
    {
        if ($this->hasFinalsMvp) {
            ob_start();
            ?>
<div class="ibl-card sco-parse-result">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">IBL Finals MVP</h2>
    </div>
    <div class="ibl-card__body">
        <span class="step-result__icon step-result__icon--ok">✔</span> Finals MVP already recorded
    </div>
</div>
            <?php
            return (string) ob_get_clean();
        }

        ob_start();
        ?>
<div class="ibl-card sco-parse-result">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">IBL Finals MVP</h2>
    </div>
    <div class="ibl-card__body">
        <form method="POST" action="/ibl5/leagueControlPanel.php">
            <input type="text" name="finals_mvp_name" maxlength="32" placeholder="Finals MVP name" class="ibl-input ibl-input--sm">
            <button type="submit" name="action" value="set_finals_mvp" class="ibl-btn ibl-btn--primary">Set Finals MVP</button>
        </form>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
