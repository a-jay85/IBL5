<?php

declare(strict_types=1);

namespace Tests\WideUnit\LeagueControlPanel;

use JsbParser\Repositories\AwaRepository;
use LeagueControlPanel\LeagueControlPanelRepository;
use Tests\WideUnit\WideUnitTestCase;

/**
 * Pins that LeagueControlPanelRepository::upsertAward owns no ibl_awards SQL
 * and forwards to AwaRepository, the single upsert site.
 */
class LeagueControlPanelAwardDelegationTest extends WideUnitTestCase
{
    public function testUpsertAwardDelegatesToInjectedAwaRepository(): void
    {
        $spy = new class($this->mockDb) extends AwaRepository {
            /** @var list<array{int, string, string}> */
            public array $calls = [];

            public function upsertAward(int $year, string $award, string $name): int
            {
                $this->calls[] = [$year, $award, $name];

                return 7;
            }
        };
        $repo = new LeagueControlPanelRepository($this->mockDb, null, $spy);

        $result = $repo->upsertAward(2098, 'Delegation Award', 'Delegation Player');

        self::assertSame([[2098, 'Delegation Award', 'Delegation Player']], $spy->calls);
        self::assertSame(7, $result);
    }

    public function testUpsertAwardIssuesNoIblAwardsSqlOfItsOwn(): void
    {
        $spy = new class($this->mockDb) extends AwaRepository {
            public function upsertAward(int $year, string $award, string $name): int
            {
                return 1;
            }
        };
        $repo = new LeagueControlPanelRepository($this->mockDb, null, $spy);

        $repo->upsertAward(2098, 'Delegation Award', 'Delegation Player');

        $this->assertQueryNotExecuted('ibl_awards');
    }

    public function testDefaultAwaRepositoryWritesThroughSameConnection(): void
    {
        $repo = new LeagueControlPanelRepository($this->mockDb);

        $repo->upsertAward(2098, 'Default Award', 'Default Player');

        $this->assertQueryExecuted('INSERT INTO ibl_awards');
    }

    public function testConstructorAcceptsOptionalNullableAwaRepository(): void
    {
        $params = (new \ReflectionMethod(LeagueControlPanelRepository::class, '__construct'))->getParameters();

        self::assertCount(3, $params);
        $param = $params[2];
        self::assertSame('awaRepository', $param->getName());
        $type = $param->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(AwaRepository::class, $type->getName());
        self::assertTrue($type->allowsNull());
        self::assertTrue($param->isDefaultValueAvailable());
    }
}
