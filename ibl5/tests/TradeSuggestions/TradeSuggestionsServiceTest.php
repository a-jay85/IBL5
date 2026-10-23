<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use League\League;
use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\RosterFactory;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use Settings\Contracts\SettingsServiceInterface;
use Trading\Contracts\TradeFormRepositoryInterface;
use Trading\Contracts\TradeValidatorInterface;
use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
use TradeSuggestions\ModeInputs;
use TradeSuggestions\NeedJudge;
use TradeSuggestions\NeedPairMatcher;
use TradeSuggestions\NeedsCardBuilder;
use TradeSuggestions\PickValuer;
use TradeSuggestions\RejectReason;
use TradeSuggestions\ReasonBuilder;
use TradeSuggestions\ResolvedTeamMode;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\SuggestionRanker;
use TradeSuggestions\SuggestionValidationFilter;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\TeamModeSetting;
use TradeSuggestions\TradeSuggestionsService;
use Validation\ValidationResult;
use Validation\ValidationResultWithContext;

/**
 * The needs engine (card builder, matcher, judge, reason builder), PickValuer and
 * SuggestionRanker are pure, so they run for real over crafted inputs. Every I/O
 * collaborator is a stub.
 */
class TradeSuggestionsServiceTest extends TestCase
{
    private const USER_TID = 1;

    public function testReturnsNullWhenTradesClosed(): void
    {
        $assetRepo = self::createMock(SuggestionAssetRepositoryInterface::class);
        $assetRepo->expects($this->never())->method('getRostersForTeams');
        $formRepo = self::createMock(TradeFormRepositoryInterface::class);
        $formRepo->expects($this->never())->method('getAllTeamsWithCity');

        $service = $this->buildService(tradesAllowed: false, assetRepo: $assetRepo, formRepo: $formRepo);

        self::assertNull($service->buildForTeam(self::USER_TID));
    }

    /**
     * The GM's own mode and the mode partners see for that team come from one
     * call path, so the panel header and the deals never disagree.
     */
    public function testEveryTeamIncludingTheUserResolvesThroughTheSameInputs(): void
    {
        $resolvedFor = [];
        $teamModeService = self::createMock(TeamModeService::class);
        $teamModeService->expects($this->exactly(3))->method('resolveFromInputs')->willReturnCallback(
            static function (TeamModeSetting $s, ModeInputs $inputs) use (&$resolvedFor): ResolvedTeamMode {
                // wins encode the tid (see the draft order below).
                $resolvedFor[] = $inputs->wins;

                return new ResolvedTeamMode(TeamMode::Rebuild, TeamMode::Rebuild, false);
            },
        );

        $draftOrder = self::createStub(ProjectedDraftOrderServiceInterface::class);
        $draftOrder->method('calculateDraftOrder')->willReturn([
            'round1' => [$this->slot(1, 1, 1), $this->slot(2, 2, 2), $this->slot(3, 3, 3)],
            'round2' => [],
        ]);

        $service = $this->buildService(
            teams: [1 => 'Lakers', 2 => 'Heat', 3 => 'Bulls'],
            teamModeService: $teamModeService,
            draftOrder: $draftOrder,
        );

        $result = $service->buildForTeam(self::USER_TID);

        self::assertNotNull($result);
        sort($resolvedFor);
        self::assertSame([1, 2, 3], $resolvedFor);
        foreach ($result->items as $item) {
            self::assertNotSame('Lakers', $item->partnerTeamName);
        }
    }

    public function testPartnerModeUsesStoredOverride(): void
    {
        $teamModeRepo = self::createStub(TeamModeRepositoryInterface::class);
        $teamModeRepo->method('getAllStoredSettings')->willReturn([2 => TeamModeSetting::Rebuild]);

        $settings = [];
        $teamModeService = self::createStub(TeamModeService::class);
        $teamModeService->method('resolveFromInputs')->willReturnCallback(
            static function (TeamModeSetting $s, ModeInputs $inputs) use (&$settings): ResolvedTeamMode {
                $settings[$inputs->wins] = $s;

                return new ResolvedTeamMode(TeamMode::WinNow, TeamMode::WinNow, false);
            },
        );

        $draftOrder = self::createStub(ProjectedDraftOrderServiceInterface::class);
        $draftOrder->method('calculateDraftOrder')->willReturn([
            'round1' => [$this->slot(1, 1, 40), $this->slot(2, 2, 6)],
            'round2' => [],
        ]);

        $service = $this->buildService(
            teams: [1 => 'Lakers', 2 => 'Heat'],
            teamModeService: $teamModeService,
            teamModeRepo: $teamModeRepo,
            draftOrder: $draftOrder,
        );

        self::assertNotNull($service->buildForTeam(self::USER_TID));
        self::assertSame([1 => TeamModeSetting::Auto, 2 => TeamModeSetting::Rebuild], $settings);
    }

    /**
     * Every generated deal lands in exactly one bucket, so an empty panel can
     * say which filter emptied it.
     */
    public function testDiagnosticsAccountForEveryCandidate(): void
    {
        $result = $this->buildService(rosters: self::rosters())->buildForTeam(self::USER_TID);

        self::assertNotNull($result);
        $d = $result->diagnostics;
        self::assertNotNull($d);
        self::assertGreaterThan(0, $d->generated);
        self::assertSame($d->generated, array_sum($d->rejections) + $d->scored);
        self::assertSame(count($result->items) + array_sum($d->validatorRejections), $d->validated);
    }

    public function testRejectionsListEveryReason(): void
    {
        $result = $this->buildService(rosters: self::rosters())->buildForTeam(self::USER_TID);

        self::assertNotNull($result);
        $d = $result->diagnostics;
        self::assertNotNull($d);
        foreach (RejectReason::cases() as $reason) {
            self::assertArrayHasKey($reason->value, $d->rejections);
        }
    }

    public function testOverCapUserGetsDealsWithAReasonOnBothSides(): void
    {
        $result = $this->buildService(rosters: self::rosters(), capTotal: 3000, userCapTotal: League::HARD_CAP_MAX + 653, picks: self::userPicks(), draftOrder: $this->draftOrderOf2())->buildForTeam(self::USER_TID);

        self::assertNotNull($result);
        self::assertNotSame([], $result->items);
        foreach ($result->items as $item) {
            self::assertSame('Heat', $item->partnerTeamName);
            self::assertNotSame('', $item->userReason);
            self::assertNotSame('', $item->partnerReason);
            self::assertDoesNotMatchRegularExpression('/\b(ovr|rating)\b/i', $item->userReason . ' ' . $item->partnerReason);
        }
    }

    public function testEmptyUserRosterKeepsDiagnosticsBalanced(): void
    {
        $rosters = self::rosters();
        $rosters[1] = array_slice($rosters[1], 0, 0);

        $result = $this->buildService(rosters: $rosters)->buildForTeam(self::USER_TID);

        self::assertNotNull($result);
        $d = $result->diagnostics;
        self::assertNotNull($d);
        self::assertSame($d->generated, array_sum($d->rejections) + $d->scored);
    }

    public function testReturnsEmptyItemsWhenNothingPasses(): void
    {
        $validator = self::createStub(TradeValidatorInterface::class);
        $validator->method('canPlayerBeTraded')->willReturn(false);

        $service = $this->buildService(rosters: self::rosters(), validator: $validator);

        $result = $service->buildForTeam(self::USER_TID);

        self::assertNotNull($result);
        self::assertSame([], $result->items);
        self::assertSame(TeamMode::Rebuild, $result->mode->mode);
    }

    /**
     * @param array<int, string> $teams
     * @param array<int, list<SuggestionPlayer>> $rosters
     * @param array<int, list<array{pickid: int, owner_teamid: int, teampick_teamid: int, year: int, round: int}>> $picks
     */
    private function buildService(
        bool $tradesAllowed = true,
        array $teams = [1 => 'Lakers', 2 => 'Heat'],
        array $rosters = [],
        ?TeamModeService $teamModeService = null,
        ?TeamModeRepositoryInterface $teamModeRepo = null,
        ?SuggestionAssetRepositoryInterface $assetRepo = null,
        ?ProjectedDraftOrderServiceInterface $draftOrder = null,
        ?TradeFormRepositoryInterface $formRepo = null,
        ?TradeValidatorInterface $validator = null,
        int $capTotal = 5000,
        ?int $userCapTotal = null,
        array $picks = [],
    ): TradeSuggestionsService {
        $settings = self::createStub(SettingsServiceInterface::class);
        $settings->method('isTradesAllowed')->willReturn($tradesAllowed);

        if ($teamModeService === null) {
            $teamModeService = self::createStub(TeamModeService::class);
            $teamModeService->method('resolveFromInputs')->willReturn(new ResolvedTeamMode(TeamMode::Rebuild, TeamMode::Rebuild, false));
        }

        if ($teamModeRepo === null) {
            $teamModeRepo = self::createStub(TeamModeRepositoryInterface::class);
            $teamModeRepo->method('getAllStoredSettings')->willReturn([]);
        }

        if ($assetRepo === null) {
            $assetRepo = self::createStub(SuggestionAssetRepositoryInterface::class);
            $assetRepo->method('getRostersForTeams')->willReturn($rosters);
            $assetRepo->method('getPicksForTeams')->willReturn($picks);
        }

        if ($draftOrder === null) {
            $draftOrder = self::createStub(ProjectedDraftOrderServiceInterface::class);
            $draftOrder->method('calculateDraftOrder')->willReturn(['round1' => [], 'round2' => []]);
        }

        if ($formRepo === null) {
            $formRepo = self::createStub(TradeFormRepositoryInterface::class);
            $rows = [];
            foreach ($teams as $tid => $name) {
                $rows[] = ['teamid' => $tid, 'team_name' => $name, 'team_city' => 'City', 'color1' => '000000', 'color2' => 'FFFFFF'];
            }
            $formRepo->method('getAllTeamsWithCity')->willReturn($rows);
            $formRepo->method('getTeamPlayersForTrading')->willReturnCallback(
                static fn (int $tid): array => array_map(
                    static fn (SuggestionPlayer $p): array => ['pid' => $p->pid, 'cy' => 1, 'salary_yr1' => $p->currentSalary],
                    $rosters[$tid] ?? [],
                ),
            );
            $formRepo->method('getTeamDraftPicksForTrading')->willReturnCallback(
                static fn (int $tid): array => array_map(
                    static fn (array $row): array => ['pickid' => $row['pickid']],
                    $picks[$tid] ?? [],
                ),
            );
        }

        if ($validator === null) {
            $validator = self::createStub(TradeValidatorInterface::class);
            $validator->method('validateSalaryCaps')->willReturn(ValidationResultWithContext::success(['userPostTradeCapTotal' => 0, 'partnerPostTradeCapTotal' => 0]));
            $validator->method('validateRosterLimits')->willReturn(ValidationResult::success());
            $validator->method('canPlayerBeTraded')->willReturn(true);
        }
        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->endingYear = 2027;

        $capCalculator = self::createStub(\Trading\TradeCapCalculator::class);
        $capCalculator->method('calculateSalaryCapData')->willReturnCallback(
            static fn (array $tradeData): array => [
                'userCurrentSeasonCapTotal' => $userCapTotal !== null && $tradeData['offeringTeam'] === 'Lakers' ? $userCapTotal : $capTotal,
                'partnerCurrentSeasonCapTotal' => $capTotal,
                'userCapSentToPartner' => 0,
                'partnerCapSentToUser' => 0,
            ],
        );

        return new TradeSuggestionsService(
            $teamModeService,
            $teamModeRepo,
            $assetRepo,
            $draftOrder,
            $formRepo,
            $settings,
            $season,
            new PickValuer(),
            new NeedsCardBuilder(),
            new NeedPairMatcher(),
            new NeedJudge(),
            new ReasonBuilder(),
            new SuggestionValidationFilter($validator, $capCalculator, $formRepo, $season),
            new SuggestionRanker(),
        );
    }

    /**
     * @return array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string, movement: int|null, player: string}
     */
    private function slot(int $teamId, int $wins, int $losses): array
    {
        return [
            'pick' => $teamId, 'teamId' => $teamId, 'teamName' => 'T' . $teamId, 'wins' => $wins, 'losses' => $losses,
            'color1' => '000000', 'color2' => 'FFFFFF', 'ownerId' => $teamId, 'ownerName' => 'T' . $teamId,
            'ownerColor1' => '000000', 'ownerColor2' => 'FFFFFF', 'isTraded' => false, 'notes' => '', 'movement' => null, 'player' => '',
        ];
    }

    /**
     * Team 1 is over the hard cap with an old, expensive rotation. Team 2 has
     * room and young players. Everyone has minutes and a points line.
     *
     * @return array<int, list<SuggestionPlayer>>
     */
    private static function rosters(): array
    {
        $positions = ['PG', 'SG', 'SF', 'PF', 'C', 'G', 'F', 'GF', 'C'];
        $rosters = [];
        foreach ([1, 2] as $tid) {
            foreach ($positions as $i => $pos) {
                $pid = $tid * 100 + $i;
                $rosters[$tid][] = RosterFactory::player(
                    $pid,
                    $pos,
                    age: $tid === 1 ? 31 : 22,
                    salary: $tid === 1 ? 900 : 400,
                    years: 1 + $i % 4,
                    dcMinutes: $i < 5 ? 36 - 3 * $i : 0,
                    points: 700 - 40 * $i - 20 * $tid,
                    laterSalaries: $tid === 1 ? [900, 900] : [],
                );
            }
        }

        return $rosters;
    }

    /**
     * @return array<int, list<array{pickid: int, owner_teamid: int, teampick_teamid: int, year: int, round: int}>>
     */
    private static function userPicks(): array
    {
        return [1 => [['pickid' => 900, 'owner_teamid' => 1, 'teampick_teamid' => 1, 'year' => 2027, 'round' => 1]]];
    }

    private function draftOrderOf2(): ProjectedDraftOrderServiceInterface
    {
        $draftOrder = self::createStub(ProjectedDraftOrderServiceInterface::class);
        $draftOrder->method('calculateDraftOrder')->willReturn([
            'round1' => [$this->slot(1, 20, 62), $this->slot(2, 40, 42)],
            'round2' => [],
        ]);

        return $draftOrder;
    }
}
