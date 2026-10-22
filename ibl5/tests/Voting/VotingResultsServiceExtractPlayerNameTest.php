<?php

declare(strict_types=1);

namespace Tests\Voting;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Voting\VotingResultsService;

/**
 * @covers \Voting\VotingResultsService::extractPlayerName
 */
final class VotingResultsServiceExtractPlayerNameTest extends TestCase
{
    #[DataProvider('voteNameProvider')]
    public function testExtractPlayerNameStripsTrailingTeamSegment(string $input, string $expected): void
    {
        $this->assertSame($expected, VotingResultsService::extractPlayerName($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function voteNameProvider(): array
    {
        return [
            'normal ballot' => ['LeBron James, Sting', 'LeBron James'],
            'only the last comma splits' => ['Gary Payton Jr., Sonics', 'Gary Payton Jr.'],
            'multiple commas keep everything before the last' => ['Smith, Jr., Sting', 'Smith, Jr.'],
            'no comma is a trimmed GM name' => ['  Phil Jackson  ', 'Phil Jackson'],
            'trailing comma with empty team' => ['Name,', 'Name'],
            'empty player segment' => [', Sting', ''],
            'empty string' => ['', ''],
        ];
    }
}
