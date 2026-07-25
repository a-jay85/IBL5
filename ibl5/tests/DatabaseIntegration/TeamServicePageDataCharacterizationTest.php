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

    private const G_AWARDS_EMPTY_ACCOMPLISHMENTS = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Awards</h2></div><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">GM History</strong></div><div class="team-card__body"><ul class="team-awards-list"><li><span class="award-year">2020-2026</span> Test Owner</li></ul></div></div>
GOLDEN;

    private const G_AWARDS_GM_AND_TEAM = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Awards</h2></div><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">GM History</strong></div><div class="team-card__body"><ul class="team-awards-list"><li><span class="award-year">2020-2026</span> Test Owner</li></ul></div><div class="team-card__body team-card__body--tight team-card__body--bordered"><strong class="team-card__section-label">Team Accomplishments</strong></div><div class="team-card__body"><ul class="team-awards-list"><li><span class="award-year">2024</span> Atlantic Division Champions</li></ul></div></div>
GOLDEN;

    private const G_BASELINE_AWARDSCARD = <<<'GOLDEN'

GOLDEN;

    private const G_BASELINE_CURRENTSEASONCARD = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Current Season</h2></div><div class="team-card__body"><div class="team-info-list"><span class="team-info-list__label">Team</span><span class="team-info-list__value">CharTest</span><span class="team-info-list__label">Record</span><span class="team-info-list__value">12-8</span><span class="team-info-list__label">Arena</span><span class="team-info-list__value">Test Arena</span><span class="team-info-list__label">Capacity</span><span class="team-info-list__value">18000</span><span class="team-info-list__label">Conference</span><span class="team-info-list__value">CharTestConf (1st)</span><span class="team-info-list__label">Division</span><span class="team-info-list__value">CharTestDiv (1st)</span><span class="team-info-list__label">Games Back</span><span class="team-info-list__value">0</span><span class="team-info-list__label">Home</span><span class="team-info-list__value">8-2</span><span class="team-info-list__label">Road</span><span class="team-info-list__value">4-6</span><span class="team-info-list__label">Last 10</span><span class="team-info-list__value">3-1</span></div></div></div>
GOLDEN;

    private const G_BASELINE_DRAFTPICKSTABLE = <<<'GOLDEN'
<ul class="draft-picks-list"><li class="draft-picks-list__item"><a href="modules.php?name=Team&amp;op=team&amp;teamid="><img class="draft-picks-list__logo" src="images/logo/Metros.png" height="24" width="24" alt="Metros"></a><div class="draft-picks-list__info"><a href="modules.php?name=Team&amp;op=team&amp;teamid=">2027 R1  Metros</a></div></li></ul>
GOLDEN;

    private const G_BASELINE_FRANCHISEHISTORYCARD = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_BASELINE_RAFTERS = <<<'GOLDEN'

GOLDEN;

    private const G_BASELINE_TABLEOUTPUT = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+encodeURIComponent(v.substring(6))}else{d=encodeURIComponent(d)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings" selected>Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_16">vs. Aces</option><option value="split:vs_22">vs. Braves</option><option value="split:vs_6">vs. Bucks</option><option value="split:vs_27">vs. Bullets</option><option value="split:vs_7">vs. Bulls</option><option value="split:vs_1">vs. Celtics</option><option value="split:vs_19">vs. Clippers</option><option value="split:vs_20">vs. Grizzlies</option><option value="split:vs_9">vs. Hawks</option><option value="split:vs_2">vs. Heat</option><option value="split:vs_13">vs. Jazz</option><option value="split:vs_26">vs. Kings</option><option value="split:vs_3">vs. Knicks</option><option value="split:vs_21">vs. Lakers</option><option value="split:vs_5">vs. Magic</option><option value="split:vs_28">vs. Mavericks</option><option value="split:vs_4">vs. Nets</option><option value="split:vs_15">vs. Nuggets</option><option value="split:vs_11">vs. Pacers</option><option value="split:vs_8">vs. Pelicans</option><option value="split:vs_25">vs. Pistons</option><option value="split:vs_12">vs. Raptors</option><option value="split:vs_17">vs. Rockets</option><option value="split:vs_10">vs. Sting</option><option value="split:vs_23">vs. Suns</option><option value="split:vs_14">vs. Timberwolves</option><option value="split:vs_18">vs. Trailblazers</option><option value="split:vs_24">vs. Warriors</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
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

    private const G_CURRENT_FKA = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Current Season</h2></div><div class="team-card__body"><div class="team-info-list"><span class="team-info-list__label">Team</span><span class="team-info-list__value">CharTest</span><span class="team-info-list__label">f.k.a.</span><span class="team-info-list__value">Old City OldName (2020-2021)</span><span class="team-info-list__label">Record</span><span class="team-info-list__value">12-8</span><span class="team-info-list__label">Arena</span><span class="team-info-list__value">Test Arena</span><span class="team-info-list__label">Capacity</span><span class="team-info-list__value">18000</span><span class="team-info-list__label">Conference</span><span class="team-info-list__value">CharTestConf (1st)</span><span class="team-info-list__label">Division</span><span class="team-info-list__value">CharTestDiv (1st)</span><span class="team-info-list__label">Games Back</span><span class="team-info-list__value">0</span><span class="team-info-list__label">Home</span><span class="team-info-list__value">8-2</span><span class="team-info-list__label">Road</span><span class="team-info-list__value">4-6</span><span class="team-info-list__label">Last 10</span><span class="team-info-list__value">3-1</span></div></div></div>
GOLDEN;

    private const G_CURRENT_NO_FKA = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Current Season</h2></div><div class="team-card__body"><div class="team-info-list"><span class="team-info-list__label">Team</span><span class="team-info-list__value">CharTest</span><span class="team-info-list__label">Record</span><span class="team-info-list__value">12-8</span><span class="team-info-list__label">Arena</span><span class="team-info-list__value">Test Arena</span><span class="team-info-list__label">Capacity</span><span class="team-info-list__value">18000</span><span class="team-info-list__label">Conference</span><span class="team-info-list__value">CharTestConf (1st)</span><span class="team-info-list__label">Division</span><span class="team-info-list__value">CharTestDiv (1st)</span><span class="team-info-list__label">Games Back</span><span class="team-info-list__value">0</span><span class="team-info-list__label">Home</span><span class="team-info-list__value">8-2</span><span class="team-info-list__label">Road</span><span class="team-info-list__value">4-6</span><span class="team-info-list__label">Last 10</span><span class="team-info-list__value">3-1</span></div></div></div>
GOLDEN;

    private const G_CURRENT_NO_POWER = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Current Season</h2></div><div class="team-card__body"></div></div>
GOLDEN;

    private const G_CURRENT_POPULATED = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Current Season</h2></div><div class="team-card__body"><div class="team-info-list"><span class="team-info-list__label">Team</span><span class="team-info-list__value">CharTest</span><span class="team-info-list__label">Record</span><span class="team-info-list__value">12-8</span><span class="team-info-list__label">Arena</span><span class="team-info-list__value">Test Arena</span><span class="team-info-list__label">Capacity</span><span class="team-info-list__value">18000</span><span class="team-info-list__label">Conference</span><span class="team-info-list__value">CharTestConf (1st)</span><span class="team-info-list__label">Division</span><span class="team-info-list__value">CharTestDiv (1st)</span><span class="team-info-list__label">Games Back</span><span class="team-info-list__value">0</span><span class="team-info-list__label">Home</span><span class="team-info-list__value">8-2</span><span class="team-info-list__label">Road</span><span class="team-info-list__value">4-6</span><span class="team-info-list__label">Last 10</span><span class="team-info-list__value">3-1</span></div></div></div>
GOLDEN;

    private const G_DRAFT_EMPTY = <<<'GOLDEN'
<ul class="draft-picks-list"></ul>
GOLDEN;

    private const G_DRAFT_POPULATED = <<<'GOLDEN'
<ul class="draft-picks-list"><li class="draft-picks-list__item"><a href="modules.php?name=Team&amp;op=team&amp;teamid="><img class="draft-picks-list__logo" src="images/logo/Metros.png" height="24" width="24" alt="Metros"></a><div class="draft-picks-list__info"><a href="modules.php?name=Team&amp;op=team&amp;teamid=">2027 R1  Metros</a></div></li></ul>
GOLDEN;

    private const G_FRANCHISE_BEST_BOLD = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"><li><strong><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024">2023-2024 CharTest</a> <span class="record">60-22 (0.732)</span></strong></li><li><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2023">2022-2023 CharTest</a> <span class="record">41-41 (0.500)</span></li></ul><div class="team-card__footer">Totals: 101-63 (0.616)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_BEST_TIEBREAK = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"><li><strong><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024">2023-2024 CharTest</a> <span class="record">50-50 (0.500)</span></strong></li><li><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2023">2022-2023 CharTest</a> <span class="record">41-41 (0.500)</span></li></ul><div class="team-card__footer">Totals: 91-91 (0.500)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_EMPTY_HISTORY = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_HEAT_HISTORY = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"><li><strong><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2025">2024 CharTest</a> <span class="record">6-2 (0.750)</span></strong></li></ul><div class="team-card__footer">Totals: 6-2 (0.750)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_NO_PLAYOFF = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_PLAYOFF_LOSS = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">First Round</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result">2099 &mdash; Rivals 4, CharTest 1</li></ul><div class="team-card__footer">Games: 1-4 (0.200) &middot; Series: 0-1 (0.000)</div><div class="team-card__footer team-card__footer--bold">Post-Season: 1-4 (0.200) &middot; Series: 0-1 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_PLAYOFF_MULTIROUND = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">First Round</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result playoff-result--win">2099 &mdash; CharTest 4, Rivals 1</li></ul><div class="team-card__footer">Games: 4-1 (0.800) &middot; Series: 1-0 (1.000)</div><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">Conference Semis</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result playoff-result--win">2099 &mdash; CharTest 4, Rivals 2</li></ul><div class="team-card__footer">Games: 4-2 (0.667) &middot; Series: 1-0 (1.000)</div><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">Conference Finals</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result playoff-result--win">2099 &mdash; CharTest 4, Rivals 3</li></ul><div class="team-card__footer">Games: 4-3 (0.571) &middot; Series: 1-0 (1.000)</div><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">IBL Finals</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result playoff-result--win">2099 &mdash; CharTest 4, Rivals 0</li></ul><div class="team-card__footer">Games: 4-0 (1.000) &middot; Series: 1-0 (1.000)</div><div class="team-card__footer team-card__footer--bold">Post-Season: 16-6 (0.727) &middot; Series: 4-0 (1.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_PLAYOFF_WIN = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">First Round</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result playoff-result--win">2099 &mdash; CharTest 4, Rivals 2</li></ul><div class="team-card__footer">Games: 4-2 (0.667) &middot; Series: 1-0 (1.000)</div><div class="team-card__footer team-card__footer--bold">Post-Season: 4-2 (0.667) &middot; Series: 1-0 (1.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_REGULAR_HISTORY = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"><li><strong><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024">2023-2024 CharTest</a> <span class="record">55-27 (0.671)</span></strong></li><li><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2023">2022-2023 CharTest</a> <span class="record">40-42 (0.488)</span></li></ul><div class="team-card__footer">Totals: 95-69 (0.579)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_ROUND5_DROPPED = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">First Round</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result playoff-result--win">2099 &mdash; CharTest 4, Rivals 2</li></ul><div class="team-card__footer">Games: 4-2 (0.667) &middot; Series: 1-0 (1.000)</div><div class="team-card__footer team-card__footer--bold">Post-Season: 4-2 (0.667) &middot; Series: 1-0 (1.000)</div></div></div></div>
GOLDEN;

    private const G_RAFTERS_ALL_THREE = <<<'GOLDEN'
<div class="banners-container" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="banners-header"><h2>CharTest Banners</h2></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2021<br>CharTest<br>IBL Champions</strong></div></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner2.gif')"><strong>2022<br>CharTest<br>Eastern Conf. Champions</strong></div></div><div class="banners-row"><div class="banner-item"><strong>2023<br>CharTest<br>Atlantic Div. Champions</strong></div></div></div>
GOLDEN;

    private const G_RAFTERS_AS_CLAUSE = <<<'GOLDEN'
<div class="banners-container" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="banners-header"><h2>CharTest Banners</h2></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2021<br>Old CharTest<br>IBL Champions</strong></div></div></div>
GOLDEN;

    private const G_RAFTERS_CONF_2V3 = <<<'GOLDEN'
<div class="banners-container" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="banners-header"><h2>CharTest Banners</h2></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner2.gif')"><strong>2021<br>CharTest<br>Eastern Conf. Champions</strong></div><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner2.gif')"><strong>2022<br>CharTest<br>Western Conf. Champions</strong></div></div></div>
GOLDEN;

    private const G_RAFTERS_DIV_ARMS = <<<'GOLDEN'
<div class="banners-container" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="banners-header"><h2>CharTest Banners</h2></div><div class="banners-row"><div class="banner-item"><strong>2021<br>CharTest<br>Atlantic Div. Champions</strong></div><div class="banner-item"><strong>2022<br>CharTest<br>Central Div. Champions</strong></div><div class="banner-item"><strong>2023<br>CharTest<br>Midwest Div. Champions</strong></div><div class="banner-item"><strong>2024<br>CharTest<br>Pacific Div. Champions</strong></div></div></div>
GOLDEN;

    private const G_RAFTERS_FIVE_PER_ROW = <<<'GOLDEN'
<div class="banners-container" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="banners-header"><h2>CharTest Banners</h2></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2019<br>CharTest<br>IBL Champions</strong></div><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2020<br>CharTest<br>IBL Champions</strong></div><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2021<br>CharTest<br>IBL Champions</strong></div><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2022<br>CharTest<br>IBL Champions</strong></div><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2023<br>CharTest<br>IBL Champions</strong></div></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2024<br>CharTest<br>IBL Champions</strong></div></div></div>
GOLDEN;

    private const G_RAFTERS_UNKNOWN_DROPPED = <<<'GOLDEN'
<div class="banners-container" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="banners-header"><h2>CharTest Banners</h2></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2021<br>CharTest<br>IBL Champions</strong></div></div></div>
GOLDEN;

    private const G_TABLE_AVG_S = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+encodeURIComponent(v.substring(6))}else{d=encodeURIComponent(d)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s" selected>Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_16">vs. Aces</option><option value="split:vs_22">vs. Braves</option><option value="split:vs_6">vs. Bucks</option><option value="split:vs_27">vs. Bullets</option><option value="split:vs_7">vs. Bulls</option><option value="split:vs_1">vs. Celtics</option><option value="split:vs_19">vs. Clippers</option><option value="split:vs_20">vs. Grizzlies</option><option value="split:vs_9">vs. Hawks</option><option value="split:vs_2">vs. Heat</option><option value="split:vs_13">vs. Jazz</option><option value="split:vs_26">vs. Kings</option><option value="split:vs_3">vs. Knicks</option><option value="split:vs_21">vs. Lakers</option><option value="split:vs_5">vs. Magic</option><option value="split:vs_28">vs. Mavericks</option><option value="split:vs_4">vs. Nets</option><option value="split:vs_15">vs. Nuggets</option><option value="split:vs_11">vs. Pacers</option><option value="split:vs_8">vs. Pelicans</option><option value="split:vs_25">vs. Pistons</option><option value="split:vs_12">vs. Raptors</option><option value="split:vs_17">vs. Rockets</option><option value="split:vs_10">vs. Sting</option><option value="split:vs_23">vs. Suns</option><option value="split:vs_14">vs. Timberwolves</option><option value="split:vs_18">vs. Trailblazers</option><option value="split:vs_24">vs. Warriors</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th>gs</th>
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>PG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Anchor</span></a></td>            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0.0</td>
            <td>0.00</td>
            <td>0.00</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.00</td>
            <td>0.00</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.00</td>
            <td>0.00</td>
            <td class="sep-r-team">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
        </tr>
        <tr>
            <td>SG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000002"><img src="./images/player/200000002.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bobby Baseline</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Baseline</span></a></td>            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0.0</td>
            <td>0.00</td>
            <td>0.00</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.00</td>
            <td>0.00</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.00</td>
            <td>0.00</td>
            <td class="sep-r-team">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
        </tr>
    </tbody>
    <tfoot>
    </tfoot>
</table>
        
GOLDEN;

    private const G_TABLE_BOGUS = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+encodeURIComponent(v.substring(6))}else{d=encodeURIComponent(d)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_16">vs. Aces</option><option value="split:vs_22">vs. Braves</option><option value="split:vs_6">vs. Bucks</option><option value="split:vs_27">vs. Bullets</option><option value="split:vs_7">vs. Bulls</option><option value="split:vs_1">vs. Celtics</option><option value="split:vs_19">vs. Clippers</option><option value="split:vs_20">vs. Grizzlies</option><option value="split:vs_9">vs. Hawks</option><option value="split:vs_2">vs. Heat</option><option value="split:vs_13">vs. Jazz</option><option value="split:vs_26">vs. Kings</option><option value="split:vs_3">vs. Knicks</option><option value="split:vs_21">vs. Lakers</option><option value="split:vs_5">vs. Magic</option><option value="split:vs_28">vs. Mavericks</option><option value="split:vs_4">vs. Nets</option><option value="split:vs_15">vs. Nuggets</option><option value="split:vs_11">vs. Pacers</option><option value="split:vs_8">vs. Pelicans</option><option value="split:vs_25">vs. Pistons</option><option value="split:vs_12">vs. Raptors</option><option value="split:vs_17">vs. Rockets</option><option value="split:vs_10">vs. Sting</option><option value="split:vs_23">vs. Suns</option><option value="split:vs_14">vs. Timberwolves</option><option value="split:vs_18">vs. Trailblazers</option><option value="split:vs_24">vs. Warriors</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
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

    private const G_TABLE_CHUNK = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+encodeURIComponent(v.substring(6))}else{d=encodeURIComponent(d)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk" selected>Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_16">vs. Aces</option><option value="split:vs_22">vs. Braves</option><option value="split:vs_6">vs. Bucks</option><option value="split:vs_27">vs. Bullets</option><option value="split:vs_7">vs. Bulls</option><option value="split:vs_1">vs. Celtics</option><option value="split:vs_19">vs. Clippers</option><option value="split:vs_20">vs. Grizzlies</option><option value="split:vs_9">vs. Hawks</option><option value="split:vs_2">vs. Heat</option><option value="split:vs_13">vs. Jazz</option><option value="split:vs_26">vs. Kings</option><option value="split:vs_3">vs. Knicks</option><option value="split:vs_21">vs. Lakers</option><option value="split:vs_5">vs. Magic</option><option value="split:vs_28">vs. Mavericks</option><option value="split:vs_4">vs. Nets</option><option value="split:vs_15">vs. Nuggets</option><option value="split:vs_11">vs. Pacers</option><option value="split:vs_8">vs. Pelicans</option><option value="split:vs_25">vs. Pistons</option><option value="split:vs_12">vs. Raptors</option><option value="split:vs_17">vs. Rockets</option><option value="split:vs_10">vs. Sting</option><option value="split:vs_23">vs. Suns</option><option value="split:vs_14">vs. Timberwolves</option><option value="split:vs_18">vs. Trailblazers</option><option value="split:vs_24">vs. Warriors</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
    </tfoot>
</table>
        
GOLDEN;

    private const G_TABLE_CONTRACTS = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+encodeURIComponent(v.substring(6))}else{d=encodeURIComponent(d)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts" selected>Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_16">vs. Aces</option><option value="split:vs_22">vs. Braves</option><option value="split:vs_6">vs. Bucks</option><option value="split:vs_27">vs. Bullets</option><option value="split:vs_7">vs. Bulls</option><option value="split:vs_1">vs. Celtics</option><option value="split:vs_19">vs. Clippers</option><option value="split:vs_20">vs. Grizzlies</option><option value="split:vs_9">vs. Hawks</option><option value="split:vs_2">vs. Heat</option><option value="split:vs_13">vs. Jazz</option><option value="split:vs_26">vs. Kings</option><option value="split:vs_3">vs. Knicks</option><option value="split:vs_21">vs. Lakers</option><option value="split:vs_5">vs. Magic</option><option value="split:vs_28">vs. Mavericks</option><option value="split:vs_4">vs. Nets</option><option value="split:vs_15">vs. Nuggets</option><option value="split:vs_11">vs. Pacers</option><option value="split:vs_8">vs. Pelicans</option><option value="split:vs_25">vs. Pistons</option><option value="split:vs_12">vs. Raptors</option><option value="split:vs_17">vs. Rockets</option><option value="split:vs_10">vs. Sting</option><option value="split:vs_23">vs. Suns</option><option value="split:vs_14">vs. Timberwolves</option><option value="split:vs_18">vs. Trailblazers</option><option value="split:vs_24">vs. Warriors</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>Age</th>
            <th>Exp</th>
            <th class="sep-r-team">Bird</th>
            <th class="col-salary">23-24</th>
            <th class="col-salary">24-25</th>
            <th class="col-salary">25-26</th>
            <th class="col-salary">26-27</th>
            <th class="col-salary">27-28</th>
            <th class="col-salary sep-r-team">28-29</th>
            <th>Tal</th>
            <th>Skl</th>
            <th class="sep-r-team">Int</th>
            <th>Loy</th>
            <th>PFW</th>
            <th>PT</th>
            <th>Sec</th>
            <th>Trd</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>PG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Anchor</span></a></td>            <td>27</td>
            <td>5</td>
            <td class="sep-r-team">3</td>
            <td class="col-salary">1500</td>
                        <td class="col-salary">1600</td>
            <td class="col-salary">0</td>
            <td class="col-salary">0</td>
            <td class="col-salary">0</td>
            <td class="col-salary sep-r-team">0</td>
                        <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
        </tr>
        <tr>
            <td>SG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000002"><img src="./images/player/200000002.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bobby Baseline</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Baseline</span></a></td>            <td>27</td>
            <td>5</td>
            <td class="sep-r-team">3</td>
            <td class="col-salary">1500</td>
                        <td class="col-salary">1600</td>
            <td class="col-salary">0</td>
            <td class="col-salary">0</td>
            <td class="col-salary">0</td>
            <td class="col-salary sep-r-team">0</td>
                        <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
        </tr>
    </tbody>
    <tfoot>
        <tr>
            <td></td>
            <td class="sticky-col">Cap Totals</td>
            <td></td>
            <td></td>
            <td class="sep-r-team"></td>
            <td class="col-salary">3000</td>
            <td class="col-salary">3200</td>
            <td class="col-salary">0</td>
            <td class="col-salary">0</td>
            <td class="col-salary">0</td>
            <td class="col-salary sep-r-team">0</td>
            <td></td>
            <td></td>
            <td class="sep-r-team"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr class="tfoot-legend">
            <td colspan="19" class="text-left">
                Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
            </td>
        </tr>
    </tfoot>
</table>
        
GOLDEN;

    private const G_TABLE_HISTORICAL = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-tabs" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=ratings" class="ibl-tab ibl-tab--active" data-display="ratings" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99&amp;yr=2024&amp;display=ratings" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=ratings">Ratings</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=total_s" class="ibl-tab" data-display="total_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99&amp;yr=2024&amp;display=total_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=total_s">Season Totals</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=avg_s" class="ibl-tab" data-display="avg_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99&amp;yr=2024&amp;display=avg_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=avg_s">Season Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=per36mins" class="ibl-tab" data-display="per36mins" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99&amp;yr=2024&amp;display=per36mins" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=per36mins">Per 36 Minutes</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=chunk" class="ibl-tab" data-display="chunk" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99&amp;yr=2024&amp;display=chunk" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=chunk">Sim Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=contracts" class="ibl-tab" data-display="contracts" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99&amp;yr=2024&amp;display=contracts" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=contracts">Contracts</a></div></caption>
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
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full player-expiring">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev player-expiring">A. Anchor</span></a></td>            <td></td>
            <td class="sep-r-team">0</td>
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

    private const G_TABLE_PER36 = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+encodeURIComponent(v.substring(6))}else{d=encodeURIComponent(d)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins" selected>Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_16">vs. Aces</option><option value="split:vs_22">vs. Braves</option><option value="split:vs_6">vs. Bucks</option><option value="split:vs_27">vs. Bullets</option><option value="split:vs_7">vs. Bulls</option><option value="split:vs_1">vs. Celtics</option><option value="split:vs_19">vs. Clippers</option><option value="split:vs_20">vs. Grizzlies</option><option value="split:vs_9">vs. Hawks</option><option value="split:vs_2">vs. Heat</option><option value="split:vs_13">vs. Jazz</option><option value="split:vs_26">vs. Kings</option><option value="split:vs_3">vs. Knicks</option><option value="split:vs_21">vs. Lakers</option><option value="split:vs_5">vs. Magic</option><option value="split:vs_28">vs. Mavericks</option><option value="split:vs_4">vs. Nets</option><option value="split:vs_15">vs. Nuggets</option><option value="split:vs_11">vs. Pacers</option><option value="split:vs_8">vs. Pelicans</option><option value="split:vs_25">vs. Pistons</option><option value="split:vs_12">vs. Raptors</option><option value="split:vs_17">vs. Rockets</option><option value="split:vs_10">vs. Sting</option><option value="split:vs_23">vs. Suns</option><option value="split:vs_14">vs. Timberwolves</option><option value="split:vs_18">vs. Trailblazers</option><option value="split:vs_24">vs. Warriors</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th>gs</th>
            <th>mpg</th>
            <th class="sep-r-team">36min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>PG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Anchor</span></a></td>            <td>0</td>
            <td>0</td>
            <td>0.0</td>
            <td class="sep-r-team">0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td class="sep-r-team">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
        </tr>
        <tr>
            <td>SG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000002"><img src="./images/player/200000002.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bobby Baseline</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Baseline</span></a></td>            <td>0</td>
            <td>0</td>
            <td>0.0</td>
            <td class="sep-r-team">0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td class="sep-r-team">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
        </tr>
    </tbody>
</table>
        
GOLDEN;

    private const G_TABLE_RATINGS = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+encodeURIComponent(v.substring(6))}else{d=encodeURIComponent(d)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings" selected>Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_16">vs. Aces</option><option value="split:vs_22">vs. Braves</option><option value="split:vs_6">vs. Bucks</option><option value="split:vs_27">vs. Bullets</option><option value="split:vs_7">vs. Bulls</option><option value="split:vs_1">vs. Celtics</option><option value="split:vs_19">vs. Clippers</option><option value="split:vs_20">vs. Grizzlies</option><option value="split:vs_9">vs. Hawks</option><option value="split:vs_2">vs. Heat</option><option value="split:vs_13">vs. Jazz</option><option value="split:vs_26">vs. Kings</option><option value="split:vs_3">vs. Knicks</option><option value="split:vs_21">vs. Lakers</option><option value="split:vs_5">vs. Magic</option><option value="split:vs_28">vs. Mavericks</option><option value="split:vs_4">vs. Nets</option><option value="split:vs_15">vs. Nuggets</option><option value="split:vs_11">vs. Pacers</option><option value="split:vs_8">vs. Pelicans</option><option value="split:vs_25">vs. Pistons</option><option value="split:vs_12">vs. Raptors</option><option value="split:vs_17">vs. Rockets</option><option value="split:vs_10">vs. Sting</option><option value="split:vs_23">vs. Suns</option><option value="split:vs_14">vs. Timberwolves</option><option value="split:vs_18">vs. Trailblazers</option><option value="split:vs_24">vs. Warriors</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
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

    private const G_TABLE_SPLIT_HOME = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+encodeURIComponent(v.substring(6))}else{d=encodeURIComponent(d)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home" selected>Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_16">vs. Aces</option><option value="split:vs_22">vs. Braves</option><option value="split:vs_6">vs. Bucks</option><option value="split:vs_27">vs. Bullets</option><option value="split:vs_7">vs. Bulls</option><option value="split:vs_1">vs. Celtics</option><option value="split:vs_19">vs. Clippers</option><option value="split:vs_20">vs. Grizzlies</option><option value="split:vs_9">vs. Hawks</option><option value="split:vs_2">vs. Heat</option><option value="split:vs_13">vs. Jazz</option><option value="split:vs_26">vs. Kings</option><option value="split:vs_3">vs. Knicks</option><option value="split:vs_21">vs. Lakers</option><option value="split:vs_5">vs. Magic</option><option value="split:vs_28">vs. Mavericks</option><option value="split:vs_4">vs. Nets</option><option value="split:vs_15">vs. Nuggets</option><option value="split:vs_11">vs. Pacers</option><option value="split:vs_8">vs. Pelicans</option><option value="split:vs_25">vs. Pistons</option><option value="split:vs_12">vs. Raptors</option><option value="split:vs_17">vs. Rockets</option><option value="split:vs_10">vs. Sting</option><option value="split:vs_23">vs. Suns</option><option value="split:vs_14">vs. Timberwolves</option><option value="split:vs_18">vs. Trailblazers</option><option value="split:vs_24">vs. Warriors</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr><td colspan="21" class="table-empty-message">No games found for <strong>Home</strong> split.</td></tr>
    </tbody>
</table>
        
GOLDEN;

    private const G_TABLE_SPLIT_NULL = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+encodeURIComponent(v.substring(6))}else{d=encodeURIComponent(d)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_16">vs. Aces</option><option value="split:vs_22">vs. Braves</option><option value="split:vs_6">vs. Bucks</option><option value="split:vs_27">vs. Bullets</option><option value="split:vs_7">vs. Bulls</option><option value="split:vs_1">vs. Celtics</option><option value="split:vs_19">vs. Clippers</option><option value="split:vs_20">vs. Grizzlies</option><option value="split:vs_9">vs. Hawks</option><option value="split:vs_2">vs. Heat</option><option value="split:vs_13">vs. Jazz</option><option value="split:vs_26">vs. Kings</option><option value="split:vs_3">vs. Knicks</option><option value="split:vs_21">vs. Lakers</option><option value="split:vs_5">vs. Magic</option><option value="split:vs_28">vs. Mavericks</option><option value="split:vs_4">vs. Nets</option><option value="split:vs_15">vs. Nuggets</option><option value="split:vs_11">vs. Pacers</option><option value="split:vs_8">vs. Pelicans</option><option value="split:vs_25">vs. Pistons</option><option value="split:vs_12">vs. Raptors</option><option value="split:vs_17">vs. Rockets</option><option value="split:vs_10">vs. Sting</option><option value="split:vs_23">vs. Suns</option><option value="split:vs_14">vs. Timberwolves</option><option value="split:vs_18">vs. Trailblazers</option><option value="split:vs_24">vs. Warriors</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr><td colspan="21" class="table-empty-message">No games found for <strong>Home</strong> split.</td></tr>
    </tbody>
</table>
        
GOLDEN;

    private const G_TABLE_SPLIT_WINS = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+encodeURIComponent(v.substring(6))}else{d=encodeURIComponent(d)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins" selected>Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_16">vs. Aces</option><option value="split:vs_22">vs. Braves</option><option value="split:vs_6">vs. Bucks</option><option value="split:vs_27">vs. Bullets</option><option value="split:vs_7">vs. Bulls</option><option value="split:vs_1">vs. Celtics</option><option value="split:vs_19">vs. Clippers</option><option value="split:vs_20">vs. Grizzlies</option><option value="split:vs_9">vs. Hawks</option><option value="split:vs_2">vs. Heat</option><option value="split:vs_13">vs. Jazz</option><option value="split:vs_26">vs. Kings</option><option value="split:vs_3">vs. Knicks</option><option value="split:vs_21">vs. Lakers</option><option value="split:vs_5">vs. Magic</option><option value="split:vs_28">vs. Mavericks</option><option value="split:vs_4">vs. Nets</option><option value="split:vs_15">vs. Nuggets</option><option value="split:vs_11">vs. Pacers</option><option value="split:vs_8">vs. Pelicans</option><option value="split:vs_25">vs. Pistons</option><option value="split:vs_12">vs. Raptors</option><option value="split:vs_17">vs. Rockets</option><option value="split:vs_10">vs. Sting</option><option value="split:vs_23">vs. Suns</option><option value="split:vs_14">vs. Timberwolves</option><option value="split:vs_18">vs. Trailblazers</option><option value="split:vs_24">vs. Warriors</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr><td colspan="21" class="table-empty-message">No games found for <strong>Wins</strong> split.</td></tr>
    </tbody>
</table>
        
GOLDEN;

    private const G_TABLE_TOTAL_S = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+encodeURIComponent(v.substring(6))}else{d=encodeURIComponent(d)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s" selected>Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_16">vs. Aces</option><option value="split:vs_22">vs. Braves</option><option value="split:vs_6">vs. Bucks</option><option value="split:vs_27">vs. Bullets</option><option value="split:vs_7">vs. Bulls</option><option value="split:vs_1">vs. Celtics</option><option value="split:vs_19">vs. Clippers</option><option value="split:vs_20">vs. Grizzlies</option><option value="split:vs_9">vs. Hawks</option><option value="split:vs_2">vs. Heat</option><option value="split:vs_13">vs. Jazz</option><option value="split:vs_26">vs. Kings</option><option value="split:vs_3">vs. Knicks</option><option value="split:vs_21">vs. Lakers</option><option value="split:vs_5">vs. Magic</option><option value="split:vs_28">vs. Mavericks</option><option value="split:vs_4">vs. Nets</option><option value="split:vs_15">vs. Nuggets</option><option value="split:vs_11">vs. Pacers</option><option value="split:vs_8">vs. Pelicans</option><option value="split:vs_25">vs. Pistons</option><option value="split:vs_12">vs. Raptors</option><option value="split:vs_17">vs. Rockets</option><option value="split:vs_10">vs. Sting</option><option value="split:vs_23">vs. Suns</option><option value="split:vs_14">vs. Timberwolves</option><option value="split:vs_18">vs. Trailblazers</option><option value="split:vs_24">vs. Warriors</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th>gs</th>
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>ftm</th>
            <th>fta</th>
            <th>3gm</th>
            <th class="sep-r-team">3ga</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>PG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Anchor</span></a></td>            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
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
            <td>0</td>
            <td>0</td>
        </tr>
        <tr>
            <td>SG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000002"><img src="./images/player/200000002.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bobby Baseline</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Baseline</span></a></td>            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
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
            <td>0</td>
            <td>0</td>
        </tr>
    </tbody>
    <tfoot>
    </tfoot>
</table>
        
GOLDEN;

    private const G_TID0_TABLEOUTPUT = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #DDDDDD; --team-color-secondary: #333333;"><caption class="team-table-caption"><div class="ibl-tabs" style="--team-color-primary: #DDDDDD; --team-color-secondary: #333333;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=ratings" class="ibl-tab ibl-tab--active" data-display="ratings" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=ratings" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=ratings">Ratings</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=total_s" class="ibl-tab" data-display="total_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=total_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=total_s">Season Totals</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=avg_s" class="ibl-tab" data-display="avg_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=avg_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=avg_s">Season Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=per36mins" class="ibl-tab" data-display="per36mins" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=per36mins" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=per36mins">Per 36 Minutes</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=chunk" class="ibl-tab" data-display="chunk" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=chunk" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=chunk">Sim Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=contracts" class="ibl-tab" data-display="contracts" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=contracts" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=contracts">Contracts</a></div></caption>
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
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2708"><img src="./images/player/2708.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Norm Van Lier</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">N. Van Lier</span></a></td>            <td>PG</td>
            <td class="sep-r-team">33</td>
            <td>43</td>
            <td class="sep-r-weak">41</td>
            <td>34</td>
            <td class="sep-r-weak">78</td>
            <td>14</td>
            <td class="sep-r-team">29</td>
            <td>5</td>
            <td>16</td>
            <td>66</td>
            <td>14</td>
            <td>84</td>
            <td>1</td>
            <td class="sep-r-team">60</td>
            <td>6</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-weak">6</td>
            <td>8</td>
            <td>7</td>
            <td>6</td>
            <td class="sep-r-team">9</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2002"><img src="./images/player/2002.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Toni Kukoc</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Kukoc</span></a></td>            <td>SF</td>
            <td class="sep-r-team">34</td>
            <td>57</td>
            <td class="sep-r-weak">46</td>
            <td>29</td>
            <td class="sep-r-weak">81</td>
            <td>26</td>
            <td class="sep-r-team">36</td>
            <td>16</td>
            <td>14</td>
            <td>60</td>
            <td>26</td>
            <td>30</td>
            <td>0</td>
            <td class="sep-r-team">80</td>
            <td>6</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>7</td>
            <td>1</td>
            <td class="sep-r-team">7</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5300"><img src="./images/player/5300.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Serkan Erdogan</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Erdogan</span></a></td>            <td>SG</td>
            <td class="sep-r-team">24</td>
            <td>39</td>
            <td class="sep-r-weak">48</td>
            <td>21</td>
            <td class="sep-r-weak">90</td>
            <td>36</td>
            <td class="sep-r-team">35</td>
            <td>9</td>
            <td>14</td>
            <td>40</td>
            <td>40</td>
            <td>29</td>
            <td>1</td>
            <td class="sep-r-team">73</td>
            <td>9</td>
            <td>6</td>
            <td>1</td>
            <td class="sep-r-weak">5</td>
            <td>5</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3867"><img src="./images/player/3867.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Darren Collison</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Collison</span></a></td>            <td>PG</td>
            <td class="sep-r-team">27</td>
            <td>46</td>
            <td class="sep-r-weak">48</td>
            <td>37</td>
            <td class="sep-r-weak">84</td>
            <td>23</td>
            <td class="sep-r-team">36</td>
            <td>7</td>
            <td>12</td>
            <td>58</td>
            <td>44</td>
            <td>50</td>
            <td>0</td>
            <td class="sep-r-team">66</td>
            <td>8</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-weak">7</td>
            <td>6</td>
            <td>9</td>
            <td>3</td>
            <td class="sep-r-team">7</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2985"><img src="./images/player/2985.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">LaPhonso Ellis</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">L. Ellis</span></a></td>            <td>PF</td>
            <td class="sep-r-team">32</td>
            <td>62</td>
            <td class="sep-r-weak">49</td>
            <td>30</td>
            <td class="sep-r-weak">72</td>
            <td>19</td>
            <td class="sep-r-team">33</td>
            <td>29</td>
            <td>34</td>
            <td>4</td>
            <td>16</td>
            <td>79</td>
            <td>4</td>
            <td class="sep-r-team">45</td>
            <td>9</td>
            <td>3</td>
            <td>8</td>
            <td class="sep-r-weak">7</td>
            <td>6</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5673"><img src="./images/player/5673.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jarrett Jack</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Jack</span></a></td>            <td>PG</td>
            <td class="sep-r-team">24</td>
            <td>30</td>
            <td class="sep-r-weak">48</td>
            <td>35</td>
            <td class="sep-r-weak">83</td>
            <td>16</td>
            <td class="sep-r-team">36</td>
            <td>7</td>
            <td>25</td>
            <td>44</td>
            <td>23</td>
            <td>71</td>
            <td>1</td>
            <td class="sep-r-team">67</td>
            <td>7</td>
            <td>9</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>5</td>
            <td>5</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3575"><img src="./images/player/3575.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Shannon Johnson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Johnson</span></a></td>            <td>PG</td>
            <td class="sep-r-team">31</td>
            <td>40</td>
            <td class="sep-r-weak">47</td>
            <td>34</td>
            <td class="sep-r-weak">70</td>
            <td>21</td>
            <td class="sep-r-team">38</td>
            <td>8</td>
            <td>12</td>
            <td>49</td>
            <td>47</td>
            <td>54</td>
            <td>1</td>
            <td class="sep-r-team">50</td>
            <td>7</td>
            <td>8</td>
            <td>3</td>
            <td class="sep-r-weak">5</td>
            <td>6</td>
            <td>6</td>
            <td>5</td>
            <td class="sep-r-team">9</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4855"><img src="./images/player/4855.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">R.J. Hampton</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Hampton</span></a></td>            <td>SG</td>
            <td class="sep-r-team">23</td>
            <td>43</td>
            <td class="sep-r-weak">46</td>
            <td>31</td>
            <td class="sep-r-weak">63</td>
            <td>17</td>
            <td class="sep-r-team">31</td>
            <td>12</td>
            <td>51</td>
            <td>19</td>
            <td>15</td>
            <td>75</td>
            <td>2</td>
            <td class="sep-r-team">80</td>
            <td>7</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">4</td>
            <td>8</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2422"><img src="./images/player/2422.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Brian Cardinal</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Cardinal</span></a></td>            <td>SF</td>
            <td class="sep-r-team">34</td>
            <td>68</td>
            <td class="sep-r-weak">48</td>
            <td>32</td>
            <td class="sep-r-weak">83</td>
            <td>34</td>
            <td class="sep-r-team">35</td>
            <td>10</td>
            <td>16</td>
            <td>4</td>
            <td>28</td>
            <td>71</td>
            <td>0</td>
            <td class="sep-r-team">58</td>
            <td>8</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-weak">6</td>
            <td>5</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-team">3</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5694"><img src="./images/player/5694.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Dijon Thompson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Thompson</span></a></td>            <td>PF</td>
            <td class="sep-r-team">24</td>
            <td>36</td>
            <td class="sep-r-weak">46</td>
            <td>16</td>
            <td class="sep-r-weak">84</td>
            <td>15</td>
            <td class="sep-r-team">29</td>
            <td>36</td>
            <td>31</td>
            <td>6</td>
            <td>35</td>
            <td>81</td>
            <td>3</td>
            <td class="sep-r-team">50</td>
            <td>6</td>
            <td>3</td>
            <td>6</td>
            <td class="sep-r-weak">2</td>
            <td>6</td>
            <td>4</td>
            <td>5</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2007"><img src="./images/player/2007.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Vern Mikkelsen</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">V. Mikkelsen</span></a></td>            <td>C</td>
            <td class="sep-r-team">34</td>
            <td>52</td>
            <td class="sep-r-weak">49</td>
            <td>40</td>
            <td class="sep-r-weak">72</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>21</td>
            <td>54</td>
            <td>6</td>
            <td>8</td>
            <td>60</td>
            <td>31</td>
            <td class="sep-r-team">90</td>
            <td>3</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-weak">4</td>
            <td>8</td>
            <td>2</td>
            <td>6</td>
            <td class="sep-r-team">4</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1747"><img src="./images/player/1747.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Hank Gathers</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">H. Gathers</span></a></td>            <td>PF</td>
            <td class="sep-r-team">35</td>
            <td>56</td>
            <td class="sep-r-weak">46</td>
            <td>56</td>
            <td class="sep-r-weak">61</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>28</td>
            <td>51</td>
            <td>3</td>
            <td>28</td>
            <td>64</td>
            <td>2</td>
            <td class="sep-r-team">77</td>
            <td>2</td>
            <td>6</td>
            <td>8</td>
            <td class="sep-r-weak">5</td>
            <td>6</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4168"><img src="./images/player/4168.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">George Lynch</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">G. Lynch</span></a></td>            <td>SF</td>
            <td class="sep-r-team">29</td>
            <td>38</td>
            <td class="sep-r-weak">49</td>
            <td>20</td>
            <td class="sep-r-weak">54</td>
            <td>9</td>
            <td class="sep-r-team">34</td>
            <td>47</td>
            <td>27</td>
            <td>7</td>
            <td>50</td>
            <td>82</td>
            <td>1</td>
            <td class="sep-r-team">56</td>
            <td>6</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">4</td>
            <td>9</td>
            <td>6</td>
            <td>7</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5297"><img src="./images/player/5297.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Mickael Pietrus</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Pietrus</span></a></td>            <td>SG</td>
            <td class="sep-r-team">24</td>
            <td>35</td>
            <td class="sep-r-weak">44</td>
            <td>40</td>
            <td class="sep-r-weak">64</td>
            <td>31</td>
            <td class="sep-r-team">30</td>
            <td>15</td>
            <td>29</td>
            <td>11</td>
            <td>34</td>
            <td>81</td>
            <td>6</td>
            <td class="sep-r-team">54</td>
            <td>7</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>7</td>
            <td>7</td>
            <td>3</td>
            <td class="sep-r-team">6</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1744"><img src="./images/player/1744.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Pau Gasol</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Gasol</span></a></td>            <td>PF</td>
            <td class="sep-r-team">35</td>
            <td>56</td>
            <td class="sep-r-weak">45</td>
            <td>40</td>
            <td class="sep-r-weak">79</td>
            <td>16</td>
            <td class="sep-r-team">33</td>
            <td>23</td>
            <td>42</td>
            <td>14</td>
            <td>9</td>
            <td>57</td>
            <td>5</td>
            <td class="sep-r-team">85</td>
            <td>7</td>
            <td>9</td>
            <td>8</td>
            <td class="sep-r-weak">5</td>
            <td>7</td>
            <td>3</td>
            <td>4</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3567"><img src="./images/player/3567.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Kerry Kittles</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Kittles</span></a></td>            <td>SG</td>
            <td class="sep-r-team">30</td>
            <td>49</td>
            <td class="sep-r-weak">48</td>
            <td>33</td>
            <td class="sep-r-weak">73</td>
            <td>17</td>
            <td class="sep-r-team">32</td>
            <td>20</td>
            <td>17</td>
            <td>11</td>
            <td>25</td>
            <td>85</td>
            <td>1</td>
            <td class="sep-r-team">85</td>
            <td>8</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-weak">7</td>
            <td>5</td>
            <td>9</td>
            <td>4</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5682"><img src="./images/player/5682.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Ryan Gomes</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Gomes</span></a></td>            <td>SF</td>
            <td class="sep-r-team">25</td>
            <td>42</td>
            <td class="sep-r-weak">47</td>
            <td>27</td>
            <td class="sep-r-weak">84</td>
            <td>15</td>
            <td class="sep-r-team">32</td>
            <td>32</td>
            <td>37</td>
            <td>11</td>
            <td>39</td>
            <td>61</td>
            <td>1</td>
            <td class="sep-r-team">56</td>
            <td>7</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-weak">4</td>
            <td>6</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5672"><img src="./images/player/5672.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Linas Kleiza</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">L. Kleiza</span></a></td>            <td>SF</td>
            <td class="sep-r-team">23</td>
            <td>37</td>
            <td class="sep-r-weak">47</td>
            <td>36</td>
            <td class="sep-r-weak">76</td>
            <td>22</td>
            <td class="sep-r-team">34</td>
            <td>15</td>
            <td>30</td>
            <td>13</td>
            <td>18</td>
            <td>83</td>
            <td>2</td>
            <td class="sep-r-team">56</td>
            <td>7</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-weak">2</td>
            <td>6</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5302"><img src="./images/player/5302.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jason Kapono</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Kapono</span></a></td>            <td>SF</td>
            <td class="sep-r-team">25</td>
            <td>37</td>
            <td class="sep-r-weak">43</td>
            <td>11</td>
            <td class="sep-r-weak">79</td>
            <td>30</td>
            <td class="sep-r-team">34</td>
            <td>10</td>
            <td>19</td>
            <td>36</td>
            <td>19</td>
            <td>85</td>
            <td>2</td>
            <td class="sep-r-team">95</td>
            <td>9</td>
            <td>4</td>
            <td>1</td>
            <td class="sep-r-weak">5</td>
            <td>3</td>
            <td>3</td>
            <td>1</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5310"><img src="./images/player/5310.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Slavko Vraneš</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Vraneš</span></a></td>            <td>C</td>
            <td class="sep-r-team">24</td>
            <td>44</td>
            <td class="sep-r-weak">45</td>
            <td>25</td>
            <td class="sep-r-weak">67</td>
            <td>13</td>
            <td class="sep-r-team">28</td>
            <td>19</td>
            <td>34</td>
            <td>30</td>
            <td>9</td>
            <td>69</td>
            <td>1</td>
            <td class="sep-r-team">71</td>
            <td>5</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-weak">5</td>
            <td>5</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3882"><img src="./images/player/3882.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Chase Budinger</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Budinger</span></a></td>            <td>SF</td>
            <td class="sep-r-team">28</td>
            <td>63</td>
            <td class="sep-r-weak">48</td>
            <td>9</td>
            <td class="sep-r-weak">59</td>
            <td>33</td>
            <td class="sep-r-team">33</td>
            <td>7</td>
            <td>14</td>
            <td>5</td>
            <td>11</td>
            <td>88</td>
            <td>1</td>
            <td class="sep-r-team">54</td>
            <td>7</td>
            <td>6</td>
            <td>5</td>
            <td class="sep-r-weak">3</td>
            <td>4</td>
            <td>7</td>
            <td>3</td>
            <td class="sep-r-team">6</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4524"><img src="./images/player/4524.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Oshae Brissett</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">O. Brissett</span></a></td>            <td>PF</td>
            <td class="sep-r-team">26</td>
            <td>29</td>
            <td class="sep-r-weak">47</td>
            <td>37</td>
            <td class="sep-r-weak">75</td>
            <td>11</td>
            <td class="sep-r-team">29</td>
            <td>12</td>
            <td>33</td>
            <td>6</td>
            <td>22</td>
            <td>92</td>
            <td>5</td>
            <td class="sep-r-team">77</td>
            <td>7</td>
            <td>6</td>
            <td>6</td>
            <td class="sep-r-weak">9</td>
            <td>6</td>
            <td>7</td>
            <td>5</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5689"><img src="./images/player/5689.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Hakim Warrick</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">H. Warrick</span></a></td>            <td>PF</td>
            <td class="sep-r-team">25</td>
            <td>31</td>
            <td class="sep-r-weak">49</td>
            <td>38</td>
            <td class="sep-r-weak">73</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>19</td>
            <td>24</td>
            <td>20</td>
            <td>27</td>
            <td>84</td>
            <td>4</td>
            <td class="sep-r-team">89</td>
            <td>1</td>
            <td>8</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>7</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-team">6</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5684"><img src="./images/player/5684.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Anna DeForge</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. DeForge</span></a></td>            <td>PG</td>
            <td class="sep-r-team">24</td>
            <td>39</td>
            <td class="sep-r-weak">42</td>
            <td>29</td>
            <td class="sep-r-weak">79</td>
            <td>26</td>
            <td class="sep-r-team">33</td>
            <td>8</td>
            <td>16</td>
            <td>16</td>
            <td>31</td>
            <td>90</td>
            <td>1</td>
            <td class="sep-r-team">56</td>
            <td>9</td>
            <td>8</td>
            <td>1</td>
            <td class="sep-r-weak">8</td>
            <td>7</td>
            <td>6</td>
            <td>1</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3281"><img src="./images/player/3281.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Pervis Ellison</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Ellison</span></a></td>            <td>C</td>
            <td class="sep-r-team">31</td>
            <td>51</td>
            <td class="sep-r-weak">47</td>
            <td>25</td>
            <td class="sep-r-weak">65</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>20</td>
            <td>40</td>
            <td>3</td>
            <td>10</td>
            <td>82</td>
            <td>18</td>
            <td class="sep-r-team">65</td>
            <td>4</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">5</td>
            <td>7</td>
            <td>3</td>
            <td>8</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5296"><img src="./images/player/5296.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Luke Ridnour</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">L. Ridnour</span></a></td>            <td>PG</td>
            <td class="sep-r-team">25</td>
            <td>32</td>
            <td class="sep-r-weak">40</td>
            <td>34</td>
            <td class="sep-r-weak">85</td>
            <td>8</td>
            <td class="sep-r-team">28</td>
            <td>6</td>
            <td>16</td>
            <td>44</td>
            <td>28</td>
            <td>67</td>
            <td>1</td>
            <td class="sep-r-team">63</td>
            <td>7</td>
            <td>7</td>
            <td>2</td>
            <td class="sep-r-weak">8</td>
            <td>7</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">9</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2699"><img src="./images/player/2699.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Josh Smith</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Smith</span></a></td>            <td>PF</td>
            <td class="sep-r-team">30</td>
            <td>52</td>
            <td class="sep-r-weak">41</td>
            <td>30</td>
            <td class="sep-r-weak">59</td>
            <td>16</td>
            <td class="sep-r-team">24</td>
            <td>12</td>
            <td>26</td>
            <td>7</td>
            <td>55</td>
            <td>65</td>
            <td>35</td>
            <td class="sep-r-team">18</td>
            <td>1</td>
            <td>7</td>
            <td>8</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>7</td>
            <td>6</td>
            <td class="sep-r-team">8</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5677"><img src="./images/player/5677.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Ike Diogu</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">I. Diogu</span></a></td>            <td>PF</td>
            <td class="sep-r-team">24</td>
            <td>31</td>
            <td class="sep-r-weak">50</td>
            <td>40</td>
            <td class="sep-r-weak">82</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>24</td>
            <td>36</td>
            <td>5</td>
            <td>14</td>
            <td>58</td>
            <td>20</td>
            <td class="sep-r-team">72</td>
            <td>5</td>
            <td>4</td>
            <td>8</td>
            <td class="sep-r-weak">4</td>
            <td>5</td>
            <td>2</td>
            <td>7</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2018"><img src="./images/player/2018.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Teresa Weatherspoon</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Weatherspoon</span></a></td>            <td>PG</td>
            <td class="sep-r-team">34</td>
            <td>44</td>
            <td class="sep-r-weak">43</td>
            <td>24</td>
            <td class="sep-r-weak">61</td>
            <td>13</td>
            <td class="sep-r-team">31</td>
            <td>3</td>
            <td>9</td>
            <td>55</td>
            <td>54</td>
            <td>68</td>
            <td>0</td>
            <td class="sep-r-team">59</td>
            <td>6</td>
            <td>9</td>
            <td>3</td>
            <td class="sep-r-weak">4</td>
            <td>4</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5293"><img src="./images/player/5293.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Travis Outlaw</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Outlaw</span></a></td>            <td>PF</td>
            <td class="sep-r-team">22</td>
            <td>45</td>
            <td class="sep-r-weak">47</td>
            <td>28</td>
            <td class="sep-r-weak">69</td>
            <td>14</td>
            <td class="sep-r-team">30</td>
            <td>12</td>
            <td>14</td>
            <td>6</td>
            <td>23</td>
            <td>64</td>
            <td>9</td>
            <td class="sep-r-team">26</td>
            <td>8</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-weak">5</td>
            <td>6</td>
            <td>6</td>
            <td>5</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5680"><img src="./images/player/5680.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Martell Webster</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Webster</span></a></td>            <td>SF</td>
            <td class="sep-r-team">21</td>
            <td>40</td>
            <td class="sep-r-weak">42</td>
            <td>21</td>
            <td class="sep-r-weak">80</td>
            <td>20</td>
            <td class="sep-r-team">33</td>
            <td>13</td>
            <td>30</td>
            <td>12</td>
            <td>33</td>
            <td>79</td>
            <td>14</td>
            <td class="sep-r-team">65</td>
            <td>9</td>
            <td>3</td>
            <td>5</td>
            <td class="sep-r-weak">4</td>
            <td>6</td>
            <td>3</td>
            <td>3</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5681"><img src="./images/player/5681.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Ian Mahinmi</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">I. Mahinmi</span></a></td>            <td>C</td>
            <td class="sep-r-team">21</td>
            <td>29</td>
            <td class="sep-r-weak">49</td>
            <td>42</td>
            <td class="sep-r-weak">60</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>30</td>
            <td>50</td>
            <td>14</td>
            <td>25</td>
            <td>68</td>
            <td>18</td>
            <td class="sep-r-team">52</td>
            <td>2</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-weak">3</td>
            <td>4</td>
            <td>5</td>
            <td>9</td>
            <td class="sep-r-team">1</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2988"><img src="./images/player/2988.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jim Jackson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Jackson</span></a></td>            <td>SG</td>
            <td class="sep-r-team">32</td>
            <td>62</td>
            <td class="sep-r-weak">44</td>
            <td>23</td>
            <td class="sep-r-weak">73</td>
            <td>19</td>
            <td class="sep-r-team">32</td>
            <td>17</td>
            <td>25</td>
            <td>27</td>
            <td>20</td>
            <td>53</td>
            <td>1</td>
            <td class="sep-r-team">72</td>
            <td>7</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-weak">5</td>
            <td>5</td>
            <td>4</td>
            <td>5</td>
            <td class="sep-r-team">6</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3579"><img src="./images/player/3579.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Boban Marjanovic</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Marjanovic</span></a></td>            <td>C</td>
            <td class="sep-r-team">32</td>
            <td>45</td>
            <td class="sep-r-weak">50</td>
            <td>32</td>
            <td class="sep-r-weak">75</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>31</td>
            <td>51</td>
            <td>6</td>
            <td>10</td>
            <td>30</td>
            <td>13</td>
            <td class="sep-r-team">88</td>
            <td>1</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">3</td>
            <td>1</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5685"><img src="./images/player/5685.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Gerald Green</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">G. Green</span></a></td>            <td>SF</td>
            <td class="sep-r-team">22</td>
            <td>41</td>
            <td class="sep-r-weak">45</td>
            <td>24</td>
            <td class="sep-r-weak">84</td>
            <td>24</td>
            <td class="sep-r-team">36</td>
            <td>6</td>
            <td>14</td>
            <td>10</td>
            <td>40</td>
            <td>53</td>
            <td>7</td>
            <td class="sep-r-team">32</td>
            <td>7</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-weak">4</td>
            <td>4</td>
            <td>3</td>
            <td>1</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5691"><img src="./images/player/5691.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Uroš Slokar</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">U. Slokar</span></a></td>            <td>C</td>
            <td class="sep-r-team">24</td>
            <td>27</td>
            <td class="sep-r-weak">52</td>
            <td>23</td>
            <td class="sep-r-weak">66</td>
            <td>12</td>
            <td class="sep-r-team">25</td>
            <td>33</td>
            <td>43</td>
            <td>13</td>
            <td>17</td>
            <td>87</td>
            <td>4</td>
            <td class="sep-r-team">74</td>
            <td>3</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-weak">2</td>
            <td>2</td>
            <td>2</td>
            <td>6</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2706"><img src="./images/player/2706.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Trevor Ariza</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Ariza</span></a></td>            <td>SF</td>
            <td class="sep-r-team">30</td>
            <td>51</td>
            <td class="sep-r-weak">40</td>
            <td>18</td>
            <td class="sep-r-weak">61</td>
            <td>33</td>
            <td class="sep-r-team">10</td>
            <td>18</td>
            <td>23</td>
            <td>12</td>
            <td>39</td>
            <td>77</td>
            <td>1</td>
            <td class="sep-r-team">84</td>
            <td>5</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-weak">2</td>
            <td>7</td>
            <td>8</td>
            <td>6</td>
            <td class="sep-r-team">7</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5298"><img src="./images/player/5298.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Sani Becirovic</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Becirovic</span></a></td>            <td>SG</td>
            <td class="sep-r-team">25</td>
            <td>34</td>
            <td class="sep-r-weak">43</td>
            <td>21</td>
            <td class="sep-r-weak">81</td>
            <td>8</td>
            <td class="sep-r-team">30</td>
            <td>6</td>
            <td>10</td>
            <td>22</td>
            <td>42</td>
            <td>83</td>
            <td>5</td>
            <td class="sep-r-team">63</td>
            <td>7</td>
            <td>7</td>
            <td>1</td>
            <td class="sep-r-weak">5</td>
            <td>7</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4535"><img src="./images/player/4535.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Josh Richardson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Richardson</span></a></td>            <td>SG</td>
            <td class="sep-r-team">27</td>
            <td>38</td>
            <td class="sep-r-weak">46</td>
            <td>16</td>
            <td class="sep-r-weak">80</td>
            <td>32</td>
            <td class="sep-r-team">35</td>
            <td>4</td>
            <td>15</td>
            <td>15</td>
            <td>28</td>
            <td>82</td>
            <td>2</td>
            <td class="sep-r-team">52</td>
            <td>8</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-weak">6</td>
            <td>9</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-team">8</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3874"><img src="./images/player/3874.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Doug Collins</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Collins</span></a></td>            <td>SG</td>
            <td class="sep-r-team">29</td>
            <td>41</td>
            <td class="sep-r-weak">43</td>
            <td>48</td>
            <td class="sep-r-weak">82</td>
            <td>2</td>
            <td class="sep-r-team">25</td>
            <td>10</td>
            <td>8</td>
            <td>8</td>
            <td>25</td>
            <td>85</td>
            <td>1</td>
            <td class="sep-r-team">45</td>
            <td>6</td>
            <td>6</td>
            <td>6</td>
            <td class="sep-r-weak">7</td>
            <td>5</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">9</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=6375"><img src="./images/player/6375.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full player-waived">Chuma Okeke</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev player-waived">C. Okeke</span></a></td>            <td>SF</td>
            <td class="sep-r-team">21</td>
            <td>29</td>
            <td class="sep-r-weak">33</td>
            <td>11</td>
            <td class="sep-r-weak">83</td>
            <td>18</td>
            <td class="sep-r-team">40</td>
            <td>7</td>
            <td>41</td>
            <td>14</td>
            <td>40</td>
            <td>87</td>
            <td>8</td>
            <td class="sep-r-team">78</td>
            <td>3</td>
            <td>4</td>
            <td>1</td>
            <td class="sep-r-weak">4</td>
            <td>2</td>
            <td>2</td>
            <td>1</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=6379"><img src="./images/player/6379.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full player-waived">Admiral Schofiel</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev player-waived">A. Schofiel</span></a></td>            <td>SF</td>
            <td class="sep-r-team">22</td>
            <td>28</td>
            <td class="sep-r-weak">38</td>
            <td>23</td>
            <td class="sep-r-weak">77</td>
            <td>12</td>
            <td class="sep-r-team">29</td>
            <td>10</td>
            <td>17</td>
            <td>14</td>
            <td>9</td>
            <td>84</td>
            <td>4</td>
            <td class="sep-r-team">65</td>
            <td>4</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-weak">4</td>
            <td>4</td>
            <td>3</td>
            <td>4</td>
            <td class="sep-r-team">4</td>
            <td>1</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1232"><img src="./images/player/1232.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Rick Barry</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Barry</span></a></td>            <td>SF</td>
            <td class="sep-r-team">36</td>
            <td>38</td>
            <td class="sep-r-weak">39</td>
            <td>36</td>
            <td class="sep-r-weak">91</td>
            <td>8</td>
            <td class="sep-r-team">31</td>
            <td>10</td>
            <td>11</td>
            <td>36</td>
            <td>13</td>
            <td>61</td>
            <td>0</td>
            <td class="sep-r-team">61</td>
            <td>3</td>
            <td>6</td>
            <td>7</td>
            <td class="sep-r-weak">8</td>
            <td>9</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4873"><img src="./images/player/4873.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Xavier Tillman</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">X. Tillman</span></a></td>            <td>C</td>
            <td class="sep-r-team">26</td>
            <td>23</td>
            <td class="sep-r-weak">51</td>
            <td>20</td>
            <td class="sep-r-weak">56</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>27</td>
            <td>35</td>
            <td>14</td>
            <td>48</td>
            <td>86</td>
            <td>4</td>
            <td class="sep-r-team">71</td>
            <td>4</td>
            <td>4</td>
            <td>2</td>
            <td class="sep-r-weak">3</td>
            <td>4</td>
            <td>5</td>
            <td>8</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4862"><img src="./images/player/4862.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Patrick Williams</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Williams</span></a></td>            <td>SF</td>
            <td class="sep-r-team">23</td>
            <td>32</td>
            <td class="sep-r-weak">44</td>
            <td>13</td>
            <td class="sep-r-weak">82</td>
            <td>10</td>
            <td class="sep-r-team">31</td>
            <td>8</td>
            <td>16</td>
            <td>7</td>
            <td>24</td>
            <td>80</td>
            <td>12</td>
            <td class="sep-r-team">69</td>
            <td>9</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-weak">7</td>
            <td>6</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1748"><img src="./images/player/1748.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Clarence Kea</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Kea</span></a></td>            <td>PF</td>
            <td class="sep-r-team">36</td>
            <td>57</td>
            <td class="sep-r-weak">44</td>
            <td>36</td>
            <td class="sep-r-weak">74</td>
            <td>18</td>
            <td class="sep-r-team">32</td>
            <td>13</td>
            <td>34</td>
            <td>1</td>
            <td>12</td>
            <td>64</td>
            <td>0</td>
            <td class="sep-r-team">50</td>
            <td>2</td>
            <td>6</td>
            <td>9</td>
            <td class="sep-r-weak">4</td>
            <td>9</td>
            <td>2</td>
            <td>6</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4529"><img src="./images/player/4529.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jahlil Okafor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Okafor</span></a></td>            <td>C</td>
            <td class="sep-r-team">25</td>
            <td>58</td>
            <td class="sep-r-weak">46</td>
            <td>32</td>
            <td class="sep-r-weak">65</td>
            <td>0</td>
            <td class="sep-r-team">33</td>
            <td>25</td>
            <td>48</td>
            <td>5</td>
            <td>9</td>
            <td>68</td>
            <td>17</td>
            <td class="sep-r-team">49</td>
            <td>5</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-weak">2</td>
            <td>4</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-team">3</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5308"><img src="./images/player/5308.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Marcus Banks</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Banks</span></a></td>            <td>PG</td>
            <td class="sep-r-team">25</td>
            <td>35</td>
            <td class="sep-r-weak">42</td>
            <td>27</td>
            <td class="sep-r-weak">71</td>
            <td>17</td>
            <td class="sep-r-team">30</td>
            <td>3</td>
            <td>14</td>
            <td>40</td>
            <td>24</td>
            <td>69</td>
            <td>1</td>
            <td class="sep-r-team">74</td>
            <td>4</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>5</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3565"><img src="./images/player/3565.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bobby Jones</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Jones</span></a></td>            <td>PF</td>
            <td class="sep-r-team">31</td>
            <td>27</td>
            <td class="sep-r-weak">46</td>
            <td>35</td>
            <td class="sep-r-weak">71</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>19</td>
            <td>25</td>
            <td>21</td>
            <td>35</td>
            <td>70</td>
            <td>4</td>
            <td class="sep-r-team">34</td>
            <td>5</td>
            <td>5</td>
            <td>7</td>
            <td class="sep-r-weak">3</td>
            <td>6</td>
            <td>8</td>
            <td>8</td>
            <td class="sep-r-team">9</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5693"><img src="./images/player/5693.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Johan Petro</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Petro</span></a></td>            <td>C</td>
            <td class="sep-r-team">22</td>
            <td>24</td>
            <td class="sep-r-weak">43</td>
            <td>29</td>
            <td class="sep-r-weak">67</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>28</td>
            <td>52</td>
            <td>16</td>
            <td>20</td>
            <td>74</td>
            <td>16</td>
            <td class="sep-r-team">59</td>
            <td>4</td>
            <td>2</td>
            <td>5</td>
            <td class="sep-r-weak">1</td>
            <td>4</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-team">2</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2439"><img src="./images/player/2439.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Josip Sesar</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Sesar</span></a></td>            <td>PG</td>
            <td class="sep-r-team">33</td>
            <td>57</td>
            <td class="sep-r-weak">45</td>
            <td>23</td>
            <td class="sep-r-weak">65</td>
            <td>9</td>
            <td class="sep-r-team">28</td>
            <td>10</td>
            <td>12</td>
            <td>43</td>
            <td>16</td>
            <td>49</td>
            <td>0</td>
            <td class="sep-r-team">83</td>
            <td>8</td>
            <td>7</td>
            <td>5</td>
            <td class="sep-r-weak">7</td>
            <td>3</td>
            <td>3</td>
            <td>3</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5669"><img src="./images/player/5669.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">C.J. Miles</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Miles</span></a></td>            <td>SG</td>
            <td class="sep-r-team">20</td>
            <td>29</td>
            <td class="sep-r-weak">44</td>
            <td>30</td>
            <td class="sep-r-weak">79</td>
            <td>24</td>
            <td class="sep-r-team">28</td>
            <td>13</td>
            <td>22</td>
            <td>14</td>
            <td>47</td>
            <td>60</td>
            <td>9</td>
            <td class="sep-r-team">12</td>
            <td>6</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">4</td>
            <td>6</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2986"><img src="./images/player/2986.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Latrell Sprewell</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">L. Sprewell</span></a></td>            <td>SG</td>
            <td class="sep-r-team">32</td>
            <td>49</td>
            <td class="sep-r-weak">44</td>
            <td>31</td>
            <td class="sep-r-weak">65</td>
            <td>10</td>
            <td class="sep-r-team">29</td>
            <td>9</td>
            <td>10</td>
            <td>17</td>
            <td>53</td>
            <td>72</td>
            <td>3</td>
            <td class="sep-r-team">63</td>
            <td>6</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-weak">8</td>
            <td>9</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">8</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2435"><img src="./images/player/2435.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Darius Miles</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Miles</span></a></td>            <td>SF</td>
            <td class="sep-r-team">31</td>
            <td>51</td>
            <td class="sep-r-weak">44</td>
            <td>18</td>
            <td class="sep-r-weak">53</td>
            <td>16</td>
            <td class="sep-r-team">31</td>
            <td>14</td>
            <td>17</td>
            <td>16</td>
            <td>12</td>
            <td>65</td>
            <td>5</td>
            <td class="sep-r-team">72</td>
            <td>5</td>
            <td>3</td>
            <td>6</td>
            <td class="sep-r-weak">7</td>
            <td>7</td>
            <td>7</td>
            <td>6</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3312"><img src="./images/player/3312.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">George McCloud</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">G. McCloud</span></a></td>            <td>SF</td>
            <td class="sep-r-team">31</td>
            <td>39</td>
            <td class="sep-r-weak">44</td>
            <td>13</td>
            <td class="sep-r-weak">75</td>
            <td>31</td>
            <td class="sep-r-team">34</td>
            <td>3</td>
            <td>8</td>
            <td>3</td>
            <td>16</td>
            <td>89</td>
            <td>0</td>
            <td class="sep-r-team">73</td>
            <td>7</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-weak">2</td>
            <td>8</td>
            <td>5</td>
            <td>1</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4846"><img src="./images/player/4846.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Payton Pritchard</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Pritchard</span></a></td>            <td>PG</td>
            <td class="sep-r-team">26</td>
            <td>26</td>
            <td class="sep-r-weak">44</td>
            <td>8</td>
            <td class="sep-r-weak">77</td>
            <td>14</td>
            <td class="sep-r-team">27</td>
            <td>9</td>
            <td>22</td>
            <td>35</td>
            <td>19</td>
            <td>87</td>
            <td>0</td>
            <td class="sep-r-team">76</td>
            <td>8</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-weak">5</td>
            <td>5</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1754"><img src="./images/player/1754.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Joe Johnson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Johnson</span></a></td>            <td>SG</td>
            <td class="sep-r-team">34</td>
            <td>56</td>
            <td class="sep-r-weak">39</td>
            <td>10</td>
            <td class="sep-r-weak">65</td>
            <td>18</td>
            <td class="sep-r-team">33</td>
            <td>9</td>
            <td>10</td>
            <td>53</td>
            <td>12</td>
            <td>75</td>
            <td>1</td>
            <td class="sep-r-team">53</td>
            <td>8</td>
            <td>6</td>
            <td>5</td>
            <td class="sep-r-weak">7</td>
            <td>4</td>
            <td>4</td>
            <td>3</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5686"><img src="./images/player/5686.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Mile Ilic</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Ilic</span></a></td>            <td>C</td>
            <td class="sep-r-team">23</td>
            <td>28</td>
            <td class="sep-r-weak">50</td>
            <td>26</td>
            <td class="sep-r-weak">67</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>32</td>
            <td>39</td>
            <td>6</td>
            <td>13</td>
            <td>58</td>
            <td>14</td>
            <td class="sep-r-team">79</td>
            <td>4</td>
            <td>4</td>
            <td>5</td>
            <td class="sep-r-weak">3</td>
            <td>5</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5283"><img src="./images/player/5283.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Kendrick Perkins</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Perkins</span></a></td>            <td>C</td>
            <td class="sep-r-team">22</td>
            <td>21</td>
            <td class="sep-r-weak">46</td>
            <td>29</td>
            <td class="sep-r-weak">52</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>30</td>
            <td>37</td>
            <td>5</td>
            <td>12</td>
            <td>72</td>
            <td>37</td>
            <td class="sep-r-team">82</td>
            <td>4</td>
            <td>4</td>
            <td>9</td>
            <td class="sep-r-weak">1</td>
            <td>7</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-team">3</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5306"><img src="./images/player/5306.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jarvis Hayes</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Hayes</span></a></td>            <td>SF</td>
            <td class="sep-r-team">25</td>
            <td>40</td>
            <td class="sep-r-weak">44</td>
            <td>10</td>
            <td class="sep-r-weak">73</td>
            <td>14</td>
            <td class="sep-r-team">33</td>
            <td>14</td>
            <td>27</td>
            <td>9</td>
            <td>40</td>
            <td>80</td>
            <td>0</td>
            <td class="sep-r-team">69</td>
            <td>8</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-weak">5</td>
            <td>5</td>
            <td>4</td>
            <td>3</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=6374"><img src="./images/player/6374.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jordan Bone</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Bone</span></a></td>            <td>PG</td>
            <td class="sep-r-team">22</td>
            <td>27</td>
            <td class="sep-r-weak">36</td>
            <td>10</td>
            <td class="sep-r-weak">70</td>
            <td>17</td>
            <td class="sep-r-team">30</td>
            <td>7</td>
            <td>24</td>
            <td>30</td>
            <td>18</td>
            <td>79</td>
            <td>1</td>
            <td class="sep-r-team">44</td>
            <td>5</td>
            <td>5</td>
            <td>1</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>5</td>
            <td>1</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4857"><img src="./images/player/4857.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">JaMychal Green</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Green</span></a></td>            <td>PF</td>
            <td class="sep-r-team">27</td>
            <td>23</td>
            <td class="sep-r-weak">47</td>
            <td>19</td>
            <td class="sep-r-weak">73</td>
            <td>9</td>
            <td class="sep-r-team">28</td>
            <td>18</td>
            <td>30</td>
            <td>5</td>
            <td>18</td>
            <td>78</td>
            <td>4</td>
            <td class="sep-r-team">71</td>
            <td>8</td>
            <td>5</td>
            <td>7</td>
            <td class="sep-r-weak">3</td>
            <td>8</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5973"><img src="./images/player/5973.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Keith Closs</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Closs</span></a></td>            <td>C</td>
            <td class="sep-r-team">22</td>
            <td>15</td>
            <td class="sep-r-weak">44</td>
            <td>17</td>
            <td class="sep-r-weak">60</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>17</td>
            <td>31</td>
            <td>4</td>
            <td>9</td>
            <td>86</td>
            <td>40</td>
            <td class="sep-r-team">97</td>
            <td>1</td>
            <td>2</td>
            <td>6</td>
            <td class="sep-r-weak">2</td>
            <td>1</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-team">1</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3870"><img src="./images/player/3870.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Tyler Hansbrough</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Hansbrough</span></a></td>            <td>PF</td>
            <td class="sep-r-team">31</td>
            <td>28</td>
            <td class="sep-r-weak">46</td>
            <td>41</td>
            <td class="sep-r-weak">75</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>33</td>
            <td>33</td>
            <td>6</td>
            <td>18</td>
            <td>69</td>
            <td>0</td>
            <td class="sep-r-team">84</td>
            <td>2</td>
            <td>5</td>
            <td>8</td>
            <td class="sep-r-weak">4</td>
            <td>6</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-team">6</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4489"><img src="./images/player/4489.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Pee Wee Kirkland</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Wee Kirkland</span></a></td>            <td>PG</td>
            <td class="sep-r-team">26</td>
            <td>23</td>
            <td class="sep-r-weak">40</td>
            <td>40</td>
            <td class="sep-r-weak">64</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>16</td>
            <td>6</td>
            <td>5</td>
            <td>56</td>
            <td>91</td>
            <td>3</td>
            <td class="sep-r-team">45</td>
            <td>6</td>
            <td>8</td>
            <td>2</td>
            <td class="sep-r-weak">8</td>
            <td>8</td>
            <td>9</td>
            <td>2</td>
            <td class="sep-r-team">9</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5309"><img src="./images/player/5309.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Nedžad Sinanovic</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">N. Sinanovic</span></a></td>            <td>C</td>
            <td class="sep-r-team">24</td>
            <td>31</td>
            <td class="sep-r-weak">41</td>
            <td>18</td>
            <td class="sep-r-weak">55</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>42</td>
            <td>52</td>
            <td>9</td>
            <td>18</td>
            <td>80</td>
            <td>6</td>
            <td class="sep-r-team">69</td>
            <td>2</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-weak">4</td>
            <td>4</td>
            <td>2</td>
            <td>9</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2992"><img src="./images/player/2992.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Robert Horry</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Horry</span></a></td>            <td>SF</td>
            <td class="sep-r-team">32</td>
            <td>39</td>
            <td class="sep-r-weak">43</td>
            <td>14</td>
            <td class="sep-r-weak">69</td>
            <td>12</td>
            <td class="sep-r-team">31</td>
            <td>17</td>
            <td>16</td>
            <td>20</td>
            <td>40</td>
            <td>61</td>
            <td>4</td>
            <td class="sep-r-team">66</td>
            <td>9</td>
            <td>4</td>
            <td>5</td>
            <td class="sep-r-weak">5</td>
            <td>9</td>
            <td>4</td>
            <td>3</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4517"><img src="./images/player/4517.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Alberto Herreros</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Herreros</span></a></td>            <td>SF</td>
            <td class="sep-r-team">26</td>
            <td>25</td>
            <td class="sep-r-weak">43</td>
            <td>43</td>
            <td class="sep-r-weak">70</td>
            <td>4</td>
            <td class="sep-r-team">25</td>
            <td>7</td>
            <td>13</td>
            <td>11</td>
            <td>51</td>
            <td>72</td>
            <td>2</td>
            <td class="sep-r-team">58</td>
            <td>9</td>
            <td>5</td>
            <td>2</td>
            <td class="sep-r-weak">4</td>
            <td>5</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4525"><img src="./images/player/4525.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Justise Winslow</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Winslow</span></a></td>            <td>SF</td>
            <td class="sep-r-team">24</td>
            <td>31</td>
            <td class="sep-r-weak">40</td>
            <td>20</td>
            <td class="sep-r-weak">67</td>
            <td>10</td>
            <td class="sep-r-team">29</td>
            <td>8</td>
            <td>33</td>
            <td>28</td>
            <td>24</td>
            <td>68</td>
            <td>4</td>
            <td class="sep-r-team">42</td>
            <td>7</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-weak">4</td>
            <td>7</td>
            <td>7</td>
            <td>6</td>
            <td class="sep-r-team">9</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2011"><img src="./images/player/2011.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Sarunas Jasikevicius</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Jasikevicius</span></a></td>            <td>PG</td>
            <td class="sep-r-team">33</td>
            <td>48</td>
            <td class="sep-r-weak">43</td>
            <td>27</td>
            <td class="sep-r-weak">81</td>
            <td>10</td>
            <td class="sep-r-team">28</td>
            <td>7</td>
            <td>9</td>
            <td>48</td>
            <td>19</td>
            <td>79</td>
            <td>0</td>
            <td class="sep-r-team">26</td>
            <td>5</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-weak">7</td>
            <td>3</td>
            <td>7</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1750"><img src="./images/player/1750.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Zach Randolph</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">Z. Randolph</span></a></td>            <td>PF</td>
            <td class="sep-r-team">34</td>
            <td>48</td>
            <td class="sep-r-weak">45</td>
            <td>27</td>
            <td class="sep-r-weak">77</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>24</td>
            <td>44</td>
            <td>2</td>
            <td>8</td>
            <td>71</td>
            <td>1</td>
            <td class="sep-r-team">53</td>
            <td>4</td>
            <td>6</td>
            <td>9</td>
            <td class="sep-r-weak">5</td>
            <td>4</td>
            <td>5</td>
            <td>9</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5291"><img src="./images/player/5291.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Leroy Edwards</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">L. Edwards</span></a></td>            <td>PF</td>
            <td class="sep-r-team">25</td>
            <td>40</td>
            <td class="sep-r-weak">41</td>
            <td>36</td>
            <td class="sep-r-weak">60</td>
            <td>6</td>
            <td class="sep-r-team">25</td>
            <td>15</td>
            <td>28</td>
            <td>10</td>
            <td>23</td>
            <td>79</td>
            <td>9</td>
            <td class="sep-r-team">95</td>
            <td>6</td>
            <td>5</td>
            <td>7</td>
            <td class="sep-r-weak">4</td>
            <td>6</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-team">5</td>
            <td>1</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3572"><img src="./images/player/3572.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Tony Delk</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Delk</span></a></td>            <td>SG</td>
            <td class="sep-r-team">31</td>
            <td>47</td>
            <td class="sep-r-weak">44</td>
            <td>6</td>
            <td class="sep-r-weak">61</td>
            <td>11</td>
            <td class="sep-r-team">28</td>
            <td>8</td>
            <td>7</td>
            <td>18</td>
            <td>25</td>
            <td>72</td>
            <td>0</td>
            <td class="sep-r-team">83</td>
            <td>7</td>
            <td>7</td>
            <td>2</td>
            <td class="sep-r-weak">2</td>
            <td>5</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3291"><img src="./images/player/3291.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Pooh Richardson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Richardson</span></a></td>            <td>PG</td>
            <td class="sep-r-team">32</td>
            <td>39</td>
            <td class="sep-r-weak">43</td>
            <td>13</td>
            <td class="sep-r-weak">73</td>
            <td>3</td>
            <td class="sep-r-team">15</td>
            <td>5</td>
            <td>7</td>
            <td>44</td>
            <td>24</td>
            <td>77</td>
            <td>0</td>
            <td class="sep-r-team">72</td>
            <td>6</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-weak">4</td>
            <td>6</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-team">6</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4531"><img src="./images/player/4531.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Rodney McCray</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. McCray</span></a></td>            <td>SF</td>
            <td class="sep-r-team">27</td>
            <td>21</td>
            <td class="sep-r-weak">42</td>
            <td>22</td>
            <td class="sep-r-weak">77</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>13</td>
            <td>30</td>
            <td>11</td>
            <td>20</td>
            <td>77</td>
            <td>3</td>
            <td class="sep-r-team">54</td>
            <td>5</td>
            <td>6</td>
            <td>5</td>
            <td class="sep-r-weak">3</td>
            <td>6</td>
            <td>5</td>
            <td>7</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3285"><img src="./images/player/3285.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Clifford Robinson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Robinson</span></a></td>            <td>SF</td>
            <td class="sep-r-team">32</td>
            <td>49</td>
            <td class="sep-r-weak">44</td>
            <td>36</td>
            <td class="sep-r-weak">67</td>
            <td>10</td>
            <td class="sep-r-team">28</td>
            <td>11</td>
            <td>11</td>
            <td>3</td>
            <td>13</td>
            <td>78</td>
            <td>3</td>
            <td class="sep-r-team">42</td>
            <td>7</td>
            <td>6</td>
            <td>6</td>
            <td class="sep-r-weak">4</td>
            <td>6</td>
            <td>7</td>
            <td>5</td>
            <td class="sep-r-team">6</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4872"><img src="./images/player/4872.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Rory Sparrow</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Sparrow</span></a></td>            <td>PG</td>
            <td class="sep-r-team">26</td>
            <td>26</td>
            <td class="sep-r-weak">42</td>
            <td>9</td>
            <td class="sep-r-weak">67</td>
            <td>1</td>
            <td class="sep-r-team">17</td>
            <td>4</td>
            <td>10</td>
            <td>33</td>
            <td>26</td>
            <td>77</td>
            <td>1</td>
            <td class="sep-r-team">57</td>
            <td>8</td>
            <td>8</td>
            <td>2</td>
            <td class="sep-r-weak">5</td>
            <td>5</td>
            <td>7</td>
            <td>1</td>
            <td class="sep-r-team">7</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4488"><img src="./images/player/4488.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Johnny Kilroy</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Kilroy</span></a></td>            <td>SG</td>
            <td class="sep-r-team">26</td>
            <td>25</td>
            <td class="sep-r-weak">33</td>
            <td>55</td>
            <td class="sep-r-weak">75</td>
            <td>2</td>
            <td class="sep-r-team">11</td>
            <td>11</td>
            <td>18</td>
            <td>16</td>
            <td>45</td>
            <td>69</td>
            <td>3</td>
            <td class="sep-r-team">25</td>
            <td>8</td>
            <td>8</td>
            <td>6</td>
            <td class="sep-r-weak">7</td>
            <td>7</td>
            <td>7</td>
            <td>8</td>
            <td class="sep-r-team">6</td>
            <td>1</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=6376"><img src="./images/player/6376.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Justin Wright-Fo</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Wright-Fo</span></a></td>            <td>PG</td>
            <td class="sep-r-team">22</td>
            <td>21</td>
            <td class="sep-r-weak">36</td>
            <td>15</td>
            <td class="sep-r-weak">72</td>
            <td>15</td>
            <td class="sep-r-team">28</td>
            <td>6</td>
            <td>9</td>
            <td>34</td>
            <td>21</td>
            <td>81</td>
            <td>1</td>
            <td class="sep-r-team">47</td>
            <td>5</td>
            <td>5</td>
            <td>1</td>
            <td class="sep-r-weak">4</td>
            <td>5</td>
            <td>5</td>
            <td>1</td>
            <td class="sep-r-team">3</td>
            <td>1</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1745"><img src="./images/player/1745.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Dominique Wilkins</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Wilkins</span></a></td>            <td>SF</td>
            <td class="sep-r-team">37</td>
            <td>46</td>
            <td class="sep-r-weak">42</td>
            <td>37</td>
            <td class="sep-r-weak">73</td>
            <td>12</td>
            <td class="sep-r-team">24</td>
            <td>16</td>
            <td>18</td>
            <td>6</td>
            <td>22</td>
            <td>67</td>
            <td>2</td>
            <td class="sep-r-team">69</td>
            <td>7</td>
            <td>7</td>
            <td>6</td>
            <td class="sep-r-weak">6</td>
            <td>5</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-team">7</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5674"><img src="./images/player/5674.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Fran Vázquez</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">F. Vázquez</span></a></td>            <td>C</td>
            <td class="sep-r-team">23</td>
            <td>16</td>
            <td class="sep-r-weak">46</td>
            <td>16</td>
            <td class="sep-r-weak">66</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>15</td>
            <td>28</td>
            <td>5</td>
            <td>8</td>
            <td>87</td>
            <td>17</td>
            <td class="sep-r-team">88</td>
            <td>3</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-weak">8</td>
            <td>6</td>
            <td>6</td>
            <td>7</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2989"><img src="./images/player/2989.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Todd Day</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Day</span></a></td>            <td>SG</td>
            <td class="sep-r-team">33</td>
            <td>46</td>
            <td class="sep-r-weak">43</td>
            <td>24</td>
            <td class="sep-r-weak">66</td>
            <td>10</td>
            <td class="sep-r-team">31</td>
            <td>13</td>
            <td>13</td>
            <td>4</td>
            <td>18</td>
            <td>67</td>
            <td>3</td>
            <td class="sep-r-team">64</td>
            <td>5</td>
            <td>6</td>
            <td>7</td>
            <td class="sep-r-weak">7</td>
            <td>4</td>
            <td>8</td>
            <td>5</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2984"><img src="./images/player/2984.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Walt Williams</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">W. Williams</span></a></td>            <td>SF</td>
            <td class="sep-r-team">32</td>
            <td>35</td>
            <td class="sep-r-weak">40</td>
            <td>17</td>
            <td class="sep-r-weak">64</td>
            <td>8</td>
            <td class="sep-r-team">26</td>
            <td>7</td>
            <td>8</td>
            <td>31</td>
            <td>23</td>
            <td>78</td>
            <td>0</td>
            <td class="sep-r-team">86</td>
            <td>4</td>
            <td>6</td>
            <td>9</td>
            <td class="sep-r-weak">8</td>
            <td>7</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-team">7</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=934"><img src="./images/player/934.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Kyle Lowry</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Lowry</span></a></td>            <td>PG</td>
            <td class="sep-r-team">37</td>
            <td>34</td>
            <td class="sep-r-weak">37</td>
            <td>29</td>
            <td class="sep-r-weak">79</td>
            <td>5</td>
            <td class="sep-r-team">24</td>
            <td>3</td>
            <td>9</td>
            <td>39</td>
            <td>24</td>
            <td>74</td>
            <td>0</td>
            <td class="sep-r-team">58</td>
            <td>7</td>
            <td>5</td>
            <td>2</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>4</td>
            <td>3</td>
            <td class="sep-r-team">7</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1746"><img src="./images/player/1746.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Adrian Dantley</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Dantley</span></a></td>            <td>SF</td>
            <td class="sep-r-team">36</td>
            <td>53</td>
            <td class="sep-r-weak">41</td>
            <td>64</td>
            <td class="sep-r-weak">75</td>
            <td>15</td>
            <td class="sep-r-team">29</td>
            <td>10</td>
            <td>12</td>
            <td>4</td>
            <td>11</td>
            <td>67</td>
            <td>0</td>
            <td class="sep-r-team">59</td>
            <td>5</td>
            <td>7</td>
            <td>7</td>
            <td class="sep-r-weak">7</td>
            <td>8</td>
            <td>3</td>
            <td>1</td>
            <td class="sep-r-team">4</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2723"><img src="./images/player/2723.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Petar Skansi</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Skansi</span></a></td>            <td>C</td>
            <td class="sep-r-team">34</td>
            <td>34</td>
            <td class="sep-r-weak">43</td>
            <td>25</td>
            <td class="sep-r-weak">79</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>13</td>
            <td>37</td>
            <td>6</td>
            <td>18</td>
            <td>65</td>
            <td>7</td>
            <td class="sep-r-team">80</td>
            <td>4</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">5</td>
            <td>6</td>
            <td>6</td>
            <td>8</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4533"><img src="./images/player/4533.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Chris McCullough</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. McCullough</span></a></td>            <td>PF</td>
            <td class="sep-r-team">25</td>
            <td>16</td>
            <td class="sep-r-weak">37</td>
            <td>17</td>
            <td class="sep-r-weak">53</td>
            <td>2</td>
            <td class="sep-r-team">17</td>
            <td>13</td>
            <td>17</td>
            <td>3</td>
            <td>33</td>
            <td>89</td>
            <td>4</td>
            <td class="sep-r-team">84</td>
            <td>4</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-weak">1</td>
            <td>8</td>
            <td>7</td>
            <td>7</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5695"><img src="./images/player/5695.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Luther Head</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">L. Head</span></a></td>            <td>SG</td>
            <td class="sep-r-team">25</td>
            <td>32</td>
            <td class="sep-r-weak">40</td>
            <td>18</td>
            <td class="sep-r-weak">77</td>
            <td>9</td>
            <td class="sep-r-team">28</td>
            <td>7</td>
            <td>11</td>
            <td>6</td>
            <td>19</td>
            <td>92</td>
            <td>1</td>
            <td class="sep-r-team">44</td>
            <td>7</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-weak">5</td>
            <td>7</td>
            <td>7</td>
            <td>3</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2440"><img src="./images/player/2440.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jamaal Magloire</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Magloire</span></a></td>            <td>C</td>
            <td class="sep-r-team">34</td>
            <td>34</td>
            <td class="sep-r-weak">46</td>
            <td>43</td>
            <td class="sep-r-weak">59</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>20</td>
            <td>29</td>
            <td>1</td>
            <td>9</td>
            <td>73</td>
            <td>4</td>
            <td class="sep-r-team">36</td>
            <td>2</td>
            <td>5</td>
            <td>7</td>
            <td class="sep-r-weak">6</td>
            <td>4</td>
            <td>3</td>
            <td>6</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3866"><img src="./images/player/3866.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Richie Guerin</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Guerin</span></a></td>            <td>SG</td>
            <td class="sep-r-team">31</td>
            <td>22</td>
            <td class="sep-r-weak">31</td>
            <td>52</td>
            <td class="sep-r-weak">78</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>8</td>
            <td>21</td>
            <td>12</td>
            <td>24</td>
            <td>70</td>
            <td>3</td>
            <td class="sep-r-team">41</td>
            <td>7</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>5</td>
            <td>7</td>
            <td class="sep-r-team">7</td>
            <td>1</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=6378"><img src="./images/player/6378.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Dylan Windler</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Windler</span></a></td>            <td>SF</td>
            <td class="sep-r-team">23</td>
            <td>19</td>
            <td class="sep-r-weak">38</td>
            <td>9</td>
            <td class="sep-r-weak">77</td>
            <td>16</td>
            <td class="sep-r-team">28</td>
            <td>8</td>
            <td>15</td>
            <td>25</td>
            <td>23</td>
            <td>85</td>
            <td>6</td>
            <td class="sep-r-team">67</td>
            <td>6</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>5</td>
            <td>4</td>
            <td>3</td>
            <td class="sep-r-team">5</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4537"><img src="./images/player/4537.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jerian Grant</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Grant</span></a></td>            <td>PG</td>
            <td class="sep-r-team">28</td>
            <td>22</td>
            <td class="sep-r-weak">37</td>
            <td>29</td>
            <td class="sep-r-weak">69</td>
            <td>4</td>
            <td class="sep-r-team">24</td>
            <td>3</td>
            <td>14</td>
            <td>30</td>
            <td>22</td>
            <td>78</td>
            <td>0</td>
            <td class="sep-r-team">67</td>
            <td>6</td>
            <td>9</td>
            <td>4</td>
            <td class="sep-r-weak">7</td>
            <td>6</td>
            <td>5</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4510"><img src="./images/player/4510.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Tyus Jones</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Jones</span></a></td>            <td>PG</td>
            <td class="sep-r-team">24</td>
            <td>23</td>
            <td class="sep-r-weak">37</td>
            <td>5</td>
            <td class="sep-r-weak">75</td>
            <td>4</td>
            <td class="sep-r-team">29</td>
            <td>3</td>
            <td>14</td>
            <td>29</td>
            <td>25</td>
            <td>88</td>
            <td>1</td>
            <td class="sep-r-team">87</td>
            <td>9</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-weak">5</td>
            <td>6</td>
            <td>8</td>
            <td>2</td>
            <td class="sep-r-team">9</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2726"><img src="./images/player/2726.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Dorell Wright</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Wright</span></a></td>            <td>SF</td>
            <td class="sep-r-team">30</td>
            <td>35</td>
            <td class="sep-r-weak">44</td>
            <td>14</td>
            <td class="sep-r-weak">70</td>
            <td>14</td>
            <td class="sep-r-team">31</td>
            <td>4</td>
            <td>13</td>
            <td>5</td>
            <td>26</td>
            <td>92</td>
            <td>0</td>
            <td class="sep-r-team">73</td>
            <td>8</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>8</td>
            <td>7</td>
            <td>2</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5304"><img src="./images/player/5304.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Steve Blake</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Blake</span></a></td>            <td>PG</td>
            <td class="sep-r-team">26</td>
            <td>19</td>
            <td class="sep-r-weak">40</td>
            <td>6</td>
            <td class="sep-r-weak">68</td>
            <td>9</td>
            <td class="sep-r-team">28</td>
            <td>3</td>
            <td>12</td>
            <td>30</td>
            <td>21</td>
            <td>81</td>
            <td>0</td>
            <td class="sep-r-team">71</td>
            <td>9</td>
            <td>7</td>
            <td>2</td>
            <td class="sep-r-weak">6</td>
            <td>5</td>
            <td>6</td>
            <td>1</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4175"><img src="./images/player/4175.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bryon Russell</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Russell</span></a></td>            <td>SF</td>
            <td class="sep-r-team">29</td>
            <td>25</td>
            <td class="sep-r-weak">40</td>
            <td>30</td>
            <td class="sep-r-weak">65</td>
            <td>3</td>
            <td class="sep-r-team">29</td>
            <td>7</td>
            <td>15</td>
            <td>4</td>
            <td>30</td>
            <td>86</td>
            <td>1</td>
            <td class="sep-r-team">47</td>
            <td>8</td>
            <td>5</td>
            <td>7</td>
            <td class="sep-r-weak">2</td>
            <td>6</td>
            <td>8</td>
            <td>4</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5303"><img src="./images/player/5303.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Sasha Pavlovic</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Pavlovic</span></a></td>            <td>SG</td>
            <td class="sep-r-team">23</td>
            <td>36</td>
            <td class="sep-r-weak">42</td>
            <td>27</td>
            <td class="sep-r-weak">79</td>
            <td>7</td>
            <td class="sep-r-team">31</td>
            <td>5</td>
            <td>12</td>
            <td>7</td>
            <td>11</td>
            <td>86</td>
            <td>1</td>
            <td class="sep-r-team">43</td>
            <td>8</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4864"><img src="./images/player/4864.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Reggie Perry</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Perry</span></a></td>            <td>PF</td>
            <td class="sep-r-team">24</td>
            <td>25</td>
            <td class="sep-r-weak">44</td>
            <td>15</td>
            <td class="sep-r-weak">65</td>
            <td>3</td>
            <td class="sep-r-team">8</td>
            <td>17</td>
            <td>24</td>
            <td>6</td>
            <td>19</td>
            <td>81</td>
            <td>3</td>
            <td class="sep-r-team">68</td>
            <td>5</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-weak">3</td>
            <td>6</td>
            <td>3</td>
            <td>8</td>
            <td class="sep-r-team">4</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4860"><img src="./images/player/4860.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jordan Nwora</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Nwora</span></a></td>            <td>SF</td>
            <td class="sep-r-team">26</td>
            <td>27</td>
            <td class="sep-r-weak">41</td>
            <td>19</td>
            <td class="sep-r-weak">67</td>
            <td>6</td>
            <td class="sep-r-team">22</td>
            <td>8</td>
            <td>26</td>
            <td>12</td>
            <td>12</td>
            <td>78</td>
            <td>2</td>
            <td class="sep-r-team">61</td>
            <td>9</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-weak">3</td>
            <td>5</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=6377"><img src="./images/player/6377.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Romeo Langford</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Langford</span></a></td>            <td>SG</td>
            <td class="sep-r-team">19</td>
            <td>29</td>
            <td class="sep-r-weak">38</td>
            <td>25</td>
            <td class="sep-r-weak">64</td>
            <td>16</td>
            <td class="sep-r-team">25</td>
            <td>14</td>
            <td>16</td>
            <td>22</td>
            <td>11</td>
            <td>80</td>
            <td>9</td>
            <td class="sep-r-team">21</td>
            <td>5</td>
            <td>4</td>
            <td>2</td>
            <td class="sep-r-weak">5</td>
            <td>4</td>
            <td>4</td>
            <td>2</td>
            <td class="sep-r-team">4</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2725"><img src="./images/player/2725.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jameer Nelson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Nelson</span></a></td>            <td>PG</td>
            <td class="sep-r-team">33</td>
            <td>25</td>
            <td class="sep-r-weak">38</td>
            <td>12</td>
            <td class="sep-r-weak">75</td>
            <td>3</td>
            <td class="sep-r-team">29</td>
            <td>5</td>
            <td>9</td>
            <td>37</td>
            <td>17</td>
            <td>72</td>
            <td>0</td>
            <td class="sep-r-team">95</td>
            <td>6</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4874"><img src="./images/player/4874.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Isaiah Stewart</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">I. Stewart</span></a></td>            <td>C</td>
            <td class="sep-r-team">23</td>
            <td>16</td>
            <td class="sep-r-weak">38</td>
            <td>15</td>
            <td class="sep-r-weak">76</td>
            <td>4</td>
            <td class="sep-r-team">24</td>
            <td>11</td>
            <td>28</td>
            <td>6</td>
            <td>9</td>
            <td>82</td>
            <td>4</td>
            <td class="sep-r-team">64</td>
            <td>3</td>
            <td>2</td>
            <td>8</td>
            <td class="sep-r-weak">2</td>
            <td>8</td>
            <td>7</td>
            <td>6</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2714"><img src="./images/player/2714.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Purvis Short</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Short</span></a></td>            <td>SF</td>
            <td class="sep-r-team">32</td>
            <td>48</td>
            <td class="sep-r-weak">46</td>
            <td>30</td>
            <td class="sep-r-weak">75</td>
            <td>6</td>
            <td class="sep-r-team">25</td>
            <td>8</td>
            <td>12</td>
            <td>5</td>
            <td>20</td>
            <td>74</td>
            <td>0</td>
            <td class="sep-r-team">35</td>
            <td>6</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-weak">8</td>
            <td>3</td>
            <td>5</td>
            <td>2</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2433"><img src="./images/player/2433.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Mike Miller</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Miller</span></a></td>            <td>SF</td>
            <td class="sep-r-team">32</td>
            <td>46</td>
            <td class="sep-r-weak">42</td>
            <td>12</td>
            <td class="sep-r-weak">66</td>
            <td>14</td>
            <td class="sep-r-team">29</td>
            <td>5</td>
            <td>15</td>
            <td>6</td>
            <td>8</td>
            <td>65</td>
            <td>0</td>
            <td class="sep-r-team">91</td>
            <td>9</td>
            <td>6</td>
            <td>6</td>
            <td class="sep-r-weak">1</td>
            <td>5</td>
            <td>8</td>
            <td>4</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=615"><img src="./images/player/615.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">John Havlicek</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Havlicek</span></a></td>            <td>SG</td>
            <td class="sep-r-team">40</td>
            <td>18</td>
            <td class="sep-r-weak">30</td>
            <td>40</td>
            <td class="sep-r-weak">84</td>
            <td>0</td>
            <td class="sep-r-team">33</td>
            <td>10</td>
            <td>12</td>
            <td>20</td>
            <td>26</td>
            <td>88</td>
            <td>0</td>
            <td class="sep-r-team">70</td>
            <td>4</td>
            <td>6</td>
            <td>6</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>2</td>
            <td>5</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4185"><img src="./images/player/4185.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Danuel House</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. House</span></a></td>            <td>SF</td>
            <td class="sep-r-team">28</td>
            <td>21</td>
            <td class="sep-r-weak">40</td>
            <td>11</td>
            <td class="sep-r-weak">75</td>
            <td>7</td>
            <td class="sep-r-team">26</td>
            <td>6</td>
            <td>15</td>
            <td>4</td>
            <td>24</td>
            <td>90</td>
            <td>2</td>
            <td class="sep-r-team">59</td>
            <td>9</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-weak">1</td>
            <td>8</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-team">6</td>
            <td>1</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2429"><img src="./images/player/2429.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Theo Papaloukas</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Papaloukas</span></a></td>            <td>PG</td>
            <td class="sep-r-team">33</td>
            <td>19</td>
            <td class="sep-r-weak">41</td>
            <td>26</td>
            <td class="sep-r-weak">60</td>
            <td>1</td>
            <td class="sep-r-team">25</td>
            <td>4</td>
            <td>6</td>
            <td>34</td>
            <td>24</td>
            <td>83</td>
            <td>0</td>
            <td class="sep-r-team">43</td>
            <td>5</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-weak">5</td>
            <td>5</td>
            <td>3</td>
            <td>6</td>
            <td class="sep-r-team">3</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2437"><img src="./images/player/2437.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Nino Buscato</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">N. Buscato</span></a></td>            <td>PG</td>
            <td class="sep-r-team">33</td>
            <td>25</td>
            <td class="sep-r-weak">39</td>
            <td>20</td>
            <td class="sep-r-weak">66</td>
            <td>5</td>
            <td class="sep-r-team">36</td>
            <td>6</td>
            <td>8</td>
            <td>41</td>
            <td>20</td>
            <td>77</td>
            <td>0</td>
            <td class="sep-r-team">9</td>
            <td>5</td>
            <td>7</td>
            <td>3</td>
            <td class="sep-r-weak">7</td>
            <td>6</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4876"><img src="./images/player/4876.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Leandro Bolmaro</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">L. Bolmaro</span></a></td>            <td>SF</td>
            <td class="sep-r-team">25</td>
            <td>14</td>
            <td class="sep-r-weak">40</td>
            <td>9</td>
            <td class="sep-r-weak">78</td>
            <td>2</td>
            <td class="sep-r-team">10</td>
            <td>11</td>
            <td>12</td>
            <td>32</td>
            <td>16</td>
            <td>79</td>
            <td>0</td>
            <td class="sep-r-team">86</td>
            <td>5</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-weak">7</td>
            <td>5</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3290"><img src="./images/player/3290.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Sherman Douglas</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Douglas</span></a></td>            <td>PG</td>
            <td class="sep-r-team">32</td>
            <td>31</td>
            <td class="sep-r-weak">41</td>
            <td>25</td>
            <td class="sep-r-weak">64</td>
            <td>2</td>
            <td class="sep-r-team">0</td>
            <td>8</td>
            <td>6</td>
            <td>42</td>
            <td>35</td>
            <td>42</td>
            <td>0</td>
            <td class="sep-r-team">71</td>
            <td>4</td>
            <td>9</td>
            <td>4</td>
            <td class="sep-r-weak">7</td>
            <td>5</td>
            <td>7</td>
            <td>1</td>
            <td class="sep-r-team">9</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=6381"><img src="./images/player/6381.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jarrell Brantley</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Brantley</span></a></td>            <td>PF</td>
            <td class="sep-r-team">23</td>
            <td>19</td>
            <td class="sep-r-weak">42</td>
            <td>11</td>
            <td class="sep-r-weak">82</td>
            <td>14</td>
            <td class="sep-r-team">32</td>
            <td>7</td>
            <td>12</td>
            <td>13</td>
            <td>9</td>
            <td>69</td>
            <td>5</td>
            <td class="sep-r-team">51</td>
            <td>4</td>
            <td>3</td>
            <td>2</td>
            <td class="sep-r-weak">4</td>
            <td>4</td>
            <td>3</td>
            <td>2</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3871"><img src="./images/player/3871.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">James Johnson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Johnson</span></a></td>            <td>SF</td>
            <td class="sep-r-team">29</td>
            <td>25</td>
            <td class="sep-r-weak">39</td>
            <td>29</td>
            <td class="sep-r-weak">72</td>
            <td>4</td>
            <td class="sep-r-team">25</td>
            <td>5</td>
            <td>20</td>
            <td>9</td>
            <td>21</td>
            <td>70</td>
            <td>4</td>
            <td class="sep-r-team">36</td>
            <td>7</td>
            <td>7</td>
            <td>5</td>
            <td class="sep-r-weak">4</td>
            <td>7</td>
            <td>4</td>
            <td>8</td>
            <td class="sep-r-team">6</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4853"><img src="./images/player/4853.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Aleksej Pokuševski</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Pokuševski</span></a></td>            <td>PF</td>
            <td class="sep-r-team">23</td>
            <td>23</td>
            <td class="sep-r-weak">38</td>
            <td>12</td>
            <td class="sep-r-weak">55</td>
            <td>10</td>
            <td class="sep-r-team">26</td>
            <td>12</td>
            <td>26</td>
            <td>11</td>
            <td>16</td>
            <td>82</td>
            <td>10</td>
            <td class="sep-r-team">60</td>
            <td>7</td>
            <td>4</td>
            <td>2</td>
            <td class="sep-r-weak">3</td>
            <td>8</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-team">3</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4881"><img src="./images/player/4881.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">KJ Martin</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Martin</span></a></td>            <td>SF</td>
            <td class="sep-r-team">24</td>
            <td>15</td>
            <td class="sep-r-weak">40</td>
            <td>26</td>
            <td class="sep-r-weak">64</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>11</td>
            <td>22</td>
            <td>6</td>
            <td>13</td>
            <td>84</td>
            <td>2</td>
            <td class="sep-r-team">65</td>
            <td>8</td>
            <td>4</td>
            <td>5</td>
            <td class="sep-r-weak">2</td>
            <td>7</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4867"><img src="./images/player/4867.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Marissa Coleman</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Coleman</span></a></td>            <td>SG</td>
            <td class="sep-r-team">26</td>
            <td>22</td>
            <td class="sep-r-weak">35</td>
            <td>29</td>
            <td class="sep-r-weak">73</td>
            <td>5</td>
            <td class="sep-r-team">22</td>
            <td>8</td>
            <td>18</td>
            <td>9</td>
            <td>36</td>
            <td>68</td>
            <td>1</td>
            <td class="sep-r-team">56</td>
            <td>8</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-weak">3</td>
            <td>6</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5986"><img src="./images/player/5986.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Ansu Sesay</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Sesay</span></a></td>            <td>SF</td>
            <td class="sep-r-team">26</td>
            <td>18</td>
            <td class="sep-r-weak">39</td>
            <td>27</td>
            <td class="sep-r-weak">68</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>12</td>
            <td>16</td>
            <td>11</td>
            <td>19</td>
            <td>85</td>
            <td>5</td>
            <td class="sep-r-team">98</td>
            <td>1</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>5</td>
            <td>5</td>
            <td>2</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5307"><img src="./images/player/5307.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Keith Bogans</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Bogans</span></a></td>            <td>SG</td>
            <td class="sep-r-team">26</td>
            <td>20</td>
            <td class="sep-r-weak">35</td>
            <td>14</td>
            <td class="sep-r-weak">70</td>
            <td>7</td>
            <td class="sep-r-team">30</td>
            <td>12</td>
            <td>19</td>
            <td>6</td>
            <td>22</td>
            <td>84</td>
            <td>0</td>
            <td class="sep-r-team">59</td>
            <td>5</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-weak">1</td>
            <td>7</td>
            <td>7</td>
            <td>6</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4176"><img src="./images/player/4176.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Calbert Cheaney</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Cheaney</span></a></td>            <td>SF</td>
            <td class="sep-r-team">28</td>
            <td>34</td>
            <td class="sep-r-weak">40</td>
            <td>23</td>
            <td class="sep-r-weak">80</td>
            <td>3</td>
            <td class="sep-r-team">20</td>
            <td>10</td>
            <td>10</td>
            <td>8</td>
            <td>26</td>
            <td>75</td>
            <td>1</td>
            <td class="sep-r-team">50</td>
            <td>7</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-weak">5</td>
            <td>5</td>
            <td>4</td>
            <td>5</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5985"><img src="./images/player/5985.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jelani McCoy</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. McCoy</span></a></td>            <td>C</td>
            <td class="sep-r-team">22</td>
            <td>14</td>
            <td class="sep-r-weak">46</td>
            <td>20</td>
            <td class="sep-r-weak">49</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>11</td>
            <td>27</td>
            <td>5</td>
            <td>9</td>
            <td>82</td>
            <td>23</td>
            <td class="sep-r-team">90</td>
            <td>1</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-weak">2</td>
            <td>2</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-team">1</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3900"><img src="./images/player/3900.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Earl Clark</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">E. Clark</span></a></td>            <td>PF</td>
            <td class="sep-r-team">29</td>
            <td>18</td>
            <td class="sep-r-weak">36</td>
            <td>14</td>
            <td class="sep-r-weak">68</td>
            <td>3</td>
            <td class="sep-r-team">8</td>
            <td>13</td>
            <td>39</td>
            <td>5</td>
            <td>13</td>
            <td>80</td>
            <td>4</td>
            <td class="sep-r-team">75</td>
            <td>6</td>
            <td>3</td>
            <td>8</td>
            <td class="sep-r-weak">1</td>
            <td>9</td>
            <td>5</td>
            <td>7</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4528"><img src="./images/player/4528.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Delon Wright</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Wright</span></a></td>            <td>PG</td>
            <td class="sep-r-team">28</td>
            <td>18</td>
            <td class="sep-r-weak">36</td>
            <td>19</td>
            <td class="sep-r-weak">74</td>
            <td>2</td>
            <td class="sep-r-team">12</td>
            <td>7</td>
            <td>16</td>
            <td>17</td>
            <td>33</td>
            <td>86</td>
            <td>2</td>
            <td class="sep-r-team">65</td>
            <td>4</td>
            <td>7</td>
            <td>5</td>
            <td class="sep-r-weak">4</td>
            <td>7</td>
            <td>8</td>
            <td>4</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4882"><img src="./images/player/4882.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Saben Lee</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Lee</span></a></td>            <td>SF</td>
            <td class="sep-r-team">25</td>
            <td>15</td>
            <td class="sep-r-weak">30</td>
            <td>28</td>
            <td class="sep-r-weak">76</td>
            <td>2</td>
            <td class="sep-r-team">10</td>
            <td>6</td>
            <td>18</td>
            <td>22</td>
            <td>38</td>
            <td>78</td>
            <td>2</td>
            <td class="sep-r-team">76</td>
            <td>5</td>
            <td>8</td>
            <td>6</td>
            <td class="sep-r-weak">5</td>
            <td>7</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-team">3</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5988"><img src="./images/player/5988.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Ryan Bowen</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Bowen</span></a></td>            <td>PF</td>
            <td class="sep-r-team">24</td>
            <td>16</td>
            <td class="sep-r-weak">39</td>
            <td>14</td>
            <td class="sep-r-weak">67</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>12</td>
            <td>21</td>
            <td>9</td>
            <td>17</td>
            <td>84</td>
            <td>5</td>
            <td class="sep-r-team">63</td>
            <td>1</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-weak">2</td>
            <td>3</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-team">3</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4861"><img src="./images/player/4861.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Anthony Morrow</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Morrow</span></a></td>            <td>SG</td>
            <td class="sep-r-team">27</td>
            <td>31</td>
            <td class="sep-r-weak">42</td>
            <td>13</td>
            <td class="sep-r-weak">74</td>
            <td>11</td>
            <td class="sep-r-team">29</td>
            <td>5</td>
            <td>14</td>
            <td>5</td>
            <td>24</td>
            <td>86</td>
            <td>1</td>
            <td class="sep-r-team">56</td>
            <td>9</td>
            <td>4</td>
            <td>2</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3293"><img src="./images/player/3293.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Keith Jennings</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Jennings</span></a></td>            <td>PG</td>
            <td class="sep-r-team">32</td>
            <td>22</td>
            <td class="sep-r-weak">39</td>
            <td>18</td>
            <td class="sep-r-weak">79</td>
            <td>7</td>
            <td class="sep-r-team">20</td>
            <td>2</td>
            <td>6</td>
            <td>39</td>
            <td>26</td>
            <td>79</td>
            <td>0</td>
            <td class="sep-r-team">79</td>
            <td>5</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-weak">4</td>
            <td>4</td>
            <td>7</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>1</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5989"><img src="./images/player/5989.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Tremaine Fowlkes</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Fowlkes</span></a></td>            <td>SG</td>
            <td class="sep-r-team">23</td>
            <td>18</td>
            <td class="sep-r-weak">36</td>
            <td>18</td>
            <td class="sep-r-weak">74</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>17</td>
            <td>25</td>
            <td>6</td>
            <td>35</td>
            <td>76</td>
            <td>2</td>
            <td class="sep-r-team">73</td>
            <td>2</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-weak">3</td>
            <td>6</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=6380"><img src="./images/player/6380.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Didi Louzada</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Louzada</span></a></td>            <td>SG</td>
            <td class="sep-r-team">22</td>
            <td>32</td>
            <td class="sep-r-weak">38</td>
            <td>21</td>
            <td class="sep-r-weak">67</td>
            <td>5</td>
            <td class="sep-r-team">21</td>
            <td>6</td>
            <td>9</td>
            <td>43</td>
            <td>24</td>
            <td>76</td>
            <td>0</td>
            <td class="sep-r-team">43</td>
            <td>4</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-weak">5</td>
            <td>2</td>
            <td>2</td>
            <td>1</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4518"><img src="./images/player/4518.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Cameron Payne</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Payne</span></a></td>            <td>PG</td>
            <td class="sep-r-team">26</td>
            <td>28</td>
            <td class="sep-r-weak">35</td>
            <td>17</td>
            <td class="sep-r-weak">80</td>
            <td>4</td>
            <td class="sep-r-team">21</td>
            <td>4</td>
            <td>17</td>
            <td>35</td>
            <td>19</td>
            <td>69</td>
            <td>1</td>
            <td class="sep-r-team">42</td>
            <td>8</td>
            <td>9</td>
            <td>2</td>
            <td class="sep-r-weak">9</td>
            <td>5</td>
            <td>7</td>
            <td>3</td>
            <td class="sep-r-team">6</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2430"><img src="./images/player/2430.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Dennis Johnson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Johnson</span></a></td>            <td>PG</td>
            <td class="sep-r-team">34</td>
            <td>23</td>
            <td class="sep-r-weak">36</td>
            <td>32</td>
            <td class="sep-r-weak">70</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>8</td>
            <td>6</td>
            <td>12</td>
            <td>34</td>
            <td>76</td>
            <td>0</td>
            <td class="sep-r-team">34</td>
            <td>2</td>
            <td>6</td>
            <td>6</td>
            <td class="sep-r-weak">7</td>
            <td>6</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-team">8</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4174"><img src="./images/player/4174.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Trent Tucker</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Tucker</span></a></td>            <td>SG</td>
            <td class="sep-r-team">29</td>
            <td>28</td>
            <td class="sep-r-weak">39</td>
            <td>7</td>
            <td class="sep-r-weak">56</td>
            <td>14</td>
            <td class="sep-r-team">30</td>
            <td>5</td>
            <td>8</td>
            <td>5</td>
            <td>28</td>
            <td>88</td>
            <td>0</td>
            <td class="sep-r-team">66</td>
            <td>9</td>
            <td>4</td>
            <td>3</td>
            <td class="sep-r-weak">2</td>
            <td>6</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4866"><img src="./images/player/4866.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Elijah Hughes</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">E. Hughes</span></a></td>            <td>SG</td>
            <td class="sep-r-team">26</td>
            <td>18</td>
            <td class="sep-r-weak">34</td>
            <td>6</td>
            <td class="sep-r-weak">79</td>
            <td>8</td>
            <td class="sep-r-team">20</td>
            <td>4</td>
            <td>17</td>
            <td>6</td>
            <td>21</td>
            <td>85</td>
            <td>2</td>
            <td class="sep-r-team">87</td>
            <td>9</td>
            <td>3</td>
            <td>2</td>
            <td class="sep-r-weak">2</td>
            <td>6</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4858"><img src="./images/player/4858.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Ioannis Bourousis</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">I. Bourousis</span></a></td>            <td>C</td>
            <td class="sep-r-team">25</td>
            <td>11</td>
            <td class="sep-r-weak">39</td>
            <td>32</td>
            <td class="sep-r-weak">64</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>22</td>
            <td>25</td>
            <td>4</td>
            <td>12</td>
            <td>69</td>
            <td>9</td>
            <td class="sep-r-team">96</td>
            <td>6</td>
            <td>2</td>
            <td>9</td>
            <td class="sep-r-weak">5</td>
            <td>4</td>
            <td>4</td>
            <td>9</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4878"><img src="./images/player/4878.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Marko Simonovic</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Simonovic</span></a></td>            <td>C</td>
            <td class="sep-r-team">26</td>
            <td>20</td>
            <td class="sep-r-weak">40</td>
            <td>40</td>
            <td class="sep-r-weak">72</td>
            <td>3</td>
            <td class="sep-r-team">17</td>
            <td>16</td>
            <td>18</td>
            <td>18</td>
            <td>8</td>
            <td>56</td>
            <td>5</td>
            <td class="sep-r-team">58</td>
            <td>8</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>3</td>
            <td>4</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3298"><img src="./images/player/3298.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">B.J. Armstrong</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Armstrong</span></a></td>            <td>PG</td>
            <td class="sep-r-team">31</td>
            <td>23</td>
            <td class="sep-r-weak">36</td>
            <td>24</td>
            <td class="sep-r-weak">82</td>
            <td>2</td>
            <td class="sep-r-team">25</td>
            <td>3</td>
            <td>8</td>
            <td>10</td>
            <td>18</td>
            <td>85</td>
            <td>0</td>
            <td class="sep-r-team">69</td>
            <td>8</td>
            <td>4</td>
            <td>3</td>
            <td class="sep-r-weak">1</td>
            <td>7</td>
            <td>9</td>
            <td>1</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4181"><img src="./images/player/4181.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Corie Blount</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Blount</span></a></td>            <td>C</td>
            <td class="sep-r-team">30</td>
            <td>13</td>
            <td class="sep-r-weak">43</td>
            <td>18</td>
            <td class="sep-r-weak">63</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>26</td>
            <td>26</td>
            <td>2</td>
            <td>12</td>
            <td>80</td>
            <td>2</td>
            <td class="sep-r-team">58</td>
            <td>3</td>
            <td>2</td>
            <td>7</td>
            <td class="sep-r-weak">3</td>
            <td>7</td>
            <td>4</td>
            <td>8</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1484"><img src="./images/player/1484.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Chauncey Billups</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Billups</span></a></td>            <td>PG</td>
            <td class="sep-r-team">37</td>
            <td>26</td>
            <td class="sep-r-weak">35</td>
            <td>34</td>
            <td class="sep-r-weak">87</td>
            <td>4</td>
            <td class="sep-r-team">17</td>
            <td>3</td>
            <td>5</td>
            <td>35</td>
            <td>7</td>
            <td>77</td>
            <td>0</td>
            <td class="sep-r-team">41</td>
            <td>6</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-weak">3</td>
            <td>3</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">4</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5696"><img src="./images/player/5696.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Joey Graham</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Graham</span></a></td>            <td>SF</td>
            <td class="sep-r-team">25</td>
            <td>15</td>
            <td class="sep-r-weak">38</td>
            <td>30</td>
            <td class="sep-r-weak">77</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>15</td>
            <td>25</td>
            <td>7</td>
            <td>10</td>
            <td>74</td>
            <td>3</td>
            <td class="sep-r-team">23</td>
            <td>4</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">5</td>
            <td>1</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5987"><img src="./images/player/5987.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bruno Šundov</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Šundov</span></a></td>            <td>C</td>
            <td class="sep-r-team">19</td>
            <td>11</td>
            <td class="sep-r-weak">33</td>
            <td>27</td>
            <td class="sep-r-weak">79</td>
            <td>4</td>
            <td class="sep-r-team">14</td>
            <td>5</td>
            <td>9</td>
            <td>31</td>
            <td>31</td>
            <td>69</td>
            <td>1</td>
            <td class="sep-r-team">82</td>
            <td>1</td>
            <td>3</td>
            <td>4</td>
            <td class="sep-r-weak">1</td>
            <td>5</td>
            <td>3</td>
            <td>6</td>
            <td class="sep-r-team">2</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3876"><img src="./images/player/3876.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Kaya Peker</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Peker</span></a></td>            <td>C</td>
            <td class="sep-r-team">27</td>
            <td>11</td>
            <td class="sep-r-weak">39</td>
            <td>38</td>
            <td class="sep-r-weak">69</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>12</td>
            <td>18</td>
            <td>3</td>
            <td>18</td>
            <td>78</td>
            <td>3</td>
            <td class="sep-r-team">19</td>
            <td>5</td>
            <td>5</td>
            <td>9</td>
            <td class="sep-r-weak">3</td>
            <td>7</td>
            <td>5</td>
            <td>9</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4536"><img src="./images/player/4536.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Cedi Osman</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Osman</span></a></td>            <td>SF</td>
            <td class="sep-r-team">27</td>
            <td>22</td>
            <td class="sep-r-weak">37</td>
            <td>24</td>
            <td class="sep-r-weak">75</td>
            <td>5</td>
            <td class="sep-r-team">24</td>
            <td>5</td>
            <td>21</td>
            <td>9</td>
            <td>17</td>
            <td>80</td>
            <td>1</td>
            <td class="sep-r-team">55</td>
            <td>8</td>
            <td>6</td>
            <td>5</td>
            <td class="sep-r-weak">6</td>
            <td>7</td>
            <td>3</td>
            <td>2</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4869"><img src="./images/player/4869.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Killian Hayes</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Hayes</span></a></td>            <td>PG</td>
            <td class="sep-r-team">23</td>
            <td>24</td>
            <td class="sep-r-weak">32</td>
            <td>13</td>
            <td class="sep-r-weak">76</td>
            <td>4</td>
            <td class="sep-r-team">11</td>
            <td>3</td>
            <td>16</td>
            <td>30</td>
            <td>33</td>
            <td>66</td>
            <td>2</td>
            <td class="sep-r-team">47</td>
            <td>7</td>
            <td>9</td>
            <td>1</td>
            <td class="sep-r-weak">7</td>
            <td>5</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4539"><img src="./images/player/4539.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Justin Anderson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Anderson</span></a></td>            <td>SF</td>
            <td class="sep-r-team">27</td>
            <td>19</td>
            <td class="sep-r-weak">37</td>
            <td>22</td>
            <td class="sep-r-weak">77</td>
            <td>4</td>
            <td class="sep-r-team">8</td>
            <td>10</td>
            <td>26</td>
            <td>4</td>
            <td>25</td>
            <td>90</td>
            <td>1</td>
            <td class="sep-r-team">65</td>
            <td>6</td>
            <td>5</td>
            <td>7</td>
            <td class="sep-r-weak">4</td>
            <td>6</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4883"><img src="./images/player/4883.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Isaiah Joe</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">I. Joe</span></a></td>            <td>SG</td>
            <td class="sep-r-team">25</td>
            <td>15</td>
            <td class="sep-r-weak">40</td>
            <td>9</td>
            <td class="sep-r-weak">79</td>
            <td>7</td>
            <td class="sep-r-team">27</td>
            <td>4</td>
            <td>17</td>
            <td>8</td>
            <td>18</td>
            <td>88</td>
            <td>2</td>
            <td class="sep-r-team">79</td>
            <td>7</td>
            <td>4</td>
            <td>3</td>
            <td class="sep-r-weak">7</td>
            <td>4</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=6383"><img src="./images/player/6383.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Justin James</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. James</span></a></td>            <td>SF</td>
            <td class="sep-r-team">22</td>
            <td>19</td>
            <td class="sep-r-weak">41</td>
            <td>19</td>
            <td class="sep-r-weak">53</td>
            <td>5</td>
            <td class="sep-r-team">29</td>
            <td>9</td>
            <td>13</td>
            <td>11</td>
            <td>16</td>
            <td>79</td>
            <td>5</td>
            <td class="sep-r-team">55</td>
            <td>3</td>
            <td>4</td>
            <td>3</td>
            <td class="sep-r-weak">4</td>
            <td>4</td>
            <td>3</td>
            <td>3</td>
            <td class="sep-r-team">4</td>
            <td>1</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5299"><img src="./images/player/5299.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Mike Sweetney</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Sweetney</span></a></td>            <td>PF</td>
            <td class="sep-r-team">24</td>
            <td>30</td>
            <td class="sep-r-weak">39</td>
            <td>39</td>
            <td class="sep-r-weak">64</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>20</td>
            <td>34</td>
            <td>4</td>
            <td>7</td>
            <td>75</td>
            <td>6</td>
            <td class="sep-r-team">8</td>
            <td>3</td>
            <td>4</td>
            <td>8</td>
            <td class="sep-r-weak">3</td>
            <td>7</td>
            <td>1</td>
            <td>8</td>
            <td class="sep-r-team">2</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3894"><img src="./images/player/3894.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Brandon Jennings</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Jennings</span></a></td>            <td>PG</td>
            <td class="sep-r-team">27</td>
            <td>29</td>
            <td class="sep-r-weak">28</td>
            <td>29</td>
            <td class="sep-r-weak">77</td>
            <td>2</td>
            <td class="sep-r-team">10</td>
            <td>4</td>
            <td>11</td>
            <td>29</td>
            <td>28</td>
            <td>67</td>
            <td>0</td>
            <td class="sep-r-team">53</td>
            <td>8</td>
            <td>9</td>
            <td>3</td>
            <td class="sep-r-weak">7</td>
            <td>7</td>
            <td>7</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4198"><img src="./images/player/4198.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Chucky Atkins</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Atkins</span></a></td>            <td>PG</td>
            <td class="sep-r-team">30</td>
            <td>27</td>
            <td class="sep-r-weak">35</td>
            <td>18</td>
            <td class="sep-r-weak">66</td>
            <td>2</td>
            <td class="sep-r-team">9</td>
            <td>3</td>
            <td>6</td>
            <td>23</td>
            <td>15</td>
            <td>72</td>
            <td>0</td>
            <td class="sep-r-team">67</td>
            <td>7</td>
            <td>8</td>
            <td>3</td>
            <td class="sep-r-weak">8</td>
            <td>9</td>
            <td>8</td>
            <td>1</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5990"><img src="./images/player/5990.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Andrae Patterson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Patterson</span></a></td>            <td>PF</td>
            <td class="sep-r-team">24</td>
            <td>10</td>
            <td class="sep-r-weak">38</td>
            <td>22</td>
            <td class="sep-r-weak">77</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>19</td>
            <td>26</td>
            <td>9</td>
            <td>23</td>
            <td>64</td>
            <td>10</td>
            <td class="sep-r-team">62</td>
            <td>2</td>
            <td>1</td>
            <td>8</td>
            <td class="sep-r-weak">2</td>
            <td>2</td>
            <td>2</td>
            <td>6</td>
            <td class="sep-r-team">2</td>
            <td>1</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2009"><img src="./images/player/2009.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Lionel Simmons</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">L. Simmons</span></a></td>            <td>SF</td>
            <td class="sep-r-team">35</td>
            <td>24</td>
            <td class="sep-r-weak">37</td>
            <td>17</td>
            <td class="sep-r-weak">66</td>
            <td>5</td>
            <td class="sep-r-team">17</td>
            <td>10</td>
            <td>11</td>
            <td>9</td>
            <td>20</td>
            <td>71</td>
            <td>1</td>
            <td class="sep-r-team">40</td>
            <td>7</td>
            <td>6</td>
            <td>8</td>
            <td class="sep-r-weak">5</td>
            <td>7</td>
            <td>8</td>
            <td>8</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3005"><img src="./images/player/3005.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Ed Macauley</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">E. Macauley</span></a></td>            <td>C</td>
            <td class="sep-r-team">31</td>
            <td>20</td>
            <td class="sep-r-weak">37</td>
            <td>49</td>
            <td class="sep-r-weak">74</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>7</td>
            <td>22</td>
            <td>1</td>
            <td>7</td>
            <td>83</td>
            <td>2</td>
            <td class="sep-r-team">44</td>
            <td>2</td>
            <td>4</td>
            <td>9</td>
            <td class="sep-r-weak">4</td>
            <td>5</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2006"><img src="./images/player/2006.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Cedric Ceballos</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Ceballos</span></a></td>            <td>SF</td>
            <td class="sep-r-team">34</td>
            <td>39</td>
            <td class="sep-r-weak">41</td>
            <td>18</td>
            <td class="sep-r-weak">58</td>
            <td>6</td>
            <td class="sep-r-team">21</td>
            <td>13</td>
            <td>12</td>
            <td>3</td>
            <td>9</td>
            <td>62</td>
            <td>0</td>
            <td class="sep-r-team">55</td>
            <td>5</td>
            <td>8</td>
            <td>8</td>
            <td class="sep-r-weak">8</td>
            <td>8</td>
            <td>8</td>
            <td>4</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3892"><img src="./images/player/3892.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Nando de Colo</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">N. de Colo</span></a></td>            <td>SG</td>
            <td class="sep-r-team">29</td>
            <td>14</td>
            <td class="sep-r-weak">36</td>
            <td>27</td>
            <td class="sep-r-weak">83</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>5</td>
            <td>6</td>
            <td>15</td>
            <td>13</td>
            <td>63</td>
            <td>1</td>
            <td class="sep-r-team">82</td>
            <td>9</td>
            <td>8</td>
            <td>3</td>
            <td class="sep-r-weak">7</td>
            <td>7</td>
            <td>5</td>
            <td>2</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3906"><img src="./images/player/3906.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Gerald Henderson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">G. Henderson</span></a></td>            <td>SG</td>
            <td class="sep-r-team">29</td>
            <td>28</td>
            <td class="sep-r-weak">41</td>
            <td>34</td>
            <td class="sep-r-weak">69</td>
            <td>2</td>
            <td class="sep-r-team">20</td>
            <td>9</td>
            <td>12</td>
            <td>5</td>
            <td>25</td>
            <td>56</td>
            <td>0</td>
            <td class="sep-r-team">52</td>
            <td>7</td>
            <td>6</td>
            <td>5</td>
            <td class="sep-r-weak">8</td>
            <td>6</td>
            <td>4</td>
            <td>3</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4868"><img src="./images/player/4868.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Udoka Azubuike</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">U. Azubuike</span></a></td>            <td>C</td>
            <td class="sep-r-team">25</td>
            <td>11</td>
            <td class="sep-r-weak">47</td>
            <td>15</td>
            <td class="sep-r-weak">40</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>15</td>
            <td>29</td>
            <td>2</td>
            <td>7</td>
            <td>87</td>
            <td>5</td>
            <td class="sep-r-team">83</td>
            <td>5</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-weak">1</td>
            <td>9</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4538"><img src="./images/player/4538.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jordan Mickey</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Mickey</span></a></td>            <td>PF</td>
            <td class="sep-r-team">26</td>
            <td>16</td>
            <td class="sep-r-weak">34</td>
            <td>20</td>
            <td class="sep-r-weak">56</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>15</td>
            <td>27</td>
            <td>4</td>
            <td>9</td>
            <td>83</td>
            <td>9</td>
            <td class="sep-r-team">84</td>
            <td>3</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-weak">1</td>
            <td>8</td>
            <td>3</td>
            <td>8</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5313"><img src="./images/player/5313.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Dahntay Jones</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Jones</span></a></td>            <td>SG</td>
            <td class="sep-r-team">26</td>
            <td>15</td>
            <td class="sep-r-weak">35</td>
            <td>25</td>
            <td class="sep-r-weak">70</td>
            <td>1</td>
            <td class="sep-r-team">33</td>
            <td>7</td>
            <td>13</td>
            <td>7</td>
            <td>16</td>
            <td>69</td>
            <td>0</td>
            <td class="sep-r-team">73</td>
            <td>3</td>
            <td>6</td>
            <td>5</td>
            <td class="sep-r-weak">5</td>
            <td>8</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-team">7</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1251"><img src="./images/player/1251.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jaylen Brown</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Brown</span></a></td>            <td>SF</td>
            <td class="sep-r-team">36</td>
            <td>33</td>
            <td class="sep-r-weak">41</td>
            <td>15</td>
            <td class="sep-r-weak">59</td>
            <td>6</td>
            <td class="sep-r-team">27</td>
            <td>2</td>
            <td>6</td>
            <td>1</td>
            <td>8</td>
            <td>91</td>
            <td>0</td>
            <td class="sep-r-team">72</td>
            <td>5</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">5</td>
            <td>8</td>
            <td>7</td>
            <td>3</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2995"><img src="./images/player/2995.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Seth Curry</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Curry</span></a></td>            <td>SG</td>
            <td class="sep-r-team">33</td>
            <td>43</td>
            <td class="sep-r-weak">42</td>
            <td>15</td>
            <td class="sep-r-weak">70</td>
            <td>15</td>
            <td class="sep-r-team">30</td>
            <td>3</td>
            <td>8</td>
            <td>4</td>
            <td>7</td>
            <td>86</td>
            <td>0</td>
            <td class="sep-r-team">69</td>
            <td>5</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-weak">8</td>
            <td>4</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-team">1</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3907"><img src="./images/player/3907.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Derrick Brown</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Brown</span></a></td>            <td>SG</td>
            <td class="sep-r-team">29</td>
            <td>7</td>
            <td class="sep-r-weak">35</td>
            <td>22</td>
            <td class="sep-r-weak">61</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>12</td>
            <td>11</td>
            <td>2</td>
            <td>21</td>
            <td>88</td>
            <td>0</td>
            <td class="sep-r-team">66</td>
            <td>6</td>
            <td>6</td>
            <td>6</td>
            <td class="sep-r-weak">6</td>
            <td>8</td>
            <td>6</td>
            <td>5</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4888"><img src="./images/player/4888.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">John Amaechi</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Amaechi</span></a></td>            <td>C</td>
            <td class="sep-r-team">27</td>
            <td>20</td>
            <td class="sep-r-weak">34</td>
            <td>48</td>
            <td class="sep-r-weak">73</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>8</td>
            <td>17</td>
            <td>6</td>
            <td>13</td>
            <td>67</td>
            <td>3</td>
            <td class="sep-r-team">52</td>
            <td>4</td>
            <td>6</td>
            <td>7</td>
            <td class="sep-r-weak">5</td>
            <td>5</td>
            <td>4</td>
            <td>8</td>
            <td class="sep-r-team">4</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4195"><img src="./images/player/4195.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Yotam Halperin</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">Y. Halperin</span></a></td>            <td>SG</td>
            <td class="sep-r-team">28</td>
            <td>13</td>
            <td class="sep-r-weak">37</td>
            <td>16</td>
            <td class="sep-r-weak">77</td>
            <td>0</td>
            <td class="sep-r-team">33</td>
            <td>6</td>
            <td>7</td>
            <td>12</td>
            <td>8</td>
            <td>79</td>
            <td>2</td>
            <td class="sep-r-team">69</td>
            <td>7</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-weak">8</td>
            <td>5</td>
            <td>5</td>
            <td>2</td>
            <td class="sep-r-team">9</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4880"><img src="./images/player/4880.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Saddiq Bey</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Bey</span></a></td>            <td>SF</td>
            <td class="sep-r-team">25</td>
            <td>19</td>
            <td class="sep-r-weak">36</td>
            <td>22</td>
            <td class="sep-r-weak">80</td>
            <td>6</td>
            <td class="sep-r-team">21</td>
            <td>5</td>
            <td>25</td>
            <td>6</td>
            <td>17</td>
            <td>88</td>
            <td>1</td>
            <td class="sep-r-team">67</td>
            <td>8</td>
            <td>3</td>
            <td>4</td>
            <td class="sep-r-weak">4</td>
            <td>5</td>
            <td>5</td>
            <td>1</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=936"><img src="./images/player/936.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Robert Jaworski</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Jaworski</span></a></td>            <td>PG</td>
            <td class="sep-r-team">39</td>
            <td>23</td>
            <td class="sep-r-weak">34</td>
            <td>23</td>
            <td class="sep-r-weak">71</td>
            <td>4</td>
            <td class="sep-r-team">20</td>
            <td>9</td>
            <td>10</td>
            <td>28</td>
            <td>18</td>
            <td>82</td>
            <td>0</td>
            <td class="sep-r-team">63</td>
            <td>4</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-weak">5</td>
            <td>5</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4543"><img src="./images/player/4543.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Dimitrios Agravanis</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Agravanis</span></a></td>            <td>PF</td>
            <td class="sep-r-team">26</td>
            <td>7</td>
            <td class="sep-r-weak">28</td>
            <td>34</td>
            <td class="sep-r-weak">73</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>16</td>
            <td>12</td>
            <td>2</td>
            <td>16</td>
            <td>61</td>
            <td>7</td>
            <td class="sep-r-team">74</td>
            <td>2</td>
            <td>3</td>
            <td>5</td>
            <td class="sep-r-weak">3</td>
            <td>6</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4189"><img src="./images/player/4189.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Chris Mills</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Mills</span></a></td>            <td>SF</td>
            <td class="sep-r-team">29</td>
            <td>16</td>
            <td class="sep-r-weak">36</td>
            <td>21</td>
            <td class="sep-r-weak">75</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>8</td>
            <td>15</td>
            <td>6</td>
            <td>16</td>
            <td>84</td>
            <td>2</td>
            <td class="sep-r-team">54</td>
            <td>6</td>
            <td>7</td>
            <td>7</td>
            <td class="sep-r-weak">3</td>
            <td>5</td>
            <td>4</td>
            <td>3</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5697"><img src="./images/player/5697.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Chris Taft</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Taft</span></a></td>            <td>C</td>
            <td class="sep-r-team">22</td>
            <td>11</td>
            <td class="sep-r-weak">48</td>
            <td>8</td>
            <td class="sep-r-weak">53</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>12</td>
            <td>19</td>
            <td>2</td>
            <td>8</td>
            <td>85</td>
            <td>12</td>
            <td class="sep-r-team">73</td>
            <td>1</td>
            <td>5</td>
            <td>7</td>
            <td class="sep-r-weak">4</td>
            <td>4</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-team">1</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4180"><img src="./images/player/4180.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Amaury Pasos</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Pasos</span></a></td>            <td>SG</td>
            <td class="sep-r-team">26</td>
            <td>21</td>
            <td class="sep-r-weak">34</td>
            <td>9</td>
            <td class="sep-r-weak">76</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>11</td>
            <td>12</td>
            <td>9</td>
            <td>24</td>
            <td>79</td>
            <td>1</td>
            <td class="sep-r-team">57</td>
            <td>7</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-weak">5</td>
            <td>6</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2443"><img src="./images/player/2443.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Quentin Richardson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">Q. Richardson</span></a></td>            <td>SG</td>
            <td class="sep-r-team">32</td>
            <td>32</td>
            <td class="sep-r-weak">39</td>
            <td>16</td>
            <td class="sep-r-weak">66</td>
            <td>4</td>
            <td class="sep-r-team">25</td>
            <td>6</td>
            <td>7</td>
            <td>2</td>
            <td>13</td>
            <td>88</td>
            <td>0</td>
            <td class="sep-r-team">66</td>
            <td>8</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-weak">7</td>
            <td>7</td>
            <td>8</td>
            <td>4</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5301"><img src="./images/player/5301.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">James Jones</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Jones</span></a></td>            <td>SF</td>
            <td class="sep-r-team">26</td>
            <td>14</td>
            <td class="sep-r-weak">37</td>
            <td>9</td>
            <td class="sep-r-weak">78</td>
            <td>4</td>
            <td class="sep-r-team">19</td>
            <td>5</td>
            <td>11</td>
            <td>5</td>
            <td>19</td>
            <td>91</td>
            <td>3</td>
            <td class="sep-r-team">71</td>
            <td>9</td>
            <td>2</td>
            <td>4</td>
            <td class="sep-r-weak">7</td>
            <td>7</td>
            <td>5</td>
            <td>2</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3297"><img src="./images/player/3297.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Michael Ansley</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Ansley</span></a></td>            <td>SF</td>
            <td class="sep-r-team">31</td>
            <td>10</td>
            <td class="sep-r-weak">31</td>
            <td>42</td>
            <td class="sep-r-weak">67</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>25</td>
            <td>20</td>
            <td>1</td>
            <td>9</td>
            <td>85</td>
            <td>0</td>
            <td class="sep-r-team">53</td>
            <td>3</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-weak">5</td>
            <td>8</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-team">3</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1765"><img src="./images/player/1765.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Samuel Dalembert</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Dalembert</span></a></td>            <td>C</td>
            <td class="sep-r-team">34</td>
            <td>9</td>
            <td class="sep-r-weak">37</td>
            <td>13</td>
            <td class="sep-r-weak">56</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>17</td>
            <td>22</td>
            <td>1</td>
            <td>9</td>
            <td>83</td>
            <td>28</td>
            <td class="sep-r-team">90</td>
            <td>4</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-weak">1</td>
            <td>4</td>
            <td>3</td>
            <td>6</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1749"><img src="./images/player/1749.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Richard Jefferson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Jefferson</span></a></td>            <td>SF</td>
            <td class="sep-r-team">35</td>
            <td>36</td>
            <td class="sep-r-weak">36</td>
            <td>32</td>
            <td class="sep-r-weak">72</td>
            <td>6</td>
            <td class="sep-r-team">31</td>
            <td>7</td>
            <td>8</td>
            <td>8</td>
            <td>8</td>
            <td>75</td>
            <td>1</td>
            <td class="sep-r-team">80</td>
            <td>7</td>
            <td>8</td>
            <td>4</td>
            <td class="sep-r-weak">7</td>
            <td>5</td>
            <td>8</td>
            <td>5</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4205"><img src="./images/player/4205.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Geert Hammink</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">G. Hammink</span></a></td>            <td>C</td>
            <td class="sep-r-team">30</td>
            <td>8</td>
            <td class="sep-r-weak">26</td>
            <td>73</td>
            <td class="sep-r-weak">61</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>16</td>
            <td>15</td>
            <td>4</td>
            <td>15</td>
            <td>80</td>
            <td>3</td>
            <td class="sep-r-team">94</td>
            <td>2</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-weak">3</td>
            <td>2</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4177"><img src="./images/player/4177.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Scott Burrell</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Burrell</span></a></td>            <td>SF</td>
            <td class="sep-r-team">29</td>
            <td>15</td>
            <td class="sep-r-weak">36</td>
            <td>18</td>
            <td class="sep-r-weak">61</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>10</td>
            <td>21</td>
            <td>6</td>
            <td>28</td>
            <td>83</td>
            <td>2</td>
            <td class="sep-r-team">44</td>
            <td>6</td>
            <td>5</td>
            <td>7</td>
            <td class="sep-r-weak">4</td>
            <td>6</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4519"><img src="./images/player/4519.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Max Strus</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Strus</span></a></td>            <td>SF</td>
            <td class="sep-r-team">28</td>
            <td>23</td>
            <td class="sep-r-weak">44</td>
            <td>9</td>
            <td class="sep-r-weak">68</td>
            <td>14</td>
            <td class="sep-r-team">26</td>
            <td>5</td>
            <td>18</td>
            <td>13</td>
            <td>15</td>
            <td>85</td>
            <td>0</td>
            <td class="sep-r-team">55</td>
            <td>8</td>
            <td>4</td>
            <td>5</td>
            <td class="sep-r-weak">1</td>
            <td>5</td>
            <td>3</td>
            <td>3</td>
            <td class="sep-r-team">4</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4521"><img src="./images/player/4521.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Pat Connaughton</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Connaughton</span></a></td>            <td>SG</td>
            <td class="sep-r-team">28</td>
            <td>17</td>
            <td class="sep-r-weak">38</td>
            <td>6</td>
            <td class="sep-r-weak">78</td>
            <td>6</td>
            <td class="sep-r-team">19</td>
            <td>5</td>
            <td>21</td>
            <td>4</td>
            <td>22</td>
            <td>92</td>
            <td>1</td>
            <td class="sep-r-team">82</td>
            <td>8</td>
            <td>3</td>
            <td>5</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4877"><img src="./images/player/4877.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Josh Green</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Green</span></a></td>            <td>SG</td>
            <td class="sep-r-team">24</td>
            <td>17</td>
            <td class="sep-r-weak">43</td>
            <td>14</td>
            <td class="sep-r-weak">64</td>
            <td>2</td>
            <td class="sep-r-team">25</td>
            <td>7</td>
            <td>11</td>
            <td>6</td>
            <td>16</td>
            <td>83</td>
            <td>0</td>
            <td class="sep-r-team">43</td>
            <td>9</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-weak">4</td>
            <td>5</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">7</td>
            <td>1</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4540"><img src="./images/player/4540.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Stanley Johnson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Johnson</span></a></td>            <td>SF</td>
            <td class="sep-r-team">24</td>
            <td>20</td>
            <td class="sep-r-weak">32</td>
            <td>16</td>
            <td class="sep-r-weak">76</td>
            <td>4</td>
            <td class="sep-r-team">17</td>
            <td>6</td>
            <td>25</td>
            <td>10</td>
            <td>19</td>
            <td>72</td>
            <td>0</td>
            <td class="sep-r-team">36</td>
            <td>7</td>
            <td>4</td>
            <td>3</td>
            <td class="sep-r-weak">1</td>
            <td>9</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-team">6</td>
            <td>1</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4544"><img src="./images/player/4544.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Martynas Pocius</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Pocius</span></a></td>            <td>SG</td>
            <td class="sep-r-team">27</td>
            <td>16</td>
            <td class="sep-r-weak">32</td>
            <td>25</td>
            <td class="sep-r-weak">69</td>
            <td>1</td>
            <td class="sep-r-team">20</td>
            <td>7</td>
            <td>12</td>
            <td>10</td>
            <td>28</td>
            <td>65</td>
            <td>0</td>
            <td class="sep-r-team">34</td>
            <td>6</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>6</td>
            <td>6</td>
            <td class="sep-r-team">5</td>
            <td>1</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5699"><img src="./images/player/5699.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Travis Diener</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Diener</span></a></td>            <td>PG</td>
            <td class="sep-r-team">25</td>
            <td>17</td>
            <td class="sep-r-weak">31</td>
            <td>12</td>
            <td class="sep-r-weak">79</td>
            <td>3</td>
            <td class="sep-r-team">27</td>
            <td>5</td>
            <td>5</td>
            <td>18</td>
            <td>10</td>
            <td>88</td>
            <td>0</td>
            <td class="sep-r-team">40</td>
            <td>7</td>
            <td>6</td>
            <td>1</td>
            <td class="sep-r-weak">5</td>
            <td>7</td>
            <td>7</td>
            <td>1</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2434"><img src="./images/player/2434.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">George Yardley</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">G. Yardley</span></a></td>            <td>SF</td>
            <td class="sep-r-team">36</td>
            <td>13</td>
            <td class="sep-r-weak">30</td>
            <td>42</td>
            <td class="sep-r-weak">70</td>
            <td>1</td>
            <td class="sep-r-team">33</td>
            <td>7</td>
            <td>22</td>
            <td>2</td>
            <td>12</td>
            <td>88</td>
            <td>0</td>
            <td class="sep-r-team">67</td>
            <td>5</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">3</td>
            <td>7</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3897"><img src="./images/player/3897.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Toney Douglas</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Douglas</span></a></td>            <td>SG</td>
            <td class="sep-r-team">30</td>
            <td>34</td>
            <td class="sep-r-weak">40</td>
            <td>12</td>
            <td class="sep-r-weak">72</td>
            <td>5</td>
            <td class="sep-r-team">20</td>
            <td>5</td>
            <td>9</td>
            <td>22</td>
            <td>19</td>
            <td>84</td>
            <td>0</td>
            <td class="sep-r-team">38</td>
            <td>7</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-weak">8</td>
            <td>5</td>
            <td>6</td>
            <td>1</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2432"><img src="./images/player/2432.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Shane Heal</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Heal</span></a></td>            <td>PG</td>
            <td class="sep-r-team">33</td>
            <td>36</td>
            <td class="sep-r-weak">42</td>
            <td>6</td>
            <td class="sep-r-weak">58</td>
            <td>10</td>
            <td class="sep-r-team">25</td>
            <td>3</td>
            <td>5</td>
            <td>8</td>
            <td>7</td>
            <td>78</td>
            <td>0</td>
            <td class="sep-r-team">73</td>
            <td>8</td>
            <td>8</td>
            <td>3</td>
            <td class="sep-r-weak">8</td>
            <td>6</td>
            <td>8</td>
            <td>2</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3888"><img src="./images/player/3888.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">T.R. Dunn</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Dunn</span></a></td>            <td>SG</td>
            <td class="sep-r-team">29</td>
            <td>15</td>
            <td class="sep-r-weak">34</td>
            <td>21</td>
            <td class="sep-r-weak">68</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>14</td>
            <td>11</td>
            <td>4</td>
            <td>26</td>
            <td>71</td>
            <td>1</td>
            <td class="sep-r-team">67</td>
            <td>7</td>
            <td>4</td>
            <td>5</td>
            <td class="sep-r-weak">3</td>
            <td>6</td>
            <td>8</td>
            <td>6</td>
            <td class="sep-r-team">9</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2040"><img src="./images/player/2040.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Antonija Misura</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Misura</span></a></td>            <td>SG</td>
            <td class="sep-r-team">34</td>
            <td>5</td>
            <td class="sep-r-weak">25</td>
            <td>24</td>
            <td class="sep-r-weak">75</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>12</td>
            <td>13</td>
            <td>7</td>
            <td>21</td>
            <td>87</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>4</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5305"><img src="./images/player/5305.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Rick Rickert</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Rickert</span></a></td>            <td>PF</td>
            <td class="sep-r-team">24</td>
            <td>19</td>
            <td class="sep-r-weak">49</td>
            <td>11</td>
            <td class="sep-r-weak">32</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>7</td>
            <td>8</td>
            <td>11</td>
            <td>22</td>
            <td>66</td>
            <td>1</td>
            <td class="sep-r-team">69</td>
            <td>5</td>
            <td>8</td>
            <td>8</td>
            <td class="sep-r-weak">6</td>
            <td>5</td>
            <td>5</td>
            <td>2</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1486"><img src="./images/player/1486.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Brevin Knight</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Knight</span></a></td>            <td>PG</td>
            <td class="sep-r-team">37</td>
            <td>10</td>
            <td class="sep-r-weak">30</td>
            <td>18</td>
            <td class="sep-r-weak">65</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>2</td>
            <td>4</td>
            <td>27</td>
            <td>40</td>
            <td>83</td>
            <td>0</td>
            <td class="sep-r-team">54</td>
            <td>2</td>
            <td>3</td>
            <td>3</td>
            <td class="sep-r-weak">8</td>
            <td>7</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3583"><img src="./images/player/3583.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Derek Fisher</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Fisher</span></a></td>            <td>PG</td>
            <td class="sep-r-team">30</td>
            <td>9</td>
            <td class="sep-r-weak">25</td>
            <td>19</td>
            <td class="sep-r-weak">81</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>2</td>
            <td>8</td>
            <td>15</td>
            <td>26</td>
            <td>82</td>
            <td>0</td>
            <td class="sep-r-team">70</td>
            <td>5</td>
            <td>8</td>
            <td>5</td>
            <td class="sep-r-weak">6</td>
            <td>5</td>
            <td>8</td>
            <td>4</td>
            <td class="sep-r-team">6</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3587"><img src="./images/player/3587.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jim Paxson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Paxson</span></a></td>            <td>SG</td>
            <td class="sep-r-team">30</td>
            <td>10</td>
            <td class="sep-r-weak">34</td>
            <td>19</td>
            <td class="sep-r-weak">69</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>5</td>
            <td>4</td>
            <td>7</td>
            <td>30</td>
            <td>82</td>
            <td>0</td>
            <td class="sep-r-team">57</td>
            <td>5</td>
            <td>9</td>
            <td>4</td>
            <td class="sep-r-weak">7</td>
            <td>8</td>
            <td>9</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>1</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3287"><img src="./images/player/3287.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Haywoode Workman</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">H. Workman</span></a></td>            <td>PG</td>
            <td class="sep-r-team">32</td>
            <td>16</td>
            <td class="sep-r-weak">37</td>
            <td>8</td>
            <td class="sep-r-weak">62</td>
            <td>2</td>
            <td class="sep-r-team">25</td>
            <td>4</td>
            <td>9</td>
            <td>29</td>
            <td>23</td>
            <td>70</td>
            <td>0</td>
            <td class="sep-r-team">58</td>
            <td>5</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-weak">5</td>
            <td>6</td>
            <td>5</td>
            <td>1</td>
            <td class="sep-r-team">6</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2445"><img src="./images/player/2445.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Marko Jaric</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Jaric</span></a></td>            <td>SG</td>
            <td class="sep-r-team">34</td>
            <td>25</td>
            <td class="sep-r-weak">36</td>
            <td>9</td>
            <td class="sep-r-weak">62</td>
            <td>3</td>
            <td class="sep-r-team">29</td>
            <td>2</td>
            <td>7</td>
            <td>20</td>
            <td>25</td>
            <td>77</td>
            <td>0</td>
            <td class="sep-r-team">68</td>
            <td>4</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-weak">7</td>
            <td>9</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3295"><img src="./images/player/3295.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">J.R. Reid</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Reid</span></a></td>            <td>C</td>
            <td class="sep-r-team">30</td>
            <td>13</td>
            <td class="sep-r-weak">40</td>
            <td>21</td>
            <td class="sep-r-weak">67</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>21</td>
            <td>26</td>
            <td>2</td>
            <td>20</td>
            <td>66</td>
            <td>2</td>
            <td class="sep-r-team">37</td>
            <td>3</td>
            <td>4</td>
            <td>9</td>
            <td class="sep-r-weak">2</td>
            <td>8</td>
            <td>3</td>
            <td>8</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5690"><img src="./images/player/5690.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Daniel Ewing</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Ewing</span></a></td>            <td>SG</td>
            <td class="sep-r-team">24</td>
            <td>20</td>
            <td class="sep-r-weak">37</td>
            <td>14</td>
            <td class="sep-r-weak">74</td>
            <td>8</td>
            <td class="sep-r-team">26</td>
            <td>3</td>
            <td>15</td>
            <td>2</td>
            <td>31</td>
            <td>76</td>
            <td>0</td>
            <td class="sep-r-team">82</td>
            <td>6</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-weak">3</td>
            <td>6</td>
            <td>5</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4884"><img src="./images/player/4884.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Kira Lewis Jr</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Lewis Jr</span></a></td>            <td>PG</td>
            <td class="sep-r-team">23</td>
            <td>16</td>
            <td class="sep-r-weak">31</td>
            <td>14</td>
            <td class="sep-r-weak">75</td>
            <td>1</td>
            <td class="sep-r-team">12</td>
            <td>3</td>
            <td>9</td>
            <td>13</td>
            <td>26</td>
            <td>87</td>
            <td>1</td>
            <td class="sep-r-team">67</td>
            <td>6</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-weak">6</td>
            <td>5</td>
            <td>6</td>
            <td>1</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=940"><img src="./images/player/940.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Dejan Bodiroga</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Bodiroga</span></a></td>            <td>SF</td>
            <td class="sep-r-team">38</td>
            <td>16</td>
            <td class="sep-r-weak">35</td>
            <td>12</td>
            <td class="sep-r-weak">73</td>
            <td>4</td>
            <td class="sep-r-team">29</td>
            <td>4</td>
            <td>9</td>
            <td>27</td>
            <td>8</td>
            <td>83</td>
            <td>0</td>
            <td class="sep-r-team">71</td>
            <td>6</td>
            <td>6</td>
            <td>6</td>
            <td class="sep-r-weak">1</td>
            <td>3</td>
            <td>3</td>
            <td>5</td>
            <td class="sep-r-team">4</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5991"><img src="./images/player/5991.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Miles Simon</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Simon</span></a></td>            <td>SG</td>
            <td class="sep-r-team">24</td>
            <td>16</td>
            <td class="sep-r-weak">31</td>
            <td>9</td>
            <td class="sep-r-weak">80</td>
            <td>4</td>
            <td class="sep-r-team">16</td>
            <td>17</td>
            <td>7</td>
            <td>17</td>
            <td>25</td>
            <td>71</td>
            <td>0</td>
            <td class="sep-r-team">86</td>
            <td>3</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>5</td>
            <td>7</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3570"><img src="./images/player/3570.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Malik Rose</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Rose</span></a></td>            <td>PF</td>
            <td class="sep-r-team">30</td>
            <td>11</td>
            <td class="sep-r-weak">29</td>
            <td>33</td>
            <td class="sep-r-weak">54</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>27</td>
            <td>23</td>
            <td>2</td>
            <td>43</td>
            <td>51</td>
            <td>3</td>
            <td class="sep-r-team">62</td>
            <td>3</td>
            <td>3</td>
            <td>5</td>
            <td class="sep-r-weak">3</td>
            <td>5</td>
            <td>7</td>
            <td>9</td>
            <td class="sep-r-team">8</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3592"><img src="./images/player/3592.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Tiffany Hayes</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Hayes</span></a></td>            <td>SG</td>
            <td class="sep-r-team">30</td>
            <td>10</td>
            <td class="sep-r-weak">32</td>
            <td>35</td>
            <td class="sep-r-weak">75</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>5</td>
            <td>9</td>
            <td>5</td>
            <td>19</td>
            <td>87</td>
            <td>0</td>
            <td class="sep-r-team">41</td>
            <td>6</td>
            <td>4</td>
            <td>1</td>
            <td class="sep-r-weak">1</td>
            <td>5</td>
            <td>6</td>
            <td>5</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4542"><img src="./images/player/4542.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Dennis Hopson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Hopson</span></a></td>            <td>SG</td>
            <td class="sep-r-team">27</td>
            <td>28</td>
            <td class="sep-r-weak">33</td>
            <td>32</td>
            <td class="sep-r-weak">77</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>11</td>
            <td>9</td>
            <td>6</td>
            <td>30</td>
            <td>74</td>
            <td>2</td>
            <td class="sep-r-team">44</td>
            <td>4</td>
            <td>8</td>
            <td>6</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2003"><img src="./images/player/2003.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Dan Issel</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Issel</span></a></td>            <td>C</td>
            <td class="sep-r-team">35</td>
            <td>21</td>
            <td class="sep-r-weak">39</td>
            <td>33</td>
            <td class="sep-r-weak">77</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>12</td>
            <td>19</td>
            <td>2</td>
            <td>7</td>
            <td>85</td>
            <td>0</td>
            <td class="sep-r-team">25</td>
            <td>1</td>
            <td>6</td>
            <td>7</td>
            <td class="sep-r-weak">6</td>
            <td>4</td>
            <td>2</td>
            <td>8</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3003"><img src="./images/player/3003.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Popeye Jones</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Jones</span></a></td>            <td>PF</td>
            <td class="sep-r-team">31</td>
            <td>19</td>
            <td class="sep-r-weak">41</td>
            <td>10</td>
            <td class="sep-r-weak">51</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>15</td>
            <td>33</td>
            <td>2</td>
            <td>8</td>
            <td>66</td>
            <td>6</td>
            <td class="sep-r-team">90</td>
            <td>5</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-weak">3</td>
            <td>6</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4204"><img src="./images/player/4204.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Briann January</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. January</span></a></td>            <td>PG</td>
            <td class="sep-r-team">28</td>
            <td>8</td>
            <td class="sep-r-weak">29</td>
            <td>26</td>
            <td class="sep-r-weak">80</td>
            <td>0</td>
            <td class="sep-r-team">50</td>
            <td>3</td>
            <td>5</td>
            <td>11</td>
            <td>24</td>
            <td>65</td>
            <td>0</td>
            <td class="sep-r-team">51</td>
            <td>9</td>
            <td>7</td>
            <td>2</td>
            <td class="sep-r-weak">4</td>
            <td>8</td>
            <td>6</td>
            <td>1</td>
            <td class="sep-r-team">8</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5692"><img src="./images/player/5692.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bracey Wright</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Wright</span></a></td>            <td>SG</td>
            <td class="sep-r-team">23</td>
            <td>22</td>
            <td class="sep-r-weak">37</td>
            <td>21</td>
            <td class="sep-r-weak">30</td>
            <td>7</td>
            <td class="sep-r-team">20</td>
            <td>11</td>
            <td>15</td>
            <td>10</td>
            <td>28</td>
            <td>72</td>
            <td>1</td>
            <td class="sep-r-team">69</td>
            <td>6</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-weak">3</td>
            <td>6</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">7</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4871"><img src="./images/player/4871.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Skylar Mays</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Mays</span></a></td>            <td>SG</td>
            <td class="sep-r-team">27</td>
            <td>18</td>
            <td class="sep-r-weak">34</td>
            <td>8</td>
            <td class="sep-r-weak">73</td>
            <td>3</td>
            <td class="sep-r-team">11</td>
            <td>4</td>
            <td>10</td>
            <td>22</td>
            <td>23</td>
            <td>86</td>
            <td>1</td>
            <td class="sep-r-team">83</td>
            <td>7</td>
            <td>9</td>
            <td>2</td>
            <td class="sep-r-weak">6</td>
            <td>5</td>
            <td>5</td>
            <td>2</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3878"><img src="./images/player/3878.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jerome Whitehead</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Whitehead</span></a></td>            <td>C</td>
            <td class="sep-r-team">29</td>
            <td>14</td>
            <td class="sep-r-weak">39</td>
            <td>23</td>
            <td class="sep-r-weak">69</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>20</td>
            <td>29</td>
            <td>2</td>
            <td>10</td>
            <td>77</td>
            <td>1</td>
            <td class="sep-r-team">16</td>
            <td>7</td>
            <td>4</td>
            <td>8</td>
            <td class="sep-r-weak">1</td>
            <td>4</td>
            <td>3</td>
            <td>6</td>
            <td class="sep-r-team">3</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2720"><img src="./images/player/2720.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Tree Rollins</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Rollins</span></a></td>            <td>C</td>
            <td class="sep-r-team">33</td>
            <td>13</td>
            <td class="sep-r-weak">38</td>
            <td>12</td>
            <td class="sep-r-weak">58</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>12</td>
            <td>16</td>
            <td>2</td>
            <td>7</td>
            <td>89</td>
            <td>19</td>
            <td class="sep-r-team">6</td>
            <td>5</td>
            <td>2</td>
            <td>6</td>
            <td class="sep-r-weak">1</td>
            <td>6</td>
            <td>2</td>
            <td>6</td>
            <td class="sep-r-team">6</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4546"><img src="./images/player/4546.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Juan Pablo Vaulet</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Pablo Vaulet</span></a></td>            <td>SF</td>
            <td class="sep-r-team">27</td>
            <td>9</td>
            <td class="sep-r-weak">32</td>
            <td>27</td>
            <td class="sep-r-weak">58</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>12</td>
            <td>17</td>
            <td>2</td>
            <td>14</td>
            <td>85</td>
            <td>3</td>
            <td class="sep-r-team">43</td>
            <td>3</td>
            <td>1</td>
            <td>5</td>
            <td class="sep-r-weak">1</td>
            <td>7</td>
            <td>2</td>
            <td>6</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3593"><img src="./images/player/3593.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Carlos Cabezas</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Cabezas</span></a></td>            <td>PG</td>
            <td class="sep-r-team">29</td>
            <td>7</td>
            <td class="sep-r-weak">27</td>
            <td>14</td>
            <td class="sep-r-weak">70</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>7</td>
            <td>7</td>
            <td>19</td>
            <td>10</td>
            <td>78</td>
            <td>1</td>
            <td class="sep-r-team">88</td>
            <td>6</td>
            <td>6</td>
            <td>1</td>
            <td class="sep-r-weak">7</td>
            <td>7</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3905"><img src="./images/player/3905.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Austin Daye</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Daye</span></a></td>            <td>SF</td>
            <td class="sep-r-team">28</td>
            <td>9</td>
            <td class="sep-r-weak">32</td>
            <td>18</td>
            <td class="sep-r-weak">64</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>8</td>
            <td>17</td>
            <td>3</td>
            <td>13</td>
            <td>87</td>
            <td>1</td>
            <td class="sep-r-team">46</td>
            <td>7</td>
            <td>3</td>
            <td>5</td>
            <td class="sep-r-weak">2</td>
            <td>8</td>
            <td>6</td>
            <td>6</td>
            <td class="sep-r-team">7</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3604"><img src="./images/player/3604.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Mark Hendrickson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Hendrickson</span></a></td>            <td>PF</td>
            <td class="sep-r-team">30</td>
            <td>5</td>
            <td class="sep-r-weak">22</td>
            <td>30</td>
            <td class="sep-r-weak">71</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>11</td>
            <td>9</td>
            <td>0</td>
            <td>18</td>
            <td>90</td>
            <td>0</td>
            <td class="sep-r-team">71</td>
            <td>4</td>
            <td>2</td>
            <td>6</td>
            <td class="sep-r-weak">1</td>
            <td>7</td>
            <td>7</td>
            <td>5</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5698"><img src="./images/player/5698.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Antoine Wright</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Wright</span></a></td>            <td>SF</td>
            <td class="sep-r-team">23</td>
            <td>18</td>
            <td class="sep-r-weak">34</td>
            <td>20</td>
            <td class="sep-r-weak">61</td>
            <td>3</td>
            <td class="sep-r-team">14</td>
            <td>8</td>
            <td>18</td>
            <td>9</td>
            <td>16</td>
            <td>82</td>
            <td>3</td>
            <td class="sep-r-team">80</td>
            <td>5</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-weak">5</td>
            <td>5</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2709"><img src="./images/player/2709.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Rickey Green II</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Green II</span></a></td>            <td>PG</td>
            <td class="sep-r-team">34</td>
            <td>19</td>
            <td class="sep-r-weak">36</td>
            <td>11</td>
            <td class="sep-r-weak">69</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>3</td>
            <td>4</td>
            <td>25</td>
            <td>25</td>
            <td>84</td>
            <td>0</td>
            <td class="sep-r-team">32</td>
            <td>4</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-weak">7</td>
            <td>6</td>
            <td>8</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1481"><img src="./images/player/1481.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Lenny Wilkens</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">L. Wilkens</span></a></td>            <td>PG</td>
            <td class="sep-r-team">37</td>
            <td>8</td>
            <td class="sep-r-weak">18</td>
            <td>33</td>
            <td class="sep-r-weak">84</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>4</td>
            <td>8</td>
            <td>23</td>
            <td>28</td>
            <td>90</td>
            <td>0</td>
            <td class="sep-r-team">82</td>
            <td>4</td>
            <td>4</td>
            <td>4</td>
            <td class="sep-r-weak">5</td>
            <td>5</td>
            <td>1</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3004"><img src="./images/player/3004.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Allen Leavell</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Leavell</span></a></td>            <td>PG</td>
            <td class="sep-r-team">32</td>
            <td>11</td>
            <td class="sep-r-weak">38</td>
            <td>19</td>
            <td class="sep-r-weak">72</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>4</td>
            <td>4</td>
            <td>24</td>
            <td>29</td>
            <td>67</td>
            <td>0</td>
            <td class="sep-r-team">19</td>
            <td>2</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">1</td>
            <td>8</td>
            <td>1</td>
            <td>9</td>
            <td class="sep-r-team">1</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3883"><img src="./images/player/3883.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Phil Hubbard</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Hubbard</span></a></td>            <td>SF</td>
            <td class="sep-r-team">30</td>
            <td>17</td>
            <td class="sep-r-weak">36</td>
            <td>40</td>
            <td class="sep-r-weak">64</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>19</td>
            <td>16</td>
            <td>4</td>
            <td>17</td>
            <td>63</td>
            <td>0</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">3</td>
            <td>7</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2022"><img src="./images/player/2022.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Gianmarco Pozzeco</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">G. Pozzeco</span></a></td>            <td>PG</td>
            <td class="sep-r-team">35</td>
            <td>20</td>
            <td class="sep-r-weak">40</td>
            <td>10</td>
            <td class="sep-r-weak">74</td>
            <td>2</td>
            <td class="sep-r-team">25</td>
            <td>2</td>
            <td>6</td>
            <td>31</td>
            <td>14</td>
            <td>69</td>
            <td>0</td>
            <td class="sep-r-team">73</td>
            <td>3</td>
            <td>6</td>
            <td>1</td>
            <td class="sep-r-weak">4</td>
            <td>2</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-team">8</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1237"><img src="./images/player/1237.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Brandon Ingram</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Ingram</span></a></td>            <td>SF</td>
            <td class="sep-r-team">35</td>
            <td>31</td>
            <td class="sep-r-weak">37</td>
            <td>35</td>
            <td class="sep-r-weak">74</td>
            <td>3</td>
            <td class="sep-r-team">18</td>
            <td>3</td>
            <td>9</td>
            <td>4</td>
            <td>11</td>
            <td>77</td>
            <td>0</td>
            <td class="sep-r-team">55</td>
            <td>6</td>
            <td>8</td>
            <td>4</td>
            <td class="sep-r-weak">4</td>
            <td>7</td>
            <td>2</td>
            <td>9</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3301"><img src="./images/player/3301.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Frank Johnson II</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">F. Johnson II</span></a></td>            <td>PG</td>
            <td class="sep-r-team">32</td>
            <td>7</td>
            <td class="sep-r-weak">24</td>
            <td>27</td>
            <td class="sep-r-weak">75</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>3</td>
            <td>8</td>
            <td>24</td>
            <td>31</td>
            <td>59</td>
            <td>0</td>
            <td class="sep-r-team">47</td>
            <td>2</td>
            <td>9</td>
            <td>2</td>
            <td class="sep-r-weak">6</td>
            <td>7</td>
            <td>9</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3580"><img src="./images/player/3580.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Samaki Walker</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Walker</span></a></td>            <td>PF</td>
            <td class="sep-r-team">28</td>
            <td>8</td>
            <td class="sep-r-weak">35</td>
            <td>17</td>
            <td class="sep-r-weak">50</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>14</td>
            <td>22</td>
            <td>1</td>
            <td>12</td>
            <td>87</td>
            <td>2</td>
            <td class="sep-r-team">11</td>
            <td>5</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-weak">2</td>
            <td>5</td>
            <td>8</td>
            <td>9</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3903"><img src="./images/player/3903.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jeff Pendergraph</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Pendergraph</span></a></td>            <td>C</td>
            <td class="sep-r-team">29</td>
            <td>11</td>
            <td class="sep-r-weak">32</td>
            <td>12</td>
            <td class="sep-r-weak">76</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>13</td>
            <td>22</td>
            <td>2</td>
            <td>7</td>
            <td>81</td>
            <td>3</td>
            <td class="sep-r-team">79</td>
            <td>7</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-weak">2</td>
            <td>9</td>
            <td>2</td>
            <td>9</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4178"><img src="./images/player/4178.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Lucious Harris</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">L. Harris</span></a></td>            <td>SG</td>
            <td class="sep-r-team">29</td>
            <td>21</td>
            <td class="sep-r-weak">36</td>
            <td>20</td>
            <td class="sep-r-weak">75</td>
            <td>2</td>
            <td class="sep-r-team">25</td>
            <td>7</td>
            <td>10</td>
            <td>6</td>
            <td>16</td>
            <td>86</td>
            <td>0</td>
            <td class="sep-r-team">65</td>
            <td>7</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-weak">7</td>
            <td>6</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4875"><img src="./images/player/4875.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jalen Harris</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Harris</span></a></td>            <td>SG</td>
            <td class="sep-r-team">26</td>
            <td>20</td>
            <td class="sep-r-weak">38</td>
            <td>11</td>
            <td class="sep-r-weak">74</td>
            <td>4</td>
            <td class="sep-r-team">15</td>
            <td>3</td>
            <td>11</td>
            <td>9</td>
            <td>21</td>
            <td>76</td>
            <td>1</td>
            <td class="sep-r-team">77</td>
            <td>7</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-weak">3</td>
            <td>6</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-team">9</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3885"><img src="./images/player/3885.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Danny Green</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Green</span></a></td>            <td>SF</td>
            <td class="sep-r-team">29</td>
            <td>16</td>
            <td class="sep-r-weak">36</td>
            <td>8</td>
            <td class="sep-r-weak">77</td>
            <td>1</td>
            <td class="sep-r-team">20</td>
            <td>3</td>
            <td>12</td>
            <td>4</td>
            <td>22</td>
            <td>86</td>
            <td>3</td>
            <td class="sep-r-team">65</td>
            <td>7</td>
            <td>3</td>
            <td>4</td>
            <td class="sep-r-weak">3</td>
            <td>9</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3001"><img src="./images/player/3001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Georgios Printezis</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">G. Printezis</span></a></td>            <td>PF</td>
            <td class="sep-r-team">31</td>
            <td>26</td>
            <td class="sep-r-weak">41</td>
            <td>18</td>
            <td class="sep-r-weak">67</td>
            <td>3</td>
            <td class="sep-r-team">20</td>
            <td>6</td>
            <td>7</td>
            <td>2</td>
            <td>15</td>
            <td>86</td>
            <td>1</td>
            <td class="sep-r-team">54</td>
            <td>7</td>
            <td>7</td>
            <td>3</td>
            <td class="sep-r-weak">4</td>
            <td>7</td>
            <td>7</td>
            <td>6</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=929"><img src="./images/player/929.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Paul Millsap</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Millsap</span></a></td>            <td>PF</td>
            <td class="sep-r-team">37</td>
            <td>18</td>
            <td class="sep-r-weak">35</td>
            <td>24</td>
            <td class="sep-r-weak">72</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>11</td>
            <td>8</td>
            <td>2</td>
            <td>14</td>
            <td>90</td>
            <td>1</td>
            <td class="sep-r-team">77</td>
            <td>3</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-weak">3</td>
            <td>7</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3899"><img src="./images/player/3899.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jonas Jerebko</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Jerebko</span></a></td>            <td>SF</td>
            <td class="sep-r-team">29</td>
            <td>13</td>
            <td class="sep-r-weak">33</td>
            <td>17</td>
            <td class="sep-r-weak">63</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>17</td>
            <td>12</td>
            <td>1</td>
            <td>17</td>
            <td>88</td>
            <td>0</td>
            <td class="sep-r-team">36</td>
            <td>7</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-weak">4</td>
            <td>6</td>
            <td>7</td>
            <td>5</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3594"><img src="./images/player/3594.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Javonte Green</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Green</span></a></td>            <td>SF</td>
            <td class="sep-r-team">30</td>
            <td>14</td>
            <td class="sep-r-weak">40</td>
            <td>13</td>
            <td class="sep-r-weak">76</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>11</td>
            <td>13</td>
            <td>5</td>
            <td>23</td>
            <td>92</td>
            <td>2</td>
            <td class="sep-r-team">62</td>
            <td>7</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-weak">6</td>
            <td>4</td>
            <td>4</td>
            <td>1</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2715"><img src="./images/player/2715.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Tony Allen</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Allen</span></a></td>            <td>SG</td>
            <td class="sep-r-team">34</td>
            <td>10</td>
            <td class="sep-r-weak">30</td>
            <td>22</td>
            <td class="sep-r-weak">74</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>7</td>
            <td>7</td>
            <td>1</td>
            <td>38</td>
            <td>82</td>
            <td>2</td>
            <td class="sep-r-team">58</td>
            <td>5</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-weak">1</td>
            <td>6</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2010"><img src="./images/player/2010.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Dino Radja</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Radja</span></a></td>            <td>PF</td>
            <td class="sep-r-team">35</td>
            <td>27</td>
            <td class="sep-r-weak">38</td>
            <td>21</td>
            <td class="sep-r-weak">67</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>22</td>
            <td>22</td>
            <td>2</td>
            <td>11</td>
            <td>75</td>
            <td>1</td>
            <td class="sep-r-team">49</td>
            <td>5</td>
            <td>6</td>
            <td>9</td>
            <td class="sep-r-weak">1</td>
            <td>6</td>
            <td>3</td>
            <td>5</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1236"><img src="./images/player/1236.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Dejounte Murray</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Murray</span></a></td>            <td>PG</td>
            <td class="sep-r-team">36</td>
            <td>29</td>
            <td class="sep-r-weak">36</td>
            <td>10</td>
            <td class="sep-r-weak">68</td>
            <td>2</td>
            <td class="sep-r-team">25</td>
            <td>4</td>
            <td>10</td>
            <td>22</td>
            <td>29</td>
            <td>82</td>
            <td>0</td>
            <td class="sep-r-team">66</td>
            <td>8</td>
            <td>1</td>
            <td>2</td>
            <td class="sep-r-weak">2</td>
            <td>4</td>
            <td>3</td>
            <td>3</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2438"><img src="./images/player/2438.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Stromile Swift</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Swift</span></a></td>            <td>PF</td>
            <td class="sep-r-team">33</td>
            <td>16</td>
            <td class="sep-r-weak">37</td>
            <td>22</td>
            <td class="sep-r-weak">70</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>19</td>
            <td>18</td>
            <td>1</td>
            <td>11</td>
            <td>75</td>
            <td>2</td>
            <td class="sep-r-team">32</td>
            <td>3</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-weak">5</td>
            <td>6</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3577"><img src="./images/player/3577.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Antoine Walker</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Walker</span></a></td>            <td>PF</td>
            <td class="sep-r-team">28</td>
            <td>36</td>
            <td class="sep-r-weak">32</td>
            <td>21</td>
            <td class="sep-r-weak">54</td>
            <td>7</td>
            <td class="sep-r-team">20</td>
            <td>21</td>
            <td>21</td>
            <td>13</td>
            <td>22</td>
            <td>51</td>
            <td>2</td>
            <td class="sep-r-team">63</td>
            <td>9</td>
            <td>7</td>
            <td>5</td>
            <td class="sep-r-weak">3</td>
            <td>8</td>
            <td>5</td>
            <td>8</td>
            <td class="sep-r-team">6</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4196"><img src="./images/player/4196.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Fernando San Emeterio</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">F. San Emeterio</span></a></td>            <td>SG</td>
            <td class="sep-r-team">28</td>
            <td>11</td>
            <td class="sep-r-weak">33</td>
            <td>18</td>
            <td class="sep-r-weak">73</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>7</td>
            <td>13</td>
            <td>4</td>
            <td>12</td>
            <td>91</td>
            <td>0</td>
            <td class="sep-r-team">82</td>
            <td>9</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-weak">5</td>
            <td>7</td>
            <td>8</td>
            <td>3</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4532"><img src="./images/player/4532.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Sam Dekker</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Dekker</span></a></td>            <td>PF</td>
            <td class="sep-r-team">26</td>
            <td>14</td>
            <td class="sep-r-weak">39</td>
            <td>10</td>
            <td class="sep-r-weak">41</td>
            <td>3</td>
            <td class="sep-r-team">12</td>
            <td>11</td>
            <td>16</td>
            <td>4</td>
            <td>14</td>
            <td>92</td>
            <td>1</td>
            <td class="sep-r-team">67</td>
            <td>6</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-weak">2</td>
            <td>8</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4199"><img src="./images/player/4199.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Acie Earl</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Earl</span></a></td>            <td>C</td>
            <td class="sep-r-team">29</td>
            <td>14</td>
            <td class="sep-r-weak">27</td>
            <td>40</td>
            <td class="sep-r-weak">67</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>12</td>
            <td>12</td>
            <td>3</td>
            <td>14</td>
            <td>74</td>
            <td>6</td>
            <td class="sep-r-team">62</td>
            <td>6</td>
            <td>5</td>
            <td>8</td>
            <td class="sep-r-weak">5</td>
            <td>6</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-team">4</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4870"><img src="./images/player/4870.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Nico Mannion</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">N. Mannion</span></a></td>            <td>PG</td>
            <td class="sep-r-team">23</td>
            <td>14</td>
            <td class="sep-r-weak">25</td>
            <td>15</td>
            <td class="sep-r-weak">80</td>
            <td>4</td>
            <td class="sep-r-team">27</td>
            <td>3</td>
            <td>14</td>
            <td>20</td>
            <td>21</td>
            <td>74</td>
            <td>0</td>
            <td class="sep-r-team">77</td>
            <td>5</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-weak">5</td>
            <td>6</td>
            <td>7</td>
            <td>2</td>
            <td class="sep-r-team">8</td>
            <td>1</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2999"><img src="./images/player/2999.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">P.J. Brown</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Brown</span></a></td>            <td>C</td>
            <td class="sep-r-team">33</td>
            <td>10</td>
            <td class="sep-r-weak">29</td>
            <td>13</td>
            <td class="sep-r-weak">67</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>17</td>
            <td>15</td>
            <td>3</td>
            <td>13</td>
            <td>82</td>
            <td>7</td>
            <td class="sep-r-team">52</td>
            <td>5</td>
            <td>3</td>
            <td>6</td>
            <td class="sep-r-weak">2</td>
            <td>6</td>
            <td>6</td>
            <td>7</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4173"><img src="./images/player/4173.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Rodney Rogers</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Rogers</span></a></td>            <td>SF</td>
            <td class="sep-r-team">28</td>
            <td>23</td>
            <td class="sep-r-weak">40</td>
            <td>15</td>
            <td class="sep-r-weak">72</td>
            <td>3</td>
            <td class="sep-r-team">12</td>
            <td>12</td>
            <td>12</td>
            <td>4</td>
            <td>18</td>
            <td>80</td>
            <td>1</td>
            <td class="sep-r-team">23</td>
            <td>9</td>
            <td>5</td>
            <td>8</td>
            <td class="sep-r-weak">4</td>
            <td>6</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3886"><img src="./images/player/3886.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Ruthie Bolton</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Bolton</span></a></td>            <td>SG</td>
            <td class="sep-r-team">30</td>
            <td>24</td>
            <td class="sep-r-weak">34</td>
            <td>19</td>
            <td class="sep-r-weak">69</td>
            <td>2</td>
            <td class="sep-r-team">10</td>
            <td>6</td>
            <td>15</td>
            <td>5</td>
            <td>44</td>
            <td>72</td>
            <td>0</td>
            <td class="sep-r-team">45</td>
            <td>7</td>
            <td>4</td>
            <td>5</td>
            <td class="sep-r-weak">1</td>
            <td>7</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-team">6</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4184"><img src="./images/player/4184.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Chris Whitney</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Whitney</span></a></td>            <td>PG</td>
            <td class="sep-r-team">28</td>
            <td>12</td>
            <td class="sep-r-weak">28</td>
            <td>15</td>
            <td class="sep-r-weak">78</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>2</td>
            <td>8</td>
            <td>17</td>
            <td>21</td>
            <td>75</td>
            <td>0</td>
            <td class="sep-r-team">43</td>
            <td>5</td>
            <td>7</td>
            <td>3</td>
            <td class="sep-r-weak">6</td>
            <td>6</td>
            <td>8</td>
            <td>2</td>
            <td class="sep-r-team">9</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4186"><img src="./images/player/4186.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Eric Riley</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">E. Riley</span></a></td>            <td>C</td>
            <td class="sep-r-team">29</td>
            <td>10</td>
            <td class="sep-r-weak">30</td>
            <td>15</td>
            <td class="sep-r-weak">48</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>14</td>
            <td>17</td>
            <td>1</td>
            <td>15</td>
            <td>84</td>
            <td>8</td>
            <td class="sep-r-team">47</td>
            <td>5</td>
            <td>3</td>
            <td>5</td>
            <td class="sep-r-weak">5</td>
            <td>7</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-team">3</td>
            <td>3</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1253"><img src="./images/player/1253.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Pierluigi Marzorati</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Marzorati</span></a></td>            <td>PG</td>
            <td class="sep-r-team">37</td>
            <td>16</td>
            <td class="sep-r-weak">33</td>
            <td>15</td>
            <td class="sep-r-weak">64</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>3</td>
            <td>7</td>
            <td>27</td>
            <td>19</td>
            <td>85</td>
            <td>0</td>
            <td class="sep-r-team">91</td>
            <td>6</td>
            <td>4</td>
            <td>1</td>
            <td class="sep-r-weak">5</td>
            <td>1</td>
            <td>4</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1482"><img src="./images/player/1482.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bill Walton</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Walton</span></a></td>            <td>C</td>
            <td class="sep-r-team">37</td>
            <td>14</td>
            <td class="sep-r-weak">35</td>
            <td>21</td>
            <td class="sep-r-weak">58</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>7</td>
            <td>34</td>
            <td>5</td>
            <td>8</td>
            <td>94</td>
            <td>0</td>
            <td class="sep-r-team">39</td>
            <td>4</td>
            <td>3</td>
            <td>8</td>
            <td class="sep-r-weak">7</td>
            <td>4</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4534"><img src="./images/player/4534.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Frank Kaminsky</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">F. Kaminsky</span></a></td>            <td>C</td>
            <td class="sep-r-team">27</td>
            <td>21</td>
            <td class="sep-r-weak">33</td>
            <td>23</td>
            <td class="sep-r-weak">77</td>
            <td>4</td>
            <td class="sep-r-team">18</td>
            <td>4</td>
            <td>18</td>
            <td>7</td>
            <td>9</td>
            <td>86</td>
            <td>2</td>
            <td class="sep-r-team">71</td>
            <td>7</td>
            <td>5</td>
            <td>7</td>
            <td class="sep-r-weak">2</td>
            <td>4</td>
            <td>4</td>
            <td>5</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5315"><img src="./images/player/5315.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Zarko Cabarkapa</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">Z. Cabarkapa</span></a></td>            <td>PG</td>
            <td class="sep-r-team">25</td>
            <td>16</td>
            <td class="sep-r-weak">34</td>
            <td>10</td>
            <td class="sep-r-weak">77</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>4</td>
            <td>7</td>
            <td>21</td>
            <td>10</td>
            <td>86</td>
            <td>7</td>
            <td class="sep-r-team">53</td>
            <td>7</td>
            <td>7</td>
            <td>3</td>
            <td class="sep-r-weak">4</td>
            <td>5</td>
            <td>3</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4197"><img src="./images/player/4197.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Mike Peplowski</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Peplowski</span></a></td>            <td>C</td>
            <td class="sep-r-team">29</td>
            <td>10</td>
            <td class="sep-r-weak">45</td>
            <td>16</td>
            <td class="sep-r-weak">42</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>13</td>
            <td>19</td>
            <td>2</td>
            <td>12</td>
            <td>82</td>
            <td>3</td>
            <td class="sep-r-team">45</td>
            <td>5</td>
            <td>3</td>
            <td>8</td>
            <td class="sep-r-weak">2</td>
            <td>5</td>
            <td>2</td>
            <td>7</td>
            <td class="sep-r-team">2</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1247"><img src="./images/player/1247.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Sergei Belov</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Belov</span></a></td>            <td>SG</td>
            <td class="sep-r-team">38</td>
            <td>16</td>
            <td class="sep-r-weak">33</td>
            <td>20</td>
            <td class="sep-r-weak">79</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>4</td>
            <td>7</td>
            <td>4</td>
            <td>23</td>
            <td>85</td>
            <td>0</td>
            <td class="sep-r-team">74</td>
            <td>7</td>
            <td>7</td>
            <td>1</td>
            <td class="sep-r-weak">4</td>
            <td>5</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-team">9</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=933"><img src="./images/player/933.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Rajon Rondo</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Rondo</span></a></td>            <td>PG</td>
            <td class="sep-r-team">37</td>
            <td>6</td>
            <td class="sep-r-weak">21</td>
            <td>30</td>
            <td class="sep-r-weak">49</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>3</td>
            <td>10</td>
            <td>20</td>
            <td>27</td>
            <td>80</td>
            <td>0</td>
            <td class="sep-r-team">43</td>
            <td>1</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-weak">7</td>
            <td>5</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3895"><img src="./images/player/3895.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jonny Flynn</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Flynn</span></a></td>            <td>PG</td>
            <td class="sep-r-team">27</td>
            <td>15</td>
            <td class="sep-r-weak">29</td>
            <td>29</td>
            <td class="sep-r-weak">74</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>3</td>
            <td>8</td>
            <td>11</td>
            <td>21</td>
            <td>59</td>
            <td>0</td>
            <td class="sep-r-team">75</td>
            <td>7</td>
            <td>7</td>
            <td>2</td>
            <td class="sep-r-weak">7</td>
            <td>8</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5705"><img src="./images/player/5705.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Julius Hodge</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Hodge</span></a></td>            <td>SG</td>
            <td class="sep-r-team">24</td>
            <td>8</td>
            <td class="sep-r-weak">35</td>
            <td>8</td>
            <td class="sep-r-weak">47</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>7</td>
            <td>13</td>
            <td>6</td>
            <td>8</td>
            <td>84</td>
            <td>1</td>
            <td class="sep-r-team">51</td>
            <td>5</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-weak">5</td>
            <td>6</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5703"><img src="./images/player/5703.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Mickael Gelabale</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Gelabale</span></a></td>            <td>SF</td>
            <td class="sep-r-team">25</td>
            <td>16</td>
            <td class="sep-r-weak">37</td>
            <td>14</td>
            <td class="sep-r-weak">78</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>5</td>
            <td>7</td>
            <td>5</td>
            <td>8</td>
            <td>90</td>
            <td>1</td>
            <td class="sep-r-team">60</td>
            <td>5</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-weak">4</td>
            <td>6</td>
            <td>3</td>
            <td>4</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=289"><img src="./images/player/289.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Kevin Johnson</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Johnson</span></a></td>            <td>PG</td>
            <td class="sep-r-team">42</td>
            <td>11</td>
            <td class="sep-r-weak">26</td>
            <td>43</td>
            <td class="sep-r-weak">88</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>2</td>
            <td>8</td>
            <td>19</td>
            <td>23</td>
            <td>70</td>
            <td>0</td>
            <td class="sep-r-team">58</td>
            <td>7</td>
            <td>3</td>
            <td>4</td>
            <td class="sep-r-weak">3</td>
            <td>3</td>
            <td>3</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2998"><img src="./images/player/2998.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Oliver Miller</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">O. Miller</span></a></td>            <td>C</td>
            <td class="sep-r-team">32</td>
            <td>10</td>
            <td class="sep-r-weak">39</td>
            <td>10</td>
            <td class="sep-r-weak">60</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>15</td>
            <td>16</td>
            <td>4</td>
            <td>10</td>
            <td>72</td>
            <td>8</td>
            <td class="sep-r-team">37</td>
            <td>7</td>
            <td>5</td>
            <td>7</td>
            <td class="sep-r-weak">3</td>
            <td>9</td>
            <td>5</td>
            <td>9</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3576"><img src="./images/player/3576.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Othella Harrington</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">O. Harrington</span></a></td>            <td>PF</td>
            <td class="sep-r-team">31</td>
            <td>20</td>
            <td class="sep-r-weak">40</td>
            <td>27</td>
            <td class="sep-r-weak">66</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>13</td>
            <td>26</td>
            <td>1</td>
            <td>6</td>
            <td>79</td>
            <td>1</td>
            <td class="sep-r-team">39</td>
            <td>1</td>
            <td>5</td>
            <td>8</td>
            <td class="sep-r-weak">4</td>
            <td>3</td>
            <td>3</td>
            <td>6</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2436"><img src="./images/player/2436.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Rebecca Lobo</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Lobo</span></a></td>            <td>C</td>
            <td class="sep-r-team">35</td>
            <td>24</td>
            <td class="sep-r-weak">40</td>
            <td>21</td>
            <td class="sep-r-weak">54</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>10</td>
            <td>10</td>
            <td>2</td>
            <td>11</td>
            <td>74</td>
            <td>3</td>
            <td class="sep-r-team">65</td>
            <td>6</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-weak">5</td>
            <td>7</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2734"><img src="./images/player/2734.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Josh Childress</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Childress</span></a></td>            <td>SF</td>
            <td class="sep-r-team">32</td>
            <td>3</td>
            <td class="sep-r-weak">22</td>
            <td>24</td>
            <td class="sep-r-weak">72</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>8</td>
            <td>9</td>
            <td>2</td>
            <td>10</td>
            <td>88</td>
            <td>0</td>
            <td class="sep-r-team">55</td>
            <td>2</td>
            <td>6</td>
            <td>5</td>
            <td class="sep-r-weak">4</td>
            <td>6</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-team">7</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5314"><img src="./images/player/5314.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Paccelis Morlende</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Morlende</span></a></td>            <td>PG</td>
            <td class="sep-r-team">25</td>
            <td>17</td>
            <td class="sep-r-weak">34</td>
            <td>31</td>
            <td class="sep-r-weak">68</td>
            <td>2</td>
            <td class="sep-r-team">22</td>
            <td>5</td>
            <td>17</td>
            <td>22</td>
            <td>8</td>
            <td>68</td>
            <td>3</td>
            <td class="sep-r-team">50</td>
            <td>5</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>4</td>
            <td>2</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
            <td>1</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3581"><img src="./images/player/3581.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Vitaly Potapenko</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">V. Potapenko</span></a></td>            <td>C</td>
            <td class="sep-r-team">29</td>
            <td>12</td>
            <td class="sep-r-weak">36</td>
            <td>22</td>
            <td class="sep-r-weak">49</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>15</td>
            <td>18</td>
            <td>3</td>
            <td>13</td>
            <td>75</td>
            <td>2</td>
            <td class="sep-r-team">19</td>
            <td>5</td>
            <td>5</td>
            <td>8</td>
            <td class="sep-r-weak">2</td>
            <td>8</td>
            <td>3</td>
            <td>6</td>
            <td class="sep-r-team">4</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3865"><img src="./images/player/3865.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Joe Ingles</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Ingles</span></a></td>            <td>SF</td>
            <td class="sep-r-team">32</td>
            <td>24</td>
            <td class="sep-r-weak">39</td>
            <td>8</td>
            <td class="sep-r-weak">67</td>
            <td>5</td>
            <td class="sep-r-team">30</td>
            <td>3</td>
            <td>15</td>
            <td>24</td>
            <td>14</td>
            <td>76</td>
            <td>0</td>
            <td class="sep-r-team">54</td>
            <td>8</td>
            <td>5</td>
            <td>1</td>
            <td class="sep-r-weak">2</td>
            <td>5</td>
            <td>2</td>
            <td>1</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5701"><img src="./images/player/5701.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Wayne Simien</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">W. Simien</span></a></td>            <td>PF</td>
            <td class="sep-r-team">24</td>
            <td>19</td>
            <td class="sep-r-weak">37</td>
            <td>15</td>
            <td class="sep-r-weak">82</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>28</td>
            <td>13</td>
            <td>3</td>
            <td>27</td>
            <td>66</td>
            <td>0</td>
            <td class="sep-r-team">65</td>
            <td>5</td>
            <td>4</td>
            <td>5</td>
            <td class="sep-r-weak">4</td>
            <td>3</td>
            <td>3</td>
            <td>3</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3890"><img src="./images/player/3890.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">DeMarre Carroll</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">D. Carroll</span></a></td>            <td>SF</td>
            <td class="sep-r-team">30</td>
            <td>11</td>
            <td class="sep-r-weak">36</td>
            <td>13</td>
            <td class="sep-r-weak">66</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>7</td>
            <td>14</td>
            <td>3</td>
            <td>22</td>
            <td>88</td>
            <td>0</td>
            <td class="sep-r-team">52</td>
            <td>7</td>
            <td>2</td>
            <td>6</td>
            <td class="sep-r-weak">2</td>
            <td>4</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-team">6</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4194"><img src="./images/player/4194.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Ryan Arcidiacono</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. Arcidiacono</span></a></td>            <td>PG</td>
            <td class="sep-r-team">29</td>
            <td>9</td>
            <td class="sep-r-weak">34</td>
            <td>12</td>
            <td class="sep-r-weak">73</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>2</td>
            <td>12</td>
            <td>15</td>
            <td>18</td>
            <td>88</td>
            <td>0</td>
            <td class="sep-r-team">50</td>
            <td>7</td>
            <td>7</td>
            <td>4</td>
            <td class="sep-r-weak">7</td>
            <td>5</td>
            <td>4</td>
            <td>2</td>
            <td class="sep-r-team">5</td>
            <td>3</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3306"><img src="./images/player/3306.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Chucky Brown</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Brown</span></a></td>            <td>PF</td>
            <td class="sep-r-team">30</td>
            <td>6</td>
            <td class="sep-r-weak">29</td>
            <td>22</td>
            <td class="sep-r-weak">71</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>11</td>
            <td>10</td>
            <td>1</td>
            <td>9</td>
            <td>87</td>
            <td>0</td>
            <td class="sep-r-team">63</td>
            <td>5</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">5</td>
            <td>5</td>
            <td>3</td>
            <td>8</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3022"><img src="./images/player/3022.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Adam Keefe</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Keefe</span></a></td>            <td>PF</td>
            <td class="sep-r-team">32</td>
            <td>5</td>
            <td class="sep-r-weak">25</td>
            <td>17</td>
            <td class="sep-r-weak">62</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>15</td>
            <td>16</td>
            <td>1</td>
            <td>16</td>
            <td>73</td>
            <td>0</td>
            <td class="sep-r-team">58</td>
            <td>4</td>
            <td>3</td>
            <td>6</td>
            <td class="sep-r-weak">3</td>
            <td>8</td>
            <td>8</td>
            <td>8</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3296"><img src="./images/player/3296.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Kenny Battle</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Battle</span></a></td>            <td>SG</td>
            <td class="sep-r-team">32</td>
            <td>7</td>
            <td class="sep-r-weak">26</td>
            <td>17</td>
            <td class="sep-r-weak">65</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>14</td>
            <td>6</td>
            <td>1</td>
            <td>29</td>
            <td>87</td>
            <td>0</td>
            <td class="sep-r-team">42</td>
            <td>4</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">5</td>
            <td>8</td>
            <td>6</td>
            <td>4</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2724"><img src="./images/player/2724.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Kris Humphries</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Humphries</span></a></td>            <td>PF</td>
            <td class="sep-r-team">31</td>
            <td>7</td>
            <td class="sep-r-weak">37</td>
            <td>19</td>
            <td class="sep-r-weak">59</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>19</td>
            <td>17</td>
            <td>1</td>
            <td>7</td>
            <td>78</td>
            <td>2</td>
            <td class="sep-r-team">29</td>
            <td>3</td>
            <td>5</td>
            <td>8</td>
            <td class="sep-r-weak">1</td>
            <td>5</td>
            <td>4</td>
            <td>9</td>
            <td class="sep-r-team">4</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4191"><img src="./images/player/4191.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Greg Graham</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">G. Graham</span></a></td>            <td>SG</td>
            <td class="sep-r-team">29</td>
            <td>10</td>
            <td class="sep-r-weak">28</td>
            <td>20</td>
            <td class="sep-r-weak">78</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>3</td>
            <td>5</td>
            <td>4</td>
            <td>32</td>
            <td>78</td>
            <td>0</td>
            <td class="sep-r-team">84</td>
            <td>5</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-weak">6</td>
            <td>7</td>
            <td>9</td>
            <td>3</td>
            <td class="sep-r-team">7</td>
            <td>1</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=1233"><img src="./images/player/1233.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Ben Simmons</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Simmons</span></a></td>            <td>PG</td>
            <td class="sep-r-team">36</td>
            <td>4</td>
            <td class="sep-r-weak">25</td>
            <td>24</td>
            <td class="sep-r-weak">37</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>5</td>
            <td>12</td>
            <td>14</td>
            <td>21</td>
            <td>77</td>
            <td>0</td>
            <td class="sep-r-team">51</td>
            <td>2</td>
            <td>5</td>
            <td>4</td>
            <td class="sep-r-weak">5</td>
            <td>4</td>
            <td>8</td>
            <td>7</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5702"><img src="./images/player/5702.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Martynas Andriuskevicius</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">M. Andriuskevicius</span></a></td>            <td>C</td>
            <td class="sep-r-team">24</td>
            <td>12</td>
            <td class="sep-r-weak">31</td>
            <td>9</td>
            <td class="sep-r-weak">66</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>16</td>
            <td>15</td>
            <td>11</td>
            <td>10</td>
            <td>61</td>
            <td>7</td>
            <td class="sep-r-team">68</td>
            <td>4</td>
            <td>4</td>
            <td>6</td>
            <td class="sep-r-weak">2</td>
            <td>6</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2727"><img src="./images/player/2727.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Pavel Podkolzin</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">P. Podkolzin</span></a></td>            <td>C</td>
            <td class="sep-r-team">32</td>
            <td>6</td>
            <td class="sep-r-weak">25</td>
            <td>50</td>
            <td class="sep-r-weak">65</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>16</td>
            <td>21</td>
            <td>2</td>
            <td>8</td>
            <td>71</td>
            <td>0</td>
            <td class="sep-r-team">72</td>
            <td>3</td>
            <td>3</td>
            <td>8</td>
            <td class="sep-r-weak">4</td>
            <td>5</td>
            <td>1</td>
            <td>9</td>
            <td class="sep-r-team">1</td>
            <td>1</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4190"><img src="./images/player/4190.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Lindsey Hunter</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">L. Hunter</span></a></td>            <td>PG</td>
            <td class="sep-r-team">29</td>
            <td>14</td>
            <td class="sep-r-weak">30</td>
            <td>13</td>
            <td class="sep-r-weak">73</td>
            <td>1</td>
            <td class="sep-r-team">33</td>
            <td>2</td>
            <td>8</td>
            <td>9</td>
            <td>25</td>
            <td>78</td>
            <td>5</td>
            <td class="sep-r-team">56</td>
            <td>7</td>
            <td>3</td>
            <td>4</td>
            <td class="sep-r-weak">4</td>
            <td>5</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=3018"><img src="./images/player/3018.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Odyssey Sims</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">O. Sims</span></a></td>            <td>PG</td>
            <td class="sep-r-team">31</td>
            <td>7</td>
            <td class="sep-r-weak">30</td>
            <td>24</td>
            <td class="sep-r-weak">72</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>3</td>
            <td>6</td>
            <td>5</td>
            <td>20</td>
            <td>82</td>
            <td>0</td>
            <td class="sep-r-team">41</td>
            <td>6</td>
            <td>7</td>
            <td>6</td>
            <td class="sep-r-weak">7</td>
            <td>6</td>
            <td>7</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">3</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2733"><img src="./images/player/2733.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Chris Duhon</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Duhon</span></a></td>            <td>PG</td>
            <td class="sep-r-team">33</td>
            <td>5</td>
            <td class="sep-r-weak">27</td>
            <td>7</td>
            <td class="sep-r-weak">56</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>3</td>
            <td>11</td>
            <td>13</td>
            <td>16</td>
            <td>86</td>
            <td>0</td>
            <td class="sep-r-team">78</td>
            <td>6</td>
            <td>8</td>
            <td>2</td>
            <td class="sep-r-weak">5</td>
            <td>7</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-team">6</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2738"><img src="./images/player/2738.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Sasha Vujacic</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">S. Vujacic</span></a></td>            <td>SG</td>
            <td class="sep-r-team">31</td>
            <td>9</td>
            <td class="sep-r-weak">31</td>
            <td>16</td>
            <td class="sep-r-weak">77</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>2</td>
            <td>10</td>
            <td>1</td>
            <td>11</td>
            <td>91</td>
            <td>0</td>
            <td class="sep-r-team">65</td>
            <td>7</td>
            <td>4</td>
            <td>3</td>
            <td class="sep-r-weak">6</td>
            <td>8</td>
            <td>4</td>
            <td>2</td>
            <td class="sep-r-team">9</td>
            <td>1</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4889"><img src="./images/player/4889.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Zeke Nnaji</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">Z. Nnaji</span></a></td>            <td>PF</td>
            <td class="sep-r-team">24</td>
            <td>11</td>
            <td class="sep-r-weak">42</td>
            <td>19</td>
            <td class="sep-r-weak">65</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>15</td>
            <td>12</td>
            <td>1</td>
            <td>10</td>
            <td>88</td>
            <td>4</td>
            <td class="sep-r-team">24</td>
            <td>6</td>
            <td>3</td>
            <td>9</td>
            <td class="sep-r-weak">2</td>
            <td>6</td>
            <td>2</td>
            <td>8</td>
            <td class="sep-r-team">3</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5700"><img src="./images/player/5700.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Rashad McCants</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">R. McCants</span></a></td>            <td>SG</td>
            <td class="sep-r-team">23</td>
            <td>24</td>
            <td class="sep-r-weak">35</td>
            <td>16</td>
            <td class="sep-r-weak">73</td>
            <td>3</td>
            <td class="sep-r-team">25</td>
            <td>5</td>
            <td>7</td>
            <td>6</td>
            <td>10</td>
            <td>79</td>
            <td>2</td>
            <td class="sep-r-team">88</td>
            <td>7</td>
            <td>5</td>
            <td>1</td>
            <td class="sep-r-weak">4</td>
            <td>5</td>
            <td>6</td>
            <td>2</td>
            <td class="sep-r-team">7</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2017"><img src="./images/player/2017.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Elden Campbell</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">E. Campbell</span></a></td>            <td>C</td>
            <td class="sep-r-team">35</td>
            <td>9</td>
            <td class="sep-r-weak">27</td>
            <td>13</td>
            <td class="sep-r-weak">54</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>18</td>
            <td>14</td>
            <td>1</td>
            <td>7</td>
            <td>87</td>
            <td>6</td>
            <td class="sep-r-team">51</td>
            <td>4</td>
            <td>3</td>
            <td>8</td>
            <td class="sep-r-weak">1</td>
            <td>7</td>
            <td>4</td>
            <td>7</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4545"><img src="./images/player/4545.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Jarell Martin</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">J. Martin</span></a></td>            <td>PF</td>
            <td class="sep-r-team">26</td>
            <td>10</td>
            <td class="sep-r-weak">32</td>
            <td>15</td>
            <td class="sep-r-weak">71</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>11</td>
            <td>17</td>
            <td>3</td>
            <td>12</td>
            <td>82</td>
            <td>3</td>
            <td class="sep-r-team">29</td>
            <td>6</td>
            <td>4</td>
            <td>9</td>
            <td class="sep-r-weak">1</td>
            <td>6</td>
            <td>4</td>
            <td>5</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2450"><img src="./images/player/2450.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Keyon Dooling</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">K. Dooling</span></a></td>            <td>PG</td>
            <td class="sep-r-team">32</td>
            <td>4</td>
            <td class="sep-r-weak">22</td>
            <td>22</td>
            <td class="sep-r-weak">80</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>5</td>
            <td>4</td>
            <td>5</td>
            <td>12</td>
            <td>80</td>
            <td>0</td>
            <td class="sep-r-team">32</td>
            <td>7</td>
            <td>5</td>
            <td>5</td>
            <td class="sep-r-weak">8</td>
            <td>6</td>
            <td>7</td>
            <td>3</td>
            <td class="sep-r-team">8</td>
            <td>1</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5992"><img src="./images/player/5992.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Corey Benjamin</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">C. Benjamin</span></a></td>            <td>SG</td>
            <td class="sep-r-team">21</td>
            <td>16</td>
            <td class="sep-r-weak">33</td>
            <td>26</td>
            <td class="sep-r-weak">64</td>
            <td>6</td>
            <td class="sep-r-team">14</td>
            <td>8</td>
            <td>14</td>
            <td>9</td>
            <td>11</td>
            <td>71</td>
            <td>4</td>
            <td class="sep-r-team">73</td>
            <td>2</td>
            <td>5</td>
            <td>3</td>
            <td class="sep-r-weak">6</td>
            <td>4</td>
            <td>6</td>
            <td>3</td>
            <td class="sep-r-team">5</td>
            <td>1</td>
            <td class="sep-r-team">1</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5960"><img src="./images/player/5960.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full player-waived">Jahidi White</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev player-waived">J. White</span></a></td>            <td>C</td>
            <td class="sep-r-team">23</td>
            <td>40</td>
            <td class="sep-r-weak">52</td>
            <td>62</td>
            <td class="sep-r-weak">52</td>
            <td>1</td>
            <td class="sep-r-team">22</td>
            <td>49</td>
            <td>68</td>
            <td>6</td>
            <td>14</td>
            <td>68</td>
            <td>48</td>
            <td class="sep-r-team">66</td>
            <td>3</td>
            <td>3</td>
            <td>6</td>
            <td class="sep-r-weak">2</td>
            <td>6</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-team">1</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=5687"><img src="./images/player/5687.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full player-waived">Sean May</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev player-waived">S. May</span></a></td>            <td>C</td>
            <td class="sep-r-team">24</td>
            <td>59</td>
            <td class="sep-r-weak">51</td>
            <td>25</td>
            <td class="sep-r-weak">77</td>
            <td>0</td>
            <td class="sep-r-team">50</td>
            <td>31</td>
            <td>54</td>
            <td>10</td>
            <td>9</td>
            <td>77</td>
            <td>4</td>
            <td class="sep-r-team">64</td>
            <td>2</td>
            <td>4</td>
            <td>8</td>
            <td class="sep-r-weak">2</td>
            <td>3</td>
            <td>3</td>
            <td>7</td>
            <td class="sep-r-team">3</td>
            <td>2</td>
            <td class="sep-r-team">2</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=4530"><img src="./images/player/4530.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full player-waived">Luka Mitrovic</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev player-waived">L. Mitrovic</span></a></td>            <td>PF</td>
            <td class="sep-r-team">26</td>
            <td>24</td>
            <td class="sep-r-weak">43</td>
            <td>18</td>
            <td class="sep-r-weak">77</td>
            <td>1</td>
            <td class="sep-r-team">0</td>
            <td>19</td>
            <td>31</td>
            <td>17</td>
            <td>18</td>
            <td>82</td>
            <td>3</td>
            <td class="sep-r-team">58</td>
            <td>3</td>
            <td>5</td>
            <td>6</td>
            <td class="sep-r-weak">5</td>
            <td>3</td>
            <td>5</td>
            <td>8</td>
            <td class="sep-r-team">5</td>
            <td>2</td>
            <td class="sep-r-team">1</td>
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

    private function seedCanonicalRoster(): void
    {
        $this->insertTestPlayer(200000001, 'Aaron Anchor', ['teamid' => self::TEAM_ID, 'pos' => 'PG', 'ordinal' => 1]);
        $this->insertTestPlayer(200000002, 'Bobby Baseline', ['teamid' => self::TEAM_ID, 'pos' => 'SG', 'ordinal' => 2]);
    }

    /**
     * @param array<string, int|float|string|null> $extra
     */
    private function seedBanner(int $year, int $bannertype, ?string $bannername = null): void
    {
        $this->insertRow('ibl_banners', [
            'year' => $year,
            'currentname' => self::TEAM_NAME,
            'bannername' => $bannername ?? self::TEAM_NAME,
            'bannertype' => $bannertype,
        ]);
    }

    private function rafters(): string
    {
        return $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null)['rafters'];
    }

    private function franchiseCard(): string
    {
        return $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null)['franchiseHistoryCard'];
    }

    private function currentSeasonCard(): string
    {
        return $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null)['currentSeasonCard'];
    }

    // -------------------------------------------------------------------------
    // Phase 2 — banner-type classification (scope: rafters)
    // -------------------------------------------------------------------------

    public function testRaftersAllThreeBannerCategories(): void
    {
        $this->seedCharTeam();
        $this->seedBanner(2021, 1); // champ
        $this->seedBanner(2022, 2); // conference (Eastern)
        $this->seedBanner(2023, 4); // division (Atlantic)
        self::assertGolden('rafters_all_three', $this->rafters());
    }

    public function testRaftersConferenceType2VsType3Labels(): void
    {
        $this->seedCharTeam();
        $this->seedBanner(2021, 2); // Eastern Conf. Champions
        $this->seedBanner(2022, 3); // Western Conf. Champions
        self::assertGolden('rafters_conf_2v3', $this->rafters());
    }

    public function testRaftersDivisionMatchAllArms(): void
    {
        $this->seedCharTeam();
        $this->seedBanner(2021, 4); // Atlantic
        $this->seedBanner(2022, 5); // Central
        $this->seedBanner(2023, 6); // Midwest
        $this->seedBanner(2024, 7); // Pacific (match default arm)
        self::assertGolden('rafters_div_arms', $this->rafters());
    }

    public function testRaftersBannerNameDiffersFromTeamShowsAsClause(): void
    {
        $this->seedCharTeam();
        // bannername differs from the current team name → "(as ...)" branch.
        $this->seedBanner(2021, 1, 'Old CharTest');
        self::assertGolden('rafters_as_clause', $this->rafters());
    }

    public function testRaftersFivePerRowGrouping(): void
    {
        $this->seedCharTeam();
        // 6 same-type banners → the 6th wraps into a new banners row.
        for ($y = 2019; $y <= 2024; $y++) {
            $this->seedBanner($y, 1);
        }
        self::assertGolden('rafters_five_per_row', $this->rafters());
    }

    public function testRaftersEmptyWhenNoBanners(): void
    {
        $this->seedCharTeam();
        self::assertSame('', $this->rafters());
    }

    public function testRaftersIgnoresUnknownBannerType(): void
    {
        $this->seedCharTeam();
        $this->seedBanner(2021, 1); // valid champ
        $this->seedBanner(2022, 8); // out of range — dropped
        $this->seedBanner(2023, 0); // out of range — dropped
        self::assertGolden('rafters_unknown_dropped', $this->rafters());
    }

    // -------------------------------------------------------------------------
    // Phase 3 — playoff round aggregates (scope: franchiseHistoryCard)
    // Years far in the future (no ambient franchise_seasons) so opponent/team
    // names fall back to the literal series winner/loser.
    // -------------------------------------------------------------------------

    public function testFranchiseCardPlayoffWinAccumulatesGameAndSeriesWins(): void
    {
        $this->seedCharTeam();
        // CharTest wins a first-round series 4-2.
        $this->insertPlayoffSeriesResultRow(2099, 1, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 2);
        self::assertGolden('franchise_playoff_win', $this->franchiseCard());
    }

    public function testFranchiseCardPlayoffLossOrientation(): void
    {
        $this->seedCharTeam();
        // CharTest loses a series 1-4 (opponent is the series winner).
        $this->insertPlayoffSeriesResultRow(2099, 1, 1, self::TEAM_ID, 'Rivals', self::TEAM_NAME, 4, 1);
        self::assertGolden('franchise_playoff_loss', $this->franchiseCard());
    }

    public function testFranchiseCardPlayoffMultipleRoundsAggregate(): void
    {
        $this->seedCharTeam();
        $this->insertPlayoffSeriesResultRow(2099, 1, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 1);
        $this->insertPlayoffSeriesResultRow(2099, 2, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 2);
        $this->insertPlayoffSeriesResultRow(2099, 3, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 3);
        $this->insertPlayoffSeriesResultRow(2099, 4, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 0);
        self::assertGolden('franchise_playoff_multiround', $this->franchiseCard());
    }

    public function testFranchiseCardNoPlayoffHistoryStillEmitsZeroFooter(): void
    {
        $this->seedCharTeam();
        self::assertGolden('franchise_no_playoff', $this->franchiseCard());
    }

    public function testFranchiseCardIgnoresOutOfRangeRound(): void
    {
        $this->seedCharTeam();
        $this->insertPlayoffSeriesResultRow(2099, 1, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 2);
        $this->insertPlayoffSeriesResultRow(2099, 5, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 0); // round 5 dropped
        self::assertGolden('franchise_round5_dropped', $this->franchiseCard());
    }

    // -------------------------------------------------------------------------
    // Phase 4 — regular-season & HEAT win/loss history (scope: franchiseHistoryCard)
    // -------------------------------------------------------------------------

    public function testFranchiseCardRegularSeasonHistoryLabelsAndTotals(): void
    {
        $this->seedCharTeam();
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2024, 1, self::TEAM_NAME, self::TEAM_NAME, 55, 27);
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2023, 1, self::TEAM_NAME, self::TEAM_NAME, 40, 42);
        self::assertGolden('franchise_regular_history', $this->franchiseCard());
    }

    public function testFranchiseCardHeatHistoryLabelFormat(): void
    {
        $this->seedCharTeam();
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2024, 3, self::TEAM_NAME, self::TEAM_NAME, 6, 2);
        self::assertGolden('franchise_heat_history', $this->franchiseCard());
    }

    public function testFranchiseCardBestRecordBolding(): void
    {
        $this->seedCharTeam();
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2024, 1, self::TEAM_NAME, self::TEAM_NAME, 60, 22); // strictly higher pct
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2023, 1, self::TEAM_NAME, self::TEAM_NAME, 41, 41);
        self::assertGolden('franchise_best_bold', $this->franchiseCard());
    }

    public function testFranchiseCardBestRecordPctTieBreaksOnWins(): void
    {
        $this->seedCharTeam();
        // Equal pct (.500) but different absolute wins → higher-wins row bolded.
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2024, 1, self::TEAM_NAME, self::TEAM_NAME, 50, 50);
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2023, 1, self::TEAM_NAME, self::TEAM_NAME, 41, 41);
        self::assertGolden('franchise_best_tiebreak', $this->franchiseCard());
    }

    public function testFranchiseCardEmptyHistoryShowsZeroTotals(): void
    {
        $this->seedCharTeam();
        self::assertGolden('franchise_empty_history', $this->franchiseCard());
    }

    // -------------------------------------------------------------------------
    // Phase 5 — current-season card incl. null-power and FKA (scope: currentSeasonCard)
    // -------------------------------------------------------------------------

    public function testCurrentSeasonCardPopulated(): void
    {
        $this->seedCharTeam();
        self::assertGolden('current_populated', $this->currentSeasonCard());
    }

    public function testCurrentSeasonCardFormerlyKnownAs(): void
    {
        $this->seedCharTeam();
        // Prior era (different city/name) then the current era (matches current
        // city/name → skipped by buildFormerlyKnownAs).
        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id' => self::TEAM_ID,
            'season_year' => 2020,
            'season_ending_year' => 2021,
            'team_city' => 'Old City',
            'team_name' => 'OldName',
        ]);
        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id' => self::TEAM_ID,
            'season_year' => 2025,
            'season_ending_year' => 2026,
            'team_city' => self::TEAM_CITY,
            'team_name' => self::TEAM_NAME,
        ]);
        self::assertGolden('current_fka', $this->currentSeasonCard());
    }

    public function testCurrentSeasonCardEmptyWhenNoPowerRow(): void
    {
        // team_info only — no standings/power → prepareCurrentSeasonData()
        // returns null, but renderCurrentSeasonCard('') still wraps empty chrome.
        $this->insertRow('ibl_team_info', [
            'teamid' => self::TEAM_ID,
            'team_city' => self::TEAM_CITY,
            'team_name' => self::TEAM_NAME,
            'color1' => '102030',
            'color2' => 'A0B0C0',
            'arena' => 'Test Arena',
            'capacity' => 18000,
            'owner_name' => 'Test Owner',
            'owner_email' => 'owner@test.local',
            'gm_username' => 'char_gm',
        ]);
        self::assertGolden('current_no_power', $this->currentSeasonCard());
    }

    public function testFormerlyKnownAsNullWhenOnlyCurrentEra(): void
    {
        $this->seedCharTeam();
        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id' => self::TEAM_ID,
            'season_year' => 2025,
            'season_ending_year' => 2026,
            'team_city' => self::TEAM_CITY,
            'team_name' => self::TEAM_NAME,
        ]);
        self::assertGolden('current_no_fka', $this->currentSeasonCard());
    }

    // -------------------------------------------------------------------------
    // Phase 6 — $display / $split variants (scope: tableOutput) + $yr + isOwnTeam
    // -------------------------------------------------------------------------

    private function tableOutputFor(?string $yr, string $display, ?string $split = null): string
    {
        return $this->service->getTeamPageData(self::TEAM_ID, $yr, $display, '', $split)['tableOutput'];
    }

    public function testTableOutputRatings(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_ratings', $this->tableOutputFor(null, 'ratings'));
    }

    public function testTableOutputSeasonTotals(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_total_s', $this->tableOutputFor(null, 'total_s'));
    }

    public function testTableOutputSeasonAverages(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_avg_s', $this->tableOutputFor(null, 'avg_s'));
    }

    public function testTableOutputPer36(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_per36', $this->tableOutputFor(null, 'per36mins'));
    }

    public function testTableOutputChunk(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_chunk', $this->tableOutputFor(null, 'chunk'));
    }

    public function testTableOutputContracts(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_contracts', $this->tableOutputFor(null, 'contracts'));
    }

    public function testTableOutputSplitHome(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_split_home', $this->tableOutputFor(null, 'split', 'home'));
    }

    public function testTableOutputSplitWins(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_split_wins', $this->tableOutputFor(null, 'split', 'wins'));
    }

    public function testHistoricalYearSetsInsertyearAndHistoricalRoster(): void
    {
        $this->seedCharTeam();
        $this->insertHistRow(200000001, 'Aaron Anchor', 2024, ['teamid' => self::TEAM_ID, 'team' => self::TEAM_NAME]);

        $result = $this->service->getTeamPageData(self::TEAM_ID, '2024', 'ratings', '', null);

        self::assertSame('&yr=2024', $result['insertyear']);
        self::assertSame('2024', $result['yr']);
        self::assertGolden('table_historical', $result['tableOutput']);
    }

    public function testIsOwnTeamTrueWhenUserTeamMatches(): void
    {
        $this->seedCharTeam();
        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', self::TEAM_NAME, null);
        self::assertTrue($result['isOwnTeam']);
        self::assertSame(self::TEAM_NAME, $result['userTeamName']);
    }

    public function testIsOwnTeamFalseWhenUserTeamDiffers(): void
    {
        $this->seedCharTeam();
        $other = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', 'Some Other Team', null);
        self::assertFalse($other['isOwnTeam']);

        $empty = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);
        self::assertFalse($empty['isOwnTeam']);
    }

    public function testTableOutputUnknownDisplayFallsBackToRatings(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        // The switch default renders the Ratings *table body* for an unknown
        // display key, but the view-dropdown reflects the literal requested
        // value (so no <option> is marked selected) — the full blob therefore
        // differs from the ratings golden. Freeze the real fallback output.
        self::assertGolden('table_bogus', $this->tableOutputFor(null, 'bogus'));
    }

    public function testTableOutputSplitDefaultsToHomeWhenSplitNull(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        // renderSplitStats uses $split ?? 'home' for the table body, but the
        // dropdown active value is 'split:' (empty) so no option is selected —
        // the full blob differs from split=home. Freeze the real output.
        self::assertGolden('table_split_null', $this->tableOutputFor(null, 'split', null));
    }

    // -------------------------------------------------------------------------
    // Phase 7 — awards card + draft picks (scope: awardsCard, draftPicksTable)
    // -------------------------------------------------------------------------

    public function testAwardsCardWithGmTenureAndTeamAward(): void
    {
        $this->seedCharTeam();
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id' => self::TEAM_ID,
            'gm_display_name' => 'Test Owner',
            'start_season_year' => 2020,
            'end_season_year' => 2026,
        ]);
        $this->insertTeamAwardRow(self::TEAM_NAME, 'Atlantic Division Champions', 2024);

        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);
        self::assertGolden('awards_gm_and_team', $result['awardsCard']);
    }

    public function testAwardsCardEmptyAccomplishments(): void
    {
        // GM tenure but zero team awards and zero playoff rows → the
        // accomplishments section renders its empty state (not blank/fatal).
        $this->seedCharTeam();
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id' => self::TEAM_ID,
            'gm_display_name' => 'Test Owner',
            'start_season_year' => 2020,
            'end_season_year' => 2026,
        ]);

        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);
        self::assertGolden('awards_empty_accomplishments', $result['awardsCard']);
    }

    public function testDraftPicksTablePopulated(): void
    {
        $this->seedCharTeam();
        $this->insertDraftPickRow(self::TEAM_ID, 1, 2027, 1);

        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);
        self::assertGolden('draft_populated', $result['draftPicksTable']);
    }

    public function testDraftPicksTableEmptyContainerWhenNoPicks(): void
    {
        $this->seedCharTeam();
        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);
        self::assertGolden('draft_empty', $result['draftPicksTable']);
    }
}
