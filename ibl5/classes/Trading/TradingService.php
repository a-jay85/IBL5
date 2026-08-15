<?php

declare(strict_types=1);

namespace Trading;

use League\League;
use Trading\Contracts\TradingServiceInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeFormRepositoryInterface;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;
use Trading\Contracts\TradeOfferGrouperInterface;
use Trading\Contracts\FutureSalaryCalculatorInterface;
use Season\Season;

/**
 * @see TradingServiceInterface
 *
 * @phpstan-import-type TradeInfoRow from \Trading\Contracts\TradeOfferRepositoryInterface
 * @phpstan-import-type TradingDraftPickRow from \Trading\Contracts\TradeFormRepositoryInterface
 * @phpstan-import-type TradingPlayerRow from \Trading\Contracts\TradeFormRepositoryInterface
 * @phpstan-import-type TeamWithCityRow from \Trading\Contracts\TradeFormRepositoryInterface
 */
class TradingService implements TradingServiceInterface
{
    private TradeOfferRepositoryInterface $offerRepository;
    private TradeFormRepositoryInterface $formRepository;
    private BuyoutLedgerRepositoryInterface $cashConsiderationRepository;
    private TradeOfferGrouperInterface $offerGrouper;
    private FutureSalaryCalculatorInterface $futureSalaryCalculator;
    private \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepository;
    private \mysqli $mysqli_db;
    /**
     * Optional injected Season. When null, methods fall back to new Season($db) (timing identical to today).
     */
    private ?Season $season = null;

    public function __construct(
        TradeOfferRepositoryInterface $offerRepository,
        TradeFormRepositoryInterface $formRepository,
        \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepository,
        \mysqli $mysqli_db,
        TradeOfferGrouperInterface $offerGrouper,
        FutureSalaryCalculatorInterface $futureSalaryCalculator,
        ?Season $season = null
    ) {
        $this->offerRepository = $offerRepository;
        $this->formRepository = $formRepository;
        $this->cashConsiderationRepository = new BuyoutLedgerRepository($mysqli_db);
        $this->commonRepository = $commonRepository;
        $this->mysqli_db = $mysqli_db;
        $this->offerGrouper = $offerGrouper;
        $this->futureSalaryCalculator = $futureSalaryCalculator;
        $this->season = $season;
    }

    /**
     * @see TradingServiceInterface::getTradeOfferPageData()
     *
     * @return array{userTeam: string, userTeamId: int, partnerTeam: string, partnerTeamId: int, userPlayers: list<TradingPlayerRow>, userPicks: list<TradingDraftPickRow>, userFutureSalary: array{player: array<int, int>, hold: array<int, int>}, partnerPlayers: list<TradingPlayerRow>, partnerPicks: list<TradingDraftPickRow>, partnerFutureSalary: array{player: array<int, int>, hold: array<int, int>}, seasonEndingYear: int, seasonPhase: string, cashStartYear: int, cashEndYear: int, userTeamColor1: string, userTeamColor2: string, partnerTeamColor1: string, partnerTeamColor2: string}
     */
    public function getTradeOfferPageData(string $username, string $partnerTeam): array
    {
        /** @var \mysqli $mysqliDb */
        $mysqliDb = $this->mysqli_db;
        $season = $this->season ?? new Season($mysqliDb);

        $userTeam = $this->commonRepository->getTeamnameFromUsername($username) ?? '';

        $userTeamData = $this->commonRepository->getTeamByName($userTeam);
        $partnerTeamData = $this->commonRepository->getTeamByName($partnerTeam);

        $userTeamId = $userTeamData !== null ? $userTeamData['teamid'] : 0;
        $partnerTeamId = $partnerTeamData !== null ? $partnerTeamData['teamid'] : 0;

        $userPlayers = $this->formRepository->getTeamPlayersForTrading($userTeamId);
        $userPicks = $this->formRepository->getTeamDraftPicksForTrading($userTeamId);
        $userCashRecords = $this->cashConsiderationRepository->getTeamCashForSalary($userTeamId);
        $userFutureSalary = $this->calculateFutureSalaries([...$userPlayers, ...$userCashRecords], $season);

        $partnerPlayers = $this->formRepository->getTeamPlayersForTrading($partnerTeamId);
        $partnerPicks = $this->formRepository->getTeamDraftPicksForTrading($partnerTeamId);
        $partnerCashRecords = $this->cashConsiderationRepository->getTeamCashForSalary($partnerTeamId);
        $partnerFutureSalary = $this->calculateFutureSalaries([...$partnerPlayers, ...$partnerCashRecords], $season);

        // Calculate cash exchange year range
        $currentSeasonEndingYear = $season->endingYear;
        $cashStartYear = 1;
        if ($season->advancesContractYears()) {
            $cashStartYear = 2;
        }

        return [
            'userTeam' => $userTeam,
            'userTeamId' => $userTeamId,
            'partnerTeam' => $partnerTeam,
            'partnerTeamId' => $partnerTeamId,
            'userPlayers' => $userPlayers,
            'userPicks' => $userPicks,
            'userFutureSalary' => $userFutureSalary,
            'partnerPlayers' => $partnerPlayers,
            'partnerPicks' => $partnerPicks,
            'partnerFutureSalary' => $partnerFutureSalary,
            'seasonEndingYear' => $currentSeasonEndingYear,
            'seasonPhase' => $season->phase,
            'cashStartYear' => $cashStartYear,
            'cashEndYear' => 6,
            'userTeamColor1' => $userTeamData !== null ? $userTeamData['color1'] : '000000',
            'userTeamColor2' => $userTeamData !== null ? $userTeamData['color2'] : 'ffffff',
            'partnerTeamColor1' => $partnerTeamData !== null ? $partnerTeamData['color1'] : '000000',
            'partnerTeamColor2' => $partnerTeamData !== null ? $partnerTeamData['color2'] : 'ffffff',
        ];
    }

    /**
     * @see TradingServiceInterface::getTradeReviewPageData()
     *
     * @return array{userTeam: string, userTeamId: int, tradeOffers: array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>, fromTeamId: int, toTeamId: int, fromColor1: string, toColor1: string, fromCash: array<int, int>, toCash: array<int, int>, cashStartYear: int, cashEndYear: int, seasonEndingYear: int}}>, teams: list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string, mobileOrder: int}>}
     */
    public function getTradeReviewPageData(string $username): array
    {
        $userTeam = $this->commonRepository->getTeamnameFromUsername($username) ?? '';
        $userTeamId = $this->commonRepository->getTidFromTeamname($userTeam) ?? 0;

        /** @var \mysqli $mysqliDb */
        $mysqliDb = $this->mysqli_db;
        $season = $this->season ?? new Season($mysqliDb);

        $allTradeRows = $this->offerRepository->getAllTradeOffers();
        $tradeOffers = $this->offerGrouper->groupOffers($allTradeRows, $userTeam, $season->endingYear);

        // Get teams for the team selection sidebar
        $allTeams = $this->formRepository->getAllTeamsWithCity();
        $teams = $this->buildTeamList($allTeams);

        // Enrich offers with preview data (team IDs, colors, cash)
        $tradeOffers = $this->offerGrouper->enrichWithPreviewData($tradeOffers, $allTeams, $season);

        return [
            'userTeam' => $userTeam,
            'userTeamId' => $userTeamId,
            'tradeOffers' => $tradeOffers,
            'teams' => $teams,
        ];
    }

    /**
     * @see TradingServiceInterface::calculateFutureSalaries()
     *
     * @param list<array<string, mixed>> $players Player rows from repository
     * @return array{player: array<int, int>, hold: array<int, int>}
     */
    public function calculateFutureSalaries(array $players, Season $season): array
    {
        return $this->futureSalaryCalculator->calculateFutureSalaries($players, $season);
    }

    /**
     * Build the Discord DM message sent to the proposing GM when a trade is declined.
     *
     * @param string $decliningGmDiscordId Discord user ID of the GM who declined
     * @param string $decliningTeamName Team name of the GM who declined (used in counter-offer link)
     */
    public static function buildDeclineMessage(string $decliningGmDiscordId, string $decliningTeamName): string
    {
        $counterOfferUrl = 'http://www.iblhoops.net/ibl5/modules.php?name=Trading&op=offertrade&partner='
            . rawurlencode($decliningTeamName);

        return 'Sorry, trade proposal declined by <@!' . $decliningGmDiscordId . '>.'
            . "\n" . '[Click/tap to counter-offer](' . $counterOfferUrl . ')';
    }

    /**
     * Build the team-selection sidebar list (excludes Free Agents).
     *
     * Returns teams split by conference, sorted by city for the desktop
     * column order, then interleaved (West[0], East[0], West[1], East[1], …).
     * Each entry also carries a `mobileOrder` slot — the name-sorted position
     * the View injects as a `--mobile-order` CSS custom property so the
     * single-column mobile layout reorders without re-querying. This ordering
     * presentation logic lives here (not the View) so the View is pure
     * iteration.
     *
     * @param list<TeamWithCityRow> $allTeams Raw team rows from repository
     * @return list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string, mobileOrder: int}>
     */
    private function buildTeamList(array $allTeams): array
    {
        $teams = [];

        foreach ($allTeams as $row) {
            $teamName = $row['team_name'];
            if ($teamName === League::FREE_AGENTS_TEAM_NAME) {
                continue;
            }

            $teamCity = $row['team_city'];
            $teams[] = [
                'name' => $teamName,
                'city' => $teamCity,
                'fullName' => "{$teamCity} {$teamName}",
                'teamid' => $row['teamid'],
                'color1' => $row['color1'],
                'color2' => $row['color2'],
            ];
        }

        // Split by conference.
        $western = [];
        $eastern = [];
        foreach ($teams as $team) {
            if (in_array($team['teamid'], League::WESTERN_CONFERENCE_TEAMIDS, true)) {
                $western[] = $team;
            } else {
                $eastern[] = $team;
            }
        }

        // Assign mobile-order slots by team name (West even, East odd) onto a
        // freshly-rebuilt entry so the slot rides along through the city sort.
        usort($western, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        $westernOrdered = [];
        foreach ($western as $i => $team) {
            $team['mobileOrder'] = $i * 2;
            $westernOrdered[] = $team;
        }
        usort($eastern, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        $easternOrdered = [];
        foreach ($eastern as $i => $team) {
            $team['mobileOrder'] = $i * 2 + 1;
            $easternOrdered[] = $team;
        }

        // Sort by city for desktop display.
        usort($westernOrdered, static fn(array $a, array $b): int => strcasecmp($a['city'], $b['city']));
        usort($easternOrdered, static fn(array $a, array $b): int => strcasecmp($a['city'], $b['city']));

        // Interleave: West[0], East[0], West[1], East[1], ...
        $interleaved = [];
        $count = max(count($westernOrdered), count($easternOrdered));
        for ($i = 0; $i < $count; $i++) {
            if (isset($westernOrdered[$i])) {
                $interleaved[] = $westernOrdered[$i];
            }
            if (isset($easternOrdered[$i])) {
                $interleaved[] = $easternOrdered[$i];
            }
        }

        return $interleaved;
    }
}
