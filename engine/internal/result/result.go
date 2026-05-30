// Package result defines the engine's output contract: the structured result
// the engine writes as JSON on stdout, which the PHP loader (a later PR) commits
// to the database.
//
// The per-possession event stream is the single source of truth: both the box
// scores in this PR and the play-by-play commentary in a later PR derive from
// it. The box-score structs map 1:1 to the RAW columns of ibl_box_scores /
// ibl_box_scores_teams; they deliberately omit the MySQL-generated columns
// (calc_points, calc_rebounds, calc_fg_made, game_type, season_year), which the
// database computes and the engine must never emit.
package result

// EventKind is the discriminator for a per-possession Event.
type EventKind string

const (
	EventPossessionStart EventKind = "possession_start"
	EventShotAttempt     EventKind = "shot_attempt"
	EventShotMake        EventKind = "shot_make"
	EventShotMiss        EventKind = "shot_miss"
	EventRebound         EventKind = "rebound"
	EventTurnover        EventKind = "turnover"
	EventSteal           EventKind = "steal"
	EventBlock           EventKind = "block"
	EventFoul            EventKind = "foul"
	EventSubstitution    EventKind = "substitution"
	EventFreeThrow       EventKind = "free_throw"
	EventPeriodBoundary  EventKind = "period_boundary"
)

// ShotType classifies a shot attempt; carried on shot_attempt/make/miss events.
type ShotType string

const (
	ShotTwoPoint  ShotType = "2pt"
	ShotThree     ShotType = "3pt"
	ShotFreeThrow ShotType = "ft"
)

// Event is one structured per-possession event. Which fields are meaningful
// depends on Kind; a zero value means "not applicable to this event kind".
type Event struct {
	Kind   EventKind `json:"kind"`
	Period int       `json:"period"`
	Clock  int       `json:"clock_seconds"` // seconds remaining in the period

	TeamID     int `json:"team_id,omitempty"`
	PlayerID   int `json:"player_id,omitempty"`
	DefenderID int `json:"defender_id,omitempty"` // opposing player for steals/blocks

	ShotType ShotType `json:"shot_type,omitempty"`

	// OffensiveRebound distinguishes an offensive (true) from a defensive
	// (false) rebound. Only meaningful when Kind == EventRebound.
	OffensiveRebound bool `json:"offensive_rebound,omitempty"`
}

// PlayerBox is one player's stat line for one game. Fields map 1:1 to the RAW
// columns of ibl_box_scores. A did-not-play row is expressed as GameMIN == 0
// (the PR8 loader enforces that convention on write).
type PlayerBox struct {
	PID     int    `json:"pid"`
	Pos     string `json:"pos"`
	GameMIN int    `json:"gameMIN"`
	Game2GM int    `json:"game2GM"`
	Game2GA int    `json:"game2GA"`
	GameFTM int    `json:"gameFTM"`
	GameFTA int    `json:"gameFTA"`
	Game3GM int    `json:"game3GM"`
	Game3GA int    `json:"game3GA"`
	GameORB int    `json:"gameORB"`
	GameDRB int    `json:"gameDRB"`
	GameAST int    `json:"gameAST"`
	GameSTL int    `json:"gameSTL"`
	GameTOV int    `json:"gameTOV"`
	GameBLK int    `json:"gameBLK"`
	GamePF  int    `json:"gamePF"`
}

// TeamBox is one team's totals for one game, mapping to ibl_box_scores_teams.
// The two TeamBoxes in a GameResult are ordered visitor-first; the
// visitor=lower-team-id dedupe convention is enforced by the PR8 loader.
type TeamBox struct {
	TeamID int  `json:"team_id"`
	IsHome bool `json:"is_home"`

	Q1 int   `json:"q1"`
	Q2 int   `json:"q2"`
	Q3 int   `json:"q3"`
	Q4 int   `json:"q4"`
	OT []int `json:"ot"` // points per overtime period in order; empty when no OT

	Game2GM int `json:"game2GM"`
	Game2GA int `json:"game2GA"`
	GameFTM int `json:"gameFTM"`
	GameFTA int `json:"gameFTA"`
	Game3GM int `json:"game3GM"`
	Game3GA int `json:"game3GA"`
	GameORB int `json:"gameORB"`
	GameDRB int `json:"gameDRB"`
	GameAST int `json:"gameAST"`
	GameSTL int `json:"gameSTL"`
	GameTOV int `json:"gameTOV"`
	GameBLK int `json:"gameBLK"`
	GamePF  int `json:"gamePF"`
}

// GameResult is the engine's full output for one scheduled game. SimGameType
// echoes the JSB-scale input game-type (2-6) for the loader's routing; it is
// NOT the ibl_box_scores.game_type generated column (which the database derives
// on a 1-3 scale).
type GameResult struct {
	Date          string `json:"date"`
	HomeTeamID    int    `json:"home_team_id"`
	VisitorTeamID int    `json:"visitor_team_id"`
	GameOfThatDay int    `json:"game_of_that_day"`
	SimGameType   int    `json:"sim_game_type"`

	Events      []Event     `json:"events"`
	PlayerBoxes []PlayerBox `json:"player_boxes"`
	TeamBoxes   []TeamBox   `json:"team_boxes"` // visitor first, then home
}

// Result is the engine's complete output for one bundle: one GameResult per
// scheduled game, plus the seed actually used so the run can be replayed.
type Result struct {
	Seed  uint64       `json:"seed"`
	Games []GameResult `json:"games"`
}
