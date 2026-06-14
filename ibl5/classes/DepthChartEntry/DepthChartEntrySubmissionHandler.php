<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntrySubmissionHandlerInterface;
use SavedDepthChart\SavedDepthChartService;
use Season\Season;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * @phpstan-import-type ProcessedPlayerData from Contracts\DepthChartEntryProcessorInterface
 * @phpstan-import-type ProcessedSubmission from Contracts\DepthChartEntryProcessorInterface
 * @phpstan-import-type SubmissionResult from Contracts\DepthChartEntrySubmissionHandlerInterface
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
    private TeamIdentityRepositoryInterface $commonRepo;
    /** Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('audit'). */
    private \Psr\Log\LoggerInterface $auditLogger;
    /** Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('app'). */
    private \Psr\Log\LoggerInterface $appLogger;

    public function __construct(
        \mysqli $db,
        TeamIdentityRepositoryInterface $commonRepo,
        ?\Psr\Log\LoggerInterface $auditLogger = null,
        ?\Psr\Log\LoggerInterface $appLogger = null
    ) {
        $this->db = $db;
        $this->repository = new DepthChartEntryRepository($db);
        $this->processor = new DepthChartEntryProcessor();
        $this->validator = new DepthChartEntryValidator();
        $this->savedDcService = new SavedDepthChartService($db);
        $this->commonRepo = $commonRepo;
        $this->auditLogger = $auditLogger ?? \Logging\LoggerFactory::getChannel('audit');
        $this->appLogger = $appLogger ?? \Logging\LoggerFactory::getChannel('app');
    }

    /**
     * @see DepthChartEntrySubmissionHandlerInterface::handleSubmission()
     * @param array<string, mixed> $postData
     * @return array{success: bool, fileOk: bool, errorsHtml: string, postData: array<string, mixed>}
     */
    public function handleSubmission(array $postData): array
    {
        $season = new Season($this->db);

        /** @var string $rawTeamName */
        $rawTeamName = $postData['Team_Name'] ?? '';
        $teamName = $this->sanitizeInput($rawTeamName);

        if ($teamName === '') {
            return [
                'success' => false,
                'fileOk' => false,
                'errorsHtml' => '<strong class="ibl-form-error">Error: Missing required team information.</strong>',
                'postData' => $postData,
            ];
        }

        /** @var ProcessedSubmission $processedData */
        $processedData = $this->processor->processSubmission($postData);

        if (!$this->validator->validate($processedData, $season->phase)) {
            return [
                'success' => false,
                'fileOk' => false,
                'errorsHtml' => $this->validator->getErrorMessagesHtml(),
                'postData' => $postData,
            ];
        }

        $this->saveDepthChart($processedData['playerData'], $teamName);

        $fileOk = $this->saveDepthChartFile($teamName, $processedData['playerData']);

        $this->saveDepthChartSnapshot($teamName, $postData, $season);

        $this->auditLogger->info('depth_chart_submitted', [
            'action' => 'depth_chart_submitted',
            'team_name' => $teamName,
        ]);

        return [
            'success' => true,
            'fileOk' => $fileOk,
            'errorsHtml' => '',
            'postData' => [],
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
            $teamid = $this->commonRepo->getTidFromTeamname($teamName) ?? 0;
            if ($teamid === 0) {
                return;
            }

            // Resolve username from team name
            $username = $this->commonRepo->getUsernameFromTeamname($teamName) ?? '';
            if ($username === '') {
                return;
            }

            // Get roster players for snapshot (fresh from DB since they were just updated)
            $rosterPlayers = $this->repository->getPlayersOnTeam($teamid);

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
                $teamid,
                $username,
                $dcName,
                $rosterPlayers,
                $postData,
                $loadedDcId,
                $season
            );
        } catch (\RuntimeException $e) {
            // Don't fail the main submission if snapshot save fails
            $this->appLogger->error('SavedDepthChart snapshot error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param list<ProcessedPlayerData> $playerData
     * @return bool True when file was written and email sent.
     */
    private function saveDepthChartFile(string $teamName, array $playerData): bool
    {
        $logger = $this->appLogger;

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
