<?php

declare(strict_types=1);

namespace Player\CareerArc;

use Player\CareerArc\Contracts\CareerArcRepositoryInterface;

/**
 * Assembles the immutable CareerArc from the repository rows. No SQL, no HTML.
 *
 * @phpstan-type SeasonRow array{year:int, teamid:int, team:string, games:int, pts:int, age:int,
 *   r_oo:int, r_drive_off:int, r_po:int, r_trans_off:int, r_od:int, r_dd:int, r_pd:int, r_td:int}
 * @phpstan-type PlayerFacts array{name:string, draftyear:int, draftround:int, draftpickno:int, draftedby:string}
 */
class CareerArcService
{
    private const MAX_DRAFT_LEAD_YEARS = 5;
    private const TRANSACTION_TYPE_TRADE = 2;
    private const TRANSACTION_TYPE_WAIVER = 3;
    private const UNKNOWN_TEAM = 'unknown team';

    private const EVENT_ORDER = [
        ArcEvent::DRAFT => 0,
        ArcEvent::TRADE => 1,
        ArcEvent::WAIVER => 2,
        ArcEvent::AWARD => 3,
        ArcEvent::RETIRED => 4,
    ];

    public function __construct(
        private CareerArcRepositoryInterface $repository,
        private CareerArcLabeler $labeler,
    ) {
    }

    public function build(int $pid, bool $isRetired): ?CareerArc
    {
        $rows = $this->repository->getSeasonRows($pid);
        if ($rows === []) {
            return null;
        }

        $seasonsByYear = self::buildSeasonsByYear($rows);
        $stints = self::buildStints($rows);

        $years = array_keys($seasonsByYear);
        $firstSeason = min($years);
        $lastSeason = max($years);

        $facts = $this->repository->getPlayerFacts($pid);
        $name = $facts !== null ? $facts['name'] : '';

        [$axisStart, $draftEvents] = self::buildDraft($facts, $firstSeason);
        [$axisEnd, $moveEvents] = $this->buildMoveEvents($pid, $axisStart, $lastSeason);
        [$awardsHidden, $awardEvents] = $this->buildAwardEvents($name, $firstSeason, $lastSeason);

        $events = [...$draftEvents, ...$moveEvents, ...$awardEvents];
        if ($isRetired) {
            $events[] = new ArcEvent(ArcEvent::RETIRED, $lastSeason, 'Retired after the ' . $lastSeason . ' season');
        }
        $events = self::sortEvents($events);

        [$bestPpgYear, $bestRatingYear] = self::findBestYears($seasonsByYear);
        $labels = $this->labeler->labelsFor($seasonsByYear, $bestPpgYear);

        return new CareerArc(
            $axisStart,
            $axisEnd,
            $seasonsByYear,
            array_values($stints),
            $events,
            $bestPpgYear,
            $bestRatingYear,
            $awardsHidden,
            $labels,
        );
    }

    /**
     * @param non-empty-list<SeasonRow> $rows
     * @return non-empty-array<int, ArcSeason> keyed by year; a repeated year overwrites the earlier row
     */
    private static function buildSeasonsByYear(array $rows): array
    {
        /** @var array<int, ArcSeason> $seasonsByYear */
        $seasonsByYear = [];
        foreach ($rows as $row) {
            $seasonsByYear[$row['year']] = new ArcSeason(
                $row['year'],
                $row['teamid'],
                $row['team'],
                self::ppgFor($row['pts'], $row['games']),
                self::ratingFor([
                    'r_oo' => $row['r_oo'],
                    'r_drive_off' => $row['r_drive_off'],
                    'r_po' => $row['r_po'],
                    'r_trans_off' => $row['r_trans_off'],
                    'r_od' => $row['r_od'],
                    'r_dd' => $row['r_dd'],
                    'r_pd' => $row['r_pd'],
                    'r_td' => $row['r_td'],
                ]),
                $row['age'],
            );
        }

        return $seasonsByYear;
    }

    /**
     * Consecutive rows on the same teamid merge into one stint, regardless of year gaps.
     *
     * @param non-empty-list<SeasonRow> $rows
     * @return array<int, ArcStint> in row order; build() re-lists it with array_values
     */
    private static function buildStints(array $rows): array
    {
        /** @var list<ArcStint> $stints */
        $stints = [];
        foreach ($rows as $row) {
            $lastIndex = count($stints) - 1;
            if ($lastIndex >= 0 && $stints[$lastIndex]->teamid === $row['teamid']) {
                $previous = $stints[$lastIndex];
                $stints[$lastIndex] = new ArcStint($previous->teamid, $previous->teamName, $previous->fromYear, $row['year']);
            } else {
                $stints[] = new ArcStint($row['teamid'], $row['team'], $row['year'], $row['year']);
            }
        }

        return $stints;
    }

    /**
     * @param PlayerFacts|null $facts
     * @return array{int, list<ArcEvent>} axis start, then zero or one draft event
     */
    private static function buildDraft(?array $facts, int $firstSeason): array
    {
        /** @var list<ArcEvent> $events */
        $events = [];

        $axisStart = $firstSeason;
        if ($facts !== null && $facts['draftyear'] > 0 && $facts['draftround'] > 0) {
            $axisStart = max(min($firstSeason, $facts['draftyear']), $firstSeason - self::MAX_DRAFT_LEAD_YEARS);
            $draftText = 'Drafted ' . $facts['draftyear'] . ': round ' . $facts['draftround'] . ', pick ' . $facts['draftpickno'];
            if ($facts['draftedby'] !== '') {
                $draftText .= ' by ' . $facts['draftedby'];
            }
            $events[] = new ArcEvent(ArcEvent::DRAFT, max($facts['draftyear'], $axisStart), $draftText);
        }

        return [$axisStart, $events];
    }

    /**
     * Trade and waiver events inside [axisStart, lastSeason + 1]; only those moves extend the axis end.
     *
     * @return array{int, list<ArcEvent>} axis end, then the move events in repository order
     */
    private function buildMoveEvents(int $pid, int $axisStart, int $lastSeason): array
    {
        /** @var list<ArcEvent> $events */
        $events = [];

        $axisEnd = $lastSeason;
        foreach ($this->repository->getMoves($pid) as $move) {
            $year = $move['season_year'];
            if ($year < $axisStart || $year > $lastSeason + 1) {
                continue;
            }
            if ($move['transaction_type'] === self::TRANSACTION_TYPE_TRADE) {
                $type = ArcEvent::TRADE;
                $prefix = 'Traded ';
            } elseif ($move['transaction_type'] === self::TRANSACTION_TYPE_WAIVER) {
                $type = ArcEvent::WAIVER;
                $prefix = 'Claimed off waivers ';
            } else {
                continue;
            }
            $axisEnd = max($axisEnd, $year);
            $events[] = new ArcEvent(
                $type,
                $year,
                $prefix . $move['transaction_month'] . '/' . $move['transaction_day'] . '/' . $year
                    . ': ' . ($move['from_team_name'] ?? self::UNKNOWN_TEAM)
                    . ' to ' . ($move['to_team_name'] ?? self::UNKNOWN_TEAM),
            );
        }

        return [$axisEnd, $events];
    }

    /**
     * Awards inside [firstSeason, lastSeason], grouped per year. A null lookup hides awards.
     *
     * @return array{bool, list<ArcEvent>} awards-hidden flag, then the award events
     */
    private function buildAwardEvents(string $name, int $firstSeason, int $lastSeason): array
    {
        /** @var list<ArcEvent> $events */
        $events = [];

        $awardsHidden = false;
        if ($name !== '') {
            $awards = $this->repository->getAwardsForUniqueName($name);
            if ($awards === null) {
                $awardsHidden = true;
            } else {
                /** @var array<int, list<string>> $awardsByYear */
                $awardsByYear = [];
                foreach ($awards as $award) {
                    if ($award['year'] < $firstSeason || $award['year'] > $lastSeason) {
                        continue;
                    }
                    $awardsByYear[$award['year']][] = $award['award'];
                }
                foreach ($awardsByYear as $year => $names) {
                    $events[] = new ArcEvent(ArcEvent::AWARD, $year, implode('; ', $names));
                }
            }
        }

        return [$awardsHidden, $events];
    }

    /**
     * Year first, then EVENT_ORDER. usort is stable, so same-year same-type events keep their input order.
     *
     * @param list<ArcEvent> $events
     * @return list<ArcEvent>
     */
    private static function sortEvents(array $events): array
    {
        usort(
            $events,
            static fn (ArcEvent $a, ArcEvent $b): int => [$a->year, self::EVENT_ORDER[$a->type] ?? 99]
                <=> [$b->year, self::EVENT_ORDER[$b->type] ?? 99],
        );

        return $events;
    }

    /**
     * Strict `>` keeps the earliest year on a tie.
     *
     * @param array<int, ArcSeason> $seasonsByYear
     * @return array{?int, ?int} best PPG year, then best rating year
     */
    private static function findBestYears(array $seasonsByYear): array
    {
        $bestPpgYear = null;
        $bestPpg = null;
        $bestRatingYear = null;
        $bestRating = null;
        foreach ($seasonsByYear as $year => $season) {
            if ($season->ppg !== null && ($bestPpg === null || $season->ppg > $bestPpg)) {
                $bestPpg = $season->ppg;
                $bestPpgYear = $year;
            }
            if ($season->rating !== null && ($bestRating === null || $season->rating > $bestRating)) {
                $bestRating = $season->rating;
                $bestRatingYear = $year;
            }
        }

        return [$bestPpgYear, $bestRatingYear];
    }

    /**
     * Off + Def composite, matching the Ratings page. Null when all eight columns are 0.
     *
     * @param array{r_oo:int,r_drive_off:int,r_po:int,r_trans_off:int,r_od:int,r_dd:int,r_pd:int,r_td:int} $seasonRow
     */
    public static function ratingFor(array $seasonRow): ?int
    {
        $off = $seasonRow['r_oo'] + $seasonRow['r_drive_off'] + $seasonRow['r_po'] + $seasonRow['r_trans_off'];
        $def = $seasonRow['r_od'] + $seasonRow['r_dd'] + $seasonRow['r_pd'] + $seasonRow['r_td'];
        $sum = $off + $def;

        return $sum === 0 ? null : $sum;
    }

    public static function ppgFor(int $pts, int $games): ?float
    {
        if ($games <= 0) {
            return null;
        }

        return round($pts / $games, 1);
    }
}
