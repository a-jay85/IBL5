<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Trading;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * DB characterization pin for Trading\TradeRosterPreviewApiHandler.
 *
 * Each test runs inside DatabaseTestCase's rolled-back transaction — no commit
 * is ever called, so no production data is mutated. The seeded fixture is a
 * synthetic team (teamid 99, sole occupant of CharTestConf/CharTestDiv) with
 * one player 'Preview Pinner' whose cy (1) !== cyt (3), making the row survive
 * both getRosterUnderContract() and getFreeAgencyRoster() regardless of season
 * phase.
 *
 * DISCIPLINE: Tests 5-8 assert empty HTML. Those assertions are load-bearing
 * ONLY because tests 1-4 prove the same fixture ('Preview Pinner' on team 99)
 * yields non-empty HTML for valid requests. The handler's catch(\Throwable)
 * block swallows all crashes as '' — so if tests 1-4 are weakened, tests 5-8
 * lose all signal. Do NOT relax the assertStringContainsString('Preview Pinner')
 * assertions in tests 1-4 to a bare assertNotSame('', $html).
 */
#[Group('database')]
final class TradeRosterPreviewApiCharacterizationTest extends DatabaseTestCase
{
    private const TEAM_ID = 99;
    private const PID     = 200140101;
    private const PLAYER  = 'Preview Pinner';

    protected function setUp(): void
    {
        parent::setUp();

        // Three inserts copied verbatim from
        // TeamServicePageDataCharacterizationTest::seedCharTeam()
        $this->insertRow('ibl_team_info', [
            'teamid'      => self::TEAM_ID,
            'team_city'   => 'Testville',
            'team_name'   => 'CharTest',
            'color1'      => '102030',
            'color2'      => 'A0B0C0',
            'arena'       => 'Test Arena',
            'capacity'    => 18000,
            'owner_name'  => 'Test Owner',
            'owner_email' => 'owner@test.local',
            'gm_username' => 'char_gm',
        ]);

        $this->insertRow('ibl_standings', [
            'teamid'         => self::TEAM_ID,
            'team_name'      => 'CharTest',
            'pct'            => 0.600,
            'league_record'  => '12-8',
            'wins'           => 12,
            'losses'         => 8,
            'conference'     => 'CharTestConf',
            'conf_record'    => '7-5',
            'conf_gb'        => 0.0,
            'division'       => 'CharTestDiv',
            'div_record'     => '4-2',
            'div_gb'         => 0.0,
            'home_record'    => '8-2',
            'away_record'    => '4-6',
            'games_unplayed' => 62,
        ]);

        $this->insertRow('ibl_power', [
            'teamid'        => self::TEAM_ID,
            'ranking'       => 5,
            'last_win'      => 3,
            'last_loss'     => 1,
            'streak_type'   => 'W',
            'streak'        => 2,
            'sos'           => 0.500,
            'remaining_sos' => 0.510,
        ]);

        // cy (1) !== cyt (3) deliberately — survives both roster query paths
        $this->insertTestPlayer(self::PID, self::PLAYER, [
            'teamid'     => self::TEAM_ID,
            'cy'         => 1,
            'cyt'        => 3,
            'salary_yr1' => 100,
            'salary_yr2' => 100,
            'ordinal'    => 1,
        ]);

        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    /**
     * Set $_GET, capture handle() JSON output, return decoded array.
     *
     * @param array<string, string> $get
     * @return array<string, mixed>
     */
    private function invoke(array $get): array
    {
        $_GET = $get;
        ob_start();
        $handler = new \Trading\TradeRosterPreviewApiHandler(
            $this->db,
            new \Trading\TradeAssetRepository($this->db),
            self::TEAM_ID
        );
        $handler->handle();
        $json = (string) ob_get_clean();
        /** @var array<string, mixed> */
        return json_decode($json, true);
    }

    // ── Valid-request pins (tests 1-4 give 5-8 their signal) ───────

    public function testValidRatingsRequestRendersSeededPlayer(): void
    {
        $decoded = $this->invoke(['teamid' => '99', 'display' => 'ratings']);

        self::assertIsArray($decoded);
        $html = (string) ($decoded['html'] ?? '');
        self::assertNotSame('', $html);
        self::assertStringContainsString(self::PLAYER, $html);
    }

    public function testUnknownDisplayRendersIdenticalHtmlToRatings(): void
    {
        $bogus   = $this->invoke(['teamid' => '99', 'display' => 'bogus']);
        $ratings = $this->invoke(['teamid' => '99', 'display' => 'ratings']);

        self::assertSame($ratings['html'], $bogus['html']);
    }

    public function testInvalidSplitKeyRendersIdenticalHtmlToRatings(): void
    {
        $split   = $this->invoke(['teamid' => '99', 'display' => 'split', 'split' => 'not-a-key']);
        $ratings = $this->invoke(['teamid' => '99', 'display' => 'ratings']);

        self::assertSame($ratings['html'], $split['html']);
    }

    public function testContractsWithCashParamsRendersCashLabel(): void
    {
        $decoded = $this->invoke([
            'teamid'        => '99',
            'display'       => 'contracts',
            'userTeam'      => 'CharTest',
            'partnerTeam'   => 'Rivals',
            'userTeamId'    => '99',
            'cashStartYear' => '1',
            'cashEndYear'   => '2',
            'userCash1'     => '50',
        ]);

        $html = (string) ($decoded['html'] ?? '');
        self::assertStringContainsString('Cash to ', $html);
        self::assertStringContainsString('Rivals', $html);
        self::assertStringContainsString(self::PLAYER, $html);
    }

    // ── Negative pins (load-bearing because tests 1-4 proved non-empty) ──

    public function testRejectedTeamIdReturnsEmptyHtml(): void
    {
        $decoded = $this->invoke(['teamid' => '0']);

        self::assertSame('', (string) ($decoded['html'] ?? ''));
    }

    public function testNonNumericAddPidsReturnsEmptyHtml(): void
    {
        $decoded = $this->invoke(['teamid' => '99', 'addPids' => '1,abc']);

        self::assertSame('', (string) ($decoded['html'] ?? ''));
    }

    // ── Boundary pin ────────────────────────────────────────────────

    public function testAddPidsAtAndAboveMaximum(): void
    {
        // 20 PIDs (at MAX_PIDS) — validation passes; seeded player still renders
        $twentyPids = implode(
            ',',
            array_map(
                static fn (int $i): string => (string) (200200000 + $i),
                range(1, 20)
            )
        );
        $atMax    = $this->invoke(['teamid' => '99', 'addPids' => $twentyPids]);
        $htmlAtMax = (string) ($atMax['html'] ?? '');
        self::assertNotSame('', $htmlAtMax);
        self::assertStringContainsString(self::PLAYER, $htmlAtMax);

        // 21 PIDs (exceeds MAX_PIDS) — validation fails; returns ''
        $twentyOnePids = implode(
            ',',
            array_map(
                static fn (int $i): string => (string) (200200000 + $i),
                range(1, 21)
            )
        );
        $aboveMax = $this->invoke(['teamid' => '99', 'addPids' => $twentyOnePids]);
        self::assertSame('', (string) ($aboveMax['html'] ?? ''));
    }

    // ── Response shape pin ───────────────────────────────────────────

    public function testResponseCarriesOnlyTheHtmlKey(): void
    {
        $decoded = $this->invoke(['teamid' => '99', 'display' => 'ratings']);

        self::assertSame(['html'], array_keys($decoded));
    }
}
