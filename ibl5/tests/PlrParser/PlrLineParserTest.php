<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PlrParser\PlrLineParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \PlrParser\PlrLineParser
 */
class PlrLineParserTest extends TestCase
{
    /**
     * Build a 607-byte .plr record line carrying ordinal, name, age, and pid.
     *
     * Only the fields exercised by these tests are populated; the rest of the
     * fixed-width record is space-padded. Offsets mirror PlrLineParser::parse():
     * ordinal(0,4) | name(4,32) | age(36,2) | pid(38,6) | teamid(44,2).
     */
    private function buildPlrLine(int $ordinal, string $name, int $pid, int $teamid = 1): string
    {
        $line = str_pad((string) $ordinal, 4, ' ', STR_PAD_LEFT); // 0-3
        $line .= str_pad($name, 32);                              // 4-35
        $line .= str_pad('25', 2, ' ', STR_PAD_LEFT);            // 36-37 (age)
        $line .= str_pad((string) $pid, 6, ' ', STR_PAD_LEFT);   // 38-43
        $line .= str_pad((string) $teamid, 2, ' ', STR_PAD_LEFT); // 44-45

        return str_pad($line, 607); // full record width
    }

    /**
     * Build a 700-byte digit line where byte i is ((i * 7 + 3) % 10), then
     * overwrite the guard slots so parse() accepts it. Neighbouring fixed-width
     * fields get different digits, so a swapped offset changes the parsed array.
     */
    private function buildDistinctValueLine(): string
    {
        $line = '';
        for ($i = 0; $i < 700; $i++) {
            $line .= (string) (($i * 7 + 3) % 10);
        }
        $line = substr_replace($line, '   7', 0, 4);    // ordinal = 7
        $line = substr_replace($line, '000042', 38, 6); // pid = 42
        $line = substr_replace($line, '1000', 56, 4);   // realLifeMIN = 1000
        $line = substr_replace($line, ' 100', 108, 4);  // realLifePF = 100
        $line = substr_replace($line, ' 5', 286, 2);    // exp = 5
        $line = substr_replace($line, ' 2', 290, 2);    // currentContractYear = 2
        $line = substr_replace($line, ' 550', 302, 4);  // contractYear2 = 550
        $line = substr_replace($line, '75', 550, 2);    // heightInches = 75
        return $line;
    }

    public function testParsesAsciiName(): void
    {
        $result = PlrLineParser::parse($this->buildPlrLine(1, 'John Smith', 12345));

        $this->assertNotNull($result);
        $this->assertSame('John Smith', $result['name']);
        $this->assertSame(1, $result['ordinal']);
        $this->assertSame(12345, $result['pid']);
    }

    public function testParsesAccentedNameFromCp1252(): void
    {
        // "José Garcia" in CP1252 (é = 0xe9) decodes to UTF-8 (é = 0xc3 0xa9).
        $result = PlrLineParser::parse($this->buildPlrLine(2, "Jos\xe9 Garcia", 67890));

        $this->assertNotNull($result);
        $this->assertSame('José Garcia', $result['name']);
    }

    public function testMapsUndefinedCp1252ByteToUnicode(): void
    {
        // 0x81 is undefined in CP1252. This locks the consolidated decode (PR 3):
        // the shared PlrFieldSerializer::toUtf8 maps it to U+0081 (0xc2 0x81),
        // whereas the previous iconv('CP1252','UTF-8//IGNORE') variant in this
        // parser silently dropped the byte ("AB"). Standardizing on the mb
        // behavior is the latent-bug fix this PR makes.
        $result = PlrLineParser::parse($this->buildPlrLine(3, "A\x81B", 11111));

        $this->assertNotNull($result);
        $this->assertSame("A\xc2\x81B", $result['name']);
    }

    public function testReturnsNullForZeroPid(): void
    {
        $this->assertNull(PlrLineParser::parse($this->buildPlrLine(1, 'Empty Slot', 0)));
    }

    public function testReturnsNullForOrdinalAbove1440(): void
    {
        $this->assertNull(PlrLineParser::parse($this->buildPlrLine(1441, 'Over Limit', 99999)));
    }

    public function testParseReturnsFullArrayForDistinctValueLine(): void
    {
        $this->assertSame(
        [
            'ordinal' => 7,
            'name' => '18529630741852963074185296307418',
            'age' => 52,
            'pid' => 42,
            'teamid' => 18,
            'peak' => 5296,
            'pos' => '30',
            'realLifeGP' => 7418,
            'realLifeMIN' => 1000,
            'realLifeFGM' => 3074,
            'realLifeFGA' => 1852,
            'realLifeFTM' => 9630,
            'realLifeFTA' => 7418,
            'realLife3GM' => 5296,
            'realLife3GA' => 3074,
            'realLifeORB' => 1852,
            'realLifeDRB' => 9630,
            'realLifeAST' => 7418,
            'realLifeSTL' => 5296,
            'realLifeTVR' => 3074,
            'realLifeBLK' => 1852,
            'realLifePF' => 100,
            'unk_112' => 74,
            'unk_114' => 18,
            'unk_116' => 52,
            'unk_118' => 96,
            'unk_120' => 30,
            'unk_122' => 74,
            'unk_124' => 18,
            'unk_126' => 52,
            'clutch' => 96,
            'consistency' => 30,
            'PGDepth' => 7,
            'SGDepth' => 4,
            'SFDepth' => 1,
            'PFDepth' => 8,
            'CDepth' => 5,
            'canPlayInGame' => 2,
            'unk_138' => 96,
            'injuryDaysLeft' => 3074,
            'seasonGamesStarted' => 1852,
            'seasonGamesPlayed' => 9630,
            'seasonMIN' => 7418,
            'season2GM' => 5296,
            'season2GA' => 3074,
            'seasonFTM' => 1852,
            'seasonFTA' => 9630,
            'season3GM' => 7418,
            'season3GA' => 5296,
            'seasonORB' => 3074,
            'seasonDRB' => 1852,
            'seasonAST' => 9630,
            'seasonSTL' => 7418,
            'seasonTVR' => 5296,
            'seasonBLK' => 3074,
            'seasonPF' => 1852,
            'playoffSeasonGP' => 9630,
            'playoffSeasonMIN' => 7418,
            'playoffSeason2GM' => 5296,
            'playoffSeason2GA' => 3074,
            'playoffSeasonFTM' => 1852,
            'playoffSeasonFTA' => 9630,
            'playoffSeason3GM' => 7418,
            'playoffSeason3GA' => 5296,
            'playoffSeasonORB' => 3074,
            'playoffSeasonDRB' => 1852,
            'playoffSeasonAST' => 9630,
            'playoffSeasonSTL' => 7418,
            'playoffSeasonTVR' => 5296,
            'playoffSeasonBLK' => 3074,
            'playoffSeasonPF' => 1852,
            'talent' => 96,
            'skill' => 30,
            'intangibles' => 74,
            'coach' => 18,
            'loyalty' => 52,
            'playingTime' => 96,
            'playForWinner' => 30,
            'tradition' => 74,
            'security' => 18,
            'exp' => 5,
            'bird' => 96,
            'currentContractYear' => 2,
            'totalContractYears' => 74,
            'unk_294' => 18,
            'unk_296' => 52,
            'contractYear1' => 9630,
            'contractYear2' => 550,
            'contractYear3' => 5296,
            'contractYear4' => 3074,
            'contractYear5' => 1852,
            'contractYear6' => 9630,
            'unk_322' => 74,
            'unk_324' => 18,
            'draftRound' => 52,
            'draftPickNumber' => 96,
            'freeAgentSigningFlag' => 3,
            'unk_331' => 7,
            'unk_333' => 41,
            'unk_335' => 85,
            'unk_337' => 29,
            'unk_339' => 63,
            'seasonHighPTS' => 7,
            'seasonHighREB' => 41,
            'seasonHighAST' => 85,
            'seasonHighSTL' => 29,
            'seasonHighBLK' => 63,
            'seasonHighDoubleDoubles' => 7,
            'seasonHighTripleDoubles' => 41,
            'seasonPlayoffHighPTS' => 85,
            'seasonPlayoffHighREB' => 29,
            'seasonPlayoffHighAST' => 63,
            'seasonPlayoffHighSTL' => 7,
            'seasonPlayoffHighBLK' => 41,
            'careerSeasonHighPTS' => 852963,
            'careerSeasonHighREB' => 74185,
            'careerSeasonHighAST' => 296307,
            'careerSeasonHighSTL' => 418529,
            'careerSeasonHighBLK' => 630741,
            'careerSeasonHighDoubleDoubles' => 852963,
            'careerSeasonHighTripleDoubles' => 74185,
            'careerPlayoffHighPTS' => 296307,
            'careerPlayoffHighREB' => 418529,
            'careerPlayoffHighAST' => 630741,
            'careerPlayoffHighSTL' => 852963,
            'careerPlayoffHighBLK' => 74185,
            'careerGP' => 29630,
            'careerMIN' => 74185,
            'career2GM' => 29630,
            'career2GA' => 74185,
            'careerFTM' => 29630,
            'careerFTA' => 74185,
            'career3GM' => 29630,
            'career3GA' => 74185,
            'careerORB' => 29630,
            'careerDRB' => 74185,
            'careerAST' => 29630,
            'careerSTL' => 74185,
            'careerTVR' => 29630,
            'careerBLK' => 74185,
            'careerPF' => 29630,
            'unk_512' => 74,
            'unk_514' => 18,
            'unk_516' => 52,
            'unk_518' => 96,
            'unk_520' => 30,
            'unk_522' => 74,
            'unk_524' => 18,
            'unk_526' => 52,
            'unk_528' => 96,
            'unk_530' => 30,
            'unk_532' => 74,
            'unk_534' => 18,
            'unk_536' => 52,
            'unk_538' => 96,
            'unk_540' => 30,
            'unk_542' => 74,
            'unk_544' => 18,
            'unk_546' => 52,
            'unk_548' => 96,
            'heightInches' => 75,
            'weight' => 741,
            'rating2GA' => 852,
            'rating2GP' => 963,
            'ratingFTA' => 74,
            'ratingFTP' => 185,
            'rating3GA' => 296,
            'rating3GP' => 307,
            'ratingORB' => 418,
            'ratingDRB' => 529,
            'ratingAST' => 630,
            'ratingSTL' => 741,
            'ratingTVR' => 852,
            'ratingBLK' => 963,
            'ratingOO' => 7,
            'ratingDO' => 41,
            'ratingPO' => 85,
            'ratingTO' => 29,
            'ratingOD' => 63,
            'ratingDD' => 7,
            'ratingPD' => 41,
            'ratingTD' => 85,
        ],
            PlrLineParser::parse($this->buildDistinctValueLine()),
        );
    }

    public function testParsesOrdinalAtUpperBound1440(): void
    {
        $result = PlrLineParser::parse($this->buildPlrLine(1440, 'Edge Case', 7));

        $this->assertNotNull($result);
        $this->assertSame(1440, $result['ordinal']);
    }
}
