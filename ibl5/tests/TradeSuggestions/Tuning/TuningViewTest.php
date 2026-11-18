<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Tuning;

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

        preg_match('/<table class="ibl-table tuning-unmatched" id="tuning-unmatched-1">(.*?)<\/table>/s', $html, $m);
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
        self::assertStringNotContainsString('<table class="ibl-table tuning-rows"', $html);
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

    /**
     * @param list<TuningRow>|null $rows
     * @param list<int> $teamsNotRun
     * @param list<TuningFeedback> $unmatched
     */
    private static function render(
        ?array $rows = null,
        string $teamName = 'Hornets',
        ?TuningFilter $filter = null,
        bool $truncated = false,
        array $teamsNotRun = [],
        int $omitted = 0,
        array $unmatched = [],
    ): string {
        $names = [];
        for ($tid = 1; $tid <= 28; $tid++) {
            $names[$tid] = 'Team ' . $tid;
        }
        $names[1] = $teamName;
        $section = new TuningTeamSection(
            tid: 1,
            teamName: $teamName,
            mode: TeamMode::Middle,
            eligiblePartners: [2, 3],
            generation: GenerationResult::empty(),
            diagnostics: new RankDiagnostics(0, 0, 0, 0, 0, [], 0, [], 0, 0),
            rows: $rows ?? [self::row()],
            omittedRows: $omitted,
            unmatchedFeedback: $unmatched,
        );
        $report = new TuningReport(
            simKey: 'sim-1',
            filter: $filter ?? new TuningFilter(1, TuningStatus::All, []),
            teamNames: $names,
            config: ['exposure_k' => 8],
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
    ): TuningRow {
        $gain = new SideGain(1, TeamMode::Middle, [0 => ['picks' => 1.5]], [0.3, 0.3, 0.2, 0.2], 1.0);

        return new TuningRow(
            tid: 1,
            partnerTid: 2,
            shapeId: 'ONE_FOR_ONE',
            candidate: new TradeCandidate(1, 2, [101], [], [201], []),
            dealKey: str_repeat('a', 40),
            coreKey: str_repeat('b', 40),
            user: $gain,
            partner: $gain,
            weakerSideTotal: 1.5,
            outcome: $outcome,
            rank: $rank,
            firedVetoIds: $vetoIds,
            detail: $detail,
            exposureEligible: true,
            feedback: $feedback,
        );
    }

    private static function feedback(string $freeText): TuningFeedback
    {
        return new TuningFeedback(1, 2, str_repeat('a', 40), str_repeat('b', 40), '{}', ['TOO_OLD'], $freeText, '2026-11-18 00:00:00');
    }
}
