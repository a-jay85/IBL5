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
}

// Player (list)
export interface Player {
    uuid: string;
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
    owner: string;
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
    visitor: TeamRef & { score: number };
    home: TeamRef & { score: number };
}

// Leader
export interface Leader {
    player: {
        uuid: string;
        name: string;
    };
    team: {
        uuid: string | null;
        city: string;
        name: string;
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
        name: string;
        position: string;
    };
    team: {
        uuid: string | null;
        city: string;
        name: string;
    };
    injury: {
        days_remaining: number;
    };
}

// Boxscore
export interface BoxscorePlayerLine {
    uuid: string | null;
    name: string;
    position: string;
    minutes: number;
    two_pt_made: number;
    two_pt_attempted: number;
    ft_made: number;
    ft_attempted: number;
    three_pt_made: number;
    three_pt_attempted: number;
    fg_made: number;
    fg_attempted: number;
    offensive_rebounds: number;
    defensive_rebounds: number;
    rebounds: number;
    assists: number;
    steals: number;
    turnovers: number;
    blocks: number;
    personal_fouls: number;
    points: number;
}

export interface BoxscoreTeamStats {
    name: string;
    quarter_scoring: {
        q1: { visitor: number; home: number };
        q2: { visitor: number; home: number };
        q3: { visitor: number; home: number };
        q4: { visitor: number; home: number };
        ot: { visitor: number; home: number };
    };
    totals: {
        fg_made: number;
        fg_attempted: number;
        two_pt_made: number;
        two_pt_attempted: number;
        ft_made: number;
        ft_attempted: number;
        three_pt_made: number;
        three_pt_attempted: number;
        offensive_rebounds: number;
        defensive_rebounds: number;
        rebounds: number;
        assists: number;
        steals: number;
        turnovers: number;
        blocks: number;
        personal_fouls: number;
        points: number;
    };
    attendance: number;
    capacity: number;
    records: {
        visitor: string;
        home: string;
    };
}

export interface Boxscore {
    game: Game;
    visitor: {
        team_stats: BoxscoreTeamStats;
        players: BoxscorePlayerLine[];
    };
    home: {
        team_stats: BoxscoreTeamStats;
        players: BoxscorePlayerLine[];
    };
}

// Player career stats
export interface PlayerCareerStats {
    uuid: string;
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
    };
}

// Player season stats (history)
export interface PlayerSeasonStats {
    year: number;
    team: {
        uuid: string | null;
        city: string;
        name: string;
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
