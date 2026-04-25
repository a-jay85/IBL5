<?php

declare(strict_types=1);

namespace Waivers;

use Discord\Discord;
use Player\Player;
use Player\PlayerContractCalculator;
use Season\Season;
use Services\PlayerDataConverter;
use Waivers\Contracts\WaiversProcessorInterface;
use Waivers\Contracts\WaiversRepositoryInterface;
use Waivers\Contracts\WaiversValidatorInterface;

/**
 * @see WaiversProcessorInterface
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class WaiversProcessor implements WaiversProcessorInterface
{
    private const DISCORD_BUGS_CHANNEL_URL = 'https://discord.com/channels/666986450889474053/671435182502576169';
    private const NOTIFICATION_EMAIL_RECIPIENT = 'ibldepthcharts@gmail.com';
    private const NOTIFICATION_EMAIL_SENDER = 'waivers@iblhoops.net';
    private const WAIVER_POOL_MOVES_CATEGORY = 'Waiver Pool Moves';

    private PlayerContractCalculator $contractCalculator;
    private WaiversRepositoryInterface $repository;
    private \Services\CommonMysqliRepository $commonRepository;
    private WaiversValidatorInterface $validator;
    private \Services\NewsService $newsService;
    private \mysqli $db;

    public function __construct(
        WaiversRepositoryInterface $repository,
        \Services\CommonMysqliRepository $commonRepository,
        WaiversValidatorInterface $validator,
        \Services\NewsService $newsService,
        \mysqli $db
    ) {
        $this->repository = $repository;
        $this->commonRepository = $commonRepository;
        $this->validator = $validator;
        $this->newsService = $newsService;
        $this->db = $db;
        $this->contractCalculator = new PlayerContractCalculator();
    }

    /**
     * @see WaiversProcessorInterface::calculateVeteranMinimumSalary()
     */
    public function calculateVeteranMinimumSalary(int $experience): int
    {
        return \ContractRules::getVeteranMinimumSalary($experience);
    }

    /**
     * @see WaiversProcessorInterface::getPlayerContractDisplay()
     */
    public function getPlayerContractDisplay(Player $player, Season $season): string
    {
        $playerArray = [
            'cy' => $player->contractCurrentYear,
            'cyt' => $player->contractTotalYears,
            'salary_yr1' => $player->contractYear1Salary,
            'salary_yr2' => $player->contractYear2Salary,
            'salary_yr3' => $player->contractYear3Salary,
            'salary_yr4' => $player->contractYear4Salary,
            'salary_yr5' => $player->contractYear5Salary,
            'salary_yr6' => $player->contractYear6Salary,
            'exp' => $player->yearsOfExperience,
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
     * @see WaiversProcessorInterface::getWaiverWaitTime()
     */
    public function getWaiverWaitTime(int $dropTime, int $currentTime): string
    {
        $timeDiff = $currentTime - $dropTime;
        $waitPeriod = 86400; // 24 hours in seconds

        if ($timeDiff >= $waitPeriod) {
            return '';
        }

        $remainingTime = $waitPeriod - $timeDiff;
        $hours = floor($remainingTime / 3600);
        $minutes = floor(($remainingTime - $hours * 3600) / 60);
        $seconds = $remainingTime % 60;

        return "(Clears in $hours h, $minutes m, $seconds s)";
    }

    /**
     * @see WaiversProcessorInterface::determineContractData()
     *
     * @param PlayerRow $playerData
     * @return array{hasExistingContract: bool, salary: int}
     */
    public function determineContractData(array $playerData, Season $season): array
    {
        $playerDataObj = PlayerDataConverter::arrayToPlayerData($playerData);

        if ($season->isOffseasonPhase()) {
            $currentSeasonSalary = $this->contractCalculator->getNextSeasonSalary($playerDataObj);
            $experience = ($playerDataObj->yearsOfExperience ?? 0) + 1;
        } else {
            $currentSeasonSalary = $this->contractCalculator->getCurrentSeasonSalary($playerDataObj);
            $experience = $playerDataObj->yearsOfExperience ?? 0;
        }

        $hasExistingContract = $currentSeasonSalary > 0;

        if ($hasExistingContract) {
            return [
                'hasExistingContract' => true,
                'salary' => $currentSeasonSalary
            ];
        }

        $vetMinSalary = $this->calculateVeteranMinimumSalary($experience);

        return [
            'hasExistingContract' => false,
            'salary' => $vetMinSalary
        ];
    }

    /**
     * @see WaiversProcessorInterface::processDrop()
     *
     * @return array{success: bool, result?: string, error?: string}
     */
    public function processDrop(?int $playerID, string $teamName, int $rosterSlots, int $totalSalary): array
    {
        if (!$this->validator->validateDrop($rosterSlots, $totalSalary)) {
            return ['success' => false, 'error' => implode(' ', $this->validator->getErrors())];
        }

        if ($playerID === null || $playerID === 0) {
            return ['success' => false, 'error' => "You didn't select a valid player. Please select a player and try again."];
        }

        $player = $this->commonRepository->getPlayerByID($playerID);
        if ($player === null) {
            return ['success' => false, 'error' => 'Player not found.'];
        }

        $timestamp = time();

        if (!$this->repository->dropPlayerToWaivers($playerID, $timestamp)) {
            return ['success' => false, 'error' => 'Failed to drop player to waivers. Please try again.'];
        }

        $playerName = is_string($player['name'] ?? null) ? $player['name'] : '';
        $this->createWaiverNewsStory($teamName, $playerName, 'waive', '');

        $hometext = "The " . \Utilities\HtmlSanitizer::e($teamName) . " cut " . \Utilities\HtmlSanitizer::e($playerName) . " to waivers.";
        Discord::postToChannel('#waiver-wire', $hometext);

        \Logging\LoggerFactory::getChannel('audit')->info('player_waived', [
            'action' => 'player_waived',
            'player_id' => $playerID,
            'player_name' => $playerName,
            'team_name' => $teamName,
        ]);

        return ['success' => true, 'result' => 'player_dropped'];
    }

    /**
     * @see WaiversProcessorInterface::processAdd()
     *
     * @return array{success: bool, result?: string, error?: string}
     */
    public function processAdd(?int $playerID, string $teamName, int $healthyRosterSlots, int $totalSalary): array
    {
        if ($playerID === null || $playerID === 0) {
            return ['success' => false, 'error' => "You didn't select a valid player. Please select a player and try again."];
        }

        $player = $this->commonRepository->getPlayerByID($playerID);
        if ($player === null) {
            return ['success' => false, 'error' => 'Player not found.'];
        }

        $season = new Season($this->db);
        /** @var array{hasExistingContract: bool, salary: int} $contractData */
        $contractData = $this->determineContractData($player, $season);
        $playerSalary = $contractData['salary'];

        if (!$this->validator->validateAdd($playerID, $healthyRosterSlots, $totalSalary, $playerSalary)) {
            return ['success' => false, 'error' => implode(' ', $this->validator->getErrors())];
        }

        $team = $this->commonRepository->getTeamByName($teamName);
        if ($team === null) {
            return ['success' => false, 'error' => 'Team not found.'];
        }

        if (!$this->repository->signPlayerFromWaivers($playerID, $team, $contractData)) {
            return ['success' => false, 'error' => "Oops, something went wrong. Post what you were trying to do in <A HREF=\"" . self::DISCORD_BUGS_CHANNEL_URL . "\">#site-bugs-and-to-do</A> and we'll fix it asap. Sorry!"];
        }

        $playerName = is_string($player['name'] ?? null) ? $player['name'] : '';
        $salaryStr = (string) $contractData['salary'];
        $this->createWaiverNewsStory($teamName, $playerName, 'add', $salaryStr);

        $storytitle = \Utilities\HtmlSanitizer::e($teamName) . " make waiver additions";
        $hometext = "The " . \Utilities\HtmlSanitizer::e($teamName) . " sign " . \Utilities\HtmlSanitizer::e($playerName) . " from waivers for " . \Utilities\HtmlSanitizer::e($salaryStr) . ".";
        \Mail\MailService::fromConfig()->send(self::NOTIFICATION_EMAIL_RECIPIENT, $storytitle, $hometext, self::NOTIFICATION_EMAIL_SENDER);

        Discord::postToChannel('#waiver-wire', $hometext);

        \Logging\LoggerFactory::getChannel('audit')->info('player_signed_from_waivers', [
            'action' => 'player_signed_from_waivers',
            'player_id' => $playerID,
            'player_name' => $playerName,
            'team_name' => $teamName,
            'salary' => $playerSalary,
        ]);

        return ['success' => true, 'result' => 'player_added'];
    }

    private function createWaiverNewsStory(string $teamName, string $playerName, string $action, string $contract): void
    {
        $this->newsService->incrementCategoryCounter(self::WAIVER_POOL_MOVES_CATEGORY);

        if ($action === 'waive') {
            $topicID = 32;
            $storytitle = \Utilities\HtmlSanitizer::e($teamName) . " make waiver cuts";
            $hometext = "The " . \Utilities\HtmlSanitizer::e($teamName) . " cut " . \Utilities\HtmlSanitizer::e($playerName) . " to waivers.";
        } else {
            $topicID = 33;
            $storytitle = \Utilities\HtmlSanitizer::e($teamName) . " make waiver additions";
            $hometext = "The " . \Utilities\HtmlSanitizer::e($teamName) . " sign " . \Utilities\HtmlSanitizer::e($playerName) . " from waivers for " . \Utilities\HtmlSanitizer::e($contract) . ".";
        }

        $categoryID = $this->newsService->getCategoryIDByTitle(self::WAIVER_POOL_MOVES_CATEGORY);
        if ($categoryID !== null) {
            $this->newsService->createNewsStory($categoryID, $topicID, $storytitle, $hometext);
        }
    }
}
