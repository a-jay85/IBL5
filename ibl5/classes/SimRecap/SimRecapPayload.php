<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * The parse/validation seam between an externally-generated recap document and
 * the database — the trust boundary of the sim-recap pipeline.
 *
 * Fail-closed by design: every recap-bearing structural error throws, so a
 * malformed document writes nothing. The one deliberate exception is `themes`,
 * which is presentational metadata and degrades to null rather than throwing.
 *
 * Extracted from storeSimRecap.php so this logic is unit-testable without
 * executing the script (the script posts to Discord on success).
 *
 * Nothing here casts: a string "3" where an int is required is a malformed
 * document, not a value to coerce.
 */
final class SimRecapPayload
{
    /**
     * @param list<array{season_year: int, game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, box_id: ?int, sort_order: int, recap_text: string}> $games
     */
    private function __construct(
        private readonly string $introText,
        private readonly string $outroText,
        private readonly string $recapText,
        private readonly array $games,
        private readonly ?string $themesJson
    ) {
    }

    /**
     * @throws \InvalidArgumentException on any recap-bearing structural error
     */
    public static function fromJson(string $json): self
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('payload is not valid JSON: ' . $e->getMessage(), 0, $e);
        }
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('payload is not a JSON object');
        }

        return new self(
            self::requireString($decoded, 'intro_text'),
            self::requireString($decoded, 'outro_text'),
            self::requireString($decoded, 'recap_text'),
            self::parseGames($decoded['games'] ?? null),
            self::parseThemes($decoded['themes'] ?? null)
        );
    }

    public function getIntroText(): string
    {
        return $this->introText;
    }

    public function getOutroText(): string
    {
        return $this->outroText;
    }

    public function getRecapText(): string
    {
        return $this->recapText;
    }

    /**
     * @return list<array{season_year: int, game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, box_id: ?int, sort_order: int, recap_text: string}>
     */
    public function getGames(): array
    {
        return $this->games;
    }

    public function getThemesJson(): ?string
    {
        return $this->themesJson;
    }

    /**
     * @param array<mixed> $source
     */
    private static function requireString(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value)) {
            throw new \InvalidArgumentException("missing or non-string field: {$key}");
        }

        return $value;
    }

    /**
     * An empty games list is valid — a sim with no games still has an envelope.
     *
     * @return list<array{season_year: int, game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, box_id: ?int, sort_order: int, recap_text: string}>
     */
    private static function parseGames(mixed $raw): array
    {
        if (!is_array($raw) || !array_is_list($raw)) {
            throw new \InvalidArgumentException('games must be a JSON list');
        }

        $games = [];
        foreach ($raw as $index => $game) {
            if (!is_array($game)) {
                throw new \InvalidArgumentException("games[{$index}] is not an object");
            }
            $games[] = self::parseGame($game, $index);
        }

        return $games;
    }

    /**
     * @param array<mixed> $game
     *
     * @return array{season_year: int, game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, box_id: ?int, sort_order: int, recap_text: string}
     */
    private static function parseGame(array $game, int|string $index): array
    {
        $boxId = $game['box_id'] ?? null;
        if ($boxId !== null && !is_int($boxId)) {
            throw new \InvalidArgumentException("games[{$index}].box_id must be an int or null");
        }

        return [
            'season_year' => self::requireInt($game, 'season_year', $index),
            'game_date' => self::requireNonEmptyString($game, 'game_date', $index),
            'visitor_teamid' => self::requireInt($game, 'visitor_teamid', $index),
            'home_teamid' => self::requireInt($game, 'home_teamid', $index),
            'game_of_that_day' => self::requireInt($game, 'game_of_that_day', $index),
            'box_id' => $boxId,
            'sort_order' => self::requireInt($game, 'sort_order', $index),
            'recap_text' => self::requireNonEmptyString($game, 'recap_text', $index),
        ];
    }

    /**
     * is_int, never a cast — "3" is a malformed document, not a 3.
     *
     * @param array<mixed> $game
     */
    private static function requireInt(array $game, string $key, int|string $index): int
    {
        $value = $game[$key] ?? null;
        if (!is_int($value)) {
            throw new \InvalidArgumentException("games[{$index}].{$key} must be an int");
        }

        return $value;
    }

    /**
     * @param array<mixed> $game
     */
    private static function requireNonEmptyString(array $game, string $key, int|string $index): string
    {
        $value = $game[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("games[{$index}].{$key} must be a non-empty string");
        }

        return $value;
    }

    /**
     * Best-effort: themes are presentational, so any malformation degrades to
     * null rather than failing the whole store.
     */
    private static function parseThemes(mixed $raw): ?string
    {
        if (!is_array($raw) || !array_is_list($raw) || $raw === []) {
            return null;
        }
        foreach ($raw as $theme) {
            if (!is_string($theme)) {
                return null;
            }
        }

        $encoded = json_encode($raw);

        return $encoded === false ? null : $encoded;
    }
}
