<?php

declare(strict_types=1);

namespace TradeSuggestions;

use Security\HtmlSanitizer;
use TradeSuggestions\Contracts\TradeSuggestionsViewInterface;

/**
 * Homepage "Trade ideas" panel for a logged-in GM.
 *
 * Every interpolated value goes through HtmlSanitizer::e(). The only raw HTML
 * appended is the CSRF hidden input built by CsrfGuard::generateToken(). The
 * panel has no submit/send control for a trade: each item links to the
 * existing Trading screen, where the GM reviews and sends it manually.
 */
class TradeSuggestionsView implements TradeSuggestionsViewInterface
{
    public const MAX_ITEMS = 3;

    public function render(TradeSuggestionsPanelData $d, bool $modeError = false): string
    {
        $html = '<section id="trade-suggestions" class="trade-suggestions" data-component="trade-suggestions">';
        $html .= '<h2 class="trade-suggestions__title">Trade ideas</h2>';
        if ($modeError) {
            $html .= '<p class="trade-suggestions__mode-error" data-testid="trade-mode-error">'
                . HtmlSanitizer::e('Could not change mode. Please try again.') . '</p>';
        }
        $html .= $this->renderModeLine($d->mode);
        $html .= $this->renderModeForm($d->mode);

        $items = array_slice($d->items, 0, self::MAX_ITEMS);
        if ($items === []) {
            $html .= '<p class="trade-suggestions__empty" data-testid="trade-suggestions-empty">No trades help both teams right now.</p>';
        } else {
            $html .= '<div class="trade-suggestions__list">';
            foreach ($items as $item) {
                $html .= $this->renderItem($item);
            }
            $html .= '</div>';
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
            TeamModeSetting::Rebuild->value => 'Rebuild',
        ];

        $html = '<form class="trade-suggestions__mode-form" method="post" action="'
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

    private function renderItem(SuggestionItem $item): string
    {
        $html = '<article class="trade-suggestions__item" data-testid="trade-suggestion">';
        $html .= '<h3 class="trade-suggestions__partner">With ' . HtmlSanitizer::e($item->partnerTeamName) . '</h3>';
        $html .= '<div class="trade-suggestions__sides">';
        $html .= $this->renderSide('You give', $item->giveLabels);
        $html .= $this->renderSide('You get', $item->getLabels);
        $html .= '</div>';
        $html .= '<p class="trade-suggestions__reason"><strong>Why you:</strong> ' . HtmlSanitizer::e($item->userReason) . '</p>';
        $html .= '<p class="trade-suggestions__reason"><strong>Why ' . HtmlSanitizer::e($item->partnerTeamName) . ':</strong> '
            . HtmlSanitizer::e($item->partnerReason) . '</p>';
        $html .= '<a class="trade-suggestions__open" data-testid="trade-suggestion-open" href="'
            . HtmlSanitizer::e($item->prefillUrl) . '">Open in Trading</a>';
        $html .= '</article>';

        return $html;
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
        };
    }
}
