<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use League\League;
use Negotiation\Contracts\NegotiationRepositoryInterface;
use PHPUnit\Framework\TestCase;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use Settings\Contracts\SettingsServiceInterface;
use Trading\Contracts\TradeFormRepositoryInterface;
use Trading\Contracts\TradeValidatorInterface;
use TradeSuggestions\CandidateGenerator;
use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
use TradeSuggestions\ModeInputs;
use TradeSuggestions\PickValuer;
use TradeSuggestions\RejectReason;
use TradeSuggestions\ResolvedTeamMode;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\SuggestionRanker;
use TradeSuggestions\SuggestionValidationFilter;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\TeamModeSetting;
use TradeSuggestions\TradeImpactEvaluator;
use TradeSuggestions\TradeSuggestionsService;
use Validation\ValidationResult;
use Validation\ValidationResultWithContext;

/**
 * PickValuer, CandidateGenerator, TradeImpactEvaluator, and SuggestionRanker are
 * pure (and mostly final), so they run for real over crafted inputs. Every I/O
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

    public function testTeamAtTheHardCapGetsOnlySalaryNeutralOrShedding(): void
    {
        $result = $this->buildService(rosters: self::rosters(), capTotal: League::HARD_CAP_MAX)->buildForTeam(self::USER_TID);

        self::assertNotNull($result);
        $d = $result->diagnostics;
        self::assertNotNull($d);
        self::assertGreaterThan(0, $d->rejections[RejectReason::OverHardCap->value]);
        self::assertSame([], $d->validatorRejections);
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
            $assetRepo->method('getPicksForTeams')->willReturn([]);
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
            $formRepo->method('getTeamPlayersForTrading')->willReturn([]);
            $formRepo->method('getTeamDraftPicksForTrading')->willReturn([]);
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
        $capCalculator->method('calculateSalaryCapData')->willReturn([
            'userCurrentSeasonCapTotal' => $capTotal,
            'partnerCurrentSeasonCapTotal' => $capTotal,
            'userCapSentToPartner' => 0,
            'partnerCapSentToUser' => 0,
        ]);

        return new TradeSuggestionsService(
            $teamModeService,
            $teamModeRepo,
            $assetRepo,
            $draftOrder,
            $formRepo,
            $settings,
            $season,
            new PickValuer(),
            new CandidateGenerator(),
            new TradeImpactEvaluator(),
            new SuggestionValidationFilter($validator, $capCalculator, $formRepo, $season),
            new SuggestionRanker(),
            self::createStub(NegotiationRepositoryInterface::class),
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
     * Two nine-man rosters with a spread of ovr, ages, and salaries.
     *
     * @return array<int, list<SuggestionPlayer>>
     */
    private static function rosters(): array
    {
        $positions = ['PG', 'SG', 'SF', 'GF', 'PF', 'C', 'G', 'F', 'C'];
        $rosters = [];
        foreach ([1, 2] as $tid) {
            foreach ($positions as $i => $pos) {
                $pid = $tid * 100 + $i;
                $ovr = 72 - 2 * $i - $tid;
                $rosters[$tid][] = new SuggestionPlayer($pid, 'Player ' . $pid, $pos, 22 + $i + $tid, $ovr, false, 400 + 100 * $i, 1 + $i % 4, 500 * ($i % 4));
            }
        }

        return $rosters;
    }
}
