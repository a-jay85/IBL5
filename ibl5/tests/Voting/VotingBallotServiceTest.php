<?php

declare(strict_types=1);

namespace Tests\Voting;

use PHPUnit\Framework\TestCase;
use Voting\VotingBallotService;
use Voting\Contracts\VotingBallotServiceInterface;

class VotingBallotServiceTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $db = $this->createStub(\mysqli::class);
        $service = new VotingBallotService($db);

        $this->assertInstanceOf(VotingBallotServiceInterface::class, $service);
    }

    public function testGetBallotDataReturnsASGCategoriesForRegularSeason(): void
    {
        $db = $this->createStub(\mysqli::class);

        $season = $this->createStub(\Season::class);
        $season->phase = 'Regular Season';

        $league = $this->createStub(\League::class);
        $league->method('getAllStarCandidatesResult')->willReturn([]);

        $service = new VotingBallotService($db);
        $result = $service->getBallotData('Test Team', $season, $league);

        $this->assertCount(4, $result);
        $this->assertSame('ECF', $result[0]['code']);
        $this->assertSame('ECB', $result[1]['code']);
        $this->assertSame('WCF', $result[2]['code']);
        $this->assertSame('WCB', $result[3]['code']);
    }

    public function testGetBallotDataReturnsEOYCategoriesForPlayoffs(): void
    {
        $db = $this->createStub(\mysqli::class);

        $season = $this->createStub(\Season::class);
        $season->phase = 'Playoffs';

        $league = $this->createStub(\League::class);
        $league->method('getMVPCandidatesResult')->willReturn([]);
        $league->method('getSixthPersonOfTheYearCandidatesResult')->willReturn([]);
        $league->method('getRookieOfTheYearCandidatesResult')->willReturn([]);
        $league->method('getGMOfTheYearCandidatesResult')->willReturn([]);

        $service = new VotingBallotService($db);
        $result = $service->getBallotData('Test Team', $season, $league);

        $this->assertCount(4, $result);
        $this->assertSame('MVP', $result[0]['code']);
        $this->assertSame('Six', $result[1]['code']);
        $this->assertSame('ROY', $result[2]['code']);
        $this->assertSame('GM', $result[3]['code']);
    }

    public function testASGCategoryTitlesAreCorrect(): void
    {
        $db = $this->createStub(\mysqli::class);

        $season = $this->createStub(\Season::class);
        $season->phase = 'Regular Season';

        $league = $this->createStub(\League::class);
        $league->method('getAllStarCandidatesResult')->willReturn([]);

        $service = new VotingBallotService($db);
        $result = $service->getBallotData('Test Team', $season, $league);

        $this->assertSame('Eastern Conference Frontcourt', $result[0]['title']);
        $this->assertSame('Eastern Conference Backcourt', $result[1]['title']);
        $this->assertSame('Western Conference Frontcourt', $result[2]['title']);
        $this->assertSame('Western Conference Backcourt', $result[3]['title']);
    }

    public function testEOYCategoryTitlesAreCorrect(): void
    {
        $db = $this->createStub(\mysqli::class);

        $season = $this->createStub(\Season::class);
        $season->phase = 'Draft';

        $league = $this->createStub(\League::class);
        $league->method('getMVPCandidatesResult')->willReturn([]);
        $league->method('getSixthPersonOfTheYearCandidatesResult')->willReturn([]);
        $league->method('getRookieOfTheYearCandidatesResult')->willReturn([]);
        $league->method('getGMOfTheYearCandidatesResult')->willReturn([]);

        $service = new VotingBallotService($db);
        $result = $service->getBallotData('Test Team', $season, $league);

        $this->assertSame('Most Valuable Player', $result[0]['title']);
        $this->assertSame('Sixth-Person of the Year', $result[1]['title']);
        $this->assertSame('Rookie of the Year', $result[2]['title']);
        $this->assertSame('General Manager of the Year', $result[3]['title']);
    }

    public function testGMCandidatesAreBuiltCorrectly(): void
    {
        $db = $this->createStub(\mysqli::class);

        $season = $this->createStub(\Season::class);
        $season->phase = 'Playoffs';

        $league = $this->createStub(\League::class);
        $league->method('getMVPCandidatesResult')->willReturn([]);
        $league->method('getSixthPersonOfTheYearCandidatesResult')->willReturn([]);
        $league->method('getRookieOfTheYearCandidatesResult')->willReturn([]);
        $league->method('getGMOfTheYearCandidatesResult')->willReturn([
            ['owner_name' => 'John Doe', 'team_city' => 'New York', 'team_name' => 'Knicks'],
        ]);

        $service = new VotingBallotService($db);
        $result = $service->getBallotData('Test Team', $season, $league);

        $gmCategory = $result[3];
        $this->assertCount(1, $gmCategory['candidates']);
        $this->assertSame('gm', $gmCategory['candidates'][0]['type']);
        $this->assertSame('John Doe', $gmCategory['candidates'][0]['name']);
        $this->assertSame('New York Knicks', $gmCategory['candidates'][0]['teamName']);
    }
}
