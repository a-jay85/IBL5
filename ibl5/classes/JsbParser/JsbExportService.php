<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\JsbExportRepositoryInterface;
use JsbParser\Contracts\JsbExportServiceInterface;
use PlrParser\PlrFileWriter;
use PlrParser\PlrWriteResult;

/**
 * Exports completed trades from the database to a .trn file via TrnFileWriter.
 * The .plr export lives in PlrParser\PlrExportService.
 */
class JsbExportService implements JsbExportServiceInterface
{
    private JsbExportRepositoryInterface $repository;

    /**
     * Maps current database team names to JSB team IDs.
     * For renamed franchises, uses current DB names (see JsbParser\Repositories\JsbLookupRepository::TEAM_NAME_ALIASES).
     *
     * @var array<string, int>
     */
    private const TEAM_NAME_TO_JSB_ID = [
        'Free Agents' => 0,
        'Celtics' => 1,
        'Heat' => 2,
        'Knicks' => 3,
        'Nets' => 4,
        'Magic' => 5,
        'Bucks' => 6,
        'Bulls' => 7,
        'Pelicans' => 8,
        'Hawks' => 9,
        'Sting' => 10,
        'Pacers' => 11,
        'Raptors' => 12,
        'Jazz' => 13,
        'Timberwolves' => 14,
        'Nuggets' => 15,
        'Aces' => 16,
        'Rockets' => 17,
        'Trailblazers' => 18,
        'Clippers' => 19,
        'Grizzlies' => 20,
        'Lakers' => 21,
        'Braves' => 22,
        'Suns' => 23,
        'Warriors' => 24,
        'Pistons' => 25,
        'Kings' => 26,
        'Bullets' => 27,
        'Mavericks' => 28,
    ];

    public function __construct(JsbExportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see JsbExportServiceInterface::exportTrnFile()
     */
    public function exportTrnFile(string $outputPath, string $seasonStartDate): PlrWriteResult
    {
        $result = new PlrWriteResult();

        $tradeItems = $this->repository->getCompletedTradeItems($seasonStartDate);
        $result->addMessage('Found ' . count($tradeItems) . ' trade items in database');

        // Group trade items by tradeofferid to build trade records
        $tradeGroups = $this->groupTradeItems($tradeItems);

        $records = [];
        foreach ($tradeGroups as $group) {
            $trnItems = [];
            $dateStr = '';
            foreach ($group as $item) {
                $dateStr = $item['created_at'];

                if ($item['itemtype'] === \Trading\TradeItemType::Player->value) {
                    // Player trade
                    $fromJsbId = $this->resolveTeamToJsbId($item['trade_from']);
                    $toJsbId = $this->resolveTeamToJsbId($item['trade_to']);
                    $trnItems[] = [
                        'marker' => TrnFileParser::TRADE_MARKER_PLAYER,
                        'from_team' => $fromJsbId,
                        'to_team' => $toJsbId,
                        'player_id' => $item['itemid'],
                    ];
                } elseif ($item['itemtype'] === \Trading\TradeItemType::DraftPick->value) {
                    // Draft pick trade
                    $fromJsbId = $this->resolveTeamToJsbId($item['trade_from']);
                    $toJsbId = $this->resolveTeamToJsbId($item['trade_to']);
                    $trnItems[] = [
                        'marker' => TrnFileParser::TRADE_MARKER_DRAFT_PICK,
                        'from_team' => $fromJsbId,
                        'to_team' => $toJsbId,
                        'draft_year' => $item['itemid'],
                    ];
                }
                // TradeItemType::Cash is not represented in .trn format
            }

            if ($trnItems !== [] && $dateStr !== '') {
                $date = new \DateTimeImmutable($dateStr);
                $records[] = TrnFileWriter::buildTradeRecord(
                    (int) $date->format('n'),
                    (int) $date->format('j'),
                    (int) $date->format('Y'),
                    $trnItems,
                );
            }
        }

        $result->addMessage('Generated ' . count($records) . ' trade records');

        $output = TrnFileWriter::generate($records);

        // Size assertion
        if (strlen($output) !== TrnFileParser::FILE_SIZE) {
            $result->addError(
                'Output size (' . strlen($output) . ') does not match expected '
                . TrnFileParser::FILE_SIZE . ' bytes'
            );
            return $result;
        }

        PlrFileWriter::writeFile($output, $outputPath);
        $result->addMessage('Wrote ' . strlen($output) . ' bytes to ' . $outputPath);

        return $result;
    }

    /**
     * Group trade items by tradeofferid.
     *
     * @param list<array{tradeofferid: int, itemid: int, itemtype: string, trade_from: string, trade_to: string, created_at: string}> $items
     * @return array<int, list<array{tradeofferid: int, itemid: int, itemtype: string, trade_from: string, trade_to: string, created_at: string}>>
     */
    private function groupTradeItems(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $groups[$item['tradeofferid']][] = $item;
        }
        return $groups;
    }

    /**
     * Resolve a database team name to a JSB team ID.
     */
    private function resolveTeamToJsbId(string $teamName): int
    {
        return self::TEAM_NAME_TO_JSB_ID[$teamName] ?? 0;
    }
}
