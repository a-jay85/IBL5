<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradePrefillParser;

class TradePrefillParserTest extends TestCase
{
    /**
     * @param list<int> $userPids
     * @param list<int> $userPickIds
     * @param list<int> $partnerPids
     * @param list<int> $partnerPickIds
     * @return array<string, mixed>
     */
    private function pageData(
        array $userPids = [101, 102],
        array $userPickIds = [7],
        array $partnerPids = [201],
        array $partnerPickIds = [9],
        int $partnerTeamId = 2,
    ): array {
        return [
            'userTeamId' => 1,
            'partnerTeamId' => $partnerTeamId,
            'userPlayers' => array_map(static fn (int $pid): array => ['pid' => $pid], $userPids),
            'userPicks' => array_map(static fn (int $id): array => ['pickid' => $id], $userPickIds),
            'partnerPlayers' => array_map(static fn (int $pid): array => ['pid' => $pid], $partnerPids),
            'partnerPicks' => array_map(static fn (int $id): array => ['pickid' => $id], $partnerPickIds),
        ];
    }

    public function testParsesValidUserAndPartnerItems(): void
    {
        $result = TradePrefillParser::parse('1:101,0:9', $this->pageData());

        $this->assertSame([
            'checkedItems' => ['1:101' => true, '0:9' => true],
            'userSendsCash' => [],
            'partnerSendsCash' => [],
        ], $result);
    }

    public function testParsesPartnerPlayerAndUserPick(): void
    {
        $result = TradePrefillParser::parse('1:201,0:7', $this->pageData());

        $this->assertNotNull($result);
        $this->assertSame(['1:201' => true, '0:7' => true], $result['checkedItems']);
    }

    public function testRejectsItemOwnedByThirdTeam(): void
    {
        $this->assertNull(TradePrefillParser::parse('1:999', $this->pageData()));
    }

    public function testRejectsPickIdUsedAsPlayerKey(): void
    {
        // pickid 7 exists, but only as a pick; a player key with that id is foreign
        $this->assertNull(TradePrefillParser::parse('1:7', $this->pageData()));
    }

    public function testRejectsWholePrefillWhenOneItemTampered(): void
    {
        $this->assertNull(TradePrefillParser::parse('1:101,1:999', $this->pageData()));
    }

    public function testRejectsMalformedInput(): void
    {
        $pageData = $this->pageData(userPids: [5, 101]);

        foreach (['1:abc', '2:5', '1:5;DROP', '1:0', '', ['1:5'], null] as $raw) {
            $this->assertNull(
                TradePrefillParser::parse($raw, $pageData),
                'Expected null for ' . var_export($raw, true)
            );
        }
    }

    public function testRejectsLeadingZeroAndTrailingComma(): void
    {
        $pageData = $this->pageData(userPids: [5, 101]);

        $this->assertNull(TradePrefillParser::parse('1:05', $pageData));
        $this->assertNull(TradePrefillParser::parse('1:5,', $pageData));
        $this->assertNull(TradePrefillParser::parse("1:5\n", $pageData));
        $this->assertNull(TradePrefillParser::parse(' 1:5', $pageData));
    }

    public function testRejectsMoreThanMaxItems(): void
    {
        $pids = [11, 12, 13, 14, 15, 16, 17, 18, 19];
        $pageData = $this->pageData(userPids: $pids);
        $keys = array_map(static fn (int $pid): string => '1:' . $pid, $pids);

        $this->assertNull(TradePrefillParser::parse(implode(',', $keys), $pageData));

        $eight = array_slice($keys, 0, 8);
        $result = TradePrefillParser::parse(implode(',', $eight), $pageData);
        $this->assertNotNull($result);
        $this->assertCount(8, $result['checkedItems']);
    }

    public function testRejectsOverLongInput(): void
    {
        $raw = str_repeat('1', 201);
        $this->assertSame(201, strlen($raw));

        $this->assertNull(TradePrefillParser::parse($raw, $this->pageData()));
    }

    public function testReturnsNullWhenPartnerUnknown(): void
    {
        $this->assertNull(TradePrefillParser::parse('1:101', $this->pageData(partnerTeamId: 0)));
    }

    public function testDedupesRepeatedKeys(): void
    {
        $result = TradePrefillParser::parse('1:101,1:101,0:9', $this->pageData());

        $this->assertNotNull($result);
        $this->assertSame(['1:101' => true, '0:9' => true], $result['checkedItems']);
    }
}
