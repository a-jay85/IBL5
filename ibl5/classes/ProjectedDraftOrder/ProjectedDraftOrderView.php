<?php

declare(strict_types=1);

namespace ProjectedDraftOrder;

use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderViewInterface;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;

/**
 * @phpstan-import-type DraftSlot from ProjectedDraftOrderServiceInterface
 * @phpstan-import-type ProjectedDraftOrderResult from ProjectedDraftOrderServiceInterface
 * @see ProjectedDraftOrderViewInterface
 */
class ProjectedDraftOrderView implements ProjectedDraftOrderViewInterface
{
    private const LOTTERY_PLAYOFF_BOUNDARY = 12;
    private const DIVISION_WINNERS_BOUNDARY = 24;
    private const CONFERENCE_WINNERS_BOUNDARY = 26;

    /** @param ProjectedDraftOrderResult $draftOrder */
    public function render(array $draftOrder, int $seasonYear, bool $isAdmin = false, bool $isFinalized = false, bool $isDraftStarted = false): string
    {
        $html = $this->renderTitle($seasonYear, $isFinalized);
        if (!$isFinalized) {
            $html .= $this->renderDescription();
        }
        if ($isAdmin && !$isFinalized) {
            $html .= '<div class="ibl-alert ibl-alert--info">Drag the lottery teams (picks 1–12) into their final draft order, then click Save.</div>';
        }
        if ($isAdmin && $isFinalized && !$isDraftStarted) {
            $html .= '<div class="ibl-alert ibl-alert--warning">The lottery order can still be changed until a player has been drafted.</div>';
        }
        if ($isAdmin) {
            $html .= '<button type="button" id="draft-order-save-btn" class="ibl-btn ibl-btn--danger" style="display: none; margin-bottom: 1rem;">Save Draft Order</button>';
        }
        $html .= $this->renderRoundTable($draftOrder['round1'], 'Round 1', showPlayoffDivider: true, isAdmin: $isAdmin);
        $html .= $this->renderRoundTable($draftOrder['round2'], 'Round 2', showPlayoffDivider: false, isAdmin: false);

        return $html;
    }

    private function renderTitle(int $seasonYear, bool $isFinalized): string
    {
        $label = $isFinalized ? 'Draft Order' : 'Projected Draft Order';

        return '<h2 class="ibl-title">' . HtmlSanitizer::e($seasonYear) . ' ' . $label . '</h2>';
    }

    private function renderDescription(): string
    {
        return '<p class="projected-draft-order-description">'
            . 'If the draft were held today, this is the projected pick order based on current standings. '
            . '</p>';
    }

    /**
     * @param list<DraftSlot> $slots
     */
    private function renderRoundTable(array $slots, string $roundLabel, bool $showPlayoffDivider, bool $isAdmin): string
    {
        $tableId = $roundLabel === 'Round 1' ? ' id="draft-order-round1"' : '';
        $html = '<h3 class="ibl-table-title">' . HtmlSanitizer::e($roundLabel) . '</h3>';
        $html .= '<table class="ibl-data-table projected-draft-order-table"' . $tableId . '>';
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
            $isLottery = $slot['pick'] <= self::LOTTERY_PLAYOFF_BOUNDARY;
            $html .= $this->renderPickRow($slot, $isAdmin && $isLottery);
        }

        $html .= '</tbody></table>';

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
        return '<tr class="projected-draft-order-separator ibl-table-subheading">'
            . '<td colspan="4">' . HtmlSanitizer::e($label) . '</td>'
            . '</tr>';
    }

    /** @param DraftSlot $slot */
    private function renderPickRow(array $slot, bool $isDraggable = false): string
    {
        $classes = [];
        if ($slot['isTraded']) {
            $classes[] = 'projected-draft-order-traded';
        }
        if ($slot['movement'] > 0) {
            $classes[] = 'draft-moved-up';
        } elseif ($slot['movement'] < 0) {
            $classes[] = 'draft-moved-down';
        }
        if ($isDraggable) {
            $classes[] = 'draft-draggable';
        }
        $classAttr = $classes !== [] ? ' class="' . implode(' ', $classes) . '"' : '';
        $dragAttr = $isDraggable ? ' draggable="true" data-team-id="' . HtmlSanitizer::e($slot['teamId']) . '"' : '';
        $html = '<tr' . $classAttr . $dragAttr . '>';

        $html .= '<td>' . HtmlSanitizer::e($slot['pick']) . '</td>';

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
        $titleAttr = $slot['notes'] !== '' ? ' title="Click/tap to expand"' : '';
        $html .= '<td class="projected-draft-order-notes"' . $titleAttr . ' onclick="this.classList.toggle(\'is-expanded\')">'
            . HtmlSanitizer::safeHtmlOutput($slot['notes']) . '</td>';

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
