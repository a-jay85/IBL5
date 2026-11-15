<?php

declare(strict_types=1);

namespace SimRecap;

use Season\Contracts\SeasonQueryRepositoryInterface;

/**
 * Builds the complete Discord publisher script for a sim: ordered steps, each
 * with its final text, target channel, allowed mention ids, and delay. IBLbot
 * has no DB access; it only posts the steps and reports progress back. The
 * script is deterministic for unchanged data, so a resumed drip rebuilds the
 * same step list and continues at last_step + 1.
 *
 * @phpstan-type ScriptGame array{game_date:string,sort_order:int,recap_text:string}
 * @phpstan-type ScriptInjury array{name:string,date:string,injuryDescription:string,injuryGamesMissed:int,teamName:string,teamAbbrev:string}
 * @phpstan-type ScriptStep array{index:int,kind:string,channel:string,content:string,mentionUsers:list<string>,mentionRoles:list<string>,delayBeforeMs:int,gifQuery?:string}
 * @phpstan-type Script array{sim:int,title:string,steps:list<ScriptStep>}
 * @phpstan-type AssembleInput array{sim:int,title:string,intro:?string,outro:?string,games:list<ScriptGame>,injuries:list<ScriptInjury>,gmByTeamName:array<string,string>}
 */
final class PublishScriptBuilder
{
    public const IBL_ROLE_ID = '793564717402554368';
    public const GAME_DELAY_MS = 30000;
    public const DAY_DELAY_MS = 120000;
    public const CONTINUATION_DELAY_MS = 1000;
    public const DISCORD_LIMIT = 2000;
    public const GIF_QUERY = 'game over';

    private const CHANNEL_LIVE_SIMS = 'live_sims';
    private const CHANNEL_THREAD = 'thread';
    private const CHANNEL_INJURIES = 'injuries';

    public function __construct(
        private readonly SimSummaryRepository $summaries,
        private readonly SimRecapContextRepository $context,
        private readonly MentionMapBuilder $mentionMap,
        private readonly SeasonQueryRepositoryInterface $seasons,
    ) {
    }

    /**
     * Gathers the inputs for a sim and assembles its script.
     *
     * @return Script
     * @throws PublishScriptUnavailable when the summary is missing or no game recap is displayable
     * @throws \InvalidArgumentException when no title override is given and the sim has no season ordinal
     */
    public function build(int $sim, ?string $titleOverride): array
    {
        $summary = $this->summaries->find($sim);
        if ($summary === null) {
            throw new PublishScriptUnavailable('No sim summary for sim ' . $sim);
        }

        $games = [];
        foreach ($this->summaries->findDisplayableGameRecaps($sim) as $row) {
            $gameDate = $row['game_date'] ?? null;
            $sortOrder = $row['sort_order'] ?? null;
            $recapText = $row['recap_text'] ?? null;
            if (!is_string($gameDate) || !is_numeric($sortOrder) || !is_string($recapText)) {
                continue;
            }
            $games[] = ['game_date' => $gameDate, 'sort_order' => (int) $sortOrder, 'recap_text' => $recapText];
        }
        if ($games === []) {
            throw new PublishScriptUnavailable('No displayable game recaps for sim ' . $sim);
        }

        $dates = array_column($games, 'game_date');
        $minDate = min($dates);
        $maxDate = max($dates);

        $injuries = [];
        foreach ($this->context->findAllWindowInjuries($minDate, $maxDate) as $row) {
            $injuries[] = [
                'name' => $row['name'],
                'date' => $row['date'],
                'injuryDescription' => $row['injuryDescription'],
                'injuryGamesMissed' => $row['injuryGamesMissed'],
                'teamName' => $row['teamName'],
                'teamAbbrev' => $row['teamAbbrev'],
            ];
        }

        $endingYear = PublishThreadTitle::seasonEndingYearForDate($maxDate);
        $title = PublishThreadTitle::build(
            $endingYear,
            $this->seasons->calculatePhaseSimNumber($sim, 'Regular Season', $endingYear),
            $titleOverride,
        );

        return self::assemble([
            'sim' => $sim,
            'title' => $title,
            'intro' => $summary['intro_text'],
            'outro' => $summary['outro_text'],
            'games' => $games,
            'injuries' => $injuries,
            'gmByTeamName' => $this->mentionMap->build(),
        ]);
    }

    /**
     * Unique user ids mentioned in $content, in order of first appearance.
     * The role form "<@&id>" never matches.
     *
     * @return list<string>
     */
    public static function extractUserIds(string $content): array
    {
        if (preg_match_all('/<@!?(\d{17,20})>/', $content, $matches) === 0) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /**
     * Splits text into chunks of at most DISCORD_LIMIT characters. Packs greedily on
     * blank lines, then newlines, then spaces, and hard-cuts a token longer than the limit.
     *
     * @return list<string>
     */
    public static function splitForDiscord(string $text): array
    {
        if (mb_strlen($text) <= self::DISCORD_LIMIT) {
            return [$text];
        }

        return self::pack($text, ["\n\n", "\n", ' ']);
    }

    /**
     * Pure assembly of the script from already-gathered inputs.
     *
     * @param AssembleInput $in
     * @return Script
     */
    public static function assemble(array $in): array
    {
        /** @var list<ScriptStep> $steps */
        $steps = [];

        $add = static function (string $kind, string $channel, string $content, int $delay, ?string $roleId = null) use (&$steps): void {
            foreach (self::splitForDiscord($content) as $i => $chunk) {
                $steps[] = [
                    'index' => count($steps),
                    'kind' => $kind,
                    'channel' => $channel,
                    'content' => $chunk,
                    'mentionUsers' => self::extractUserIds($chunk),
                    'mentionRoles' => $i === 0 && $roleId !== null ? [$roleId] : [],
                    'delayBeforeMs' => $i === 0 ? $delay : self::CONTINUATION_DELAY_MS,
                ];
            }
        };

        $add('thread_start', self::CHANNEL_LIVE_SIMS, '**' . $in['title'] . '**', 0);
        $add(
            'thread_message',
            self::CHANNEL_THREAD,
            '<@&' . self::IBL_ROLE_ID . "> Let's do the darned thing!",
            0,
            self::IBL_ROLE_ID,
        );

        $intro = $in['intro'];
        if ($intro !== null && trim($intro) !== '') {
            $add('thread_message', self::CHANNEL_THREAD, $intro, self::GAME_DELAY_MS);
        }

        $gamesByDay = [];
        foreach ($in['games'] as $game) {
            $gamesByDay[$game['game_date']][] = $game;
        }
        ksort($gamesByDay);
        $days = array_map('strval', array_keys($gamesByDay));

        $injuriesByDay = [];
        if ($days !== []) {
            foreach ($in['injuries'] as $injury) {
                $injuriesByDay[self::dayForInjury($injury['date'], $days)][] = $injury;
            }
        }

        foreach ($days as $dayIndex => $day) {
            $add(
                'thread_message',
                self::CHANNEL_THREAD,
                '**DAY ' . ($dayIndex + 1) . '**',
                $dayIndex === 0 ? self::GAME_DELAY_MS : self::DAY_DELAY_MS,
            );

            $dayGames = $gamesByDay[$day];
            usort($dayGames, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);
            foreach ($dayGames as $game) {
                $add('thread_message', self::CHANNEL_THREAD, $game['recap_text'], self::GAME_DELAY_MS);
            }

            $dayInjuries = $injuriesByDay[$day] ?? [];
            if ($dayInjuries !== []) {
                $lines = [InjuryLineFormatter::formatHeader($day)];
                foreach ($dayInjuries as $injury) {
                    $lines[] = InjuryLineFormatter::formatLine([
                        'name' => $injury['name'],
                        'teamAbbrev' => $injury['teamAbbrev'],
                        'injuryDescription' => $injury['injuryDescription'],
                        'injuryGamesMissed' => $injury['injuryGamesMissed'],
                    ], $in['gmByTeamName'][$injury['teamName']] ?? null);
                }
                $add('injuries', self::CHANNEL_INJURIES, implode("\n", $lines), 0);
            }
        }

        $outro = $in['outro'];
        if ($outro !== null && trim($outro) !== '') {
            $add(
                'thread_message',
                self::CHANNEL_THREAD,
                str_repeat('=', 80) . "\n\n**WHERE THINGS STAND**\n\n" . $outro,
                self::DAY_DELAY_MS,
            );
        }

        $steps[] = [
            'index' => count($steps),
            'kind' => 'gif',
            'channel' => self::CHANNEL_THREAD,
            'content' => '',
            'mentionUsers' => [],
            'mentionRoles' => [],
            'delayBeforeMs' => self::GAME_DELAY_MS,
            'gifQuery' => self::GIF_QUERY,
        ];

        return ['sim' => $in['sim'], 'title' => $in['title'], 'steps' => $steps];
    }

    /**
     * The game day an injury belongs to: its own date when that is a game day,
     * else the latest earlier game day, else the first game day.
     *
     * @param non-empty-list<string> $days game dates, ascending
     */
    private static function dayForInjury(string $date, array $days): string
    {
        $chosen = $days[0];
        foreach ($days as $day) {
            if ($day <= $date) {
                $chosen = $day;
            }
        }

        return $chosen;
    }

    /**
     * @param list<non-empty-string> $separators remaining separators, coarsest first
     * @return list<string>
     */
    private static function pack(string $text, array $separators): array
    {
        if (mb_strlen($text) <= self::DISCORD_LIMIT) {
            return $text === '' ? [] : [$text];
        }
        if ($separators === []) {
            return self::hardCut($text);
        }

        $separator = $separators[0];
        $deeper = array_slice($separators, 1);
        $chunks = [];
        $current = '';
        foreach (explode($separator, $text) as $part) {
            if (mb_strlen($part) > self::DISCORD_LIMIT) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                foreach (self::pack($part, $deeper) as $piece) {
                    $chunks[] = $piece;
                }
                continue;
            }
            $candidate = $current === '' ? $part : $current . $separator . $part;
            if (mb_strlen($candidate) <= self::DISCORD_LIMIT) {
                $current = $candidate;
                continue;
            }
            if ($current !== '') {
                $chunks[] = $current;
            }
            $current = $part;
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private static function hardCut(string $text): array
    {
        $chunks = [];
        $length = mb_strlen($text);
        for ($offset = 0; $offset < $length; $offset += self::DISCORD_LIMIT) {
            $chunks[] = mb_substr($text, $offset, self::DISCORD_LIMIT);
        }

        return $chunks;
    }
}
