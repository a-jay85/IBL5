<?php

declare(strict_types=1);

namespace Tests\WideUnit\SimRecap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimRecap\SimRecapPayload;

/**
 * Pure unit tests for the SimRecapPayload DTO.
 *
 * No DB, no script execution — the script posts to Discord on success, so it
 * must never run under PHPUnit. All fail-closed logic is testable here via the
 * extracted DTO seam.
 */
final class SimRecapPayloadTest extends TestCase
{
    // ── Fixtures ───────────────────────────────────────────────────────────────

    /**
     * Build a minimal valid JSON document, optionally overriding top-level keys.
     *
     * @param array<string, mixed> $overrides
     */
    private function makeValidJson(array $overrides = []): string
    {
        $doc = array_merge([
            'intro_text' => 'Great intro.',
            'outro_text' => 'Great outro.',
            'recap_text' => 'Full recap prose.',
            'games' => [
                [
                    'season_year'      => 2025,
                    'game_date'        => '2025-01-01',
                    'visitor_teamid'   => 1,
                    'home_teamid'      => 2,
                    'game_of_that_day' => 1,
                    'box_id'           => 42,
                    'sort_order'       => 0,
                    'recap_text'       => 'Game one recap.',
                ],
            ],
            'themes' => ['comeback', 'blowout'],
        ], $overrides);

        return json_encode($doc, JSON_THROW_ON_ERROR);
    }

    // ── Happy path ─────────────────────────────────────────────────────────────

    public function testHappyPathAllGettersReturnTypedValues(): void
    {
        $payload = SimRecapPayload::fromJson($this->makeValidJson());

        self::assertSame('Great intro.', $payload->getIntroText());
        self::assertSame('Great outro.', $payload->getOutroText());
        self::assertSame('Full recap prose.', $payload->getRecapText());
        // themes are re-encoded by the DTO — compare against json_encode output
        self::assertSame(json_encode(['comeback', 'blowout']), $payload->getThemesJson());

        $games = $payload->getGames();
        self::assertCount(1, $games);
        self::assertSame(2025, $games[0]['season_year']);
        self::assertSame('2025-01-01', $games[0]['game_date']);
        self::assertSame(1, $games[0]['visitor_teamid']);
        self::assertSame(2, $games[0]['home_teamid']);
        self::assertSame(1, $games[0]['game_of_that_day']);
        self::assertSame(42, $games[0]['box_id']);
        self::assertSame(0, $games[0]['sort_order']);
        self::assertSame('Game one recap.', $games[0]['recap_text']);
    }

    public function testEmptyGamesListIsAccepted(): void
    {
        $payload = SimRecapPayload::fromJson($this->makeValidJson(['games' => []]));

        self::assertSame([], $payload->getGames());
    }

    public function testBoxIdNullInGameIsAccepted(): void
    {
        $json = $this->makeValidJson([
            'games' => [
                [
                    'season_year'      => 2025,
                    'game_date'        => '2025-01-01',
                    'visitor_teamid'   => 1,
                    'home_teamid'      => 2,
                    'game_of_that_day' => 1,
                    'box_id'           => null,
                    'sort_order'       => 0,
                    'recap_text'       => 'Game recap.',
                ],
            ],
        ]);

        $payload = SimRecapPayload::fromJson($json);

        self::assertNull($payload->getGames()[0]['box_id']);
    }

    public function testMalformedThemesDegradesToNullWithoutThrowing(): void
    {
        $payload = SimRecapPayload::fromJson($this->makeValidJson(['themes' => 'not-a-list']));

        self::assertNull($payload->getThemesJson(), 'Malformed themes must degrade to null, not throw');
    }

    // ── Negative / fail-closed paths ───────────────────────────────────────────

    public function testInvalidJsonStringThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SimRecapPayload::fromJson('{not valid json[}');
    }

    /**
     * @param array<string, mixed> $fieldOverride
     */
    #[DataProvider('missingRequiredStringFieldProvider')]
    public function testMissingRequiredStringFieldThrows(array $fieldOverride): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $doc = array_merge([
            'intro_text' => 'Intro.',
            'outro_text' => 'Outro.',
            'recap_text' => 'Recap.',
            'games'      => [],
        ], $fieldOverride);

        SimRecapPayload::fromJson(json_encode($doc, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function missingRequiredStringFieldProvider(): array
    {
        return [
            'missing intro_text' => [['intro_text' => null]],
            'missing outro_text' => [['outro_text' => null]],
            'missing recap_text' => [['recap_text' => null]],
        ];
    }

    public function testGamesNotAListThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SimRecapPayload::fromJson(json_encode([
            'intro_text' => 'Intro.',
            'outro_text' => 'Outro.',
            'recap_text' => 'Recap.',
            'games'      => ['key' => 'not-a-list'],  // associative — not a list
        ], JSON_THROW_ON_ERROR));
    }

    public function testGameMissingRequiredFieldThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SimRecapPayload::fromJson(json_encode([
            'intro_text' => 'Intro.',
            'outro_text' => 'Outro.',
            'recap_text' => 'Recap.',
            'games'      => [
                [
                    // season_year absent — any missing int field must throw
                    'game_date'        => '2025-01-01',
                    'visitor_teamid'   => 1,
                    'home_teamid'      => 2,
                    'game_of_that_day' => 1,
                    'sort_order'       => 0,
                    'recap_text'       => 'Game recap.',
                ],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function testGameIntFieldAsStringThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // visitor_teamid is "3" (string) — the DTO uses is_int, never casts
        SimRecapPayload::fromJson(json_encode([
            'intro_text' => 'Intro.',
            'outro_text' => 'Outro.',
            'recap_text' => 'Recap.',
            'games'      => [
                [
                    'season_year'      => 2025,
                    'game_date'        => '2025-01-01',
                    'visitor_teamid'   => '3',
                    'home_teamid'      => 2,
                    'game_of_that_day' => 1,
                    'box_id'           => null,
                    'sort_order'       => 0,
                    'recap_text'       => 'Game recap.',
                ],
            ],
        ], JSON_THROW_ON_ERROR));
    }
}
