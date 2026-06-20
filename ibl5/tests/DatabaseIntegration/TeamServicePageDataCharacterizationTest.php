<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\LeagueContext;
use PHPUnit\Framework\Attributes\Group;
use Team\TeamRepository;
use Team\TeamService;

/**
 * Golden-master characterization net for Team\TeamService::getTeamPageData().
 *
 * This file pins the CURRENT byte-for-byte output of getTeamPageData() so a
 * FUTURE refactor (moving view instantiation out of TeamService into a
 * controller) can run green-green against a frozen baseline. It adds NO
 * production code — every method here captures and freezes real behavior.
 *
 * Golden discipline (capture-and-freeze, never imagine): each HTML blob is a
 * `G_*` nowdoc class constant whose body was captured verbatim from a real run
 * against the deterministic fixtures below — never hand-written. See the plan
 * `god-class-net-teamservice.md` for the capture procedure. Re-capture by
 * running this class with the env var CAPTURE_GOLDENS=1 (which dumps every
 * blob to ibl5/.goldens/<label>.txt) and regenerating the constants.
 *
 * Determinism: a synthetic team "CharTest" (teamid 99, sole occupant of a
 * uniquely-named division/conference → standings position deterministically 1)
 * is self-seeded inside each test's rolled-back transaction. Every repository
 * query getTeamPageData fans out to is name- or id-scoped to this team, so the
 * goldens never depend on ambient db-seed.sql rows. Season reads the league
 * 'ibl' settings (Regular Season / ending year 2026). No date/random tokens
 * leak into the render path (verified: no date()/rand()/uniqid() in the
 * TeamService → view chain).
 */
#[Group('database')]
final class TeamServicePageDataCharacterizationTest extends DatabaseTestCase
{
    private const TEAM_ID = 99;
    private const TEAM_NAME = 'CharTest';
    private const TEAM_CITY = 'Testville';

    private TeamService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Real wiring: TeamService builds its own TeamQueryRepository, League,
        // Season and TeamTableService internally. LeagueContext::getConfig()
        // for the default 'ibl' league returns a hardcoded images_path
        // ('images/') with no DB hit, so the real object is deterministic.
        $this->service = new TeamService($this->db, new TeamRepository($this->db), new LeagueContext());
    }

    /**
     * Seed a self-contained, deterministic current-season team.
     *
     * One ibl_team_info row (fixed colors/arena/capacity/owner), one
     * ibl_standings row in a uniquely-named division+conference (sole occupant
     * → position 1), one ibl_power row. Every value is fixed; nothing is read
     * from ambient seed rows. The 'ibl' "Current Season Phase"/"Current Season
     * Ending Year" settings already exist in the loaded seed (Regular Season /
     * 2026).
     */
    private function seedCharTeam(
        int $teamid = self::TEAM_ID,
        string $name = self::TEAM_NAME,
        string $city = self::TEAM_CITY,
    ): void {
        $this->insertRow('ibl_team_info', [
            'teamid' => $teamid,
            'team_city' => $city,
            'team_name' => $name,
            'color1' => '102030',
            'color2' => 'A0B0C0',
            'arena' => 'Test Arena',
            'capacity' => 18000,
            'owner_name' => 'Test Owner',
            'owner_email' => 'owner@test.local',
            'gm_username' => 'char_gm',
        ]);

        $this->insertRow('ibl_standings', [
            'teamid' => $teamid,
            'team_name' => $name,
            'pct' => 0.600,
            'league_record' => '12-8',
            'wins' => 12,
            'losses' => 8,
            'conference' => 'CharTestConf',
            'conf_record' => '7-5',
            'conf_gb' => 0.0,
            'division' => 'CharTestDiv',
            'div_record' => '4-2',
            'div_gb' => 0.0,
            'home_record' => '8-2',
            'away_record' => '4-6',
            'games_unplayed' => 62,
        ]);

        $this->insertRow('ibl_power', [
            'teamid' => $teamid,
            'ranking' => 5,
            'last_win' => 3,
            'last_loss' => 1,
            'streak_type' => 'W',
            'streak' => 2,
            'sos' => 0.500,
            'remaining_sos' => 0.510,
        ]);
    }

    /**
     * Capture-or-assert a golden HTML blob.
     *
     * In capture mode (env CAPTURE_GOLDENS=1) the actual value is dumped to
     * ibl5/.goldens/<label>.txt for constant regeneration and no assertion is
     * made. Otherwise the value is asserted byte-for-byte against the frozen
     * `G_<UPPER(label)>` class constant; a missing constant FAILS (never passes
     * vacuously).
     */
    private function assertGolden(string $label, string $actual): void
    {
        if (getenv('CAPTURE_GOLDENS') === '1') {
            $dir = __DIR__ . '/../../.goldens';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($dir . '/' . $label . '.txt', $actual);
            return;
        }

        $const = self::class . '::G_' . strtoupper($label);
        self::assertTrue(defined($const), "Missing golden constant for label '$label' ($const)");
        self::assertSame(constant($const), $actual, "Golden mismatch for label '$label'");
    }

    // === GENERATED GOLDENS START ===
    // Nowdoc `G_*` constants are spliced in here from ibl5/.goldens/*.txt.
    // Do not edit by hand — re-capture with CAPTURE_GOLDENS=1 and regenerate.

    private const G_BASELINE_AWARDSCARD = <<<'GOLDEN'

GOLDEN;

    private const G_BASELINE_CURRENTSEASONCARD = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h3 class="team-card__title">Current Season</h3></div><div class="team-card__body"><div class="team-info-list"><span class="team-info-list__label">Team</span><span class="team-info-list__value">CharTest</span><span class="team-info-list__label">Record</span><span class="team-info-list__value">12-8</span><span class="team-info-list__label">Arena</span><span class="team-info-list__value">Test Arena</span><span class="team-info-list__label">Capacity</span><span class="team-info-list__value">18000</span><span class="team-info-list__label">Conference</span><span class="team-info-list__value">CharTestConf (1st)</span><span class="team-info-list__label">Division</span><span class="team-info-list__value">CharTestDiv (1st)</span><span class="team-info-list__label">Games Back</span><span class="team-info-list__value">0</span><span class="team-info-list__label">Home</span><span class="team-info-list__value">8-2</span><span class="team-info-list__label">Road</span><span class="team-info-list__value">4-6</span><span class="team-info-list__label">Last 10</span><span class="team-info-list__value">3-1</span></div></div></div>
GOLDEN;

    private const G_BASELINE_DRAFTPICKSTABLE = <<<'GOLDEN'
<ul class="draft-picks-list"><li class="draft-picks-list__item"><a href="modules.php?name=Team&amp;op=team&amp;teamid=1"><img class="draft-picks-list__logo" src="images/logo/Metros.png" height="24" width="24" alt="Metros"></a><div class="draft-picks-list__info"><a href="modules.php?name=Team&amp;op=team&amp;teamid=1">2027 R1 New York Metros</a></div></li></ul>
GOLDEN;

    private const G_BASELINE_FRANCHISEHISTORYCARD = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h3 class="team-card__title">Franchise History</h3></div><div class="franchise-history-columns"><div class="franchise-history-column"><h4 class="franchise-history-column__title">H.E.A.T.</h4><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h4 class="franchise-history-column__title">Regular Season</h4><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h4 class="franchise-history-column__title">Playoffs</h4><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_BASELINE_RAFTERS = <<<'GOLDEN'

GOLDEN;

    private const G_BASELINE_TABLEOUTPUT = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+v.substring(6)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings" selected>Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_13">vs. Apollos</option><option value="split:vs_16">vs. Blizzard</option><option value="split:vs_15">vs. Blues</option><option value="split:vs_18">vs. Bucks</option><option value="split:vs_22">vs. Cavaliers</option><option value="split:vs_3">vs. Cougars</option><option value="split:vs_4">vs. Diesels</option><option value="split:vs_9">vs. Flames</option><option value="split:vs_25">vs. Generals</option><option value="split:vs_17">vs. Huskies</option><option value="split:vs_27">vs. Jazz</option><option value="split:vs_21">vs. Mavericks</option><option value="split:vs_1">vs. Metros</option><option value="split:vs_5">vs. Minutemen</option><option value="split:vs_8">vs. Monarchs</option><option value="split:vs_24">vs. Nets</option><option value="split:vs_19">vs. Nuggets</option><option value="split:vs_26">vs. Pacers</option><option value="split:vs_14">vs. Phoenixes</option><option value="split:vs_20">vs. Pilots</option><option value="split:vs_11">vs. Pioneers</option><option value="split:vs_6">vs. Rage</option><option value="split:vs_12">vs. Royals</option><option value="split:vs_10">vs. Spurs</option><option value="split:vs_2">vs. Stars</option><option value="split:vs_23">vs. Supersonics</option><option value="split:vs_28">vs. Thunder</option><option value="split:vs_7">vs. Tropics</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th class="sticky-col">Player</th>
            <th>Pos</th>
            <th class="sep-r-team">Age</th>
            <th>2ga</th>
            <th>2g%</th>
            <th>fta</th>
            <th>ft%</th>
            <th>3ga</th>
            <th class="sep-r-team">3g%</th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th class="sep-r-team">foul</th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th class="sep-r-team">td</th>
            <th>Clu</th>
            <th class="sep-r-team">Con</th>
            <th>Days Injured</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Anchor</span></a></td>            <td>PG</td>
            <td class="sep-r-team">27</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000002"><img src="./images/player/200000002.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bobby Baseline</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Baseline</span></a></td>            <td>SG</td>
            <td class="sep-r-team">27</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
                        <td>0</td>
        </tr>
    </tbody>
</table>
        
GOLDEN;

    private const G_TID0_TABLEOUTPUT = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #888888; --team-color-secondary: #cccccc;"><caption class="team-table-caption"><div class="ibl-tabs" style="--team-color-primary: #888888; --team-color-secondary: #cccccc;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=ratings" class="ibl-tab ibl-tab--active" data-display="ratings" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=ratings" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=ratings">Ratings</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=total_s" class="ibl-tab" data-display="total_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=total_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=total_s">Season Totals</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=avg_s" class="ibl-tab" data-display="avg_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=avg_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=avg_s">Season Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=per36mins" class="ibl-tab" data-display="per36mins" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=per36mins" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=per36mins">Per 36 Minutes</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=chunk" class="ibl-tab" data-display="chunk" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=chunk" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=chunk">Sim Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=contracts" class="ibl-tab" data-display="contracts" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=contracts" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=contracts">Contracts</a></div></caption>
    <thead>
        <tr>
            <th class="sticky-col">Player</th>
            <th>Pos</th>
            <th class="sep-r-team">Age</th>
            <th>2ga</th>
            <th>2g%</th>
            <th>fta</th>
            <th>ft%</th>
            <th>3ga</th>
            <th class="sep-r-team">3g%</th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th class="sep-r-team">foul</th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th class="sep-r-team">td</th>
            <th>Clu</th>
            <th class="sep-r-team">Con</th>
            <th>Days Injured</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2"><img src="./images/player/2.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Test Player Two</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Player Two</span></a></td>            <td>SF</td>
            <td class="sep-r-team">22</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
                        <td>0</td>
        </tr>
    </tbody>
</table>
        
GOLDEN;

    // === GENERATED GOLDENS END ===

    // -------------------------------------------------------------------------
    // Phase 0 — scaffold + contract smoke / not-found boundary
    // -------------------------------------------------------------------------

    public function testGetTeamPageDataReturnsExpectedTopLevelKeys(): void
    {
        $this->seedCharTeam();

        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);

        self::assertSame([
            'teamid',
            'team',
            'imagesPath',
            'yr',
            'display',
            'insertyear',
            'isActualTeam',
            'tableOutput',
            'draftPicksTable',
            'currentSeasonCard',
            'awardsCard',
            'franchiseHistoryCard',
            'rafters',
            'userTeamName',
            'isOwnTeam',
            'extensionResult',
            'extensionMsg',
        ], array_keys($result));
    }

    public function testGetTeamPageDataThrowsOnUnknownTeam(): void
    {
        // teamid 12345 is not present in ibl_team_info → Team::initialize()'s
        // load() throws "Team not found", pinning the not-found contract.
        $this->expectException(\RuntimeException::class);
        $this->service->getTeamPageData(12345, null, 'ratings', '', null);
    }

    // -------------------------------------------------------------------------
    // Phase 1 — baseline full-array golden + teamid=0 boundary
    // -------------------------------------------------------------------------

    public function testBaselineCurrentSeasonRatingsFullArray(): void
    {
        $this->seedCharTeam();
        // Minimal fixed roster so the ratings table renders without bloating.
        $this->insertTestPlayer(200000001, 'Aaron Anchor', ['teamid' => self::TEAM_ID, 'pos' => 'PG', 'ordinal' => 1]);
        $this->insertTestPlayer(200000002, 'Bobby Baseline', ['teamid' => self::TEAM_ID, 'pos' => 'SG', 'ordinal' => 2]);
        // One owned draft pick (the pick belongs to a real team → resolvable).
        $this->insertDraftPickRow(self::TEAM_ID, 1, 2027, 1);

        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);

        // Non-object scalars.
        self::assertSame(self::TEAM_ID, $result['teamid']);
        self::assertSame('images/', $result['imagesPath']);
        self::assertNull($result['yr']);
        self::assertSame('ratings', $result['display']);
        self::assertSame('', $result['insertyear']);
        self::assertTrue($result['isActualTeam']);
        self::assertSame('', $result['userTeamName']);
        self::assertFalse($result['isOwnTeam']);
        self::assertNull($result['extensionResult']);
        self::assertNull($result['extensionMsg']);

        // The `team` key is a Team object — assert scalar public props individually.
        $team = $result['team'];
        self::assertInstanceOf(\Team\Team::class, $team);
        self::assertSame(self::TEAM_ID, $team->teamid);
        self::assertSame(self::TEAM_NAME, $team->name);
        self::assertSame(self::TEAM_CITY, $team->city);
        self::assertSame('102030', $team->color1);
        self::assertSame('A0B0C0', $team->color2);
        self::assertSame('Test Arena', $team->arena);
        self::assertSame(18000, $team->capacity);
        self::assertSame('Test Owner', $team->ownerName);
        self::assertSame('12-8', $team->seasonRecord);

        // The six rendered-HTML blobs, frozen verbatim.
        self::assertGolden('baseline_tableoutput', $result['tableOutput']);
        self::assertGolden('baseline_draftpickstable', $result['draftPicksTable']);
        self::assertGolden('baseline_currentseasoncard', $result['currentSeasonCard']);
        self::assertGolden('baseline_awardscard', $result['awardsCard']);
        self::assertGolden('baseline_franchisehistorycard', $result['franchiseHistoryCard']);
        self::assertGolden('baseline_rafters', $result['rafters']);
    }

    public function testTeamidZeroIsNotActualTeamAndSidebarsEmpty(): void
    {
        // teamid 0 = Free Agents (present in seed). isActualTeam=false short-
        // circuits all sidebar/draft rendering; only tableOutput renders.
        $result = $this->service->getTeamPageData(0, null, 'ratings', '', null);

        self::assertFalse($result['isActualTeam']);
        self::assertSame('', $result['draftPicksTable']);
        self::assertSame('', $result['currentSeasonCard']);
        self::assertSame('', $result['awardsCard']);
        self::assertSame('', $result['franchiseHistoryCard']);
        self::assertSame('', $result['rafters']);

        // tableOutput for the free-agents path is its own frozen golden.
        self::assertGolden('tid0_tableoutput', $result['tableOutput']);
    }
}
