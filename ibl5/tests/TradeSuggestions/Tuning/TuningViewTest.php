<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Tuning;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\GenerationResult;
use TradeSuggestions\Engine\RankDiagnostics;
use TradeSuggestions\Engine\RankOutcome;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Tuning\TuningFeedback;
use TradeSuggestions\Tuning\TuningFilter;
use TradeSuggestions\Tuning\TuningReport;
use TradeSuggestions\Tuning\TuningRow;
use TradeSuggestions\Tuning\TuningStatus;
use TradeSuggestions\Tuning\TuningTeamSection;
use TradeSuggestions\Tuning\TuningView;

final class TuningViewTest extends TestCase
{
    public function testStoredScriptTagRendersEscaped(): void
    {
        $html = self::render(rows: [self::row(feedback: self::feedback('<script>alert(1)</script>'))]);

        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testUnmatchedFeedbackTableIsEscaped(): void
    {
        $html = self::render(unmatched: [self::feedback('<script>alert("tuning")</script> & co')]);

        preg_match('/<table class="ibl-data-table tuning-unmatched" id="tuning-unmatched-1">(.*?)<\/table>/s', $html, $m);
        $table = $m[1] ?? '';
        self::assertStringContainsString('&lt;script&gt;alert(&quot;tuning&quot;)&lt;/script&gt; &amp; co', $table);
        self::assertStringContainsString('Too old', $table);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('tuning-unmatched', self::render());
    }

    public function testTeamNameWithAmpersandIsEscaped(): void
    {
        $html = self::render(teamName: 'Fire & Ice');

        self::assertStringContainsString('Fire &amp; Ice', $html);
        self::assertStringNotContainsString('Fire & Ice', $html);
    }

    public function testDetailAndVetoIdsAreEscaped(): void
    {
        $html = self::render(rows: [self::row(detail: '<b>FAIL_CAP</b>', vetoIds: ['<i>VETO</i>'])]);

        self::assertStringContainsString('&lt;b&gt;FAIL_CAP&lt;/b&gt;', $html);
        self::assertStringContainsString('&lt;i&gt;VETO&lt;/i&gt;', $html);
        self::assertStringNotContainsString('<b>', $html);
        self::assertStringNotContainsString('<i>', $html);
    }

    public function testEmptySectionShowsNoSuggestionsNote(): void
    {
        $html = self::render(rows: []);

        self::assertStringContainsString('No suggestions for this team in this sim.', $html);
        self::assertStringNotContainsString('<table class="ibl-data-table tuning-rows"', $html);
    }

    public function testIgnoredFilterNoteNamesTheParameter(): void
    {
        $ignored = self::render(filter: new TuningFilter(1, TuningStatus::All, ['team']));
        $clean = self::render(filter: new TuningFilter(1, TuningStatus::All, []));

        self::assertMatchesRegularExpression('/id="tuning-ignored">[^<]*team/', $ignored);
        self::assertStringNotContainsString('id="tuning-ignored"', $clean);
    }

    public function testTruncatedNoteListsTeamsNotRun(): void
    {
        $html = self::render(truncated: true, teamsNotRun: [4, 5, 6]);

        self::assertMatchesRegularExpression('/id="tuning-truncated">[^<]*4, 5, 6/', $html);
        self::assertStringNotContainsString('id="tuning-truncated"', self::render());
    }

    public function testSelectedOptionsFollowTheFilter(): void
    {
        $html = self::render(filter: new TuningFilter(7, TuningStatus::Vetoed, []));

        preg_match_all('/<option value="([^"]*)" selected>/', $html, $m);
        self::assertSame(['7', 'vetoed'], $m[1]);
    }

    public function testTeamSelectListsEveryFranchiseForAOneTeamRun(): void
    {
        $html = self::render();

        preg_match('/<select name="team" id="tuning-team">(.*?)<\/select>/s', $html, $m);
        self::assertSame(29, substr_count($m[1] ?? '', '<option '));
    }

    public function testOmittedRowsNoteAppearsOnlyWhenCapHit(): void
    {
        self::assertStringContainsString('40 more rows not shown (cap 60).', self::render(omitted: 40));
        self::assertStringNotContainsString('more rows not shown', self::render(omitted: 0));
    }

    public function testHugeFreeTextIsRenderedWhole(): void
    {
        $note = str_repeat('abcdefghi<', 50);
        $html = self::render(rows: [self::row(feedback: self::feedback($note))]);

        preg_match('/<span class="tuning-note">(.*?)<\/span>/s', $html, $m);
        $span = $m[1] ?? '';
        self::assertSame(50, substr_count($span, '&lt;'));
        self::assertStringNotContainsString('<', $span);
    }

    public function testEveryRowCarriesDataOutcome(): void
    {
        $html = self::render(rows: [
            self::row(outcome: RankOutcome::Selected, rank: 1),
            self::row(outcome: RankOutcome::Vetoed),
            self::row(outcome: RankOutcome::Hidden),
        ]);

        preg_match_all('/<tr data-outcome="([a-z_]+)">/', $html, $m);
        self::assertSame(['selected', 'vetoed', 'hidden'], $m[1]);
    }

    public function testTruncatedNoteShowsElapsedAndBudgetSeconds(): void
    {
        $html = self::render(truncated: true, teamsNotRun: [4]);

        self::assertStringContainsString('Stopped after 21.5 s (budget 20 s). Teams not run: 4</div>', $html);
    }

    public function testRowCellsShowRankPartnerWeakerSideAndExposure(): void
    {
        $html = self::render(rows: [
            self::row(rank: 4, weakerSideTotal: 1.2345),
            self::row(rank: null, partnerTid: 99, exposureEligible: false),
        ]);

        preg_match_all('/<tr data-outcome="[a-z_]+">(.*?)<\/tr>/s', $html, $m);
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $m[1][0] ?? '', $first);
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $m[1][1] ?? '', $second);
        self::assertSame(['4', 'Eligible (later refresh)', 'Team 2', 'ONE_FOR_ONE', '1.23'], array_slice($first[1], 0, 5));
        self::assertSame('yes', $first[1][9] ?? null);
        self::assertSame(['-', 'Eligible (later refresh)', '99'], array_slice($second[1], 0, 3));
        self::assertSame('no', $second[1][9] ?? null);
    }

    public function testGainBySeasonListsEachSeasonWithTwoDecimals(): void
    {
        $twoSeasons = new SideGain(1, TeamMode::Middle, [0 => ['picks' => 1.234], 1 => ['cap' => 0.5]], [0.5, 0.5], 1.0);
        $none = new SideGain(1, TeamMode::Middle, [], [], 1.0);
        $html = self::render(rows: [self::row(gain: $twoSeasons), self::row(gain: $none)]);

        preg_match_all('/<tr data-outcome="[a-z_]+">(.*?)<\/tr>/s', $html, $m);
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $m[1][0] ?? '', $first);
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $m[1][1] ?? '', $second);
        self::assertSame(['1.23 / 0.50', '1.23 / 0.50'], array_slice($first[1], 5, 2));
        self::assertSame(['-', '-'], array_slice($second[1], 5, 2));
    }

    public function testUnmatchedPartnerShowsNameOrTid(): void
    {
        $known = self::render(unmatched: [self::feedback('x', partnerTid: 2)]);
        $unknown = self::render(unmatched: [self::feedback('x', partnerTid: 99)]);

        self::assertMatchesRegularExpression('/id="tuning-unmatched-1">.*?<tbody>\s*<tr>\s*<td>Team 2<\/td>/s', $known);
        self::assertMatchesRegularExpression('/id="tuning-unmatched-1">.*?<tbody>\s*<tr>\s*<td>99<\/td>/s', $unknown);
    }

    /**
     * @return array<string, array{TeamMode, string}>
     */
    public static function modeProvider(): array
    {
        return [
            'win now' => [TeamMode::WinNow, 'Win now'],
            'rebuild' => [TeamMode::Rebuild, 'Rebuild'],
            'middle' => [TeamMode::Middle, 'Middle'],
        ];
    }

    #[DataProvider('modeProvider')]
    public function testModeLabel(TeamMode $mode, string $label): void
    {
        self::assertStringContainsString('<tr><th scope="row">Mode</th><td>' . $label . '</td></tr>', self::render(mode: $mode));
    }

    public function testCountsListShowsCappedAndDiagnostics(): void
    {
        $diagnostics = new RankDiagnostics(7, 1, 2, 3, 4, ['V1' => 2], 5, [], 6, 3);
        $capped = self::render(capped: true, diagnostics: $diagnostics);
        $open = self::render(capped: false);

        self::assertStringContainsString('<tr><th scope="row">Capped</th><td>yes</td></tr>', $capped);
        self::assertStringContainsString('<tr><th scope="row">Capped</th><td>no</td></tr>', $open);
        self::assertStringContainsString('<tr><th scope="row">generated</th><td>7</td></tr>', $capped);
        self::assertStringContainsString('<tr><th scope="row">validator_rejections</th><td>-</td></tr>', $capped);
    }

    public function testVetoCountsShowOneLinePerId(): void
    {
        $html = self::render(diagnostics: new RankDiagnostics(0, 0, 0, 0, 0, ['V1' => 2, '<V2>' => 0], 0, [], 0, 0));

        self::assertStringContainsString('<div class="tuning-count-line">V1: 2</div>', $html);
        self::assertStringContainsString('<div class="tuning-count-line">&lt;V2&gt;: 0</div>', $html);
    }

    public function testConfigValuesArePrettyPrintedOneEntryPerLine(): void
    {
        $html = self::render(config: ['veto_ids' => ['A_B', 'C/D'], 'ranker' => ['keep_top' => 12]]);

        self::assertStringContainsString("<pre class=\"tuning-json\">[\n    &quot;A_B&quot;,\n    &quot;C/D&quot;\n]</pre>", $html);
        self::assertStringContainsString("<pre class=\"tuning-json\">{\n    &quot;keep_top&quot;: 12\n}</pre>", $html);
    }

    /**
     * @return array<string, array{RankOutcome, string}>
     */
    public static function outcomeProvider(): array
    {
        return [
            'selected' => [RankOutcome::Selected, 'Shown (top 3)'],
            'eligible' => [RankOutcome::Eligible, 'Eligible (later refresh)'],
            'not both positive' => [RankOutcome::NotBothPositive, 'One side does not gain'],
            'lopsided' => [RankOutcome::Lopsided, 'Lopsided'],
            'exposure' => [RankOutcome::Exposure, 'Partner not open to this team'],
            'vetoed' => [RankOutcome::Vetoed, 'Vetoed'],
            'validator' => [RankOutcome::Validator, 'Refused by trade rules'],
            'no motive' => [RankOutcome::NoMotive, 'No reason for one side'],
            'not reached' => [RankOutcome::NotReached, 'Not reached (cap hit)'],
            'hidden' => [RankOutcome::Hidden, 'Hidden by the GM'],
        ];
    }

    #[DataProvider('outcomeProvider')]
    public function testOutcomeCellShowsItsLabel(RankOutcome $outcome, string $label): void
    {
        $html = self::render(rows: [self::row(outcome: $outcome)]);

        self::assertMatchesRegularExpression('/<tr data-outcome="' . $outcome->value . '">\s*<td>[^<]*<\/td>\s*<td class="tuning-outcome">' . preg_quote($label, '/') . '<\/td>/', $html);
    }

    /**
     * @param list<TuningRow>|null $rows
     * @param list<int> $teamsNotRun
     * @param list<TuningFeedback> $unmatched
     * @param array<string, mixed> $config
     */
    private static function render(
        ?array $rows = null,
        string $teamName = 'Hornets',
        ?TuningFilter $filter = null,
        bool $truncated = false,
        array $teamsNotRun = [],
        int $omitted = 0,
        array $unmatched = [],
        TeamMode $mode = TeamMode::Middle,
        bool $capped = false,
        ?RankDiagnostics $diagnostics = null,
        array $config = ['exposure_k' => 8],
    ): string {
        $names = [];
        for ($tid = 1; $tid <= 28; $tid++) {
            $names[$tid] = 'Team ' . $tid;
        }
        $names[1] = $teamName;
        $section = new TuningTeamSection(
            tid: 1,
            teamName: $teamName,
            mode: $mode,
            eligiblePartners: [2, 3],
            generation: new GenerationResult([], 0, 0, [], [], $capped, []),
            diagnostics: $diagnostics ?? new RankDiagnostics(0, 0, 0, 0, 0, [], 0, [], 0, 0),
            rows: $rows ?? [self::row()],
            omittedRows: $omitted,
            unmatchedFeedback: $unmatched,
        );
        $report = new TuningReport(
            simKey: 'sim-1',
            filter: $filter ?? new TuningFilter(1, TuningStatus::All, []),
            teamNames: $names,
            config: $config,
            sections: [$section],
            truncated: $truncated,
            teamsNotRun: $teamsNotRun,
            elapsedSeconds: 21.5,
        );

        return (new TuningView())->render($report);
    }

    /**
     * @param list<string> $vetoIds
     */
    private static function row(
        RankOutcome $outcome = RankOutcome::Eligible,
        ?int $rank = 4,
        ?string $detail = null,
        array $vetoIds = [],
        ?TuningFeedback $feedback = null,
        int $partnerTid = 2,
        float $weakerSideTotal = 1.5,
        bool $exposureEligible = true,
        ?SideGain $gain = null,
    ): TuningRow {
        $gain ??= new SideGain(1, TeamMode::Middle, [0 => ['picks' => 1.5]], [0.3, 0.3, 0.2, 0.2], 1.0);

        return new TuningRow(
            tid: 1,
            partnerTid: $partnerTid,
            shapeId: 'ONE_FOR_ONE',
            candidate: new TradeCandidate(1, 2, [101], [], [201], []),
            dealKey: str_repeat('a', 40),
            coreKey: str_repeat('b', 40),
            user: $gain,
            partner: $gain,
            weakerSideTotal: $weakerSideTotal,
            outcome: $outcome,
            rank: $rank,
            firedVetoIds: $vetoIds,
            detail: $detail,
            exposureEligible: $exposureEligible,
            feedback: $feedback,
        );
    }

    private static function feedback(string $freeText, int $partnerTid = 2): TuningFeedback
    {
        return new TuningFeedback(1, $partnerTid, str_repeat('a', 40), str_repeat('b', 40), '{}', ['TOO_OLD'], $freeText, '2026-11-18 00:00:00');
    }
}
