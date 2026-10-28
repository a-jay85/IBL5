<?php

declare(strict_types=1);

namespace OnThisDay;

use Security\HtmlSanitizer;

/**
 * Renders the "On this day" homepage card. Every interpolated value is escaped.
 */
final class OnThisDayView implements Contracts\OnThisDayViewInterface
{
    public function __construct(
        private readonly MomentFormatter $formatter = new MomentFormatter(),
        private readonly OnThisDayCalendar $calendar = new OnThisDayCalendar(),
    ) {
    }

    public function renderHomepageBlock(OnThisDaySelection $selection): string
    {
        if ($selection->isEmpty()) {
            return '';
        }

        $centerLabel = (new \DateTimeImmutable($selection->centerDate))->format('M j');

        $h  = '<section class="ibl-card" data-component="on-this-day">';
        $h .= '<div class="ibl-card__header">';
        $h .= '<h2 class="ibl-card__title">On this day</h2>';
        $h .= '<span class="ibl-card__subtitle">League history around ' . HtmlSanitizer::e($centerLabel) . '</span>';
        $h .= '</div>';
        $h .= '<div class="ibl-card__body">';
        $h .= '<ul class="on-this-day__list">';

        foreach ($selection->moments as $moment) {
            $seasonsAgo = $this->calendar->seasonsAgo($selection->currentSeasonYear, $moment->seasonYear);
            if ($seasonsAgo === null) {
                continue;
            }

            $h .= '<li class="centerbox-item" data-moment-kind="' . HtmlSanitizer::e($moment->kind->value) . '">';
            $h .= '<span class="ibl-badge ibl-badge--info on-this-day__when">' . HtmlSanitizer::e($this->formatter->seasonsAgoLabel($seasonsAgo)) . '</span> ';
            $h .= '<span>' . HtmlSanitizer::e($this->formatter->describe($moment)) . '</span> ';
            $h .= '<span class="on-this-day__date">' . HtmlSanitizer::e($this->formatter->dateLabel($moment->date)) . '</span>';
            $h .= '</li>';
        }

        $h .= '</ul>';
        $h .= '</div>';
        $h .= '</section>';

        return $h;
    }
}
