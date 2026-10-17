<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use Settings\Contracts\SettingsServiceInterface;
use Trading\Contracts\TradeFormRepositoryInterface;
use Trading\Contracts\TradeValidatorInterface;
use TradeSuggestions\CandidateGenerator;
use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
use TradeSuggestions\PickValuer;
use TradeSuggestions\ResolvedTeamMode;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\SuggestionRanker;
use TradeSuggestions\SuggestionValidationFilter;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\TeamModeSetting;
use TradeSuggestions\TradeImpactEvaluator;
use TradeSuggestions\TradeSuggestionsService;

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

    public function testExcludesOwnTeamFromPartners(): void
    {
        // Three real franchises including the GM's own; only the two others are partners.
        $teamModeService = self::createMock(TeamModeService::class);
        $teamModeService->method('resolve')->willReturn(new ResolvedTeamMode(TeamMode::WinNow, TeamMode::WinNow, false));
        $partnerTids = [];
        $teamModeService->expects($this->exactly(2))->method('resolveFromInputs')->willReturnCallback(
            static function (TeamModeSetting $s, int $wins, int $losses, float $avgAge) use (&$partnerTids): ResolvedTeamMode {
                $partnerTids[] = $wins;

                return new ResolvedTeamMode(TeamMode::Rebuild, TeamMode::Rebuild, false);
            },
        );

        $draftOrder = self::createStub(ProjectedDraftOrderServiceInterface::class);
        // wins encode the tid so the callback can tell which team it was asked about.
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
        sort($partnerTids);
        self::assertSame([2, 3], $partnerTids);
        foreach ($result->items as $item) {
            self::assertNotSame('Lakers', $item->partnerTeamName);
        }
    }

    public function testReturnsEmptyItemsWhenNothingPasses(): void
    {
        $validator = self::createStub(TradeValidatorInterface::class);
        $validator->method('canPlayerBeTraded')->willReturn(false);

        $rosters = [
            1 => [$this->player(101, 'User Guard', 'PG', 30), $this->player(102, 'User Wing', 'SF', 22)],
            2 => [$this->player(201, 'Partner Big', 'C', 31), $this->player(202, 'Partner Guard', 'SG', 21)],
        ];

        $service = $this->buildService(teams: [1 => 'Lakers', 2 => 'Heat'], rosters: $rosters, validator: $validator);

        $result = $service->buildForTeam(self::USER_TID);

        self::assertNotNull($result);
        self::assertSame([], $result->items);
        self::assertSame(TeamMode::WinNow, $result->mode->mode);
    }

    public function testPartnerModeUsesStoredOverride(): void
    {
        $teamModeRepo = self::createStub(TeamModeRepositoryInterface::class);
        $teamModeRepo->method('getAllStoredSettings')->willReturn([2 => TeamModeSetting::Rebuild]);

        // Partner 2 is 50-6 (would guess WinNow); its stored override must reach the resolver.
        $teamModeService = self::createMock(TeamModeService::class);
        $teamModeService->method('resolve')->willReturn(new ResolvedTeamMode(TeamMode::WinNow, TeamMode::WinNow, false));
        $teamModeService->expects($this->once())->method('resolveFromInputs')
            ->with(TeamModeSetting::Rebuild, 50, 6, 30.0)
            ->willReturn(new ResolvedTeamMode(TeamMode::Rebuild, TeamMode::WinNow, true));

        $draftOrder = self::createStub(ProjectedDraftOrderServiceInterface::class);
        $draftOrder->method('calculateDraftOrder')->willReturn([
            'round1' => [$this->slot(1, 20, 36), $this->slot(2, 50, 6)],
            'round2' => [],
        ]);

        $rosters = [
            2 => [$this->player(201, 'Vet', 'C', 32), $this->player(202, 'Kid', 'PG', 28), $this->player(203, 'Unknown', 'SF', 0)],
        ];

        $service = $this->buildService(
            teams: [1 => 'Lakers', 2 => 'Heat'],
            rosters: $rosters,
            teamModeService: $teamModeService,
            teamModeRepo: $teamModeRepo,
            draftOrder: $draftOrder,
        );

        self::assertNotNull($service->buildForTeam(self::USER_TID));
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
    ): TradeSuggestionsService {
        $settings = self::createStub(SettingsServiceInterface::class);
        $settings->method('isTradesAllowed')->willReturn($tradesAllowed);

        if ($teamModeService === null) {
            $teamModeService = self::createStub(TeamModeService::class);
            $teamModeService->method('resolve')->willReturn(new ResolvedTeamMode(TeamMode::WinNow, TeamMode::WinNow, false));
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

        $validator ??= self::createStub(TradeValidatorInterface::class);
        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->endingYear = 2027;

        $capCalculator = self::createStub(\Trading\TradeCapCalculator::class);

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

    private function player(int $pid, string $name, string $pos, int $age): SuggestionPlayer
    {
        return new SuggestionPlayer($pid, $name, $pos, $age, 15, false, 1000, 2, 1000);
    }
}
