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
    private SavedDepthChartService $savedDcService;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->repository = new DepthChartEntryRepository($db);
        $this->processor = new DepthChartEntryProcessor();
        $this->validator = new DepthChartEntryValidator();
        $this->savedDcService = new SavedDepthChartService($db);
    }

    /**
     * @see DepthChartEntrySubmissionHandlerInterface::handleSubmission()
     * @param array<string, mixed> $postData
     */
    public function handleSubmission(array $postData): bool
    {
        $season = new Season($this->db);

        /** @var string $rawTeamName */
        $rawTeamName = $postData['Team_Name'] ?? '';
        $teamName = $this->sanitizeInput($rawTeamName);

        if ($teamName === '') {
            $this->stashFailure(
                '<strong class="ibl-form-error">Error: Missing required team information.</strong>',
                $postData
            );
            return false;
        }

        /** @var ProcessedSubmission $processedData */
        $processedData = $this->processor->processSubmission($postData);

        if (!$this->validator->validate($processedData, $season->phase)) {
            $this->stashFailure($this->validator->getErrorMessagesHtml(), $postData);
            return false;
        }

        $this->saveDepthChart($processedData['playerData'], $teamName);

        $fileOk = $this->saveDepthChartFile($teamName, $processedData['playerData']);

        $this->saveDepthChartSnapshot($teamName, $postData, $season);

        if (!$fileOk) {
            $_SESSION['flash_success'] = 'Depth chart saved, but the file/email could not be sent. Please contact the commissioner.';
        }

        return true;
    }

    /**
     * Stash the submission failure for the redirected GET to re-render.
     *
     * Key: `$_SESSION['_ibl_depth_chart_flash']` — module-scoped so it doesn't
     * collide with the generic `flash_success` PageLayout already renders.
     *
     * @param array<string, mixed> $postData
     */
    private function stashFailure(string $errorsHtml, array $postData): void
    {
        $_SESSION['_ibl_depth_chart_flash'] = [
            'errors_html' => $errorsHtml,
            'post_data' => $postData,
        ];
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
     * @return bool True when file was written and email sent.
     */
    private function saveDepthChartFile(string $teamName, array $playerData): bool
    {
        $logger = \Logging\LoggerFactory::getChannel('app');

        $safeTeamName = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $teamName);
        if ($safeTeamName === null) {
            $logger->warning('DepthChartFile: invalid team name (regex null)', ['team' => $teamName]);
            return false;
        }
        $safeTeamName = str_replace(['..', '/', '\\'], '', $safeTeamName);

        if ($safeTeamName === '') {
            $logger->warning('DepthChartFile: sanitized team name is empty', ['team' => $teamName]);
            return false;
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
                return true;
            }
            $logger->error('DepthChartFile: write failed', ['team' => $teamName, 'path' => $filename]);
            return false;
        }

        $logger->error('DepthChartFile: path traversal guard rejected write', ['team' => $teamName, 'path' => $filename]);
        return false;
    }
}
