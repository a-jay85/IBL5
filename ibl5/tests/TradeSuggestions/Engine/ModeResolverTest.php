<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use Trading\Contracts\TradeFormRepositoryInterface;
use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
use TradeSuggestions\Engine\ModeResolver;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TeamModeGuesser;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\TeamModeSetting;

final class ModeResolverTest extends TestCase
{
    /**
     * Current W/L comes from the draft order's round-one slots. The prior-season
     * records say the opposite, and at PRIOR_GAMES (20) weight they cannot
     * outvote a 70-12 current season.
     */
    public function testCurrentRecordsComeFromDraftOrderRoundOne(): void
    {
        $assetRepo = $this->assetRepo([1 => ['wins' => 10, 'losses' => 72], 2 => ['wins' => 72, 'losses' => 10]]);
        $draftOrder = $this->draftOrder([self::slot(1, 70, 12), self::slot(2, 12, 70)]);

        $modes = $this->resolver($assetRepo, $draftOrder, [])->resolveAll();

        self::assertSame([1 => TeamMode::WinNow, 2 => TeamMode::Rebuild], $modes);
    }

    public function testAutoTeamNearFiveHundredResolvesMiddle(): void
    {
        $assetRepo = $this->assetRepo([1 => ['wins' => 41, 'losses' => 41], 2 => ['wins' => 41, 'losses' => 41]]);
        $draftOrder = $this->draftOrder([self::slot(1, 41, 41), self::slot(2, 41, 41)]);

        $modes = $this->resolver($assetRepo, $draftOrder, [2 => TeamModeSetting::Rebuild])->resolveAll();

        self::assertSame(TeamMode::Middle, $modes[1]);
        self::assertSame(TeamMode::Rebuild, $modes[2]);
    }

    public function testRostersReadWithSeasonPhaseAndLastSeasonYear(): void
    {
        $assetRepo = self::createMock(SuggestionAssetRepositoryInterface::class);
        $assetRepo->expects($this->once())->method('getRostersForTeams')
            ->with([1, 2], true, 2026)
            ->willReturn([1 => self::rotation(), 2 => self::rotation()]);
        $assetRepo->expects($this->once())->method('getRegularSeasonRecords')->with(2026)->willReturn([]);

        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->endingYear = 2027;
        $season->phase = 'Free Agency';

        $resolver = new ModeResolver(
            $assetRepo,
            $this->draftOrder([self::slot(1, 0, 0), self::slot(2, 0, 0)]),
            $this->teamModeRepo([]),
            $this->formRepo(),
            new TeamModeService(self::createStub(TeamModeRepositoryInterface::class), new TeamModeGuesser()),
            $season,
        );

        self::assertCount(2, $resolver->resolveAll());
    }

    /**
     * @param array<int, array{wins: int, losses: int}> $prior
     */
    private function assetRepo(array $prior): SuggestionAssetRepositoryInterface
    {
        $repo = self::createStub(SuggestionAssetRepositoryInterface::class);
        $repo->method('getRostersForTeams')->willReturn([1 => self::rotation(), 2 => self::rotation()]);
        $repo->method('getRegularSeasonRecords')->willReturn($prior);

        return $repo;
    }

    /**
     * @param list<array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string, movement: int|null, player: string}> $round1
     */
    private function draftOrder(array $round1): ProjectedDraftOrderServiceInterface
    {
        $draftOrder = self::createStub(ProjectedDraftOrderServiceInterface::class);
        $draftOrder->method('calculateDraftOrder')->willReturn(['round1' => $round1, 'round2' => []]);

        return $draftOrder;
    }

    /**
     * @param array<int, TeamModeSetting> $stored
     */
    private function teamModeRepo(array $stored): TeamModeRepositoryInterface
    {
        $repo = self::createStub(TeamModeRepositoryInterface::class);
        $repo->method('getAllStoredSettings')->willReturn($stored);

        return $repo;
    }

    private function formRepo(): TradeFormRepositoryInterface
    {
        $formRepo = self::createStub(TradeFormRepositoryInterface::class);
        $formRepo->method('getAllTeamsWithCity')->willReturn([
            ['teamid' => 1, 'team_name' => 'One', 'team_city' => 'A', 'color1' => '000000', 'color2' => 'FFFFFF'],
            ['teamid' => 2, 'team_name' => 'Two', 'team_city' => 'B', 'color1' => '000000', 'color2' => 'FFFFFF'],
        ]);

        return $formRepo;
    }

    /**
     * @param array<int, TeamModeSetting> $stored
     */
    private function resolver(
        SuggestionAssetRepositoryInterface $assetRepo,
        ProjectedDraftOrderServiceInterface $draftOrder,
        array $stored,
    ): ModeResolver {
        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->endingYear = 2027;

        return new ModeResolver(
            $assetRepo,
            $draftOrder,
            $this->teamModeRepo($stored),
            $this->formRepo(),
            new TeamModeService(self::createStub(TeamModeRepositoryInterface::class), new TeamModeGuesser()),
            $season,
        );
    }

    /**
     * Neutral roster terms: mean age 26.5 (the pivot) and half the minutes locked.
     *
     * @return list<SuggestionPlayer>
     */
    private static function rotation(): array
    {
        $players = [];
        foreach ([1, 2] as $i) {
            $players[] = new SuggestionPlayer(
                pid: $i,
                name: 'Player ' . $i,
                pos: 'SF',
                age: $i === 1 ? 26 : 27,
                injured: false,
                currentSalary: 1000,
                yearsRemaining: $i === 1 ? 2 : 1,
                futureSalary: 0,
                dcMinutes: 30,
            );
        }

        return $players;
    }

    /**
     * @return array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string, movement: int|null, player: string}
     */
    private static function slot(int $teamId, int $wins, int $losses): array
    {
        return [
            'pick' => $teamId, 'teamId' => $teamId, 'teamName' => 'T' . $teamId, 'wins' => $wins, 'losses' => $losses,
            'color1' => '000000', 'color2' => 'FFFFFF', 'ownerId' => $teamId, 'ownerName' => 'T' . $teamId,
            'ownerColor1' => '000000', 'ownerColor2' => 'FFFFFF', 'isTraded' => false, 'notes' => '', 'movement' => null, 'player' => '',
        ];
    }
}
