<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use SimRecap\RecapBox;
use SimRecap\SimSummaryRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * The copy-paste box and the txt download both read through RecapBox. Sim 725
 * (2026-08-02) stored 49 games with a bad `game_of_that_day`, and a box-score filter
 * on the read showed 7. These tests seed that shape and check that every game lands
 * in the box with the outro last.
 */
#[Group('database')]
final class RecapBoxDbTest extends DatabaseTestCase
{
    private const SIM  = 999725;
    private const DAYS = ['2025-02-14', '2025-02-15', '2025-02-16', '2025-02-17', '2025-02-18', '2025-02-19', '2025-02-20'];

    private SimSummaryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SimSummaryRepository($this->db);
    }

    public function testBoxHoldsEveryStoredGameEvenWhenMostMatchNoBoxScore(): void
    {
        $this->seedSim725Shape();

        self::assertCount(1, $this->repo->findDisplayableGameRecaps(self::SIM), 'precondition: the box-score filter keeps only one game');
        self::assertCount(48, $this->repo->findOrphanedGameRecaps(self::SIM), 'precondition: the other 48 are orphans');

        self::assertCount(49, RecapBox::gameRecaps($this->repo, self::SIM));

        $summary = $this->repo->find(self::SIM);
        self::assertNotNull($summary);
        $text = RecapBox::text($this->repo, self::SIM, $summary);

        self::assertSame(49, preg_match_all('/^\*\*Team \d+ \d+ @ Team \d+ \d+\*\*$/m', $text));
        self::assertStringStartsWith('Opening intro.', $text);
        self::assertStringEndsWith("\n\n\nWHERE THINGS STAND closing story.\n", $text);
        self::assertStringNotContainsString('Teaser only.', $text);
    }

    public function testBoxKeepsSortOrder(): void
    {
        $this->seedSim725Shape();

        $sortOrders = array_column(RecapBox::gameRecaps($this->repo, self::SIM), 'sort_order');

        self::assertSame(range(0, 48), $sortOrders);
    }

    public function testLegacyRowWithNoPartsShowsTheStoredTeaser(): void
    {
        $this->repo->markDone(self::SIM, '', '', 'Legacy teaser only.', [], null);

        $summary = $this->repo->find(self::SIM);
        self::assertNotNull($summary);

        self::assertSame('Legacy teaser only.', RecapBox::text($this->repo, self::SIM, $summary));
    }

    /**
     * 49 games: seven matchups on each of seven dates, every one stored at
     * game_of_that_day 1. Only one box score exists, for the first matchup on the
     * first date, so 48 rows match nothing.
     */
    private function seedSim725Shape(): void
    {
        $games = [];
        $sort  = 0;
        foreach (self::DAYS as $date) {
            for ($m = 0; $m < 7; $m++) {
                $visitor = 2 * $m + 1;
                $home    = 2 * $m + 2;
                $games[] = [
                    'season_year'      => 2025,
                    'game_date'        => $date,
                    'visitor_teamid'   => $visitor,
                    'home_teamid'      => $home,
                    'game_of_that_day' => 1,
                    'box_id'           => null,
                    'sort_order'       => $sort,
                    'recap_text'       => "**Team {$visitor} {$sort} @ Team {$home} {$sort}**\n<@1> · <@2>\nGame {$sort} prose.",
                ];
                $sort++;
            }
        }
        $this->repo->markDone(self::SIM, 'Opening intro.', 'WHERE THINGS STAND closing story.', 'Teaser only.', $games, null);

        $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)"
            . " VALUES ('2025-02-14', 1, 2, 1, 'Only match')"
        );
    }
}
