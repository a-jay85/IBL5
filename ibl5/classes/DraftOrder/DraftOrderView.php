<?php

declare(strict_types=1);

namespace DraftOrder;

use DraftOrder\Contracts\DraftOrderServiceInterface;
use DraftOrder\Contracts\DraftOrderViewInterface;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;

/**
 * @phpstan-import-type DraftSlot from DraftOrderServiceInterface
 * @phpstan-import-type DraftOrderResult from DraftOrderServiceInterface
 * @see DraftOrderViewInterface
 */
class DraftOrderView implements DraftOrderViewInterface
{
    private const LOTTERY_PLAYOFF_BOUNDARY = 12;
    private const DIVISION_WINNERS_BOUNDARY = 24;
    private const CONFERENCE_WINNERS_BOUNDARY = 26;

    /** @param DraftOrderResult $draftOrder */
    public function render(array $draftOrder, int $seasonYear): string
    {
        $html = '<div class="draft-order-container">';
        $html .= $this->renderTitle($seasonYear);
        $html .= $this->renderDescription();
        $html .= $this->renderRoundTable($draftOrder['round1'], 'Round 1', showPlayoffDivider: true);
        $html .= $this->renderRoundTable($draftOrder['round2'], 'Round 2', showPlayoffDivider: false);
        $html .= '</div>';

        return $html;
    }

    private function renderTitle(int $seasonYear): string
    {
        return '<h2>' . HtmlSanitizer::safeHtmlOutput($seasonYear) . ' Projected Draft Order</h2>';
    }

    private function renderDescription(): string
    {
        return '<p class="draft-order-description">'
            . 'If the draft were held today, this is the projected pick order based on current standings. '
            . 'Non-playoff teams (picks 1-12) are ordered by worst record first. '
            . 'Playoff teams (picks 13-28) are ordered by regular season record.'
            . '</p>';
    }

    /**
     * @param list<DraftSlot> $slots
     */
    private function renderRoundTable(array $slots, string $roundLabel, bool $showPlayoffDivider): string
    {
        $html = '<h3>' . HtmlSanitizer::safeHtmlOutput($roundLabel) . '</h3>';
        $html .= '<div class="table-container">';
        $html .= '<table class="ibl-data-table draft-order-table">';
        $html .= '<thead><tr>';
        $html .= '<th>Pick</th>';
        $html .= '<th>Team</th>';
        $html .= '<th>Record</th>';
        $html .= '<th>Notes</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        foreach ($slots as $slot) {
            if ($showPlayoffDivider) {
                $separator = $this->getSeparatorLabel($slot['pick']);
                if ($separator !== null) {
                    $html .= $this->renderSeparatorRow($separator);
                }
            }
            $html .= $this->renderPickRow($slot);
        }

        $html .= '</tbody></table>';
        $html .= '</div>';

        return $html;
    }

    private function getSeparatorLabel(int $pick): ?string
    {
        return match ($pick) {
            1 => 'Lottery Teams',
            self::LOTTERY_PLAYOFF_BOUNDARY + 1 => 'Playoff Teams',
            self::DIVISION_WINNERS_BOUNDARY + 1 => 'Division Winners',
            self::CONFERENCE_WINNERS_BOUNDARY + 1 => 'Conference Winners',
            default => null,
        };
    }

    private function renderSeparatorRow(string $label): string
    {
        return '<tr class="draft-order-separator">'
            . '<td colspan="4">' . HtmlSanitizer::e($label) . '</td>'
            . '</tr>';
    }

    /** @param DraftSlot $slot */
    private function renderPickRow(array $slot): string
    {
        $rowClass = $slot['isTraded'] ? ' class="draft-order-traded"' : '';
        $html = '<tr' . $rowClass . '>';

        $html .= '<td>' . HtmlSanitizer::safeHtmlOutput($slot['pick']) . '</td>';

        if ($slot['isTraded']) {
            $html .= TeamCellHelper::renderTeamCell(
                $slot['ownerId'],
                $slot['ownerName'],
                $slot['ownerColor1'],
                $slot['ownerColor2'],
                nameHtml: HtmlSanitizer::e($slot['ownerName']) . '*',
            );
        } else {
            $html .= TeamCellHelper::renderTeamCell(
                $slot['teamId'],
                $slot['teamName'],
                $slot['color1'],
                $slot['color2'],
            );
        }

        $html .= $this->renderRecordCell($slot);
        $html .= '<td>' . HtmlSanitizer::safeHtmlOutput($slot['notes']) . '</td>';

        $html .= '</tr>';
        return $html;
    }

    /** @param DraftSlot $slot */
    private function renderRecordCell(array $slot): string
    {
        $recordHtml = HtmlSanitizer::e($slot['wins']) . '-' . HtmlSanitizer::e($slot['losses']);

        $cell = TeamCellHelper::renderTeamCell(
            $slot['teamId'],
            $slot['teamName'],
            $slot['color1'],
            $slot['color2'],
            nameHtml: $recordHtml,
        );

        return $cell;
    }
}
