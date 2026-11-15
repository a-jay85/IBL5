<?php

declare(strict_types=1);

namespace Tests\Unit\SimRecap;

use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Contracts\SeasonQueryRepositoryInterface;
use SimRecap\MentionMapBuilder;
use SimRecap\PublishScriptBuilder;
use SimRecap\PublishScriptUnavailable;
use SimRecap\SimRecapContextRepository;
use SimRecap\SimSummaryRepository;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @phpstan-import-type AssembleInput from PublishScriptBuilder
 */
final class PublishScriptBuilderTest extends TestCase
{
    private const GM_PORTIS = '123456789012345678';
    private const GM_OTHER = '222222222222222222';
    private const GM_GAME_ONE = '111111111111111111';

    /**
     * Two game days, three games, injuries on day 1 only, non-blank intro and outro.
     * The same input produced ibl5/IBLbot/src/sim-recap/fixtures/two-day-script.json.
     *
     * @return AssembleInput
     */
    public static function fixtureInput(): array
    {
        return [
            'sim' => 12,
            'title' => '2008-09, SIM #10',
            'intro' => 'Welcome to the sim.',
            'outro' => 'Standings are in.',
            'games' => [
                ['game_date' => '2009-01-04', 'sort_order' => 2, 'recap_text' => 'Stars beat Rockets 98-90.'],
                ['game_date' => '2009-01-05', 'sort_order' => 3, 'recap_text' => 'Heat edge Jazz 88-87, thanks to <@' . self::GM_OTHER . '>.'],
                ['game_date' => '2009-01-04', 'sort_order' => 1, 'recap_text' => 'Metros top Bulls 101-99, led by <@' . self::GM_GAME_ONE . '>.'],
            ],
            'injuries' => [
                [
                    'name' => 'Bobby Portis',
                    'date' => '2009-01-04',
                    'injuryDescription' => 'left shoulder strain',
                    'injuryGamesMissed' => 4,
                    'teamName' => 'Timberwolves',
                    'teamAbbrev' => 'MIN',
                ],
                [
                    'name' => 'Nene',
                    'date' => '2009-01-04',
                    'injuryDescription' => 'sprained ankle',
                    'injuryGamesMissed' => 2,
                    'teamName' => 'Nuggets',
                    'teamAbbrev' => 'DEN',
                ],
            ],
            'gmByTeamName' => ['Timberwolves' => self::GM_PORTIS],
        ];
    }

    public function testStepOrderForTwoDaySim(): void
    {
        $script = PublishScriptBuilder::assemble(self::fixtureInput());

        self::assertSame(12, $script['sim']);
        self::assertSame('2008-09, SIM #10', $script['title']);

        $expected = [
            ['thread_start', 'live_sims', '**2008-09, SIM #10**'],
            ['thread_message', 'thread', '<@&793564717402554368> Let\'s do the darned thing!'],
            ['thread_message', 'thread', 'Welcome to the sim.'],
            ['thread_message', 'thread', '**DAY 1**'],
            ['thread_message', 'thread', 'Metros top Bulls 101-99, led by <@' . self::GM_GAME_ONE . '>.'],
            ['thread_message', 'thread', 'Stars beat Rockets 98-90.'],
            [
                'injuries',
                'injuries',
                "**1/4/09**\n-B.Portis, MIN - left shoulder strain (4) <@" . self::GM_PORTIS . ">\n-Nene, DEN - sprained ankle (2)",
            ],
            ['thread_message', 'thread', '**DAY 2**'],
            ['thread_message', 'thread', 'Heat edge Jazz 88-87, thanks to <@' . self::GM_OTHER . '>.'],
            [
                'thread_message',
                'thread',
                str_repeat('=', 80) . "\n\n**WHERE THINGS STAND**\n\nStandings are in.",
            ],
            ['gif', 'thread', ''],
        ];

        $actual = array_map(
            static fn (array $step): array => [$step['kind'], $step['channel'], $step['content']],
            $script['steps'],
        );
        self::assertSame($expected, $actual);

        foreach ($script['steps'] as $i => $step) {
            self::assertSame($i, $step['index']);
        }
        $last = $script['steps'][count($script['steps']) - 1];
        self::assertSame('game over', $last['gifQuery'] ?? null);
    }

    public function testIntroOmittedWhenBlank(): void
    {
        $input = self::fixtureInput();
        $input['intro'] = "  \n ";

        $contents = array_column(PublishScriptBuilder::assemble($input)['steps'], 'content');

        self::assertNotContains('Welcome to the sim.', $contents);
        self::assertNotContains("  \n ", $contents);
        self::assertSame('**DAY 1**', $contents[2]);

        $input['intro'] = null;
        self::assertSame('**DAY 1**', PublishScriptBuilder::assemble($input)['steps'][2]['content']);
    }

    public function testOnlyOpeningThreadMessageCarriesRole(): void
    {
        $steps = PublishScriptBuilder::assemble(self::fixtureInput())['steps'];

        $withRole = array_values(array_filter(
            $steps,
            static fn (array $step): bool => $step['mentionRoles'] !== [],
        ));

        self::assertCount(1, $withRole);
        self::assertSame(1, $withRole[0]['index']);
        self::assertSame(['793564717402554368'], $withRole[0]['mentionRoles']);
    }

    public function testGameStepMentionsOnlyIdsInItsText(): void
    {
        $steps = PublishScriptBuilder::assemble(self::fixtureInput())['steps'];

        self::assertSame([self::GM_GAME_ONE], $steps[4]['mentionUsers']);
        self::assertSame([], $steps[5]['mentionUsers']);
        self::assertSame([self::GM_PORTIS], $steps[6]['mentionUsers']);
        self::assertSame([self::GM_OTHER], $steps[8]['mentionUsers']);
        self::assertSame([], $steps[3]['mentionUsers']);
    }

    /**
     * Guards the id pattern. Widening `<@!?` to `<@[!&]?` would let the role id through,
     * and widening `{17,20}` to `{2,20}` would let the 2-digit id `<@12>` through.
     */
    public function testExtractUserIdsIgnoresRoleAndShortIds(): void
    {
        $content = '<@&793564717402554368> <@12> <@!123456789012345678> <@123456789012345679> <@123456789012345678>';

        self::assertSame(
            ['123456789012345678', '123456789012345679'],
            PublishScriptBuilder::extractUserIds($content),
        );
    }

    public function testExactly2000CharsStaysOneMessage(): void
    {
        $text = str_repeat('a', 2000);

        self::assertSame([$text], PublishScriptBuilder::splitForDiscord($text));
    }

    public function testSplitsOver2000CharsWithinLimit(): void
    {
        $text = str_repeat('a', 2001);

        $chunks = PublishScriptBuilder::splitForDiscord($text);

        self::assertCount(2, $chunks);
        foreach ($chunks as $chunk) {
            self::assertGreaterThan(0, mb_strlen($chunk));
            self::assertLessThanOrEqual(2000, mb_strlen($chunk));
        }
        self::assertSame($text, implode('', $chunks));
    }

    public function testSplitsOnParagraphThenLineThenSpaceBoundaries(): void
    {
        $paragraphOne = str_repeat('x', 1500);
        $paragraphTwo = str_repeat('y', 1500);

        self::assertSame(
            [$paragraphOne, $paragraphTwo],
            PublishScriptBuilder::splitForDiscord($paragraphOne . "\n\n" . $paragraphTwo),
        );

        $words = implode(' ', array_fill(0, 600, 'word'));
        $chunks = PublishScriptBuilder::splitForDiscord($words);
        self::assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(2000, mb_strlen($chunk));
            self::assertStringStartsWith('word', $chunk);
            self::assertStringEndsWith('word', $chunk);
        }
    }

    public function testInjuryOnNonGameDateJoinsPreviousDay(): void
    {
        $input = self::fixtureInput();
        $input['injuries'] = [
            [
                'name' => 'Nene',
                'date' => '2009-01-06',
                'injuryDescription' => 'sprained ankle',
                'injuryGamesMissed' => 2,
                'teamName' => 'Nuggets',
                'teamAbbrev' => 'DEN',
            ],
            [
                'name' => 'Bobby Portis',
                'date' => '2009-01-01',
                'injuryDescription' => 'left shoulder strain',
                'injuryGamesMissed' => 4,
                'teamName' => 'Timberwolves',
                'teamAbbrev' => 'MIN',
            ],
        ];

        $steps = PublishScriptBuilder::assemble($input)['steps'];
        $kinds = array_column($steps, 'kind');

        $injurySteps = array_values(array_filter($steps, static fn (array $s): bool => $s['kind'] === 'injuries'));
        self::assertCount(2, $injurySteps);

        // Predates every game date: joins DAY 1 and renders under that day's header.
        self::assertSame(
            "**1/4/09**\n-B.Portis, MIN - left shoulder strain (4) <@" . self::GM_PORTIS . '>',
            $injurySteps[0]['content'],
        );
        // After the last game date's own day: joins the latest earlier day (DAY 2, 1/5).
        self::assertSame("**1/5/09**\n-Nene, DEN - sprained ankle (2)", $injurySteps[1]['content']);

        $firstInjury = array_search('injuries', $kinds, true);
        self::assertSame('**DAY 1**', $steps[3]['content']);
        self::assertSame(6, $firstInjury);
    }

    public function testPacingDelays(): void
    {
        $steps = PublishScriptBuilder::assemble(self::fixtureInput())['steps'];

        self::assertSame(0, $steps[0]['delayBeforeMs']);
        self::assertSame(30000, $steps[3]['delayBeforeMs']);
        self::assertSame(30000, $steps[4]['delayBeforeMs']);
        self::assertSame(120000, $steps[7]['delayBeforeMs']);
        self::assertSame(30000, $steps[8]['delayBeforeMs']);

        $input = self::fixtureInput();
        $input['games'][1]['recap_text'] = str_repeat('a', 2001);
        $split = PublishScriptBuilder::assemble($input)['steps'];

        $dayTwoIndex = array_search('**DAY 2**', array_column($split, 'content'), true);
        self::assertIsInt($dayTwoIndex);
        self::assertSame(120000, $split[$dayTwoIndex]['delayBeforeMs']);
        self::assertSame(30000, $split[$dayTwoIndex + 1]['delayBeforeMs']);
        self::assertSame(1000, $split[$dayTwoIndex + 2]['delayBeforeMs']);
        self::assertSame(2000, mb_strlen($split[$dayTwoIndex + 1]['content']));
        self::assertSame('a', $split[$dayTwoIndex + 2]['content']);
    }

    /**
     * @param array<string, mixed>|null $summary
     * @param list<array<string, mixed>> $games
     * @param list<array<string, mixed>> $injuryRows
     */
    private static function builder(?array $summary, array $games, array $injuryRows, MockDatabase $db): PublishScriptBuilder
    {
        $summaries = self::createStub(SimSummaryRepository::class);
        $summaries->method('find')->willReturn($summary);
        $summaries->method('findDisplayableGameRecaps')->willReturn($games);

        $db->onQuery('ibl_jsb_transactions', $injuryRows);

        $teams = self::createStub(TeamIdentityRepositoryInterface::class);
        $teams->method('getAllRealTeams')->willReturn([['team_name' => 'Timberwolves'], ['team_name' => 'Nuggets']]);
        $teams->method('getTeamDiscordID')->willReturnMap([
            ['Timberwolves', (int) self::GM_PORTIS],
            ['Nuggets', null],
        ]);

        $seasons = self::createStub(SeasonQueryRepositoryInterface::class);
        $seasons->method('calculatePhaseSimNumber')->willReturn(10);

        return new PublishScriptBuilder($summaries, new SimRecapContextRepository($db), new MentionMapBuilder($teams), $seasons);
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(): array
    {
        return ['sim' => 7, 'intro_text' => 'Intro paragraph.', 'outro_text' => 'Standings paragraph.'];
    }

    public function testBuildThrowsNamingTheSimWhenSummaryMissing(): void
    {
        $builder = self::builder(null, [], [], new MockDatabase());

        try {
            $builder->build(7, null);
            self::fail('Expected PublishScriptUnavailable');
        } catch (PublishScriptUnavailable $e) {
            self::assertSame('No sim summary for sim 7', $e->getMessage());
        }
    }

    public function testBuildSkipsMalformedRowsAndFeedsInjuriesWithGmMentions(): void
    {
        $games = [
            ['game_date' => '2009-01-05', 'sort_order' => 1, 'recap_text' => 'Late game.'],
            ['game_date' => null, 'sort_order' => 1, 'recap_text' => 'Bad date.'],
            ['game_date' => '2009-01-04', 'sort_order' => 'x', 'recap_text' => 'Bad order.'],
            ['game_date' => '2009-01-04', 'sort_order' => 3, 'recap_text' => null],
            ['game_date' => '2009-01-04', 'sort_order' => '2', 'recap_text' => 'Early game.'],
        ];
        $injuryRows = [
            [
                'pid' => 1, 'name' => 'Bobby Portis', 'injury_date' => '2009-01-04',
                'injury_description' => 'left shoulder strain', 'injury_games_missed' => 4,
                'from_teamid' => 16, 'team_name' => 'Timberwolves',
            ],
            [
                'pid' => 2, 'name' => 'Nene', 'injury_date' => '2009-01-05',
                'injury_description' => 'sprained ankle', 'injury_games_missed' => 2,
                'from_teamid' => 7, 'team_name' => 'Nuggets',
            ],
        ];
        $db = new MockDatabase();

        $script = self::builder(self::summary(), $games, $injuryRows, $db)->build(7, null);

        self::assertSame(7, $script['sim']);
        self::assertSame('2008-09, SIM #10', $script['title']);
        self::assertSame([
            '**2008-09, SIM #10**',
            '<@&' . PublishScriptBuilder::IBL_ROLE_ID . "> Let's do the darned thing!",
            'Intro paragraph.',
            '**DAY 1**',
            'Early game.',
            "**1/4/09**\n-B.Portis, Timberwolves - left shoulder strain (4) <@" . self::GM_PORTIS . '>',
            '**DAY 2**',
            'Late game.',
            "**1/5/09**\n-Nene, Nuggets - sprained ankle (2)",
            str_repeat('=', 80) . "\n\n**WHERE THINGS STAND**\n\nStandings paragraph.",
            '',
        ], array_column($script['steps'], 'content'));
        self::assertSame([self::GM_PORTIS], $script['steps'][5]['mentionUsers']);
        // The injury window spans the first through the last game date.
        self::assertSame([2009, 2009, '2009-01-04', '2009-01-05'], $db->getLastBoundParams());
    }

    /**
     * A numeric-string sort_order truncates to an int, so '1.9' ties with 1 and input order holds.
     */
    public function testBuildTruncatesNumericStringSortOrder(): void
    {
        $games = [
            ['game_date' => '2009-01-04', 'sort_order' => '1.9', 'recap_text' => 'Listed first.'],
            ['game_date' => '2009-01-04', 'sort_order' => 1, 'recap_text' => 'Listed second.'],
        ];

        $script = self::builder(self::summary(), $games, [], new MockDatabase())->build(7, null);
        $contents = array_column($script['steps'], 'content');

        self::assertSame(['**DAY 1**', 'Listed first.', 'Listed second.'], array_slice($contents, 3, 3));
    }

    public function testDaysOrderedByDateNotInputOrder(): void
    {
        $input = self::fixtureInput();
        $input['injuries'] = [];
        $input['games'] = [
            ['game_date' => '2009-01-05', 'sort_order' => 1, 'recap_text' => 'Second day.'],
            ['game_date' => '2009-01-04', 'sort_order' => 1, 'recap_text' => 'First day.'],
        ];

        $contents = array_column(PublishScriptBuilder::assemble($input)['steps'], 'content');

        self::assertSame(['**DAY 1**', 'First day.', '**DAY 2**', 'Second day.'], array_slice($contents, 3, 4));
    }

    public function testInjuryOnSecondGameDateJoinsThatDay(): void
    {
        $input = self::fixtureInput();
        $input['injuries'] = [[
            'name' => 'Nene',
            'date' => '2009-01-05',
            'injuryDescription' => 'sprained ankle',
            'injuryGamesMissed' => 2,
            'teamName' => 'Nuggets',
            'teamAbbrev' => 'Nuggets',
        ]];

        $steps = PublishScriptBuilder::assemble($input)['steps'];
        $injurySteps = array_values(array_filter($steps, static fn (array $s): bool => $s['kind'] === 'injuries'));

        self::assertCount(1, $injurySteps);
        self::assertSame("**1/5/09**\n-Nene, Nuggets - sprained ankle (2)", $injurySteps[0]['content']);
    }

    public function testWhitespaceOnlyOutroIsOmitted(): void
    {
        $input = self::fixtureInput();
        $input['outro'] = "   \n ";

        $steps = PublishScriptBuilder::assemble($input)['steps'];
        $count = count($steps);

        self::assertSame('gif', $steps[$count - 1]['kind']);
        self::assertSame($input['games'][1]['recap_text'], $steps[$count - 2]['content']);
        foreach ($steps as $step) {
            self::assertStringNotContainsString('WHERE THINGS STAND', $step['content']);
        }
    }

    public function testExtractUserIdsReturnsListAfterDuplicate(): void
    {
        $content = '<@123456789012345678> <@123456789012345678> <@123456789012345679>';

        self::assertSame(
            ['123456789012345678', '123456789012345679'],
            PublishScriptBuilder::extractUserIds($content),
        );
    }

    public function testExtractUserIdsEmptyWhenNoMention(): void
    {
        self::assertSame([], PublishScriptBuilder::extractUserIds('no mentions here'));
    }

    public function testEmptyTextStaysOneEmptyMessage(): void
    {
        self::assertSame([''], PublishScriptBuilder::splitForDiscord(''));
    }

    public function testPackedChunksKeepTheirSeparator(): void
    {
        $a = str_repeat('a', 1000);
        $b = str_repeat('b', 500);
        $c = str_repeat('c', 1000);

        self::assertSame(
            [$a . "\n\n" . $b, $c],
            PublishScriptBuilder::splitForDiscord($a . "\n\n" . $b . "\n\n" . $c),
        );
    }

    public function testCandidateOfExactly2000CharsStaysPacked(): void
    {
        $a = str_repeat('a', 1000);
        $b = str_repeat('b', 998);
        $c = str_repeat('c', 10);

        self::assertSame(
            [$a . "\n\n" . $b, $c],
            PublishScriptBuilder::splitForDiscord($a . "\n\n" . $b . "\n\n" . $c),
        );
    }

    public function testPartsAfterAnOversizedPartAreKept(): void
    {
        $x = str_repeat('x', 2500);
        $y = str_repeat('y', 10);

        self::assertSame(
            [str_repeat('x', 2000), str_repeat('x', 500), $y],
            PublishScriptBuilder::splitForDiscord($x . "\n\n" . $y),
        );
    }

    /**
     * The 2000 limit counts characters, so a multibyte text of exactly 2000
     * characters is sent whole, leading blank line included.
     */
    public function testExactly2000MultibyteCharsStaysOneMessage(): void
    {
        $text = "\n\n" . str_repeat('é', 1998);

        self::assertSame([$text], PublishScriptBuilder::splitForDiscord($text));
    }

    /**
     * A paragraph of exactly 2000 characters fits in one message, so it is kept
     * whole instead of being split further on its own newlines.
     */
    public function testParagraphOfExactly2000CharsIsKeptWhole(): void
    {
        $head = str_repeat('a', 10);
        $paragraph = "\n" . str_repeat('x', 1999);

        self::assertSame(
            [$head, $paragraph],
            PublishScriptBuilder::splitForDiscord($head . "\n\n" . $paragraph),
        );
    }

    /**
     * An oversized paragraph splits on single newlines before spaces or a hard cut.
     */
    public function testOversizedParagraphSplitsOnNewlinesNext(): void
    {
        $lines = array_fill(0, 25, str_repeat('l', 100));
        $text = implode("\n", $lines) . "\n\nend";

        self::assertSame(
            [
                implode("\n", array_slice($lines, 0, 19)),
                implode("\n", array_slice($lines, 19)),
                'end',
            ],
            PublishScriptBuilder::splitForDiscord($text),
        );
    }

    /**
     * Lengths count characters, not bytes: 'é' is two bytes.
     */
    public function testMultibyteLengthsCountCharacters(): void
    {
        $p1 = str_repeat('é', 1500);
        $p3 = str_repeat('é', 1000);

        self::assertSame(
            [$p1 . "\n\nz", $p3],
            PublishScriptBuilder::splitForDiscord($p1 . "\n\nz\n\n" . $p3),
        );
    }

    public function testHardCutCountsCharacters(): void
    {
        self::assertSame(
            [str_repeat('é', 2000), str_repeat('é', 500)],
            PublishScriptBuilder::splitForDiscord(str_repeat('é', 2500)),
        );
    }

    public function testHardCutAtExactMultipleHasNoEmptyTail(): void
    {
        self::assertSame(
            [str_repeat('a', 2000), str_repeat('a', 2000)],
            PublishScriptBuilder::splitForDiscord(str_repeat('a', 4000)),
        );
    }

    public function testAssembleMatchesIblbotFixture(): void
    {
        $path = dirname(__DIR__, 3) . '/IBLbot/src/sim-recap/fixtures/two-day-script.json';
        $json = file_get_contents($path);
        self::assertIsString($json);

        $expected = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($expected, PublishScriptBuilder::assemble(self::fixtureInput()));
    }
}
