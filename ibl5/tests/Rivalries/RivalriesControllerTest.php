<?php

declare(strict_types=1);

namespace Tests\Rivalries;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rivalries\Contracts\RivalriesServiceInterface;
use Rivalries\Contracts\RivalriesViewInterface;
use Rivalries\RivalriesController;

final class RivalriesControllerTest extends TestCase
{
    private ControllerFakeService $service;
    private RivalriesSpyRepository $repo;
    private RivalriesController $controller;

    protected function setUp(): void
    {
        $this->service = new ControllerFakeService();
        $this->repo = new class extends RivalriesSpyRepository {
            /** @var list<string> */
            public array $known = ['Mover Gm', 'Rival Gm'];

            public function gmExists(string $gmName): bool
            {
                parent::gmExists($gmName);
                return in_array($gmName, $this->known, true);
            }
        };
        $this->controller = new RivalriesController($this->service, new ControllerFakeView(), $this->repo);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidTeamIdProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'above max' => ['29'],
            'letters' => ['abc'],
            'decimal' => ['3.5'],
            'exponent' => ['1e1'],
            'leading space' => [' 3'],
            'empty' => [''],
            'array' => [['3']],
            'missing' => [null],
        ];
    }

    #[DataProvider('invalidTeamIdProvider')]
    public function testInvalidTeamIdsRenderEmptyState(mixed $raw): void
    {
        $get = ['op' => 'teams', 'b' => '7'];
        if ($raw !== null) {
            $get['a'] = $raw;
        }

        self::assertSame('EMPTY:Pick two different franchises.', $this->controller->handle($get, null, null));
        self::assertSame([], $this->service->calls);
    }

    public function testEqualTeamIdsRenderEmptyState(): void
    {
        $out = $this->controller->handle(['op' => 'teams', 'a' => '3', 'b' => '3'], null, null);

        self::assertSame('EMPTY:Pick two different franchises.', $out);
        self::assertSame([], $this->service->calls);
    }

    public function testValidTeamPairCallsService(): void
    {
        $out = $this->controller->handle(['op' => 'teams', 'a' => '3', 'b' => '7'], null, null);

        self::assertSame('PAIR:teams', $out);
        self::assertSame([['getFranchisePair', 3, 7]], $this->service->calls);
    }

    public function testOverLongGmNameIsRejectedBeforeLookup(): void
    {
        $out = $this->controller->handle(['op' => 'gm', 'gm' => str_repeat('x', 61)], null, null);

        self::assertSame('EMPTY:No general manager by that name.', $out);
        self::assertSame([], $this->repo->calls);
    }

    public function testUnknownGmRendersEmptyState(): void
    {
        self::assertSame(
            'EMPTY:No general manager by that name.',
            $this->controller->handle(['op' => 'gm', 'gm' => 'Ghost Gm'], null, null),
        );
        self::assertSame(
            'EMPTY:Pick two different general managers.',
            $this->controller->handle(['op' => 'gms', 'a' => 'Ghost Gm', 'b' => 'Rival Gm'], null, null),
        );
        self::assertSame([], $this->service->calls);
    }

    public function testKnownGmRendersGmPage(): void
    {
        self::assertSame('GM:Mover Gm', $this->controller->handle(['op' => 'gm', 'gm' => ' Mover Gm '], null, null));
        self::assertSame('PAIR:gms', $this->controller->handle(['op' => 'gms', 'a' => 'Mover Gm', 'b' => 'Rival Gm'], null, null));
    }

    public function testArrayGmParamIsRejected(): void
    {
        $out = $this->controller->handle(['op' => 'gm', 'gm' => ['x']], null, null);

        self::assertSame('EMPTY:No general manager by that name.', $out);
        self::assertSame([], $this->repo->calls);
    }

    public function testUnknownOpFallsBackToIndex(): void
    {
        self::assertSame('INDEX', $this->controller->handle(['op' => 'drop'], null, null));
        self::assertSame('INDEX', $this->controller->handle(['op' => ['teams']], null, null));
    }
}

final class ControllerFakeService implements RivalriesServiceInterface
{
    /** @var list<list<int|string>> */
    public array $calls = [];

    public function getLeagueTop(int $limit): array
    {
        return ['franchises' => [], 'gms' => []];
    }

    public function getTopFranchiseRivals(int $teamId, int $limit): array
    {
        $this->calls[] = ['getTopFranchiseRivals', $teamId, $limit];
        return [];
    }

    public function getTopGmRivals(string $gmName, int $limit): array
    {
        $this->calls[] = ['getTopGmRivals', $gmName, $limit];
        return [];
    }

    public function getFranchisePair(int $a, int $b): ?array
    {
        $this->calls[] = ['getFranchisePair', $a, $b];
        return null;
    }

    public function getGmPair(string $a, string $b): ?array
    {
        $this->calls[] = ['getGmPair', $a, $b];
        return null;
    }
}

final class ControllerFakeView implements RivalriesViewInterface
{
    public function renderIndex(array $league, ?string $userTeamKey, ?string $userGm): string
    {
        return 'INDEX';
    }

    public function renderPair(?array $page, string $kind): string
    {
        return 'PAIR:' . $kind;
    }

    public function renderGm(string $gmName, array $rivals, bool $known, ?string $userGm): string
    {
        return 'GM:' . $gmName;
    }

    public function renderEmpty(string $message): string
    {
        return 'EMPTY:' . $message;
    }
}
