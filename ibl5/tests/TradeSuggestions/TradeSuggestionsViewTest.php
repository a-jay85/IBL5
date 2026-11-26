<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeSuggestions\Feedback\HideReason;
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
            dealKey: str_repeat('a', 40),
            coreKey: str_repeat('b', 40),
            profileLines: [],
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
            dealKey: str_repeat('a', 40),
            coreKey: str_repeat('b', 40),
            profileLines: [],
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

    /**
     * A Middle mode renders its label, and the picker offers Auto, Win now,
     * Middle and Rebuild, in that order.
     */
    public function testMiddleModeRendersMiddleLabel(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData(
            new ResolvedTeamMode(TeamMode::Middle, TeamMode::Middle, false),
            [],
        ));

        self::assertStringContainsString('Mode: Middle', $html);
        self::assertStringContainsString('(our guess)', $html);
        preg_match_all('/<option value="([a-z_]+)"/', $html, $m);
        self::assertSame(['auto', 'win_now', 'middle', 'rebuild'], $m[1]);
        self::assertStringContainsString('<option value="auto" selected>', $html);
    }

    public function testModeFormOffersMiddle(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), []));

        self::assertStringContainsString('<option value="middle">Middle</option>', $html);
    }

    public function testMiddleOverrideMarksMiddleSelected(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData(
            new ResolvedTeamMode(TeamMode::Middle, TeamMode::WinNow, true),
            [],
        ));

        self::assertStringContainsString('Mode: Middle', $html);
        self::assertStringContainsString('(your choice, we guessed Win now)', $html);
        self::assertStringContainsString('<option value="middle" selected>', $html);
        self::assertSame(1, substr_count($html, ' selected>'));
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

    public function testProfileLinesRendered(): void
    {
        $item = $this->itemWith(profileLines: ['Rebounding 24th -> 11th', '<b>x</b>']);

        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), [$item]));

        self::assertStringContainsString('data-testid="trade-suggestion-profile"', $html);
        self::assertStringContainsString('Rebounding 24th -&gt; 11th', $html);
        self::assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>x</b>', $html);
    }

    public function testProfileListOmittedWhenEmpty(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), [$this->itemWith()]));

        self::assertStringNotContainsString('trade-suggestion-profile', $html);
        self::assertStringNotContainsString('Your team after', $html);
    }

    public function testHideFormCarriesDealKey(): void
    {
        $key = str_repeat('1234abcd', 5);

        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), [$this->itemWith(dealKey: $key)]));

        self::assertStringContainsString('<input type="hidden" name="deal_key" value="' . $key . '">', $html);
    }

    public function testHideFormFields(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), [$this->itemWith()]));

        preg_match_all('/name="reasons\[\]" value="([A-Z_]+)"/', $html, $m);
        $codes = array_map(static fn (HideReason $r): string => $r->value, HideReason::cases());
        self::assertSame($codes, $m[1]);
        self::assertCount(6, $m[1]);
        self::assertStringContainsString('<input type="hidden" name="action" value="hide">', $html);
        self::assertStringContainsString('name="free_text" maxlength="500"', $html);
    }

    public function testHideFormUsesActionToken(): void
    {
        $items = [
            $this->itemWith(dealKey: str_repeat('1', 40)),
            $this->itemWith(dealKey: str_repeat('2', 40)),
            $this->itemWith(dealKey: str_repeat('3', 40)),
        ];

        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), $items, null, 9, 0));

        // Mode form, three hide forms, one refresh form.
        self::assertSame(5, substr_count($html, 'name="_csrf_token"'));
        $stored = $_SESSION['_csrf_tokens'] ?? [];
        self::assertIsArray($stored);
        self::assertIsArray($stored['trade_suggestion_action']);
        self::assertCount(1, $stored['trade_suggestion_action']);
        self::assertCount(1, $stored['trade_mode']);
    }

    public function testRefreshFormOmittedWithoutItems(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), []));

        self::assertStringNotContainsString('trade-suggestions-refresh', $html);
        self::assertStringNotContainsString('trade-suggestions__refresh-form', $html);
    }

    public function testRefreshFormExposesEligibleAndOffset(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), [$this->itemWith()], null, 9, 3));

        self::assertStringContainsString('data-testid="trade-suggestions-refresh"', $html);
        self::assertStringContainsString('data-eligible="9" data-offset="3"', $html);
        self::assertStringContainsString('<input type="hidden" name="action" value="refresh">', $html);
    }

    public function testHiddenNoteEscaped(): void
    {
        $html = $this->render(
            new TradeSuggestionsPanelData($this->autoMode(), []),
            hiddenNote: $this->note('<script>alert(1)</script>'),
        );

        self::assertStringContainsString('data-testid="trade-suggestion-hidden-note"', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert', $html);
    }

    public function testHiddenNoteOmittedWhenNull(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), []));

        self::assertStringNotContainsString('trade-suggestion-hidden-note', $html);
    }

    public function testHiddenNoteWithoutTextSaysIdeaHidden(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), []), hiddenNote: $this->note(null));

        self::assertStringContainsString('Idea hidden.', $html);
        self::assertStringNotContainsString('Your note', $html);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function feedbackErrorProvider(): array
    {
        $cases = [];
        foreach (['csrf', 'auth', 'action', 'method', 'reason', 'length', 'stale'] as $code) {
            $cases[$code] = [$code];
        }

        return $cases;
    }

    #[DataProvider('feedbackErrorProvider')]
    public function testFeedbackErrorText(string $code): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), []), feedbackError: $code);

        self::assertMatchesRegularExpression(
            '/<p class="trade-suggestions__error" data-testid="trade-suggestion-error">[^<]+<\/p>/',
            $html,
        );
    }

    public function testFeedbackErrorUnknownCodeRendersNothing(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), []), feedbackError: '<img>');

        self::assertStringNotContainsString('trade-suggestion-error', $html);
        self::assertStringNotContainsString('<img>', $html);
    }

    public function testModeErrorIsOffByDefault(): void
    {
        $html = (new TradeSuggestionsView())->render(new TradeSuggestionsPanelData($this->autoMode(), []));

        self::assertStringNotContainsString('trade-mode-error', $html);
        self::assertStringNotContainsString('Could not change mode', $html);
    }

    public function testFeedbackErrorParagraphFollowsTitleWithoutDroppingEarlierMarkup(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), []), feedbackError: 'reason');

        self::assertStringStartsWith('<section id="trade-suggestions" class="trade-suggestions" data-component="trade-suggestions">', $html);
        self::assertStringContainsString(
            '<h2 class="trade-suggestions__title">Trade ideas</h2>'
            . '<p class="trade-suggestions__error" data-testid="trade-suggestion-error">Pick at least one reason.</p>',
            $html,
        );
    }

    public function testHiddenNoteWithTextFollowsTitleAndEndsWithEscapedNote(): void
    {
        $html = $this->render(
            new TradeSuggestionsPanelData($this->autoMode(), []),
            hiddenNote: $this->note('Too <b>old</b>'),
        );

        self::assertStringStartsWith('<section id="trade-suggestions"', $html);
        self::assertStringContainsString(
            '<h2 class="trade-suggestions__title">Trade ideas</h2>'
            . '<p class="trade-suggestions__hidden-note" data-testid="trade-suggestion-hidden-note">'
            . 'Idea hidden. Your note: Too &lt;b&gt;old&lt;/b&gt;</p>',
            $html,
        );
    }

    public function testHiddenNoteWithoutTextRendersBareParagraph(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), []), hiddenNote: $this->note(''));

        self::assertStringContainsString(
            '<p class="trade-suggestions__hidden-note" data-testid="trade-suggestion-hidden-note">Idea hidden.</p>',
            $html,
        );
    }

    public function testProfileBlockRendersLabelListAndItemsInOrder(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData(
            $this->autoMode(),
            [$this->itemWith(profileLines: ['First line', 'Second <i>line</i>'])],
        ));

        self::assertStringContainsString(
            '<article class="trade-suggestions__item" data-testid="trade-suggestion"><h3 class="trade-suggestions__partner">With Heat</h3>',
            $html,
        );
        self::assertStringContainsString(
            '</ul></div></div>'
            . '<p class="trade-suggestions__profile-label">Your team after:</p>'
            . '<ul class="trade-suggestions__profile" data-testid="trade-suggestion-profile">'
            . '<li>First line</li><li>Second &lt;i&gt;line&lt;/i&gt;</li></ul>'
            . '<p class="trade-suggestions__reason"><strong>Why you:</strong>',
            $html,
        );
    }

    public function testHideFormOpensWithSummaryThenFormAfterOpenLink(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), [$this->itemWith()]));

        self::assertStringContainsString(
            '>Open in Trading</a>'
            . '<details class="trade-suggestions__hide" data-testid="trade-suggestion-hide">'
            . '<summary class="trade-suggestions__hide-summary">Hide this idea</summary>'
            . '<form class="trade-suggestions__hide-form" method="post" hx-boost="false" '
            . 'action="modules.php?name=Trading&amp;op=trade-suggestion-action">',
            $html,
        );
    }

    public function testHideCheckboxIdsUseLowercaseReasonAndFirstEightKeyChars(): void
    {
        $key = 'abcdef01' . str_repeat('9', 32);

        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), [$this->itemWith(dealKey: $key)]));

        foreach (HideReason::cases() as $reason) {
            $id = 'hide-' . strtolower($reason->value) . '-abcdef01';
            self::assertStringContainsString(
                '<label for="' . $id . '"><input type="checkbox" id="' . $id . '" name="reasons[]" value="'
                . $reason->value . '"> ' . $reason->label() . '</label>',
                $html,
            );
        }
        self::assertStringContainsString('id="hide-too_old-abcdef01"', $html);
        self::assertStringContainsString('id="hide-weakens_team-abcdef01"', $html);
        self::assertStringNotContainsString('id="hide-too_old-' . $key, $html);
        self::assertStringNotContainsString('-bcdef019"', $html);
        self::assertStringNotContainsString('-abcdef0"', $html);
        self::assertStringNotContainsString('-abcdef019"', $html);
        self::assertStringNotContainsString('id="hide-TOO_OLD', $html);
    }

    public function testHideFormFreeTextLabelWrapsTextareaAndPrecedesSubmit(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), [$this->itemWith()]));

        self::assertStringContainsString(
            '</fieldset><label class="trade-suggestions__hide-text">Anything else? '
            . '<textarea name="free_text" maxlength="500" rows="2"></textarea></label>'
            . '<button type="submit" class="trade-suggestions__hide-submit"',
            $html,
        );
    }

    public function testFormsTargetTheTradingActionRoute(): void
    {
        $html = $this->render(new TradeSuggestionsPanelData($this->autoMode(), [$this->itemWith()], null, 4, 0));

        self::assertSame(
            2,
            substr_count($html, 'action="modules.php?name=Trading&amp;op=trade-suggestion-action"'),
        );
    }

    /**
     * @param array{deal_key: string, reason_codes: string, free_text: ?string, created_at: string}|null $hiddenNote
     */
    private function render(
        TradeSuggestionsPanelData $data,
        bool $modeError = false,
        string $feedbackError = '',
        ?array $hiddenNote = null,
    ): string {
        return (new TradeSuggestionsView())->render($data, $modeError, $feedbackError, $hiddenNote);
    }

    /**
     * @return array{deal_key: string, reason_codes: string, free_text: ?string, created_at: string}
     */
    private function note(?string $freeText): array
    {
        return [
            'deal_key' => str_repeat('a', 40),
            'reason_codes' => 'TOO_OLD',
            'free_text' => $freeText,
            'created_at' => '2026-11-01 10:00:00',
        ];
    }

    /**
     * @param list<string> $profileLines
     */
    private function itemWith(string $dealKey = '', array $profileLines = []): SuggestionItem
    {
        return new SuggestionItem(
            partnerTeamName: 'Heat',
            giveLabels: ['Player A'],
            getLabels: ['Player B'],
            userReason: 'Fills a hole.',
            partnerReason: 'Adds upside.',
            prefillUrl: 'modules.php?name=Trading&op=offertrade&partner=Heat',
            dealKey: $dealKey === '' ? str_repeat('a', 40) : $dealKey,
            coreKey: str_repeat('b', 40),
            profileLines: $profileLines,
        );
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
            dealKey: str_repeat('a', 40),
            coreKey: str_repeat('b', 40),
            profileLines: [],
        );
    }
}
