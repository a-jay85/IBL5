<?php

declare(strict_types=1);

namespace Team;

use League\League;
use Team\Contracts\SplitStatsRepositoryInterface;
use Season\Season;

/**
 * SplitStatsRepository - Queries per-game averages filtered by game context
 *
 * Reuses the same SQL aggregation pattern as PeriodAverages (SUM/COUNT grouped by player)
 * with additional WHERE clauses or JOINs based on the split type.
 *
 * @phpstan-import-type SplitStatsRow from Contracts\SplitStatsRepositoryInterface
 *
 * @see SplitStatsRepositoryInterface
 */
class SplitStatsRepository extends \Database\BaseMysqliRepository implements SplitStatsRepositoryInterface
{
    /**
     * Map of split keys to human-readable labels.
     * Dynamic keys (month_N, div_X, conf_X, vs_N) are resolved at runtime.
     *
     * @var array<string, string>
     */
    private const STATIC_LABELS = [
        'home' => 'Home',
        'road' => 'Road',
        'wins' => 'Wins',
        'losses' => 'Losses',
        'pre_allstar' => 'Pre All-Star',
        'post_allstar' => 'Post All-Star',
    ];

    /** @var array<int, string> Month number to display name */
    private const MONTH_NAMES = [
        11 => 'November',
        12 => 'December',
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
    ];

    /**
     * @see SplitStatsRepositoryInterface::getSplitStats()
     * @return list<SplitStatsRow>
     */
    public function getSplitStats(int $teamid, int $seasonEndingYear, string $splitKey): array
    {
        $splitCondition = $this->buildSplitCondition($splitKey, $teamid, $seasonEndingYear);

        $query = "SELECT p.name,
            bs.pos,
            bs.pid,
            COUNT(DISTINCT bs.`game_date`) as games,
            ROUND(SUM(bs.game_min)/COUNT(DISTINCT bs.`game_date`), 1) as gameMINavg,
            ROUND(SUM(bs.game_2gm + bs.game_3gm)/COUNT(DISTINCT bs.`game_date`), 2) as gameFGMavg,
            ROUND(SUM(bs.game_2ga + bs.game_3ga)/COUNT(DISTINCT bs.`game_date`), 2) as gameFGAavg,
            ROUND((SUM(bs.game_2gm) + SUM(bs.game_3gm)) / NULLIF(SUM(bs.game_2ga) + SUM(bs.game_3ga), 0), 3) as gameFGPavg,
            ROUND(SUM(bs.game_ftm)/COUNT(DISTINCT bs.`game_date`), 2) as gameFTMavg,
            ROUND(SUM(bs.game_fta)/COUNT(DISTINCT bs.`game_date`), 2) as gameFTAavg,
            ROUND(SUM(bs.game_ftm) / NULLIF(SUM(bs.game_fta), 0), 3) as gameFTPavg,
            ROUND(SUM(bs.game_3gm)/COUNT(DISTINCT bs.`game_date`), 2) as game3GMavg,
            ROUND(SUM(bs.game_3ga)/COUNT(DISTINCT bs.`game_date`), 2) as game3GAavg,
            ROUND(SUM(bs.game_3gm) / NULLIF(SUM(bs.game_3ga), 0), 3) as game3GPavg,
            ROUND(SUM(bs.game_orb)/COUNT(DISTINCT bs.`game_date`), 1) as gameORBavg,
            ROUND((SUM(bs.game_orb) + SUM(bs.game_drb))/COUNT(DISTINCT bs.`game_date`), 1) as gameREBavg,
            ROUND(SUM(bs.game_ast)/COUNT(DISTINCT bs.`game_date`), 1) as gameASTavg,
            ROUND(SUM(bs.game_stl)/COUNT(DISTINCT bs.`game_date`), 1) as gameSTLavg,
            ROUND(SUM(bs.game_tov)/COUNT(DISTINCT bs.`game_date`), 1) as gameTOVavg,
            ROUND(SUM(bs.game_blk)/COUNT(DISTINCT bs.`game_date`), 1) as gameBLKavg,
            ROUND(SUM(bs.game_pf)/COUNT(DISTINCT bs.`game_date`), 1) as gamePFavg,
            ROUND(((2 * SUM(bs.game_2gm)) + SUM(bs.game_ftm) + (3 * SUM(bs.game_3gm)))/COUNT(DISTINCT bs.`game_date`), 1) as gamePTSavg
        FROM `ibl_box_scores` bs
        JOIN `ibl_plr` p ON bs.pid = p.pid
        " . $splitCondition['joins'] . "
        WHERE bs.season_year = ?
            AND (bs.home_teamid = ? OR bs.visitor_teamid = ?)
            AND " . $this->playedCondition('bs') . "
            AND p.teamid = ?
            AND p.retired = 0
            AND bs.game_type = 1
            " . $splitCondition['where'] . "
        GROUP BY p.name, bs.pos, bs.pid
        ORDER BY p.name ASC";

        // The opponent-join placeholder precedes the WHERE placeholders in the SQL text.
        $types = $splitCondition['joinTypes'] . 'iiii' . $splitCondition['types'];
        $params = array_merge(
            $splitCondition['joinParams'],
            [$seasonEndingYear, $teamid, $teamid, $teamid],
            $splitCondition['params']
        );

        /** @var list<SplitStatsRow> */
        return $this->fetchAll($query, $types, ...$params);
    }

    /**
     * @see SplitStatsRepositoryInterface::getValidSplitKeys()
     * @return list<string>
     */
    public function getValidSplitKeys(): array
    {
        $keys = ['home', 'road', 'wins', 'losses', 'pre_allstar', 'post_allstar'];

        foreach (array_keys(self::MONTH_NAMES) as $month) {
            $keys[] = 'month_' . $month;
        }

        foreach (League::DIVISION_NAMES as $division) {
            $keys[] = 'div_' . strtolower($division);
        }

        foreach (League::CONFERENCE_NAMES as $conference) {
            $keys[] = 'conf_' . strtolower($conference);
        }

        for ($teamid = 1; $teamid <= League::MAX_REAL_TEAMID; $teamid++) {
            $keys[] = 'vs_' . $teamid;
        }

        return $keys;
    }

    /**
     * @see SplitStatsRepositoryInterface::resolveDisplayAndSplit()
     * @return array{string, ?string}
     */
    public function resolveDisplayAndSplit(string $display, mixed $rawSplit): array
    {
        if ($display !== 'split') {
            return [$display, null];
        }

        if (is_string($rawSplit) && in_array($rawSplit, $this->getValidSplitKeys(), true)) {
            return ['split', $rawSplit];
        }

        return ['ratings', null];
    }

    /**
     * @see SplitStatsRepositoryInterface::getSplitLabel()
     */
    public function getSplitLabel(string $splitKey): string
    {
        if (isset(self::STATIC_LABELS[$splitKey])) {
            return self::STATIC_LABELS[$splitKey];
        }

        // month_N
        if (str_starts_with($splitKey, 'month_')) {
            $month = (int) substr($splitKey, 6);
            return self::MONTH_NAMES[$month] ?? 'Unknown Month';
        }

        // div_X
        if (str_starts_with($splitKey, 'div_')) {
            $divKey = substr($splitKey, 4);
            foreach (League::DIVISION_NAMES as $division) {
                if (strtolower($division) === $divKey) {
                    return 'vs. ' . $division;
                }
            }
            return 'vs. Unknown Division';
        }

        // conf_X
        if (str_starts_with($splitKey, 'conf_')) {
            $confKey = substr($splitKey, 5);
            foreach (League::CONFERENCE_NAMES as $conference) {
                if (strtolower($conference) === $confKey) {
                    return 'vs. ' . $conference;
                }
            }
            return 'vs. Unknown Conference';
        }

        // vs_N
        if (str_starts_with($splitKey, 'vs_')) {
            $opponentTid = (int) substr($splitKey, 3);
            $row = $this->fetchOne(
                "SELECT team_name FROM `ibl_team_info` WHERE teamid = ?",
                "i",
                $opponentTid
            );
            if (is_array($row) && isset($row['team_name']) && is_string($row['team_name'])) {
                return 'vs. ' . $row['team_name'];
            }
            return 'vs. Team #' . $opponentTid;
        }

        return $splitKey;
    }

    private const JOIN_SCHEDULE = 'JOIN `ibl_schedule` sch ON bs.`game_date` = sch.`game_date` AND ((sch.home_teamid = bs.home_teamid AND sch.visitor_teamid = bs.visitor_teamid) OR (sch.home_teamid = bs.visitor_teamid AND sch.visitor_teamid = bs.home_teamid))';

    private const JOIN_OPPONENT_STANDINGS = 'JOIN `ibl_standings` opp_s ON opp_s.teamid = CASE WHEN bs.home_teamid = ? THEN bs.visitor_teamid ELSE bs.home_teamid END';

    /**
     * WHERE fragments keyed by split type; every value is a constant with `?` placeholders only.
     */
    private const SPLIT_WHERE = [
        'home' => 'AND bs.home_teamid = ?',
        'road' => 'AND bs.visitor_teamid = ?',
        'wins' => 'AND ((sch.home_teamid = ? AND sch.home_score > sch.visitor_score) OR (sch.visitor_teamid = ? AND sch.visitor_score > sch.home_score))',
        'losses' => 'AND ((sch.home_teamid = ? AND sch.home_score < sch.visitor_score) OR (sch.visitor_teamid = ? AND sch.visitor_score < sch.home_score))',
        'pre_allstar' => 'AND bs.`game_date` < ?',
        'post_allstar' => 'AND bs.`game_date` >= ?',
        'month' => 'AND MONTH(bs.`game_date`) = ?',
        'div' => 'AND opp_s.division = ?',
        'conf' => 'AND opp_s.conference = ?',
        'vs' => 'AND CASE WHEN bs.home_teamid = ? THEN bs.visitor_teamid ELSE bs.home_teamid END = ?',
    ];

    /**
     * One data-only definition per split. Exact splits are keyed by their key; suffix-bearing splits are keyed
     * by their prefix, underscore included (`month_`, `div_`, `conf_`, `vs_`), so a bare `month` never resolves.
     *
     * Every `joins` / `where` value is a reference to a SQL constant above; this map holds no SQL text.
     * `params` and `joinParams` list bind-value sources that resolveBindValue() turns into values, in bind order.
     * No `@var` here on purpose: PHPStan must keep the literal string types so `where` stays
     * `value-of<self::SPLIT_WHERE>` for BanSqlStringConcatenationRule.
     */
    private const SPLIT_DEFINITIONS = [
        'home' => ['joins' => '', 'where' => self::SPLIT_WHERE['home'], 'types' => 'i', 'params' => ['teamid'], 'joinTypes' => '', 'joinParams' => []],
        'road' => ['joins' => '', 'where' => self::SPLIT_WHERE['road'], 'types' => 'i', 'params' => ['teamid'], 'joinTypes' => '', 'joinParams' => []],
        'wins' => ['joins' => self::JOIN_SCHEDULE, 'where' => self::SPLIT_WHERE['wins'], 'types' => 'ii', 'params' => ['teamid', 'teamid'], 'joinTypes' => '', 'joinParams' => []],
        'losses' => ['joins' => self::JOIN_SCHEDULE, 'where' => self::SPLIT_WHERE['losses'], 'types' => 'ii', 'params' => ['teamid', 'teamid'], 'joinTypes' => '', 'joinParams' => []],
        'pre_allstar' => ['joins' => '', 'where' => self::SPLIT_WHERE['pre_allstar'], 'types' => 's', 'params' => ['cutoff_pre'], 'joinTypes' => '', 'joinParams' => []],
        'post_allstar' => ['joins' => '', 'where' => self::SPLIT_WHERE['post_allstar'], 'types' => 's', 'params' => ['cutoff_post'], 'joinTypes' => '', 'joinParams' => []],
        'month_' => ['joins' => '', 'where' => self::SPLIT_WHERE['month'], 'types' => 'i', 'params' => ['month'], 'joinTypes' => '', 'joinParams' => []],
        'div_' => ['joins' => self::JOIN_OPPONENT_STANDINGS, 'where' => self::SPLIT_WHERE['div'], 'types' => 's', 'params' => ['division'], 'joinTypes' => 'i', 'joinParams' => ['teamid']],
        'conf_' => ['joins' => self::JOIN_OPPONENT_STANDINGS, 'where' => self::SPLIT_WHERE['conf'], 'types' => 's', 'params' => ['conference'], 'joinTypes' => 'i', 'joinParams' => ['teamid']],
        'vs_' => ['joins' => '', 'where' => self::SPLIT_WHERE['vs'], 'types' => 'ii', 'params' => ['teamid', 'opponent'], 'joinTypes' => '', 'joinParams' => []],
    ];

    /**
     * Build the WHERE additions, extra JOINs, types, and params for a split condition
     *
     * The opponent-standings join binds the team id (joinTypes/joinParams); its placeholder
     * precedes every WHERE placeholder in the SQL text, so getSplitStats() binds it first.
     *
     * @return array{joins: ''|self::JOIN_SCHEDULE|self::JOIN_OPPONENT_STANDINGS, where: ''|value-of<self::SPLIT_WHERE>, types: string, params: list<int|string>, joinTypes: ''|'i', joinParams: list<int>}
     */
    private function buildSplitCondition(string $splitKey, int $teamid, int $seasonEndingYear): array
    {
        // Resolve the lookup key: the text up to and including the first underscore when that is a known
        // prefix entry, otherwise the key itself. One `??` chain, no per-key branch ladder.
        $underscore = strpos($splitKey, '_');
        $prefix = $underscore === false ? $splitKey : substr($splitKey, 0, $underscore + 1);
        $definition = self::SPLIT_DEFINITIONS[$prefix] ?? self::SPLIT_DEFINITIONS[$splitKey] ?? null;

        if ($definition === null) {
            return ['joins' => '', 'where' => '', 'types' => '', 'params' => [], 'joinTypes' => '', 'joinParams' => []];
        }

        $suffix = substr($splitKey, strlen($prefix));

        $params = [];
        foreach ($definition['params'] as $source) {
            $params[] = $this->resolveBindValue($source, $suffix, $teamid, $seasonEndingYear);
        }

        $joinParams = [];
        foreach ($definition['joinParams'] as $source) {
            $joinParams[] = $this->resolveBindValue($source, $suffix, $teamid, $seasonEndingYear);
        }

        return [
            'joins' => $definition['joins'],
            'where' => $definition['where'],
            'types' => $definition['types'],
            'params' => $params,
            'joinTypes' => $definition['joinTypes'],
            'joinParams' => $joinParams,
        ];
    }

    /**
     * Turn a bind-value source token from SPLIT_DEFINITIONS into the value to bind. Produces values only;
     * no SQL text is built here.
     *
     * @param 'teamid'|'cutoff_pre'|'cutoff_post'|'month'|'opponent'|'division'|'conference' $source
     * @param string $suffix The split key with its prefix removed ('' for exact splits)
     * @return ($source is 'teamid'|'month'|'opponent' ? int : string)
     */
    private function resolveBindValue(string $source, string $suffix, int $teamid, int $seasonEndingYear): int|string
    {
        return match ($source) {
            'teamid' => $teamid,
            'cutoff_pre' => sprintf('%d-%02d-%02d', $seasonEndingYear, Season::IBL_ALL_STAR_MONTH, Season::IBL_ALL_STAR_BREAK_START_DAY),
            'cutoff_post' => sprintf('%d-%02d-%02d', $seasonEndingYear, Season::IBL_ALL_STAR_MONTH, Season::IBL_POST_ALL_STAR_FIRST_DAY),
            'month', 'opponent' => (int) $suffix,
            'division' => self::matchName(League::DIVISION_NAMES, $suffix),
            'conference' => self::matchName(League::CONFERENCE_NAMES, $suffix),
        };
    }

    /**
     * First name whose lowercase form equals the suffix exactly, or '' when none does.
     * The suffix is compared as given (not lowercased), preserving the pre-refactor behavior where
     * `div_ATLANTIC` binds ''.
     *
     * @param list<string> $names
     */
    private static function matchName(array $names, string $suffix): string
    {
        foreach ($names as $name) {
            if (strtolower($name) === $suffix) {
                return $name;
            }
        }

        return '';
    }
}
