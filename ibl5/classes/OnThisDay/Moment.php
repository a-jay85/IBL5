<?php

declare(strict_types=1);

namespace OnThisDay;

/**
 * A single notable historical event that happened on a calendar day.
 *
 * Documented fact keys per kind:
 * - FiftyPointGame, TripleDouble: points, rebounds, assists, steals, blocks
 * - RecordGame: value (plus $statLabel)
 * - SeriesClincher: round, wins, losses
 * - Trade: players_moved
 */
final readonly class Moment
{
    /**
     * @param string $key Stable identity, format `<kind>:<Y-m-d>:<pid-or-tid-pair>`
     * @param string $date Y-m-d (box-score Date)
     * @param int $pid 0 for Trade / SeriesClincher
     * @param array<string, int> $facts
     */
    public function __construct(
        public MomentKind $kind,
        public string $key,
        public string $date,
        public int $seasonYear,
        public int $pid,
        public string $playerName,
        public int $teamId,
        public string $teamName,
        public int $opponentTeamId,
        public string $opponentName,
        public array $facts,
        public ?string $statLabel = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'key' => $this->key,
            'date' => $this->date,
            'seasonYear' => $this->seasonYear,
            'pid' => $this->pid,
            'playerName' => $this->playerName,
            'teamId' => $this->teamId,
            'teamName' => $this->teamName,
            'opponentTeamId' => $this->opponentTeamId,
            'opponentName' => $this->opponentName,
            'facts' => $this->facts,
            'statLabel' => $this->statLabel,
        ];
    }

    /**
     * Rebuild a moment from toArray() output.
     *
     * @param array<mixed> $row
     * @throws \InvalidArgumentException when a field is missing or malformed
     */
    public static function fromArray(array $row): self
    {
        $kind = $row['kind'] ?? null;
        $key = $row['key'] ?? null;
        $date = $row['date'] ?? null;
        $seasonYear = $row['seasonYear'] ?? null;
        $pid = $row['pid'] ?? null;
        $playerName = $row['playerName'] ?? null;
        $teamId = $row['teamId'] ?? null;
        $teamName = $row['teamName'] ?? null;
        $opponentTeamId = $row['opponentTeamId'] ?? null;
        $opponentName = $row['opponentName'] ?? null;
        $facts = $row['facts'] ?? null;
        $statLabel = $row['statLabel'] ?? null;

        if (
            !is_string($kind) || !is_string($key) || !is_string($date)
            || !is_int($seasonYear) || !is_int($pid) || !is_string($playerName)
            || !is_int($teamId) || !is_string($teamName) || !is_int($opponentTeamId)
            || !is_string($opponentName) || !is_array($facts)
            || ($statLabel !== null && !is_string($statLabel))
        ) {
            throw new \InvalidArgumentException('Malformed moment row');
        }

        $cleanFacts = [];
        foreach ($facts as $name => $value) {
            if (!is_string($name) || !is_int($value)) {
                throw new \InvalidArgumentException('Malformed moment facts');
            }
            $cleanFacts[$name] = $value;
        }

        $momentKind = MomentKind::tryFrom($kind);
        if ($momentKind === null) {
            throw new \InvalidArgumentException('Unknown moment kind: ' . $kind);
        }

        return new self(
            kind: $momentKind,
            key: $key,
            date: $date,
            seasonYear: $seasonYear,
            pid: $pid,
            playerName: $playerName,
            teamId: $teamId,
            teamName: $teamName,
            opponentTeamId: $opponentTeamId,
            opponentName: $opponentName,
            facts: $cleanFacts,
            statLabel: $statLabel,
        );
    }
}
