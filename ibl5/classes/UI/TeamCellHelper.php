<?php

declare(strict_types=1);

namespace UI;

use UI\Contracts\TeamCellHelperInterface;
use Utilities\HtmlSanitizer;

/**
 * @see TeamCellHelperInterface
 */
class TeamCellHelper implements TeamCellHelperInterface
{
    /**
     * @see TeamCellHelperInterface::renderTeamCell()
     */
    public static function renderTeamCell(
        int $teamId,
        string $teamName,
        string $color1,
        string $color2,
        string $extraClasses = '',
        string $linkUrl = '',
        string $nameHtml = '',
    ): string {
        $safeColor1 = TableStyles::sanitizeColor($color1);
        $safeColor2 = TableStyles::sanitizeColor($color2);

        $classes = 'ibl-team-cell--colored';
        if ($extraClasses !== '') {
            $classes .= ' ' . $extraClasses;
        }

        $href = $linkUrl !== '' ? $linkUrl : self::teamPageUrl($teamId);

        $safeName = $nameHtml !== '' ? $nameHtml : HtmlSanitizer::safeHtmlOutput($teamName);

        return '<td class="' . $classes . '" style="background-color: #' . $safeColor1 . ';">'
            . '<a href="' . $href . '" class="ibl-team-cell__name" style="color: #' . $safeColor2 . ';">'
            . '<img src="images/logo/new' . $teamId . '.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">'
            . '<span class="ibl-team-cell__text">' . $safeName . '</span>'
            . '</a></td>';
    }

    /**
     * @see TeamCellHelperInterface::renderTeamCellOrFreeAgent()
     */
    public static function renderTeamCellOrFreeAgent(
        int $teamId,
        string $teamName,
        string $color1,
        string $color2,
        string $extraClasses = '',
        string $freeAgentText = 'Free Agent',
    ): string {
        if ($teamId === 0) {
            $safeFreeAgentText = HtmlSanitizer::safeHtmlOutput($freeAgentText);
            if ($extraClasses !== '') {
                return '<td class="' . $extraClasses . '">' . $safeFreeAgentText . '</td>';
            }
            return '<td>' . $safeFreeAgentText . '</td>';
        }

        return self::renderTeamCell($teamId, $teamName, $color1, $color2, $extraClasses);
    }

    /**
     * @see TeamCellHelperInterface::teamPageUrl()
     */
    public static function teamPageUrl(int $teamId, ?int $year = null): string
    {
        $url = 'modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId;
        if ($year !== null) {
            $url .= '&amp;yr=' . $year;
        }
        return $url;
    }
}
