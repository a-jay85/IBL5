<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntrySubmissionHandlerInterface;
use SavedDepthChart\SavedDepthChartService;
use Season\Season;

/**
 * @phpstan-import-type ProcessedPlayerData from Contracts\DepthChartEntryProcessorInterface
 * @phpstan-import-type ProcessedSubmission from Contracts\DepthChartEntryProcessorInterface
 *
 * @see DepthChartEntrySubmissionHandlerInterface
 */
class DepthChartEntrySubmissionHandler implements DepthChartEntrySubmissionHandlerInterface
{
    private \mysqli $db;
    private DepthChartEntryRepository $repository;
    private DepthChartEntryProcessor $processor;
    private DepthChartEntryValidator $validator;
    private DepthChartEntryView $view;
    private SavedDepthChartService $savedDcService;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->repository = new DepthChartEntryRepository($db);
        $this->processor = new DepthChartEntryProcessor();
        $this->validator = new DepthChartEntryValidator();
        $this->view = new DepthChartEntryView();
        $this->savedDcService = new SavedDepthChartService($db);
    }

    /**
     * @see DepthChartEntrySubmissionHandlerInterface::handleSubmission()
     * @param array<string, mixed> $postData
     */
    public function handleSubmission(array $postData): void
    {
        $season = new Season($this->db);

        /** @var string $rawTeamName */
        $rawTeamName = $postData['Team_Name'] ?? '';
        $teamName = $this->sanitizeInput($rawTeamName);

        if ($teamName === '') {
            echo '<strong class="ibl-form-error">Error: Missing required team information.</strong>';
            return;
        }

        /** @var ProcessedSubmission $processedData */
        $processedData = $this->processor->processSubmission($postData);

        $isValid = $this->validator->validate($processedData, $season->phase);

        if (!$isValid) {
            $errorHtml = $this->validator->getErrorMessagesHtml();
            $this->view->renderSubmissionResult($teamName, $processedData['playerData'], false, $errorHtml);
            return;
        }

        $this->saveDepthChart($processedData['playerData'], $teamName);

        $this->saveDepthChartFile($teamName, $processedData['playerData']);

        // Save depth chart snapshot
        $this->saveDepthChartSnapshot($teamName, $postData, $season);

        $this->view->renderSubmissionResult($teamName, $processedData['playerData'], true);
    }

    private function sanitizeInput(string $input): string
    {
        return trim(strip_tags($input));
    }

    /**
     * @param list<ProcessedPlayerData> $playerData
     */
    private function saveDepthChart(array $playerData, string $teamName): void
    {
        foreach ($playerData as $player) {
            $this->repository->updatePlayerDepthChart($player['name'], $player);
        }

        $this->repository->updateTeamHistory($teamName);
    }

    /**
     * Save depth chart snapshot for historical tracking
     *
     * @param array<string, mixed> $postData
     */
    private function saveDepthChartSnapshot(string $teamName, array $postData, Season $season): void
    {
        try {
            $commonRepo = new \Services\CommonMysqliRepository($this->db);
            $tid = $commonRepo->getTidFromTeamname($teamName) ?? 0;
            if ($tid === 0) {
                return;
            }

            // Resolve username from team name
            $username = $commonRepo->getUsernameFromTeamname($teamName) ?? '';
            if ($username === '') {
                return;
            }

            // Get roster players for snapshot (fresh from DB since they were just updated)
            $rosterPlayers = $this->repository->getPlayersOnTeam($tid);

            $loadedDcId = 0;
            $rawLoadedDcId = $postData['loaded_dc_id'] ?? '0';
            if (is_string($rawLoadedDcId) && is_numeric($rawLoadedDcId)) {
                $loadedDcId = (int) $rawLoadedDcId;
            } elseif (is_int($rawLoadedDcId)) {
                $loadedDcId = $rawLoadedDcId;
            }

            $dcName = null;
            $rawDcName = $postData['dc_name'] ?? null;
            if (is_string($rawDcName) && trim($rawDcName) !== '') {
                $dcName = trim(strip_tags($rawDcName));
            }

            $this->savedDcService->saveOnSubmit(
                $tid,
                $username,
                $dcName,
                $rosterPlayers,
                $postData,
                $loadedDcId,
                $season
            );
        } catch (\RuntimeException $e) {
            // Don't fail the main submission if snapshot save fails
            \Logging\LoggerFactory::getChannel('app')->error('SavedDepthChart snapshot error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param list<ProcessedPlayerData> $playerData
     */
    private function saveDepthChartFile(string $teamName, array $playerData): void
    {
        $safeTeamName = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $teamName);
        if ($safeTeamName === null) {
            echo '<strong class="ibl-form-error">Invalid team name for file creation.</strong>';
            return;
        }
        $safeTeamName = str_replace(['..', '/', '\\'], '', $safeTeamName);

        if ($safeTeamName === '') {
            echo '<strong class="ibl-form-error">Invalid team name for file creation.</strong>';
            return;
        }

        $csvContent = $this->processor->generateCsvContent($playerData);
        $filename = 'depthcharts/' . $safeTeamName . '.txt';

        $convertedContent = iconv('UTF-8', 'WINDOWS-1252//TRANSLIT', $csvContent);
        if ($convertedContent === false) {
            $convertedContent = $csvContent;
        }

        $realPath = realpath(dirname($filename));
        $expectedPath = realpath('depthcharts');

        if ($realPath !== false && $expectedPath !== false && strpos($realPath, $expectedPath) === 0) {
            $bytesWritten = file_put_contents($filename, $convertedContent);
            if ($bytesWritten !== false && $bytesWritten > 0) {
                \Mail\MailService::fromConfig()->send('ibldepthcharts@gmail.com', $teamName . " Depth Chart", $convertedContent, 'ibldepthcharts@gmail.com');
            } else {
                echo '<strong class="ibl-form-error">Depth chart failed to save properly; please contact the commissioner.</strong>';
            }
        } else {
            echo '<strong class="ibl-form-error">Invalid file path detected. Please contact the commissioner.</strong>';
        }
    }
}
