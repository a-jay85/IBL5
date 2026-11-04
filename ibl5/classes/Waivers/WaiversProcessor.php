<?php

declare(strict_types=1);

namespace Waivers;

use Discord\Discord;
use Player\Player;
use Player\Contract\PlayerContractCalculator;
use Season\Season;
use Player\PlayerDataConverter;
use Player\PlayerData;
use Waivers\Contracts\WaiversProcessorInterface;
use Waivers\Contracts\WaiversRepositoryInterface;
use Waivers\Contracts\WaiversValidatorInterface;

/**
 * @see WaiversProcessorInterface
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-import-type WaiverAudit from WaiversProcessorInterface
 * @phpstan-import-type WaiverNotification from WaiversProcessorInterface
 */
class WaiversProcessor implements WaiversProcessorInterface
{
    private const DISCORD_BUGS_CHANNEL_URL = 'https://discord.com/channels/666986450889474053/671435182502576169';
    private const NOTIFICATION_EMAIL_RECIPIENT = 'ibldepthcharts@gmail.com';
    private const NOTIFICATION_EMAIL_SENDER = 'waivers@iblhoops.net';
    private const WAIVER_POOL_MOVES_CATEGORY = 'Waiver Pool Moves';

    private PlayerContractCalculator $contractCalculator;
    private WaiversRepositoryInterface $repository;
    private \Repositories\Contracts\TeamIdentityRepositoryInterface $teamIdentityRepo;
    private \Repositories\Contracts\PlayerLookupRepositoryInterface $playerLookupRepo;
    private WaiversValidatorInterface $validator;
    private \Topics\News\NewsRepository $newsService;
    private \mysqli $db;

    /**
     * Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('audit').
     */
    private \Psr\Log\LoggerInterface $logger;
    /**
     * Optional injected Season. When null, methods fall back to SharedSeason::get($db) (one shared instance per web request when armed).
     */
    private ?Season $season = null;
    private \Clock\ClockInterface $clock;
    /** @var \Closure(): \Mail\Contracts\MailServiceInterface */
    private \Closure $mailerFactory;
    /** @var \Closure(string, string): void */
    private \Closure $discordPoster;

    /**
     * @param ?\Closure(): \Mail\Contracts\MailServiceInterface $mailerFactory Builds the mailer at send time; may throw. When null, uses MailService::fromConfig().
     * @param ?\Closure(string, string): void $discordPoster Posts one message to a channel; may throw. When null, uses Discord::postToChannel().
     */
    public function __construct(
        WaiversRepositoryInterface $repository,
        \Repositories\Contracts\TeamIdentityRepositoryInterface $teamIdentityRepo,
        \Repositories\Contracts\PlayerLookupRepositoryInterface $playerLookupRepo,
        WaiversValidatorInterface $validator,
        \Topics\News\NewsRepository $newsService,
        \mysqli $db,
        ?\Psr\Log\LoggerInterface $logger = null,
        ?Season $season = null,
        ?\Clock\ClockInterface $clock = null,
        ?\Closure $mailerFactory = null,
        ?\Closure $discordPoster = null
    ) {
        $this->repository = $repository;
        $this->teamIdentityRepo = $teamIdentityRepo;
        $this->playerLookupRepo = $playerLookupRepo;
        $this->validator = $validator;
        $this->newsService = $newsService;
        $this->db = $db;
        $this->contractCalculator = new PlayerContractCalculator();
        $this->logger = $logger ?? \Logging\LoggerFactory::getChannel('audit');
        $this->season = $season;
        $this->clock = $clock ?? new \Clock\SystemClock();
        $this->mailerFactory = $mailerFactory ?? \Mail\MailService::fromConfig(...);
        $this->discordPoster = $discordPoster ?? Discord::postToChannel(...);
    }

    /**
     * @see WaiversProcessorInterface::calculateVeteranMinimumSalary()
     */
    public function calculateVeteranMinimumSalary(int $experience): int
    {
        return \League\ContractRules::getVeteranMinimumSalary($experience);
    }

    /**
     * @see WaiversProcessorInterface::getPlayerContractDisplay()
     */
    public function getPlayerContractDisplay(Player $player, Season $season): string
    {
        $playerArray = [
            'cy' => $player->getContractCurrentYear(),
            'cyt' => $player->getContractTotalYears(),
            'salary_yr1' => $player->getContractYear1Salary(),
            'salary_yr2' => $player->getContractYear2Salary(),
            'salary_yr3' => $player->getContractYear3Salary(),
            'salary_yr4' => $player->getContractYear4Salary(),
            'salary_yr5' => $player->getContractYear5Salary(),
            'salary_yr6' => $player->getContractYear6Salary(),
            'exp' => $player->getYearsOfExperience(),
        ];
        $playerData = PlayerDataConverter::arrayToPlayerData($playerArray);

        if ($season->isOffseasonPhase()) {
            $currentSeasonSalary = $this->contractCalculator->getNextSeasonSalary($playerData);
            $experience = ($playerData->yearsOfExperience ?? 0) + 1;
        } else {
            $currentSeasonSalary = $this->contractCalculator->getCurrentSeasonSalary($playerData);
            $experience = $playerData->yearsOfExperience ?? 0;
        }

        if ($currentSeasonSalary === 0) {
            return (string) $this->calculateVeteranMinimumSalary($experience);
        }

        $remainingContract = $this->contractCalculator->getRemainingContractArray($playerData);
        return implode(" ", $remainingContract);
    }

    /**
     * @see WaiversProcessorInterface::determineContractData()
     *
     * @param PlayerRow $playerData
     * @return array{hasExistingContract: bool, salary: int}
     */
    public function determineContractData(array $playerData, Season $season): array
    {
        return $this->determineContractDataFromPlayer(
            PlayerDataConverter::arrayToPlayerData($playerData),
            $season
        );
    }

    /**
     * DTO-based worker for determineContractData(); processAdd() calls this
     * directly so one conversion serves the contract salary and the veteran minimum.
     *
     * @return array{hasExistingContract: bool, salary: int}
     */
    private function determineContractDataFromPlayer(PlayerData $playerDataObj, Season $season): array
    {
        if ($season->isOffseasonPhase()) {
            $currentSeasonSalary = $this->contractCalculator->getNextSeasonSalary($playerDataObj);
        } else {
            $currentSeasonSalary = $this->contractCalculator->getCurrentSeasonSalary($playerDataObj);
        }

        $hasExistingContract = $currentSeasonSalary > 0;

        if ($hasExistingContract) {
            return [
                'hasExistingContract' => true,
                'salary' => $currentSeasonSalary
            ];
        }

        $vetMinSalary = $this->resolveVeteranMinimum($playerDataObj, $season);

        return [
            'hasExistingContract' => false,
            'salary' => $vetMinSalary
        ];
    }

    /**
     * Single source for the veteran minimum, shared by the signed contract
     * (determineContractData) and the validator's over-cap check (processAdd).
     */
    private function resolveVeteranMinimum(PlayerData $playerDataObj, Season $season): int
    {
        return $this->calculateVeteranMinimumSalary($this->resolveContractExperience($playerDataObj, $season));
    }

    /**
     * Experience used for contract math: one extra year during the offseason phase.
     */
    private function resolveContractExperience(PlayerData $playerDataObj, Season $season): int
    {
        $yearsOfExperience = $playerDataObj->yearsOfExperience ?? 0;
        return $season->isOffseasonPhase() ? $yearsOfExperience + 1 : $yearsOfExperience;
    }

    /**
     * @see WaiversProcessorInterface::processDrop()
     *
     * @return array{success: bool, result?: string, error?: string, notification?: WaiverNotification}
     */
    public function processDrop(?int $playerID, string $teamName, int $rosterSlots, int $totalSalary): array
    {
        $dropValidation = $this->validator->validateDrop($rosterSlots, $totalSalary);
        if (!$dropValidation->isValid()) {
            return ['success' => false, 'error' => implode(' ', $dropValidation->getErrors())];
        }

        if ($playerID === null || $playerID === 0) {
            return ['success' => false, 'error' => "You didn't select a valid player. Please select a player and try again."];
        }

        $player = $this->playerLookupRepo->getPlayerByID($playerID);
        if ($player === null) {
            return ['success' => false, 'error' => 'Player not found.'];
        }

        $timestamp = $this->clock->now();

        if (!$this->repository->dropPlayerToWaivers($playerID, $timestamp)) {
            return ['success' => false, 'error' => 'Failed to drop player to waivers. Please try again.'];
        }

        $playerName = is_string($player['name'] ?? null) ? $player['name'] : '';
        $this->createWaiverNewsStory($teamName, $playerName, 'waive', '');

        $hometext = "The " . \Security\HtmlSanitizer::e($teamName) . " cut " . \Security\HtmlSanitizer::e($playerName) . " to waivers.";

        return [
            'success' => true,
            'result' => 'player_dropped',
            'notification' => [
                'mailSubject' => null,
                'message' => $hometext,
                'audit' => [
                    'event' => 'player_waived',
                    'context' => [
                        'action' => 'player_waived',
                        'player_id' => $playerID,
                        'player_name' => $playerName,
                        'team_name' => $teamName,
                    ],
                ],
            ],
        ];
    }

    /**
     * @see WaiversProcessorInterface::processAdd()
     *
     * @return array{success: bool, result?: string, error?: string, notification?: WaiverNotification}
     */
    public function processAdd(?int $playerID, string $teamName, int $openRosterSlots, int $healthyRosterSlots, int $totalSalary): array
    {
        if ($playerID === null || $playerID === 0) {
            return ['success' => false, 'error' => "You didn't select a valid player. Please select a player and try again."];
        }

        $player = $this->playerLookupRepo->getPlayerByID($playerID);
        if ($player === null) {
            return ['success' => false, 'error' => 'Player not found.'];
        }

        $season = $this->season ?? \Season\SharedSeason::get($this->db);
        $playerDataObj = PlayerDataConverter::arrayToPlayerData($player);
        $contractData = $this->determineContractDataFromPlayer($playerDataObj, $season);
        $playerSalary = $contractData['salary'];

        $veteranMinimum = $this->resolveVeteranMinimum($playerDataObj, $season);

        $addValidation = $this->validator->validateAdd(
            $playerID,
            $openRosterSlots,
            $healthyRosterSlots,
            $totalSalary,
            $playerSalary,
            $veteranMinimum
        );
        if (!$addValidation->isValid()) {
            return ['success' => false, 'error' => implode(' ', $addValidation->getErrors())];
        }

        $team = $this->teamIdentityRepo->getTeamByName($teamName);
        if ($team === null) {
            return ['success' => false, 'error' => 'Team not found.'];
        }

        if (!$this->repository->signPlayerFromWaivers($playerID, $team, $contractData)) {
            return ['success' => false, 'error' => "Oops, something went wrong. Post what you were trying to do in <A HREF=\"" . self::DISCORD_BUGS_CHANNEL_URL . "\">#site-bugs-and-to-do</A> and we'll fix it asap. Sorry!"];
        }

        $playerName = is_string($player['name'] ?? null) ? $player['name'] : '';
        $salaryStr = (string) $contractData['salary'];
        $this->createWaiverNewsStory($teamName, $playerName, 'add', $salaryStr);

        $storytitle = \Security\HtmlSanitizer::e($teamName) . " make waiver additions";
        $hometext = "The " . \Security\HtmlSanitizer::e($teamName) . " sign " . \Security\HtmlSanitizer::e($playerName) . " from waivers for " . \Security\HtmlSanitizer::e($salaryStr) . ".";

        return [
            'success' => true,
            'result' => 'player_added',
            'notification' => [
                'mailSubject' => $storytitle,
                'message' => $hometext,
                'audit' => [
                    'event' => 'player_signed_from_waivers',
                    'context' => [
                        'action' => 'player_signed_from_waivers',
                        'player_id' => $playerID,
                        'player_name' => $playerName,
                        'team_name' => $teamName,
                        'salary' => $playerSalary,
                    ],
                ],
            ],
        ];
    }

    /**
     * @see WaiversProcessorInterface::finishCommittedMove()
     */
    public function finishCommittedMove(array $notification): void
    {
        $mailSubject = $notification['mailSubject'];
        $message = $notification['message'];

        $this->writeAuditEntry($notification['audit']);

        if ($mailSubject !== null) {
            try {
                // Built inside the try: fromConfig() is where a mail config error surfaces.
                $mailer = ($this->mailerFactory)();
                if (!$mailer->send(self::NOTIFICATION_EMAIL_RECIPIENT, $mailSubject, $message, self::NOTIFICATION_EMAIL_SENDER)) {
                    $this->warnQuietly('waiver_mail_notification_failed', 'The mail service reported a failed send.');
                }
            } catch (\Throwable $mailErr) {
                $this->warnQuietly('waiver_mail_notification_failed', $mailErr->getMessage());
            }
        }

        try {
            ($this->discordPoster)('#waiver-wire', $message);
        } catch (\Throwable $discordErr) {
            $this->warnQuietly('waiver_discord_notification_failed', $discordErr->getMessage());
        }
    }

    /**
     * @param WaiverAudit $audit
     */
    private function writeAuditEntry(array $audit): void
    {
        try {
            $this->logger->info($audit['event'], $audit['context']);
        } catch (\Throwable $auditErr) {
            $this->warnQuietly('waiver_audit_log_failed', $auditErr->getMessage());
        }
    }

    private function warnQuietly(string $event, string $error): void
    {
        try {
            $this->logger->warning($event, ['error' => $error]);
        } catch (\Throwable) {
            // The move already committed, and the log is the only place left to report this.
        }
    }

    private function createWaiverNewsStory(string $teamName, string $playerName, string $action, string $contract): void
    {
        $this->newsService->incrementCategoryCounter(self::WAIVER_POOL_MOVES_CATEGORY);

        if ($action === 'waive') {
            $topicID = 32;
            $storytitle = \Security\HtmlSanitizer::e($teamName) . " make waiver cuts";
            $hometext = "The " . \Security\HtmlSanitizer::e($teamName) . " cut " . \Security\HtmlSanitizer::e($playerName) . " to waivers.";
        } else {
            $topicID = 33;
            $storytitle = \Security\HtmlSanitizer::e($teamName) . " make waiver additions";
            $hometext = "The " . \Security\HtmlSanitizer::e($teamName) . " sign " . \Security\HtmlSanitizer::e($playerName) . " from waivers for " . \Security\HtmlSanitizer::e($contract) . ".";
        }

        $categoryID = $this->newsService->getCategoryIDByTitle(self::WAIVER_POOL_MOVES_CATEGORY);
        if ($categoryID !== null) {
            $this->newsService->createNewsStory($categoryID, $topicID, $storytitle, $hometext);
        }
    }
}
