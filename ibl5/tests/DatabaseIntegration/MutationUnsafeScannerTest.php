<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\TestCase;

final class MutationUnsafeScannerTest extends TestCase
{
    public function testCommitCallNeedsTag(): void
    {
        self::assertTrue(MutationUnsafeScanner::needsTag('$db->commit();'));
    }

    public function testCommitLikeMethodNameDoesNotNeedTag(): void
    {
        self::assertFalse(MutationUnsafeScanner::needsTag('$repo->commitTrade($x);'));
    }

    public function testDbNameLiteralNeedsTag(): void
    {
        self::assertTrue(MutationUnsafeScanner::needsTag("getenv('DB_NAME')"));
    }

    public function testNearMissTagIsNotATag(): void
    {
        self::assertFalse(MutationUnsafeScanner::isTagged("#[Group('mutation-unsafe-later')]"));
    }

    public function testEverySelfConnectingDbTestIsTaggedMutationUnsafe(): void
    {
        $untagged = MutationUnsafeScanner::untaggedFiles(dirname(__DIR__));

        self::assertSame(
            [],
            $untagged,
            "These DB tests commit, connect on their own, or spawn a process. Add #[Group('mutation-unsafe')]:\n" . implode("\n", $untagged)
        );
    }
}
