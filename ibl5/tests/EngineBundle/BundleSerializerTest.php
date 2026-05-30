<?php

declare(strict_types=1);

namespace Tests\EngineBundle;

use EngineBundle\BundleSerializer;
use EngineBundle\Dto\Bundle;
use EngineBundle\Dto\Game;
use EngineBundle\Dto\Player;
use EngineBundle\Dto\Team;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the serializer emits JSON whose keys/types match the Go engine
 * contract in engine/internal/bundle/bundle.go exactly.
 */
class BundleSerializerTest extends TestCase
{
    private BundleSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new BundleSerializer();
    }

    /**
     * Decode a serialized one-of-each bundle to an assoc array.
     *
     * @param array<string, int|string> $playerOverrides
     * @return array<string, mixed>
     */
    private function serializeAndDecode(array $playerOverrides = []): array
    {
        $row = array_fill_keys(Player::FIELDS, 0);
        $row['name'] = 'Test Player';
        $row = array_merge($row, $playerOverrides);

        $bundle = new Bundle(
            leagueId: 1,
            seed: 42,
            teams: [new Team(3, 'New York Metros')],
            players: [Player::fromRow($row)],
            schedule: [new Game(homeTeamId: 3, visitorTeamId: 7, date: '2026-03-12', gameType: 2)],
        );

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($this->serializer->serialize($bundle), true, 512, JSON_THROW_ON_ERROR);
        return $decoded;
    }

    public function testBundleEnvelopeHasContractKeys(): void
    {
        $decoded = $this->serializeAndDecode();
        self::assertSame(
            ['league_id', 'seed', 'teams', 'players', 'schedule'],
            array_keys($decoded),
        );
        self::assertSame(1, $decoded['league_id']);
        self::assertSame(42, $decoded['seed']);
    }

    public function testPlayerHasExactlyTheContractKeys(): void
    {
        $decoded = $this->serializeAndDecode();
        /** @var list<array<string, mixed>> $players */
        $players = $decoded['players'];
        self::assertCount(1, $players);

        // Exact contract: same keys, same order, nothing extra, nothing missing.
        self::assertSame(Player::FIELDS, array_keys($players[0]));
        // 3 identity + 8 ODPT + 13 main ratings + 8 attributes + 12 depth = 44.
        self::assertCount(44, Player::FIELDS);
    }

    public function testPlayerScalarTypesMatchContract(): void
    {
        $decoded = $this->serializeAndDecode(['pid' => 101, 'teamid' => 3, 'oo' => 7, 'dc_minutes' => 34]);
        /** @var list<array<string, mixed>> $players */
        $players = $decoded['players'];
        $p = $players[0];

        self::assertIsInt($p['pid']);
        self::assertIsInt($p['teamid']);
        self::assertIsInt($p['oo']);
        self::assertIsInt($p['dc_minutes']);
        self::assertIsString($p['name']);
        self::assertSame(101, $p['pid']);
        self::assertSame('Test Player', $p['name']);
    }

    public function testTeamKeysMatchContract(): void
    {
        $decoded = $this->serializeAndDecode();
        /** @var list<array<string, mixed>> $teams */
        $teams = $decoded['teams'];
        self::assertSame(['teamid', 'name'], array_keys($teams[0]));
        self::assertSame(3, $teams[0]['teamid']);
        self::assertSame('New York Metros', $teams[0]['name']);
    }

    public function testGameKeysAreRemappedToContract(): void
    {
        $decoded = $this->serializeAndDecode();
        /** @var list<array<string, mixed>> $schedule */
        $schedule = $decoded['schedule'];
        // The DB columns are home_teamid/visitor_teamid/game_date — the contract
        // tags are home_team_id/visitor_team_id/date. Assert the remapped form.
        self::assertSame(['home_team_id', 'visitor_team_id', 'date', 'game_type'], array_keys($schedule[0]));
        self::assertSame(3, $schedule[0]['home_team_id']);
        self::assertSame(7, $schedule[0]['visitor_team_id']);
        self::assertSame('2026-03-12', $schedule[0]['date']);
        self::assertSame(2, $schedule[0]['game_type']);
    }

    /**
     * Negative: a player row missing some fields must still emit all 44 contract
     * keys (defaulted), never a silently-dropped key that would break the contract.
     */
    public function testMissingPlayerFieldsStillEmitAllContractKeys(): void
    {
        // fromRow with only a couple of fields present.
        $player = Player::fromRow(['pid' => 5, 'name' => 'Sparse']);
        $bundle = new Bundle(1, 1, [], [$player], [new Game(1, 2, '2026-01-01', 2)]);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($this->serializer->serialize($bundle), true, 512, JSON_THROW_ON_ERROR);
        /** @var list<array<string, mixed>> $players */
        $players = $decoded['players'];

        self::assertSame(Player::FIELDS, array_keys($players[0]));
        self::assertSame(5, $players[0]['pid']);
        self::assertSame('Sparse', $players[0]['name']);
        self::assertSame(0, $players[0]['dc_minutes']); // defaulted, present, int
    }
}
