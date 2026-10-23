<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\ResolvedTeamMode;
use TradeSuggestions\SuggestionItem;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeSuggestionsPanelData;
use TradeSuggestions\TradeSuggestionsView;

class TradeSuggestionsViewTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testRendersAtMostThreeItemsWithOpenLink(): void
    {
        $items = [];
        for ($i = 1; $i <= 4; $i++) {
            $items[] = $this->item(partner: 'Team' . $i, url: 'modules.php?name=Trading&op=offertrade&partner=Team' . $i);
        }

        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), $items));

        self::assertStringContainsString('data-component="trade-suggestions"', $html);
        self::assertSame(3, substr_count($html, 'data-testid="trade-suggestion"'));
        self::assertSame(3, substr_count($html, 'data-testid="trade-suggestion-open"'));
        self::assertStringContainsString(
            'href="modules.php?name=Trading&amp;op=offertrade&amp;partner=Team1">Open in Trading</a>',
            $html,
        );
        self::assertStringNotContainsString('Team4', $html);
        self::assertStringNotContainsString('maketradeoffer', $html);
        self::assertStringNotContainsString('data-testid="trade-suggestions-empty"', $html);
    }

    public function testRendersEmptyState(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), []));

        self::assertStringContainsString(
            '<p class="trade-suggestions__empty" data-testid="trade-suggestions-empty">No trades help both teams right now.</p>',
            $html,
        );
        self::assertStringNotContainsString('data-testid="trade-suggestion"', $html);
        self::assertStringContainsString('Trade ideas', $html);
    }

    public function testEscapesPlayerNameAndReason(): void
    {
        $item = new SuggestionItem(
            partnerTeamName: 'Heat',
            giveLabels: ['<script>x</script>'],
            getLabels: ['Joe "Quote" O\'Neil'],
            userReason: '<img src=x onerror=alert(1)>',
            partnerReason: 'Adds a 2027 round 1 draft pick.',
            prefillUrl: 'modules.php?name=Trading&op=offertrade&partner=%22x',
        );

        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), [$item]));

        self::assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>x</script>', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('Joe &quot;Quote&quot; O&apos;Neil', $html);
    }

    public function testReasonWithScriptTagInPlayerNameIsEscaped(): void
    {
        $item = new SuggestionItem(
            partnerTeamName: 'Heat',
            giveLabels: ['<script>alert(1)</script> PG'],
            getLabels: ['Plain Name'],
            userReason: 'Moving <script>alert(1)</script> clears room.',
            partnerReason: 'They add a starter.',
            prefillUrl: 'modules.php?name=Trading&op=offertrade&partner=Heat',
        );

        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), [$item]));

        self::assertStringContainsString('Moving &lt;script&gt;alert(1)&lt;/script&gt; clears room.', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; PG', $html);
        self::assertStringNotContainsString('<script>alert', $html);
    }

    public function testShowsGuessLabelAndOverrideLabel(): void
    {
        $guess = $this->render(new TradeSuggestionsPanelData(
            new ResolvedTeamMode(TeamMode::Rebuild, TeamMode::Rebuild, false),
            [],
        ));
        self::assertStringContainsString('Mode: Rebuild', $guess);
        self::assertStringContainsString('(our guess)', $guess);
        self::assertStringNotContainsString('your choice', $guess);

        $override = $this->render(new TradeSuggestionsPanelData(
            new ResolvedTeamMode(TeamMode::WinNow, TeamMode::Rebuild, true),
            [],
        ));
        self::assertStringContainsString('Mode: Win now', $override);
        self::assertStringContainsString('(your choice, we guessed Rebuild)', $override);
        self::assertStringNotContainsString('(our guess)', $override);
    }

    public function testMarksStoredSettingSelected(): void
    {
        $auto = $this->render(new TradeSuggestionsPanelData($this->autoMode(), []));
        self::assertStringContainsString('<option value="auto" selected>', $auto);
        self::assertStringContainsString('<option value="win_now">', $auto);
        self::assertStringContainsString('<option value="rebuild">', $auto);

        $rebuild = $this->render(new TradeSuggestionsPanelData(
            new ResolvedTeamMode(TeamMode::Rebuild, TeamMode::WinNow, true),
            [],
        ));
        self::assertStringContainsString('<option value="rebuild" selected>', $rebuild);
        self::assertStringContainsString('<option value="auto">', $rebuild);
        self::assertSame(1, substr_count($rebuild, ' selected>'));
        self::assertStringContainsString('name="trade_mode" data-testid="trade-mode-select"', $rebuild);
    }

    public function testRendersFixedModeErrorNotice(): void
    {
        $data = new TradeSuggestionsPanelData($this->autoMode(), []);

        $withError = (new TradeSuggestionsView())->render($data, true);
        self::assertStringContainsString(
            '<p class="trade-suggestions__mode-error" data-testid="trade-mode-error">Could not change mode. Please try again.</p>',
            $withError,
        );
        self::assertStringContainsString('<section id="trade-suggestions"', $withError);

        $withoutError = (new TradeSuggestionsView())->render($data, false);
        self::assertStringNotContainsString('trade-mode-error', $withoutError);
        self::assertStringNotContainsString('Could not change mode', $this->render($data));
    }

    public function testFormCarriesCsrfFieldAndPostsToTradeMode(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), []));

        self::assertMatchesRegularExpression(
            '/<form class="trade-suggestions__mode-form" method="post" hx-boost="false" action="[^"]*op=trade-mode">\s*'
            . '<input type="hidden" name="_csrf_token" value="[0-9a-f]{64}">/',
            $html,
        );
        self::assertSame(1, substr_count($html, 'name="_csrf_token"'));
    }

    private function render(TradeSuggestionsPanelData $data): string
    {
        return (new TradeSuggestionsView())->render($data);
    }

    private function autoMode(): ResolvedTeamMode
    {
        return new ResolvedTeamMode(TeamMode::WinNow, TeamMode::WinNow, false);
    }

    private function item(string $partner, string $url): SuggestionItem
    {
        return new SuggestionItem(
            partnerTeamName: $partner,
            giveLabels: ['Player A'],
            getLabels: ['2027 Round 1 (Heat)'],
            userReason: 'Fills a hole at big.',
            partnerReason: 'Adds young upside.',
            prefillUrl: $url,
        );
    }
}
