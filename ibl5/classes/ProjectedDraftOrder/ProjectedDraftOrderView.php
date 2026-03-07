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
            $html .= '<div class="ibl-alert ibl-alert--info">Admin-only message:<br>Drag the lottery teams (picks 1–12) into their final draft order, then click Save.</div>';
        }
        if ($isAdmin && $isFinalized && !$isDraftStarted) {
            $html .= '<div class="ibl-alert ibl-alert--warning">Admin-only message:<br>You can still adjust the lottery order until a player has been drafted.</div>';
        }
        $isDraggable = $isAdmin && !$isDraftStarted;
        if ($isDraggable) {
            $html .= '<button type="button" id="draft-order-save-btn" class="ibl-btn ibl-btn--danger" style="display: none; margin-bottom: 1rem;">Save Draft Order</button>';
        }
        $html .= $this->renderRoundTable($draftOrder['round1'], 'Round 1', showPlayoffDivider: true, isAdmin: $isDraggable, showPlayer: $isDraftStarted, isFinalized: $isFinalized);
        $html .= $this->renderRoundTable($draftOrder['round2'], 'Round 2', showPlayoffDivider: false, isAdmin: false, showPlayer: $isDraftStarted, isFinalized: $isFinalized);

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
    private function renderRoundTable(array $slots, string $roundLabel, bool $showPlayoffDivider, bool $isAdmin, bool $showPlayer, bool $isFinalized = false): string
    {
        $colspan = $showPlayer ? '5' : '4';
        $tableId = $roundLabel === 'Round 1' ? ' id="draft-order-round1"' : '';
        $html = '<h3 class="ibl-table-title">' . HtmlSanitizer::e($roundLabel) . '</h3>';
        $html .= '<table class="ibl-data-table projected-draft-order-table sticky-header"' . $tableId . '>';
        $html .= '<thead><tr>';
        $html .= '<th>Pick</th>';
        $html .= '<th>Team</th>';
        $html .= '<th>Record</th>';
        $html .= '<th>Notes</th>';
        if ($showPlayer) {
            $html .= '<th>Player</th>';
        }
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        foreach ($slots as $slot) {
            if ($showPlayoffDivider) {
                $separator = $this->getSeparatorLabel($slot['pick'], $isFinalized);
                if ($separator !== null) {
                    $isLotteryResults = $slot['pick'] === 1 && $isFinalized;
                    $html .= $this->renderSeparatorRow($separator, $colspan, $isLotteryResults);
                }
            }
            $isLottery = $slot['pick'] <= self::LOTTERY_PLAYOFF_BOUNDARY;
            $html .= $this->renderPickRow($slot, $isAdmin && $isLottery, $showPlayer);
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function getSeparatorLabel(int $pick, bool $isFinalized = false): ?string
    {
        return match ($pick) {
            1 => $isFinalized ? 'Lottery Results' : 'Lottery Teams',
            self::LOTTERY_PLAYOFF_BOUNDARY + 1 => 'Playoff Teams',
            self::DIVISION_WINNERS_BOUNDARY + 1 => 'Division Winners',
            self::CONFERENCE_WINNERS_BOUNDARY + 1 => 'Conference Winners',
            default => null,
        };
    }

    private function renderSeparatorRow(string $label, string $colspan = '4', bool $isLotteryResults = false): string
    {
        $classes = 'projected-draft-order-separator ibl-table-subheading';
        if ($isLotteryResults) {
            $classes .= ' projected-draft-order-lottery-results';
        }

        return '<tr class="' . $classes . '">'
            . '<td colspan="' . $colspan . '">' . HtmlSanitizer::e($label) . '</td>'
            . '</tr>';
    }

    /** @param DraftSlot $slot */
    private function renderPickRow(array $slot, bool $isDraggable, bool $showPlayer): string
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

        $pickHtml = $isDraggable
            ? '<td class="draft-pick-cell"><span class="draft-drag-handle">⠿</span>' . HtmlSanitizer::e($slot['pick']) . '</td>'
            : '<td>' . HtmlSanitizer::e($slot['pick']) . '</td>';
        $html .= $pickHtml;

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

        if ($showPlayer) {
            $html .= '<td>' . HtmlSanitizer::e($slot['player']) . '</td>';
        }

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
