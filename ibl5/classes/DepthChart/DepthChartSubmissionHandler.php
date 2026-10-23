<?php

declare(strict_types=1);

namespace DepthChart;

use DepthChart\Contracts\DepthChartSubmissionHandlerInterface;
use DepthChartSnapshot\DepthChartSnapshotService;
use Season\Season;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Validation\ValidationResultWithContext;

/**
 * @phpstan-import-type ProcessedPlayerData from Contracts\DepthChartProcessorInterface
 * @phpstan-import-type ProcessedSubmission from Contracts\DepthChartProcessorInterface
 * @phpstan-import-type SubmissionResult from Contracts\DepthChartSubmissionHandlerInterface
 *
 * @see DepthChartSubmissionHandlerInterface
 */
class DepthChartSubmissionHandler implements DepthChartSubmissionHandlerInterface
{
    private \mysqli $db;
    private DepthChartRepository $repository;
    private DepthChartProcessor $processor;
    private DepthChartValidator $validator;
    private DepthChartSnapshotService $savedDcService;
    private TeamIdentityRepositoryInterface $commonRepo;
    /** Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('audit'). */
    private \Psr\Log\LoggerInterface $auditLogger;
    /** Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('app'). */
    private \Psr\Log\LoggerInterface $appLogger;
    /**
     * Optional injected Season. When null, methods fall back to SharedSeason::get($db) (one shared instance per web request when armed).
     */
    private ?Season $season = null;

    public function __construct(
        \mysqli $db,
        TeamIdentityRepositoryInterface $commonRepo,
        ?\Psr\Log\LoggerInterface $auditLogger = null,
        ?\Psr\Log\LoggerInterface $appLogger = null,
        ?Season $season = null
    ) {
        $this->db = $db;
        $this->season = $season;
        $this->repository = new DepthChartRepository($db);
        $this->processor = new DepthChartProcessor();
        $this->validator = new DepthChartValidator();
        $this->savedDcService = new DepthChartSnapshotService($db);
        $this->commonRepo = $commonRepo;
        $this->auditLogger = $auditLogger ?? \Logging\LoggerFactory::getChannel('audit');
        $this->appLogger = $appLogger ?? \Logging\LoggerFactory::getChannel('app');
    }

    /**
     * @see DepthChartSubmissionHandlerInterface::handleSubmission()
     * @param array<string, mixed> $postData
     * @param string $sessionUsername Authenticated username; the write target is derived from it.
     * @return array{success: bool, fileOk: bool, errorsHtml: string, postData: array<string, mixed>}
     */
    public function handleSubmission(array $postData, string $sessionUsername): array
    {
        $season = $this->season ?? \Season\SharedSeason::get($this->db);

        $target = $this->authorizeSessionTeam($sessionUsername);
        if ($target === null) {
            return $this->missingTeamResult($postData);
        }
        [$teamName, $teamid] = $target;

        $rosterPlayers = $this->repository->getPlayersOnTeam($teamid);

        $parsed = $this->parseAndValidate($postData, $rosterPlayers, $season->phase);
        if ($parsed['rejection'] !== null) {
            return [
                'success' => false,
                'fileOk' => false,
                'errorsHtml' => DepthChartErrorHtmlRenderer::render($parsed['rejection']->getErrors()),
                'postData' => $postData,
            ];
        }

        $fileOk = $this->persist($parsed['processed'], $teamName, $teamid, $postData, $season);

        return [
            'success' => true,
            'fileOk' => $fileOk,
            'errorsHtml' => '',
            'postData' => [],
        ];
    }

    /**
     * Derive the authoritative team from the session, not POST `Team_Name`
     * (IDOR fix D-09). A logged-in GM cannot replay a valid CSRF token with
     * another team's name — the write target is always their own team.
     *
     * Session-derived team id keys every write; an unresolvable id is refused
     * before any read of ibl_plr so a misconfigured team cannot reach the roster query.
     *
     * @return array{0: string, 1: int}|null [teamName, teamid]; null when the session cannot be tied to a real team
     */
    private function authorizeSessionTeam(string $sessionUsername): ?array
    {
        $sessionTeam = $this->commonRepo->getTeamnameFromUsername($sessionUsername);
        if ($sessionTeam === null || $sessionTeam === '' || $sessionTeam === \League\League::FREE_AGENTS_TEAM_NAME) {
            return null;
        }

        $teamName = $this->sanitizeInput($sessionTeam);

        if ($teamName === '') {
            return null;
        }

        $teamid = $this->commonRepo->getTidFromTeamname($teamName) ?? 0;
        if ($teamid === 0) {
            return null;
        }

        return [$teamName, $teamid];
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{success: bool, fileOk: bool, errorsHtml: string, postData: array<string, mixed>}
     */
    private function missingTeamResult(array $postData): array
    {
        return [
            'success' => false,
            'fileOk' => false,
            'errorsHtml' => '<strong class="ibl-form-error">Error: Missing required team information.</strong>',
            'postData' => $postData,
        ];
    }

    /**
     * Parse the form rows, then run roster membership before phase rules;
     * the first failing result is returned so its errors render as before.
     *
     * @param array<string, mixed> $postData
     * @param list<array<string, mixed>> $rosterPlayers
     * @return array{processed: ProcessedSubmission, rejection: ValidationResultWithContext<null>|null}
     */
    private function parseAndValidate(array $postData, array $rosterPlayers, string $phase): array
    {
        /** @var list<int> $rosterPids */
        $rosterPids = array_column($rosterPlayers, 'pid');

        // The form emits one row per roster player, so the roster size is the row cap.
        /** @var ProcessedSubmission $processedData */
        $processedData = $this->processor->processSubmission($postData, count($rosterPlayers));
        /** @var list<int> $submittedPids */
        $submittedPids = array_column($processedData['playerData'], 'pid');

        $rosterResult = $this->validator->validateRoster($submittedPids, $rosterPids);
        if (!$rosterResult->isValid()) {
            return ['processed' => $processedData, 'rejection' => $rosterResult];
        }

        $validation = $this->validator->validate($processedData, $phase);
        if (!$validation->isValid()) {
            return ['processed' => $processedData, 'rejection' => $validation];
        }

        return ['processed' => $processedData, 'rejection' => null];
    }

    /**
     * All writes, in the original order; only reached after every check passed.
     *
     * @param ProcessedSubmission $processedData
     * @param array<string, mixed> $postData
     * @return bool whether the depth chart file was written
     */
    private function persist(array $processedData, string $teamName, int $teamid, array $postData, Season $season): bool
    {
        $this->saveDepthChart($processedData['playerData'], $teamName, $teamid);

        $fileOk = $this->saveDepthChartFile($teamName, $processedData['playerData']);

        $this->saveDepthChartSnapshot($teamName, $postData, $season, $teamid);

        $this->auditLogger->info('depth_chart_submitted', [
            'action' => 'depth_chart_submitted',
            'team_name' => $teamName,
        ]);

        return $fileOk;
    }

    private function sanitizeInput(string $input): string
    {
        return trim(strip_tags($input));
    }

    /**
     * @param list<ProcessedPlayerData> $playerData
     */
    private function saveDepthChart(array $playerData, string $teamName, int $teamid): void
    {
        foreach ($playerData as $player) {
            $this->repository->updatePlayerDepthChart($player['pid'], $teamid, $player);
        }

        $this->repository->updateTeamHistory($teamName);
    }

    /**
     * Save depth chart snapshot for historical tracking
     *
     * @param array<string, mixed> $postData
     */
    private function saveDepthChartSnapshot(string $teamName, array $postData, Season $season, int $teamid): void
    {
        try {
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
