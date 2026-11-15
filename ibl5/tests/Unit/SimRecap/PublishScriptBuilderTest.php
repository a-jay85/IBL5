<?php

declare(strict_types=1);

namespace Tests\Unit\SimRecap;

use PHPUnit\Framework\TestCase;
use SimRecap\PublishScriptBuilder;

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

    public function testAssembleMatchesIblbotFixture(): void
    {
        $path = dirname(__DIR__, 3) . '/IBLbot/src/sim-recap/fixtures/two-day-script.json';
        $json = file_get_contents($path);
        self::assertIsString($json);

        $expected = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($expected, PublishScriptBuilder::assemble(self::fixtureInput()));
    }
}
