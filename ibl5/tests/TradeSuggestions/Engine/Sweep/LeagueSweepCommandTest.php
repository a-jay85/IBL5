<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Sweep;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\LaughTestRows;
use Tests\TradeSuggestions\LaughTest\Fixtures\LaughTestFactory;
use Trading\Contracts\TradeFormRepositoryInterface;
use TradeSuggestions\Contracts\TradeSuggestionsServiceInterface;
use TradeSuggestions\Engine\Contracts\LeagueSnapshotProviderInterface;
use TradeSuggestions\Engine\Sweep\LeagueSweepCommand;
use TradeSuggestions\ResolvedTeamMode;
use TradeSuggestions\SuggestionDiagnostics;
use TradeSuggestions\SuggestionItem;
use TradeSuggestions\SuggestionLink;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\TradeSuggestionsPanelData;
use TradeSuggestions\Veto\VetoSet;

final class LeagueSweepCommandTest extends TestCase
{
    public function testSkipsMissingPanelsFiltersAboveMaxTeamsAndSortsTids(): void
    {
        $panels = [3 => self::panel([]), 4 => self::panel([])];
        // Rows arrive unsorted; tid 4 is above the cap of 3.
        $text = $this->sweep(3, [4 => 'D', 3 => 'C', 2 => 'B'], $panels);

        $this->assertSame(
            "tid=2 skipped\n"
            . "tid=3 ideas=0 vetoed=0 shown=0 violations=0 violation_ids=\n"
            . "TEAMS=1 IDEAS=0 VETOED=0 SHOWN=0 VIOLATIONS=0\n",
            $text['text'],
        );
        $this->assertSame(0, $text['code']);
    }

    public function testTidsPrintInAscendingOrder(): void
    {
        $panels = [1 => self::panel([]), 2 => self::panel([])];
        $text = $this->sweep(28, [2 => 'B', 1 => 'A'], $panels);

        $this->assertSame(
            "tid=1 ideas=0 vetoed=0 shown=0 violations=0 violation_ids=\n"
            . "tid=2 ideas=0 vetoed=0 shown=0 violations=0 violation_ids=\n"
            . "TEAMS=2 IDEAS=0 VETOED=0 SHOWN=0 VIOLATIONS=0\n",
            $text['text'],
        );
    }

    public function testSumsCountsAcrossTeamsAndMergesViolationIds(): void
    {
        $league = LaughTestFactory::league();
        $partner1 = $league->teamNames[LaughTestRows::PARTNER_TID];
        $partner2 = $league->teamNames[LaughTestRows::USER_TID];

        // Team 1: Dupree for Laettner (three vetoes + a gain break), then picks for nothing.
        $dealA = new TradeCandidate(1, 2, [LaughTestRows::DUPREE_PID], [], [LaughTestRows::LAETTNER_PID], []);
        $dealE = new TradeCandidate(1, 2, [], LaughTestFactory::pickIds(), [], []);
        $dealB = new TradeCandidate(1, 2, [LaughTestRows::DUPREE_PID], LaughTestFactory::pickIds(), [LaughTestRows::LAETTNER_PID], []);
        // Team 2: the mirror of deal A.
        $dealC = new TradeCandidate(2, 1, [LaughTestRows::LAETTNER_PID], [], [LaughTestRows::DUPREE_PID], []);

        $panel1 = self::panel(
            [self::item($partner1, $dealA), self::item($partner1, $dealB), self::item($partner1, $dealE)],
            new SuggestionDiagnostics(5, 5, ['VETO:ONE' => 2, 'VETO:TWO' => 3, 'OTHER' => 10], 5, []),
        );
        $panel2 = self::panel(
            [self::item($partner2, $dealC)],
            new SuggestionDiagnostics(4, 4, ['VETO:THREE' => 1], 4, []),
        );
        $panel3 = self::panel([]);

        $result = $this->sweep(3, [1 => $partner2, 2 => $partner1, 3 => 'Third'], [1 => $panel1, 2 => $panel2, 3 => $panel3], $league);

        $this->assertSame(
            "tid=1 ideas=5 vetoed=5 shown=3 violations=3 violation_ids=NOT_BOTH_POSITIVE,PICK_TO_NON_BURDEN_TAKER,STRONG_FOR_WEAK_WITHOUT_NET_GAIN,WIN_NOW_WORSE_THIS_SEASON,YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE\n"
            . "tid=2 ideas=4 vetoed=1 shown=1 violations=1 violation_ids=NOT_BOTH_POSITIVE,STRONG_FOR_WEAK_WITHOUT_NET_GAIN,WIN_NOW_WORSE_THIS_SEASON,YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE\n"
            . "tid=3 ideas=0 vetoed=0 shown=0 violations=0 violation_ids=\n"
            . "TEAMS=3 IDEAS=9 VETOED=6 SHOWN=4 VIOLATIONS=4\n",
            $result['text'],
        );
        $this->assertSame(1, $result['code']);
    }

    public function testMultipleIdsFromOneDealAllReported(): void
    {
        $league = LaughTestFactory::league();
        $partner = $league->teamNames[LaughTestRows::PARTNER_TID];
        $dealA = new TradeCandidate(1, 2, [LaughTestRows::DUPREE_PID], [], [LaughTestRows::LAETTNER_PID], []);
        $panel = self::panel([self::item($partner, $dealA)]);

        $result = $this->sweep(1, [1 => 'A', 2 => $partner], [1 => $panel], $league);

        $this->assertSame(
            "tid=1 ideas=0 vetoed=0 shown=1 violations=1 violation_ids=NOT_BOTH_POSITIVE,STRONG_FOR_WEAK_WITHOUT_NET_GAIN,WIN_NOW_WORSE_THIS_SEASON,YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE\n"
            . "TEAMS=1 IDEAS=0 VETOED=0 SHOWN=1 VIOLATIONS=1\n",
            $result['text'],
        );
    }

    public function testRejectsMalformedPrefillKeys(): void
    {
        try {
            LeagueSweepCommand::candidateFromPrefill(1, self::rawItem('B', '1:900001x'), ['B' => 2], LaughTestFactory::league());
            self::fail('Expected UnexpectedValueException');
        } catch (\UnexpectedValueException $e) {
            $this->assertSame("Malformed prefill key '1:900001x'", $e->getMessage());
        }
    }

    public function testRejectsPrefillKeyWithLeadingJunk(): void
    {
        try {
            LeagueSweepCommand::candidateFromPrefill(1, self::rawItem('B', 'x1:900001'), ['B' => 2], LaughTestFactory::league());
            self::fail('Expected UnexpectedValueException');
        } catch (\UnexpectedValueException $e) {
            $this->assertSame("Malformed prefill key 'x1:900001'", $e->getMessage());
        }
    }

    public function testRejectsAssetOwnedByNeitherSide(): void
    {
        try {
            LeagueSweepCommand::candidateFromPrefill(1, self::rawItem('B', '1:777777'), ['B' => 2], LaughTestFactory::league());
            self::fail('Expected UnexpectedValueException');
        } catch (\UnexpectedValueException $e) {
            $this->assertSame("Prefill key '1:777777' belongs to neither side", $e->getMessage());
        }
    }

    public function testSplitsAssetsByOwner(): void
    {
        $picks = LaughTestFactory::pickIds();
        $item = self::rawItem('B', '1:' . LaughTestRows::DUPREE_PID . ',1:' . LaughTestRows::LAETTNER_PID . ',0:' . $picks[0]);

        $c = LeagueSweepCommand::candidateFromPrefill(1, $item, ['B' => 2], LaughTestFactory::league());

        $this->assertSame([LaughTestRows::DUPREE_PID], $c->userPids);
        $this->assertSame([LaughTestRows::LAETTNER_PID], $c->partnerPids);
        $this->assertSame([$picks[0]], $c->userPickIds);
        $this->assertSame([], $c->partnerPickIds);
    }

    /**
     * @param array<int, string> $teamNameByTid
     * @param array<int, TradeSuggestionsPanelData> $panels tid => panel; a missing tid returns null
     * @return array{text: string, code: int}
     */
    private function sweep(int $maxTeams, array $teamNameByTid, array $panels, ?\TradeSuggestions\Engine\LeagueSnapshot $league = null): array
    {
        $service = self::createStub(TradeSuggestionsServiceInterface::class);
        $service->method('buildForTeam')->willReturnCallback(
            static fn (int $tid): ?TradeSuggestionsPanelData => $panels[$tid] ?? null,
        );
        $snapshots = self::createStub(LeagueSnapshotProviderInterface::class);
        $snapshots->method('snapshot')->willReturn($league ?? LaughTestFactory::league());
        $rows = [];
        foreach ($teamNameByTid as $tid => $name) {
            $rows[] = ['teamid' => $tid, 'team_name' => $name, 'team_city' => '', 'color1' => '', 'color2' => ''];
        }
        $formRepo = self::createStub(TradeFormRepositoryInterface::class);
        $formRepo->method('getAllTeamsWithCity')->willReturn($rows);
        $out = fopen('php://memory', 'w+');
        $this->assertIsResource($out);

        $code = (new LeagueSweepCommand($service, $snapshots, VetoSet::standard(), $formRepo))->run($maxTeams, $out);

        rewind($out);

        return ['text' => (string) stream_get_contents($out), 'code' => $code];
    }

    /**
     * @param list<SuggestionItem> $items
     */
    private static function panel(array $items, ?SuggestionDiagnostics $diagnostics = null): TradeSuggestionsPanelData
    {
        return new TradeSuggestionsPanelData(new ResolvedTeamMode(TeamMode::WinNow, TeamMode::WinNow, false), $items, $diagnostics);
    }

    private static function item(string $partnerName, TradeCandidate $deal): SuggestionItem
    {
        return new SuggestionItem($partnerName, [], [], 'u', 'p', SuggestionLink::forCandidate($partnerName, $deal), 'k', 'c', []);
    }

    private static function rawItem(string $partnerName, string $prefill): SuggestionItem
    {
        return new SuggestionItem($partnerName, [], [], 'u', 'p', 'modules.php?name=Trading&prefill=' . $prefill, 'k', 'c', []);
    }
}
