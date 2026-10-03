<?php

declare(strict_types=1);

namespace Tests\SeasonLeaderboards;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SeasonLeaderboards\SeasonLeaderboardsRepository;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * SeasonLeaderboardsRepositoryTest - Tests for SeasonLeaderboardsRepository database operations
 */
class SeasonLeaderboardsRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }



    /**
     * Literal copy of the sort-option to SQL-expression map (independent oracle).
     *
     * @return array<string, array{string, string}>
     */
    public static function sortOptionExpressionProvider(): array
    {
        return [
            'PPG' => ['PPG', '((2*`fgm`+`ftm`+`tgm`)/`games`)'],
            'REB' => ['REB', '((`reb`)/`games`)'],
            'OREB' => ['OREB', '((`orb`)/`games`)'],
            'DREB' => ['DREB', '((`reb`-`orb`)/`games`)'],
            'AST' => ['AST', '((`ast`)/`games`)'],
            'STL' => ['STL', '((`stl`)/`games`)'],
            'BLK' => ['BLK', '((`blk`)/`games`)'],
            'TO' => ['TO', '((`tvr`)/`games`)'],
            'FOUL' => ['FOUL', '((`pf`)/`games`)'],
            'QA' => ['QA', '((((2*fgm+ftm+tgm)+reb+(2*ast)+(2*stl)+(2*blk))-((fga-fgm)+(fta-ftm)+tvr+pf))/games)'],
            'FGM' => ['FGM', '((`fgm`)/`games`)'],
            'FGA' => ['FGA', '((`fga`)/`games`)'],
            'FGP' => ['FGP', '(fgm/fga)'],
            'FTM' => ['FTM', '((`ftm`)/`games`)'],
            'FTA' => ['FTA', '((`fta`)/`games`)'],
            'FTP' => ['FTP', '(ftm/fta)'],
            'TGM' => ['TGM', '((`tgm`)/`games`)'],
            'TGA' => ['TGA', '((`tga`)/`games`)'],
            'TGP' => ['TGP', '(tgm/tga)'],
            'GAMES' => ['GAMES', '(games)'],
            'MIN' => ['MIN', '((`minutes`)/`games`)'],
        ];
    }

    /**
     * Raw prepared SQL text (backticks intact) for getSeasonLeaders().
     */
    private function preparedLeadersSql(string $sortBy): string
    {
        $repository = new SeasonLeaderboardsRepository($this->mockDb);

        $repository->getSeasonLeaders(['sortby' => $sortBy]);

        $prepared = $this->mockDb->getPreparedQueries();
        $this->assertCount(1, $prepared);

        return $prepared[0];
    }

    #[DataProvider('sortOptionExpressionProvider')]
    public function testOrderByUsesMappedExpressionForEachSortOption(string $sortBy, string $expression): void
    {
        $sql = $this->preparedLeadersSql($sortBy);

        $this->assertStringContainsString('ORDER BY ' . $expression . ' DESC, h.pid ASC', $sql);
    }

    public function testUnknownSortOptionFallsBackToPpgExpression(): void
    {
        $sql = $this->preparedLeadersSql('nonsense');

        $this->assertStringContainsString('ORDER BY ((2*`fgm`+`ftm`+`tgm`)/`games`) DESC, h.pid ASC', $sql);
        $this->assertStringNotContainsString('nonsense', $sql);
    }

    public function testMultipleRepositoriesCanBeInstantiated(): void
    {
        $repo1 = new SeasonLeaderboardsRepository($this->mockDb);
        $repo2 = new SeasonLeaderboardsRepository($this->mockDb);

        $this->assertNotSame($repo1, $repo2);
    }
}
