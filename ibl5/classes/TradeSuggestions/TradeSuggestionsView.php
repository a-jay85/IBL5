<?php

declare(strict_types=1);

namespace TradeSuggestions;

use Security\HtmlSanitizer;
use TradeSuggestions\Feedback\HideReason;
use TradeSuggestions\Contracts\TradeSuggestionsViewInterface;

/**
 * Homepage "Trade ideas" panel for a logged-in GM.
 *
 * Every interpolated value goes through HtmlSanitizer::e(). The only raw HTML
 * appended is the CSRF hidden input built by CsrfGuard::generateToken(). The
 * card forms (hide, refresh) share one `trade_suggestion_action` token per page. The
 * panel has no submit/send control for a trade: each item links to the
 * existing Trading screen, where the GM reviews and sends it manually.
 */
class TradeSuggestionsView implements TradeSuggestionsViewInterface
{
    public const MAX_ITEMS = 3;

    private const ACTION_URL = 'modules.php?name=Trading&op=trade-suggestion-action';

    /**
     * @see TradeSuggestionsViewInterface::render()
     *
     * @param array{deal_key: string, reason_codes: string, free_text: ?string, created_at: string}|null $hiddenNote
     */
    public function render(TradeSuggestionsPanelData $d, bool $modeError = false, string $feedbackError = '', ?array $hiddenNote = null): string
    {
        $html = '<section id="trade-suggestions" class="trade-suggestions" data-component="trade-suggestions">';
        $html .= '<h2 class="trade-suggestions__title">Trade ideas</h2>';
        if ($modeError) {
            $html .= '<p class="trade-suggestions__mode-error" data-testid="trade-mode-error">'
                . HtmlSanitizer::e('Could not change mode. Please try again.') . '</p>';
        }
        $errorText = self::feedbackErrorText($feedbackError);
        if ($errorText !== '') {
            $html .= '<p class="trade-suggestions__error" data-testid="trade-suggestion-error">'
                . HtmlSanitizer::e($errorText) . '</p>';
        }
        if ($hiddenNote !== null) {
            $html .= '<p class="trade-suggestions__hidden-note" data-testid="trade-suggestion-hidden-note">Idea hidden.';
            $note = $hiddenNote['free_text'];
            if (is_string($note) && $note !== '') {
                $html .= ' Your note: ' . HtmlSanitizer::e($note);
            }
            $html .= '</p>';
        }
        $html .= $this->renderModeLine($d->mode);
        $html .= $this->renderModeForm($d->mode);
        $actionToken = \Security\CsrfGuard::generateToken('trade_suggestion_action');

        $items = array_slice($d->items, 0, self::MAX_ITEMS);
        if ($items === []) {
            $html .= '<p class="trade-suggestions__empty" data-testid="trade-suggestions-empty">No trades help both teams right now.</p>';
        } else {
            $html .= '<div class="trade-suggestions__list">';
            foreach ($items as $item) {
                $html .= $this->renderItem($item, $actionToken);
            }
            $html .= '</div>';
            $html .= $this->renderRefreshForm($actionToken, $d->eligibleCount, $d->offset);
        }

        $html .= '</section>';

        return $html;
    }

    private function renderModeLine(ResolvedTeamMode $mode): string
    {
        $label = 'Mode: ' . self::modeLabel($mode->mode);
        if (!$mode->isOverride) {
            $note = '(our guess)';
        } elseif ($mode->mode !== $mode->guessed) {
            $note = '(your choice, we guessed ' . self::modeLabel($mode->guessed) . ')';
        } else {
            $note = '(your choice)';
        }

        return '<p class="trade-suggestions__mode" data-testid="trade-suggestions-mode">'
            . HtmlSanitizer::e($label)
            . ' <span class="trade-suggestions__mode-note">' . HtmlSanitizer::e($note) . '</span></p>';
    }

    private function renderModeForm(ResolvedTeamMode $mode): string
    {
        $stored = self::storedSetting($mode);
        $options = [
            TeamModeSetting::Auto->value => 'Auto',
            TeamModeSetting::WinNow->value => 'Win now',
            TeamModeSetting::Middle->value => 'Middle',
            TeamModeSetting::Rebuild->value => 'Rebuild',
        ];

        $html = '<form class="trade-suggestions__mode-form" method="post" hx-boost="false" action="'
            . HtmlSanitizer::e('modules.php?name=Trading&op=trade-mode') . '">';
        $html .= \Security\CsrfGuard::generateToken('trade_mode');
        $html .= '<label class="trade-suggestions__mode-label" for="trade-mode-select">Team mode</label>';
        $html .= '<select id="trade-mode-select" name="trade_mode" data-testid="trade-mode-select">';
        foreach ($options as $value => $text) {
            $selected = $value === $stored->value ? ' selected' : '';
            $html .= '<option value="' . HtmlSanitizer::e($value) . '"' . $selected . '>'
                . HtmlSanitizer::e($text) . '</option>';
        }
        $html .= '</select>';
        $html .= '<button type="submit" class="trade-suggestions__mode-save">Save</button>';
        $html .= '</form>';

        return $html;
    }

    private function renderItem(SuggestionItem $item, string $actionToken): string
    {
        $html = '<article class="trade-suggestions__item" data-testid="trade-suggestion">';
        $html .= '<h3 class="trade-suggestions__partner">With ' . HtmlSanitizer::e($item->partnerTeamName) . '</h3>';
        $html .= '<div class="trade-suggestions__sides">';
        $html .= $this->renderSide('You give', $item->giveLabels);
        $html .= $this->renderSide('You get', $item->getLabels);
        $html .= '</div>';
        if ($item->profileLines !== []) {
            $html .= '<p class="trade-suggestions__profile-label">Your team after:</p>';
            $html .= '<ul class="trade-suggestions__profile" data-testid="trade-suggestion-profile">';
            foreach ($item->profileLines as $line) {
                $html .= '<li>' . HtmlSanitizer::e($line) . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '<p class="trade-suggestions__reason"><strong>Why you:</strong> ' . HtmlSanitizer::e($item->userReason) . '</p>';
        $html .= '<p class="trade-suggestions__reason"><strong>Why ' . HtmlSanitizer::e($item->partnerTeamName) . ':</strong> '
            . HtmlSanitizer::e($item->partnerReason) . '</p>';
        $html .= '<a class="trade-suggestions__open" data-testid="trade-suggestion-open" href="'
            . HtmlSanitizer::e($item->prefillUrl) . '">Open in Trading</a>';
        $html .= $this->renderHideForm($item, $actionToken);
        $html .= '</article>';

        return $html;
    }

    private function renderHideForm(SuggestionItem $item, string $actionToken): string
    {
        $idSuffix = substr($item->dealKey, 0, 8);
        $html = '<details class="trade-suggestions__hide" data-testid="trade-suggestion-hide">';
        $html .= '<summary class="trade-suggestions__hide-summary">Hide this idea</summary>';
        $html .= '<form class="trade-suggestions__hide-form" method="post" hx-boost="false" action="'
            . HtmlSanitizer::e(self::ACTION_URL) . '">';
        $html .= $actionToken;
        $html .= '<input type="hidden" name="action" value="hide">';
        $html .= '<input type="hidden" name="deal_key" value="' . HtmlSanitizer::e($item->dealKey) . '">';
        $html .= '<fieldset class="trade-suggestions__hide-reasons"><legend>Why?</legend>';
        foreach (HideReason::cases() as $reason) {
            $id = HtmlSanitizer::e('hide-' . strtolower($reason->value) . '-' . $idSuffix);
            $html .= '<label for="' . $id . '"><input type="checkbox" id="' . $id . '" name="reasons[]" value="'
                . HtmlSanitizer::e($reason->value) . '"> ' . HtmlSanitizer::e($reason->label()) . '</label>';
        }
        $html .= '</fieldset>';
        $html .= '<label class="trade-suggestions__hide-text">Anything else? '
            . '<textarea name="free_text" maxlength="500" rows="2"></textarea></label>';
        $html .= '<button type="submit" class="trade-suggestions__hide-submit" data-testid="trade-suggestion-hide-submit">Hide</button>';
        $html .= '</form></details>';

        return $html;
    }

    private function renderRefreshForm(string $actionToken, int $eligibleCount, int $offset): string
    {
        $html = '<form class="trade-suggestions__refresh-form" method="post" hx-boost="false" action="'
            . HtmlSanitizer::e(self::ACTION_URL) . '" data-eligible="' . $eligibleCount . '" data-offset="' . $offset . '">';
        $html .= $actionToken;
        $html .= '<input type="hidden" name="action" value="refresh">';
        $html .= '<button type="submit" class="trade-suggestions__refresh" data-testid="trade-suggestions-refresh">Show 3 more</button>';
        $html .= '</form>';

        return $html;
    }

    private static function feedbackErrorText(string $code): string
    {
        return match ($code) {
            'csrf', 'auth', 'action', 'method' => 'Could not hide that idea. Please try again.',
            'reason' => 'Pick at least one reason.',
            'length' => 'Keep the note under 500 characters.',
            'stale' => 'That idea is no longer on your list.',
            default => '',
        };
    }

    /**
     * @param list<string> $labels
     */
    private function renderSide(string $heading, array $labels): string
    {
        $html = '<div class="trade-suggestions__side"><span class="trade-suggestions__side-label">'
            . HtmlSanitizer::e($heading) . '</span><ul class="trade-suggestions__assets">';
        foreach ($labels as $label) {
            $html .= '<li>' . HtmlSanitizer::e($label) . '</li>';
        }
        $html .= '</ul></div>';

        return $html;
    }

    private static function modeLabel(TeamMode $mode): string
    {
        return match ($mode) {
            TeamMode::WinNow => 'Win now',
            TeamMode::Rebuild => 'Rebuild',
            TeamMode::Middle => 'Middle',
        };
    }

    /**
     * The stored setting is Auto unless the mode is a GM override, in which case
     * it is the override itself.
     */
    private static function storedSetting(ResolvedTeamMode $mode): TeamModeSetting
    {
        if (!$mode->isOverride) {
            return TeamModeSetting::Auto;
        }

        return match ($mode->mode) {
            TeamMode::WinNow => TeamModeSetting::WinNow,
            TeamMode::Rebuild => TeamModeSetting::Rebuild,
            TeamMode::Middle => TeamModeSetting::Middle,
        };
    }
}
