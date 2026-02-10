// API response envelope
export interface ApiResponse<T> {
    status: 'success' | 'error';
    data: T;
    meta: ApiMeta;
}

export interface ApiErrorResponse {
    status: 'error';
    error: {
        code: string;
        message: string;
    };
    meta: ApiMeta;
}

export interface ApiMeta {
    timestamp: string;
    version: string;
    page?: number;
    per_page?: number;
    total?: number;
    total_pages?: number;
    sort?: string;
    order?: string;
}

// Team sub-object (embedded in other responses)
export interface TeamRef {
    uuid: string;
    city: string;
    name: string;
    full_name: string;
    team_id: number;
}

// Player (list)
export interface Player {
    uuid: string;
    pid: number;
    name: string;
    position: string;
    age: number;
    height: string;
    experience: number;
    team: TeamRef | null;
    contract: {
        current_salary: number;
        year1: number;
        year2: number;
    };
    stats: {
        games_played: number;
        points_per_game: number | null;
        fg_percentage: number | null;
        ft_percentage: number | null;
        three_pt_percentage: number | null;
    };
}

// Player (detail)
export interface PlayerDetail extends Player {
    bird_rights: number;
    stats: {
        games_played: number;
        minutes_played: number;
        field_goals_made: number;
        field_goals_attempted: number;
        free_throws_made: number;
        free_throws_attempted: number;
        three_pointers_made: number;
        three_pointers_attempted: number;
        offensive_rebounds: number;
        defensive_rebounds: number;
        assists: number;
        steals: number;
        turnovers: number;
        blocks: number;
        personal_fouls: number;
        points_per_game: number | null;
        fg_percentage: number | null;
        ft_percentage: number | null;
        three_pt_percentage: number | null;
    };
}

// Team (list)
export interface Team {
    uuid: string;
    city: string;
    name: string;
    full_name: string;
    team_id: number;
    owner: string;
    owner_discord_id: number | null;
    arena: string;
    conference: string | null;
    division: string | null;
}

// Team (detail)
export interface TeamDetail extends Team {
    record: {
        league: string | null;
        conference: string | null;
        division: string | null;
        home: string | null;
        away: string | null;
    };
    standings: {
        win_percentage: number | null;
        conference_games_back: string | null;
        division_games_back: string | null;
        games_remaining: number | null;
    };
}

// Standings
export interface StandingsEntry {
    team: TeamRef;
    conference: string;
    division: string;
    record: {
        league: string;
        conference: string;
        division: string;
        home: string;
        away: string;
    };
    win_percentage: number | null;
    games_back: {
        conference: string | null;
        division: string | null;
    };
    games_remaining: number;
    clinched: {
        conference: boolean;
        division: boolean;
        playoffs: boolean;
    };
}

// Game
export interface Game {
    uuid: string;
    season: number;
    date: string;
    status: string;
    box_score_id: number;
    game_of_that_day: number;
    visitor: TeamRef & { score: number; team_id: number };
    home: TeamRef & { score: number; team_id: number };
}

// Season info
export interface SeasonInfo {
    phase: string;
    last_sim: {
        number: number;
        phase_sim_number: number;
        start_date: string;
        end_date: string;
    };
    projected_next_sim_end_date: string;
}

// Leader
export interface Leader {
    player: {
        uuid: string;
        pid: number;
        name: string;
    };
    team: {
        uuid: string | null;
        city: string;
        name: string;
        team_id: number;
    };
    season: number;
    stats: {
        games: number;
        minutes_per_game: number;
        points_per_game: number;
        rebounds_per_game: number;
        assists_per_game: number;
        steals_per_game: number;
        blocks_per_game: number;
        turnovers_per_game: number;
        fg_percentage: number;
        ft_percentage: number;
        three_pt_percentage: number;
    };
}

// Injury
export interface Injury {
    player: {
        uuid: string;
        pid: number;
        name: string;
        position: string;
    };
    team: {
        uuid: string | null;
        city: string;
        name: string;
        team_id: number;
    };
    injury: {
        days_remaining: number;
    };
}

// Player career stats
export interface PlayerCareerStats {
    uuid: string;
    pid: number;
    name: string;
    career_totals: {
        games: number;
        minutes: number;
        points: number;
        rebounds: number;
        assists: number;
        steals: number;
        blocks: number;
    };
    career_averages: {
        points_per_game: number | null;
        rebounds_per_game: number | null;
        assists_per_game: number | null;
    };
    career_percentages: {
        fg_percentage: number | null;
        ft_percentage: number | null;
        three_pt_percentage: number | null;
    };
    playoff_minutes: number;
    draft: {
        year: number | null;
        round: number | null;
        pick: number | null;
        team: string | null;
        team_id: number | null;
    };
}

// Player season stats (history)
export interface PlayerSeasonStats {
    year: number;
    pid: number;
    player_name: string;
    team: {
        uuid: string | null;
        city: string;
        name: string;
        team_id: number;
    };
    games: number;
    minutes: number;
    stats: {
        points: number;
        rebounds: number;
        offensive_rebounds: number;
        assists: number;
        steals: number;
        blocks: number;
        turnovers: number;
        personal_fouls: number;
        fg_made: number;
        fg_attempted: number;
        ft_made: number;
        ft_attempted: number;
        three_pt_made: number;
        three_pt_attempted: number;
    };
    per_game: {
        points: number;
        rebounds: number;
        assists: number;
        steals: number;
        blocks: number;
        turnovers: number;
        minutes: number;
    };
    percentages: {
        fg: number;
        ft: number;
        three_pt: number;
    };
    salary: number;
}
