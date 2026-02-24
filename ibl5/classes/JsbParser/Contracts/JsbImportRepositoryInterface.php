<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for database operations related to JSB file imports.
 */
interface JsbImportRepositoryInterface
{
    /**
     * Upsert a season record into ibl_hist from .car data.
     *
     * Converts .car 2GM/3GM stats to ibl_hist FGM/FGA conventions and upserts.
     *
     * @param array{pid: int, name: string, year: int, team: string, teamid: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, blk: int, tvr: int, pf: int, pts: int} $record
     * @return int Affected rows (1=inserted, 2=updated, 0=unchanged)
     */
    public function upsertHistRecord(array $record): int;

    /**
     * Upsert a transaction record from .trn data.
     *
     * @param array{season_year: int, transaction_month: int, transaction_day: int, transaction_type: int, pid: int, player_name: string|null, from_teamid: int, to_teamid: int, injury_games_missed: int|null, injury_description: string|null, trade_group_id: int|null, is_draft_pick: int, draft_pick_year: int|null, source_file: string|null} $record
     * @return int Affected rows (1=inserted, 2=updated, 0=unchanged)
     */
    public function upsertTransaction(array $record): int;

    /**
     * Upsert a history record from .his data.
     *
     * @param array{season_year: int, team_name: string, teamid: int|null, wins: int, losses: int, made_playoffs: int, playoff_result: string|null, playoff_round_reached: string|null, won_championship: int, source_file: string|null} $record
     * @return int Affected rows (1=inserted, 2=updated, 0=unchanged)
     */
    public function upsertHistoryRecord(array $record): int;

    /**
     * Upsert an All-Star roster entry from .asw data.
     *
     * @param array{season_year: int, event_type: string, roster_slot: int, pid: int|null, player_name: string|null} $record
     * @return int Affected rows (1=inserted, 2=updated, 0=unchanged)
     */
    public function upsertAllStarRoster(array $record): int;

    /**
     * Upsert an All-Star contest score from .asw data.
     *
     * @param array{season_year: int, contest_type: string, round: int, participant_slot: int, pid: int|null, score: int} $record
     * @return int Affected rows (1=inserted, 2=updated, 0=unchanged)
     */
    public function upsertAllStarScore(array $record): int;

    /**
     * Resolve a JSB team ID to a database teamid.
     *
     * @param int $jsbTeamId JSB engine team ID (0-28)
     * @return int|null Database teamid, or null if not found
     */
    public function resolveTeamId(int $jsbTeamId): ?int;

    /**
     * Resolve a team name to a database teamid.
     *
     * @param string $teamName Team name from .car/.his files
     * @return int|null Database teamid, or null if not found
     */
    public function resolveTeamIdByName(string $teamName): ?int;

    /**
     * Upsert an all-time record from .rcb data.
     *
     * @param array{scope: string, team_id: int|null, record_type: string, stat_category: string, ranking: int, player_name: string, car_block_id: int, pid: int|null, stat_value: float, stat_raw: int, team_of_record: int|null, season_year: int|null, career_total: int|null, source_file: string|null} $record
     * @return int Affected rows (1=inserted, 2=updated, 0=unchanged)
     */
    public function upsertRcbAlltimeRecord(array $record): int;

    /**
     * Upsert a current season record from .rcb data.
     *
     * @param array{season_year: int, scope: string, team_id: int|null, context: string, stat_category: string, ranking: int, player_name: string, player_position: string|null, car_block_id: int, pid: int|null, stat_value: int, record_season_year: int, source_file: string|null} $record
     * @return int Affected rows (1=inserted, 2=updated, 0=unchanged)
     */
    public function upsertRcbSeasonRecord(array $record): int;
}
