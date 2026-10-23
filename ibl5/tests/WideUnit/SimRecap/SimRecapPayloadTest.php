<?php

declare(strict_types=1);

namespace Tests\WideUnit\SimRecap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimRecap\RecapDocument;
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
    private const HOST = 'iblhoops.net';

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
        $payload = SimRecapPayload::fromJson($this->makeValidJson(), self::HOST);

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
        $payload = SimRecapPayload::fromJson($this->makeValidJson(['games' => []]), self::HOST);

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

        $payload = SimRecapPayload::fromJson($json, self::HOST);

        self::assertNull($payload->getGames()[0]['box_id']);
    }

    public function testMalformedThemesDegradesToNullWithoutThrowing(): void
    {
        $payload = SimRecapPayload::fromJson($this->makeValidJson(['themes' => 'not-a-list']), self::HOST);

        self::assertNull($payload->getThemesJson(), 'Malformed themes must degrade to null, not throw');
    }

    // ── Negative / fail-closed paths ───────────────────────────────────────────

    public function testInvalidJsonStringThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SimRecapPayload::fromJson('{not valid json[}', self::HOST);
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

        SimRecapPayload::fromJson(json_encode($doc, JSON_THROW_ON_ERROR), self::HOST);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function missingRequiredStringFieldProvider(): array
    {
        return [
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
        ], JSON_THROW_ON_ERROR), self::HOST);
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
        ], JSON_THROW_ON_ERROR), self::HOST);
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
        ], JSON_THROW_ON_ERROR), self::HOST);
    }

    // ── game_of_that_day floor — fail-closed tests (Phase 1: written red) ──────

    /**
     * @return array<string, array{int}>
     */
    public static function belowOneGameOfThatDayProvider(): array
    {
        return [
            'zero'          => [0],
            'negative'      => [-1],
            'large negative' => [-99],
        ];
    }

    #[DataProvider('belowOneGameOfThatDayProvider')]
    public function testGameOfThatDayBelowOneThrows(int $gotd): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SimRecapPayload::fromJson($this->makeValidJson([
            'games' => [
                [
                    'season_year'      => 2025,
                    'game_date'        => '2025-01-01',
                    'visitor_teamid'   => 1,
                    'home_teamid'      => 2,
                    'game_of_that_day' => $gotd,
                    'box_id'           => 42,
                    'sort_order'       => 0,
                    'recap_text'       => 'Game recap.',
                ],
            ],
        ]), self::HOST);
    }

    public function testGameOfThatDayBelowOneMessageNamesTheField(): void
    {
        try {
            SimRecapPayload::fromJson($this->makeValidJson([
                'games' => [
                    [
                        'season_year'      => 2025,
                        'game_date'        => '2025-01-01',
                        'visitor_teamid'   => 1,
                        'home_teamid'      => 2,
                        'game_of_that_day' => 0,
                        'box_id'           => 42,
                        'sort_order'       => 0,
                        'recap_text'       => 'Game recap.',
                    ],
                ],
            ]), self::HOST);
            self::fail('Expected \InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(str_contains($e->getMessage(), 'games[0].game_of_that_day'));
            self::assertTrue(str_contains($e->getMessage(), '>= 1'));
        }
    }

    public function testSecondGameWithZeroGameOfThatDayThrowsWithItsOwnIndex(): void
    {
        try {
            SimRecapPayload::fromJson($this->makeValidJson([
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
                    [
                        'season_year'      => 2025,
                        'game_date'        => '2025-01-01',
                        'visitor_teamid'   => 3,
                        'home_teamid'      => 4,
                        'game_of_that_day' => 0,
                        'box_id'           => 43,
                        'sort_order'       => 1,
                        'recap_text'       => 'Game two recap.',
                    ],
                ],
            ]), self::HOST);
            self::fail('Expected \InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(str_contains($e->getMessage(), 'games[1].'));
        }
    }

    // ── game_of_that_day floor — boundary / anti-over-reach tests (Phase 3) ────

    public function testGameOfThatDayOneIsAcceptedAtTheBoundary(): void
    {
        $payload = SimRecapPayload::fromJson($this->makeValidJson(), self::HOST);

        $games = $payload->getGames();
        self::assertSame(1, $games[0]['game_of_that_day']);
    }

    public function testLargeGameOfThatDayIsAccepted(): void
    {
        $payload = SimRecapPayload::fromJson($this->makeValidJson([
            'games' => [
                [
                    'season_year'      => 2025,
                    'game_date'        => '2025-01-01',
                    'visitor_teamid'   => 1,
                    'home_teamid'      => 2,
                    'game_of_that_day' => 4,
                    'box_id'           => 42,
                    'sort_order'       => 0,
                    'recap_text'       => 'Game recap.',
                ],
            ],
        ]), self::HOST);

        self::assertSame(4, $payload->getGames()[0]['game_of_that_day']);
    }

    public function testSortOrderZeroIsStillAccepted(): void
    {
        $payload = SimRecapPayload::fromJson($this->makeValidJson([
            'games' => [
                [
                    'season_year'      => 2025,
                    'game_date'        => '2025-01-01',
                    'visitor_teamid'   => 1,
                    'home_teamid'      => 2,
                    'game_of_that_day' => 1,
                    'box_id'           => 42,
                    'sort_order'       => 0,
                    'recap_text'       => 'Game recap.',
                ],
            ],
        ]), self::HOST);

        self::assertSame(0, $payload->getGames()[0]['sort_order']);
    }

    public function testGameOfThatDayAsStringZeroStillThrowsIntError(): void
    {
        try {
            SimRecapPayload::fromJson($this->makeValidJson([
                'games' => [
                    [
                        'season_year'      => 2025,
                        'game_date'        => '2025-01-01',
                        'visitor_teamid'   => 1,
                        'home_teamid'      => 2,
                        'game_of_that_day' => '0',
                        'box_id'           => 42,
                        'sort_order'       => 0,
                        'recap_text'       => 'Game recap.',
                    ],
                ],
            ]), self::HOST);
            self::fail('Expected \InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(str_contains($e->getMessage(), 'must be an int'));
        }
    }

    // ── Intro/outro, link policy, and the per-block length cap ─────────────────

    /**
     * One game row as fromJson() parses it, with $prose as its recap_text.
     *
     * @return array{season_year: int, game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, box_id: ?int, sort_order: int, recap_text: string}
     */
    private function gameRow(string $prose): array
    {
        return [
            'season_year'      => 2025,
            'game_date'        => '2025-01-01',
            'visitor_teamid'   => 1,
            'home_teamid'      => 2,
            'game_of_that_day' => 1,
            'box_id'           => 42,
            'sort_order'       => 0,
            'recap_text'       => $prose,
        ];
    }

    private function jsonWithProse(string $prose): string
    {
        return $this->makeValidJson(['games' => [$this->gameRow($prose)]]);
    }

    /** Prose of $fill repeated so the rendered block is exactly $target characters. */
    private function proseRenderingTo(int $target, string $fill = 'x'): string
    {
        $overhead = mb_strlen(RecapDocument::gameBlock($this->gameRow($fill), self::HOST), 'UTF-8') - 1;

        return str_repeat($fill, $target - $overhead);
    }

    public function testAcceptsPayloadWithoutIntroAndOutro(): void
    {
        $doc = json_decode($this->makeValidJson(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($doc);
        unset($doc['intro_text'], $doc['outro_text']);

        $payload = SimRecapPayload::fromJson(json_encode($doc, JSON_THROW_ON_ERROR), self::HOST);

        self::assertSame('Full recap prose.', $payload->getRecapText());
        self::assertSame('Game one recap.', $payload->getGames()[0]['recap_text']);
    }

    public function testIgnoresLegacyIntroAndOutroFields(): void
    {
        $payload = SimRecapPayload::fromJson(
            $this->makeValidJson(['intro_text' => 5, 'outro_text' => ['x']]),
            self::HOST
        );

        self::assertSame('Full recap prose.', $payload->getRecapText());
    }

    public function testAcceptsGameBlockOfExactlyMaxChars(): void
    {
        $prose = $this->proseRenderingTo(SimRecapPayload::MAX_BLOCK_CHARS);
        self::assertSame(1900, mb_strlen(RecapDocument::gameBlock($this->gameRow($prose), self::HOST), 'UTF-8'));

        $payload = SimRecapPayload::fromJson($this->jsonWithProse($prose), self::HOST);

        self::assertSame($prose, $payload->getGames()[0]['recap_text']);
    }

    public function testRejectsGameBlockOfMaxCharsPlusOne(): void
    {
        $prose = $this->proseRenderingTo(SimRecapPayload::MAX_BLOCK_CHARS) . 'x';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('games[0] renders to 1901 chars');

        SimRecapPayload::fromJson($this->jsonWithProse($prose), self::HOST);
    }

    public function testLengthCapCountsTheLinksFooter(): void
    {
        $header = "**A 100 @ B 99**\n<@1> · <@2>\n\n";
        $prose  = $header . str_repeat('x', 1850 - mb_strlen($header, 'UTF-8'));
        self::assertSame(1850, mb_strlen($prose, 'UTF-8'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('over the 1900-char Discord block cap');

        SimRecapPayload::fromJson($this->jsonWithProse($prose), self::HOST);
    }

    public function testLengthCapCountsMultibyteCharactersOnce(): void
    {
        $prose = $this->proseRenderingTo(SimRecapPayload::MAX_BLOCK_CHARS, 'é');
        $block = RecapDocument::gameBlock($this->gameRow($prose), self::HOST);
        self::assertSame(1900, mb_strlen($block, 'UTF-8'));
        self::assertGreaterThan(1900, strlen($block));

        $payload = SimRecapPayload::fromJson($this->jsonWithProse($prose), self::HOST);

        self::assertCount(1, $payload->getGames());
    }

    public function testRejectsGameProseWithNonWikipediaLink(): void
    {
        try {
            SimRecapPayload::fromJson($this->jsonWithProse('A [x](<https://evil.com>) game.'), self::HOST);
            self::fail('a non-Wikipedia link in game prose must be rejected');
        } catch (\InvalidArgumentException $e) {
            self::assertStringStartsWith('games[0].recap_text:', $e->getMessage());
        }
    }

    public function testRejectsTeaserWithNonWikipediaLink(): void
    {
        try {
            SimRecapPayload::fromJson($this->makeValidJson(['recap_text' => 'Read it at https://evil.com']), self::HOST);
            self::fail('a non-Wikipedia link in the teaser must be rejected');
        } catch (\InvalidArgumentException $e) {
            self::assertStringStartsWith('recap_text:', $e->getMessage());
        }
    }

    public function testAcceptsGameProseWithWikipediaLink(): void
    {
        $prose   = 'Like [the Miracle on Ice](<https://en.wikipedia.org/wiki/Miracle_on_Ice>), nobody saw it coming.';
        $payload = SimRecapPayload::fromJson($this->jsonWithProse($prose), self::HOST);

        self::assertSame($prose, $payload->getGames()[0]['recap_text']);
    }

    public function testReportsBadLinkBeforeLength(): void
    {
        $prose = str_pad('A [x](<https://evil.com>) game. ', 2000, 'x');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('games[0].recap_text: link not allowed');

        SimRecapPayload::fromJson($this->jsonWithProse($prose), self::HOST);
    }

    public function testEmptyHostFallsBackToProductionHost(): void
    {
        $atCap = $this->proseRenderingTo(SimRecapPayload::MAX_BLOCK_CHARS);

        $payload = SimRecapPayload::fromJson($this->jsonWithProse($atCap), '');
        self::assertCount(1, $payload->getGames());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('renders to 1901 chars');
        SimRecapPayload::fromJson($this->jsonWithProse($atCap . 'x'), '');
    }
}
