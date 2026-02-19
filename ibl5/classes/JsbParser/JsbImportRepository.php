<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\JsbImportRepositoryInterface;

/**
 * Repository for database operations related to JSB file imports.
 *
 * Handles upserts into ibl_hist, ibl_jsb_transactions, ibl_jsb_history,
 * ibl_jsb_allstar_rosters, and ibl_jsb_allstar_scores tables.
 */
class JsbImportRepository extends \BaseMysqliRepository implements JsbImportRepositoryInterface
{
    /**
     * JSB team ID → team name mapping for historical team names.
     *
     * These are the names used in .car/.his files. They may differ from current
     * database team_name values due to franchise renames.
     *
     * @var array<int, string>
     */
    public const JSB_TEAM_NAMES = [
        0 => 'Free Agents',
        1 => 'Celtics',
        2 => 'Heat',
        3 => 'Knicks',
        4 => 'Nets',
        5 => 'Magic',
        6 => 'Bucks',
        7 => 'Bulls',
        8 => 'Pelicans',
        9 => 'Hawks',
        10 => 'Hornets',
        11 => 'Pacers',
        12 => 'Raptors',
        13 => 'Jazz',
        14 => 'Timberwolves',
        15 => 'Nuggets',
        16 => 'Thunder',
        17 => 'Spurs',
        18 => 'Trailblazers',
        19 => 'Clippers',
        20 => 'Grizzlies',
        21 => 'Lakers',
        22 => 'Supersonics',
        23 => 'Suns',
        24 => 'Warriors',
        25 => 'Pistons',
        26 => 'Kings',
        27 => 'Bullets',
        28 => 'Mavericks',
    ];

    /**
     * Mapping of historical JSB team names to current database team names.
     *
     * Some franchises were renamed in the IBL. The JSB engine retains the old names
     * in historical data files, but the database uses the current names.
     *
     * @var array<string, string>
     */
    public const TEAM_NAME_ALIASES = [
        'Hornets' => 'Sting',
        'Thunder' => 'Aces',
        'Spurs' => 'Rockets',
        'Supersonics' => 'Braves',
    ];

    /**
     * Cache of team name → teamid lookups.
     * @var array<string, int|null>
     */
    private array $teamIdCache = [];

    /**
     * @see JsbImportRepositoryInterface::upsertHistRecord()
     */
    public function upsertHistRecord(array $record): bool
    {
        $affected = $this->execute(
            'INSERT INTO ibl_hist
                (pid, name, year, team, teamid, games, minutes, fgm, fga, ftm, fta, tgm, tga, orb, reb, ast, stl, blk, tvr, pf, pts)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                team = VALUES(team),
                teamid = VALUES(teamid),
                games = VALUES(games),
                minutes = VALUES(minutes),
                fgm = VALUES(fgm),
                fga = VALUES(fga),
                ftm = VALUES(ftm),
                fta = VALUES(fta),
                tgm = VALUES(tgm),
                tga = VALUES(tga),
                orb = VALUES(orb),
                reb = VALUES(reb),
                ast = VALUES(ast),
                stl = VALUES(stl),
                blk = VALUES(blk),
                tvr = VALUES(tvr),
                pf = VALUES(pf),
                pts = VALUES(pts)',
            'isisiiiiiiiiiiiiiiiii',
            $record['pid'],
            $record['name'],
            $record['year'],
            $record['team'],
            $record['teamid'],
            $record['games'],
            $record['minutes'],
            $record['fgm'],
            $record['fga'],
            $record['ftm'],
            $record['fta'],
            $record['tgm'],
            $record['tga'],
            $record['orb'],
            $record['reb'],
            $record['ast'],
            $record['stl'],
            $record['blk'],
            $record['tvr'],
            $record['pf'],
            $record['pts']
        );

        return $affected >= 0;
    }

    /**
     * @see JsbImportRepositoryInterface::upsertTransaction()
     */
    public function upsertTransaction(array $record): bool
    {
        // Build query with nullable fields handled explicitly
        $affected = $this->execute(
            'INSERT INTO ibl_jsb_transactions
                (season_year, transaction_month, transaction_day, transaction_type,
                 pid, player_name, from_teamid, to_teamid,
                 injury_games_missed, injury_description, trade_group_id,
                 is_draft_pick, draft_pick_year, source_file)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                player_name = VALUES(player_name),
                injury_games_missed = VALUES(injury_games_missed),
                injury_description = VALUES(injury_description),
                trade_group_id = VALUES(trade_group_id),
                is_draft_pick = VALUES(is_draft_pick),
                draft_pick_year = VALUES(draft_pick_year),
                source_file = VALUES(source_file)',
            'iiiiisiiisisiss',
            $record['season_year'],
            $record['transaction_month'],
            $record['transaction_day'],
            $record['transaction_type'],
            $record['pid'],
            $record['player_name'],
            $record['from_teamid'],
            $record['to_teamid'],
            $record['injury_games_missed'],
            $record['injury_description'],
            $record['trade_group_id'],
            $record['is_draft_pick'],
            $record['draft_pick_year'],
            $record['source_file']
        );

        return $affected >= 0;
    }

    /**
     * @see JsbImportRepositoryInterface::upsertHistoryRecord()
     */
    public function upsertHistoryRecord(array $record): bool
    {
        $affected = $this->execute(
            'INSERT INTO ibl_jsb_history
                (season_year, team_name, teamid, wins, losses, made_playoffs,
                 playoff_result, playoff_round_reached, won_championship, source_file)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                teamid = VALUES(teamid),
                wins = VALUES(wins),
                losses = VALUES(losses),
                made_playoffs = VALUES(made_playoffs),
                playoff_result = VALUES(playoff_result),
                playoff_round_reached = VALUES(playoff_round_reached),
                won_championship = VALUES(won_championship),
                source_file = VALUES(source_file)',
            'isiiiissss',
            $record['season_year'],
            $record['team_name'],
            $record['teamid'],
            $record['wins'],
            $record['losses'],
            $record['made_playoffs'],
            $record['playoff_result'],
            $record['playoff_round_reached'],
            $record['won_championship'],
            $record['source_file']
        );

        return $affected >= 0;
    }

    /**
     * @see JsbImportRepositoryInterface::upsertAllStarRoster()
     */
    public function upsertAllStarRoster(array $record): bool
    {
        $affected = $this->execute(
            'INSERT INTO ibl_jsb_allstar_rosters
                (season_year, event_type, roster_slot, pid, player_name)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                pid = VALUES(pid),
                player_name = VALUES(player_name)',
            'isiis',
            $record['season_year'],
            $record['event_type'],
            $record['roster_slot'],
            $record['pid'],
            $record['player_name']
        );

        return $affected >= 0;
    }

    /**
     * @see JsbImportRepositoryInterface::upsertAllStarScore()
     */
    public function upsertAllStarScore(array $record): bool
    {
        $affected = $this->execute(
            'INSERT INTO ibl_jsb_allstar_scores
                (season_year, contest_type, round, participant_slot, pid, score)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                pid = VALUES(pid),
                score = VALUES(score)',
            'isiiii',
            $record['season_year'],
            $record['contest_type'],
            $record['round'],
            $record['participant_slot'],
            $record['pid'],
            $record['score']
        );

        return $affected >= 0;
    }

    /**
     * @see JsbImportRepositoryInterface::resolveTeamId()
     */
    public function resolveTeamId(int $jsbTeamId): ?int
    {
        // JSB team IDs 0-28 map directly to database teamids
        // But team names may differ (renamed franchises)
        if ($jsbTeamId >= 0 && $jsbTeamId <= 28) {
            return $jsbTeamId;
        }
        return null;
    }

    /**
     * @see JsbImportRepositoryInterface::resolveTeamIdByName()
     */
    public function resolveTeamIdByName(string $teamName): ?int
    {
        if (array_key_exists($teamName, $this->teamIdCache)) {
            return $this->teamIdCache[$teamName];
        }

        // Try direct lookup first
        $row = $this->fetchOne(
            'SELECT teamid FROM ibl_team_info WHERE team_name = ? LIMIT 1',
            's',
            $teamName
        );

        if ($row !== null) {
            /** @var int $teamId */
            $teamId = $row['teamid'];
            $this->teamIdCache[$teamName] = $teamId;
            return $teamId;
        }

        // Try alias mapping (historical JSB names → current DB names)
        if (isset(self::TEAM_NAME_ALIASES[$teamName])) {
            $aliasName = self::TEAM_NAME_ALIASES[$teamName];
            $row = $this->fetchOne(
                'SELECT teamid FROM ibl_team_info WHERE team_name = ? LIMIT 1',
                's',
                $aliasName
            );

            if ($row !== null) {
                /** @var int $teamId */
                $teamId = $row['teamid'];
                $this->teamIdCache[$teamName] = $teamId;
                return $teamId;
            }
        }

        // Try looking in ibl_hist for historical team names
        $row = $this->fetchOne(
            'SELECT teamid FROM ibl_hist WHERE team = ? AND teamid > 0 LIMIT 1',
            's',
            $teamName
        );

        if ($row !== null) {
            /** @var int $teamId */
            $teamId = $row['teamid'];
            $this->teamIdCache[$teamName] = $teamId;
            return $teamId;
        }

        $this->teamIdCache[$teamName] = null;
        return null;
    }

    /**
     * Get the maximum trade_group_id currently in the database.
     *
     * @return int Maximum trade_group_id, or 0 if no trades exist
     */
    public function fetchMaxTradeGroupId(): int
    {
        $row = $this->fetchOne(
            'SELECT COALESCE(MAX(trade_group_id), 0) AS max_id FROM ibl_jsb_transactions',
            ''
        );

        if ($row === null) {
            return 0;
        }

        /** @var int|string $maxId */
        $maxId = $row['max_id'];
        return (int) $maxId;
    }

    /**
     * Look up a player name by pid.
     *
     * @param int $pid Player ID
     * @return string|null Player name, or null if not found
     */
    public function getPlayerName(int $pid): ?string
    {
        $row = $this->fetchOne(
            'SELECT name FROM ibl_plr WHERE pid = ? LIMIT 1',
            'i',
            $pid
        );

        if ($row !== null) {
            return is_string($row['name']) ? $row['name'] : null;
        }

        return null;
    }
}
