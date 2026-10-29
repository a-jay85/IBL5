<?php

declare(strict_types=1);

namespace Tests\Rivalries;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Rivalries\Contracts\RivalriesServiceInterface;
use Rivalries\Contracts\RivalriesViewInterface;
use Rivalries\RivalriesController;

final class RivalriesControllerTest extends TestCase
{
    private ControllerFakeService $service;
    private RivalriesSpyRepository $repo;
    private RivalriesController $controller;
    private ControllerFakeView $view;
    /** @var array<int, string> teamid => owner name */
    private array $owners = [];
    /** @var array<string, string> team nickname => city; a missing nickname means no ibl_team_info row */
    private array $cities = ['Metros' => 'Metro City'];

    protected function setUp(): void
    {
        $this->service = new ControllerFakeService();
        $this->repo = new class extends RivalriesSpyRepository {
            /** @var list<string> */
            public array $known = ['Mover Gm', 'Rival Gm'];

            public function findGmName(string $gmName): ?string
            {
                parent::findGmName($gmName);
                foreach ($this->known as $stored) {
                    if (strcasecmp($stored, $gmName) === 0) {
                        return $stored;
                    }
                }
                return null;
            }
        };
        $teams = self::createStub(TeamIdentityRepositoryInterface::class);
        $teams->method('getTeamColorRow')->willReturnCallback(
            static fn (int $id): ?array => $id === 3 ? ['color1' => '112233', 'color2' => 'ffffff'] : null,
        );
        $teams->method('getTeamnameFromTeamID')->willReturnCallback(
            static fn (int $id): ?string => $id === 3 ? 'Metros' : null,
        );
        $teams->method('getOwnerName')->willReturnCallback(
            fn (int $id): ?string => $this->owners[$id] ?? null,
        );
        $teams->method('getTeamByName')->willReturnCallback(
            fn (string $name): ?array => isset($this->cities[$name])
                ? ['teamid' => 3, 'team_city' => $this->cities[$name], 'team_name' => $name]
                : null,
        );
        $this->view = new ControllerFakeView();
        $this->controller = new RivalriesController($this->service, $this->view, $this->repo, $teams);
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

    public function testValidTeamIdRendersTeamPage(): void
    {
        $out = $this->controller->handle(['op' => 'team', 'team' => '3'], null, null);

        self::assertSame('TEAM:Metro City Metros', $out);
        self::assertSame([['getTopFranchiseRivals', 3, RivalriesController::TOP_LIMIT]], $this->service->calls);
    }

    public function testTeamLabelFallsBackToNicknameWithoutTeamRow(): void
    {
        $this->cities = [];

        self::assertSame('TEAM:Metros', $this->controller->handle(['op' => 'team', 'team' => '3'], null, null));
    }

    public function testTeamLabelFallsBackToNicknameWhenCityIsEmpty(): void
    {
        $this->cities = ['Metros' => ''];

        self::assertSame('TEAM:Metros', $this->controller->handle(['op' => 'team', 'team' => '3'], null, null));
    }

    public function testCaptionColorIsThePageTeamPrimaryColorForAnotherTeamsViewer(): void
    {
        $this->controller->handle(['op' => 'team', 'team' => '3'], '7', null);

        self::assertSame('112233', $this->view->teamArgs['captionColor']);
    }

    public function testCaptionColorIsThePageTeamPrimaryColorForAnonymousViewer(): void
    {
        $this->controller->handle(['op' => 'team', 'team' => '3'], null, null);

        self::assertSame('112233', $this->view->teamArgs['captionColor']);
    }

    public function testTeamPagePassesCurrentGmAndTheirRivals(): void
    {
        $this->owners[3] = 'mover gm';
        $this->service->gmRivals = [self::rivalry('Mover Gm', 'Rival Gm')];

        $this->controller->handle(['op' => 'team', 'team' => '3'], '3', 'Mover Gm');

        self::assertSame(
            [['getTopFranchiseRivals', 3, RivalriesController::TOP_LIMIT], ['getTopGmRivals', 'Mover Gm', RivalriesController::TOP_LIMIT]],
            $this->service->calls,
        );
        self::assertSame('Mover Gm', $this->view->teamArgs['gmName']);
        self::assertSame($this->service->gmRivals, $this->view->teamArgs['gmRivals']);
        self::assertNull($this->view->teamArgs['userTeamKey']);
        self::assertNull($this->view->teamArgs['userGm']);
    }

    public function testTeamPageKeepsHighlightForAnotherTeamsViewer(): void
    {
        $this->owners[3] = 'mover gm';

        $this->controller->handle(['op' => 'team', 'team' => '3'], '7', 'Rival Gm');

        self::assertSame('7', $this->view->teamArgs['userTeamKey']);
        self::assertSame('Rival Gm', $this->view->teamArgs['userGm']);
    }

    public function testGmPageDropsHighlightForItsOwnGm(): void
    {
        $this->controller->handle(['op' => 'gm', 'gm' => 'Mover Gm'], null, 'Mover Gm');

        self::assertNull($this->view->gmUserGm);
    }

    public function testGmPageKeepsHighlightForAnotherGm(): void
    {
        $this->controller->handle(['op' => 'gm', 'gm' => 'Mover Gm'], null, 'Rival Gm');

        self::assertSame('Rival Gm', $this->view->gmUserGm);
    }

    public function testTeamPageWithoutOwnerPassesNullGmAndNoRivals(): void
    {
        $this->controller->handle(['op' => 'team', 'team' => '3'], null, null);

        self::assertNull($this->view->teamArgs['gmName']);
        self::assertSame([], $this->view->teamArgs['gmRivals']);
        self::assertSame([], $this->view->teamArgs['gmSideColors']);
        self::assertSame([['getTopFranchiseRivals', 3, RivalriesController::TOP_LIMIT]], $this->service->calls);
    }

    public function testTeamPageWithUnknownOwnerPassesNullGm(): void
    {
        $this->owners[3] = 'Ghost Gm';

        $this->controller->handle(['op' => 'team', 'team' => '3'], null, null);

        self::assertNull($this->view->teamArgs['gmName']);
        self::assertSame([['getTopFranchiseRivals', 3, RivalriesController::TOP_LIMIT]], $this->service->calls);
    }

    public function testTeamPageColorMapsUseTeamColorAndGmColorFranchise(): void
    {
        $this->owners[3] = 'Mover Gm';
        $this->repo->colorFranchises = ['Mover Gm' => 3];
        $this->service->franchiseRivals = [self::rivalry('3', '7')];
        $this->service->gmRivals = [self::rivalry('Mover Gm', 'Rival Gm')];

        $this->controller->handle(['op' => 'team', 'team' => '3'], null, null);

        // Team 7 has no color row and gets the fallback team color.
        self::assertSame(['3' => '112233', '7' => 'D4AF37'], $this->view->teamArgs['teamSideColors']);
        // Rival Gm has no color franchise and gets gray.
        self::assertSame(['Mover Gm' => '112233', 'Rival Gm' => 'e9eaeb'], $this->view->teamArgs['gmSideColors']);
    }

    public function testGmPagePassesGmColorMap(): void
    {
        $this->repo->colorFranchises = ['Mover Gm' => 3];
        $this->service->gmRivals = [self::rivalry('Mover Gm', 'Rival Gm')];

        $this->controller->handle(['op' => 'gm', 'gm' => 'Mover Gm'], null, null);

        self::assertSame(['Mover Gm' => '112233', 'Rival Gm' => 'e9eaeb'], $this->view->gmSideColors);
    }

    #[DataProvider('invalidTeamIdProvider')]
    public function testInvalidTeamPageIdsRenderEmptyState(mixed $raw): void
    {
        $get = ['op' => 'team'];
        if ($raw !== null) {
            $get['team'] = $raw;
        }

        self::assertSame('EMPTY:No franchise by that id.', $this->controller->handle($get, null, null));
        self::assertSame([], $this->service->calls);
    }

    public function testTeamIdWithoutNameRendersEmptyState(): void
    {
        $out = $this->controller->handle(['op' => 'team', 'team' => '7'], null, null);

        self::assertSame('EMPTY:No franchise by that id.', $out);
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

        // Team 3 has a color row; team 7 has none and gets the fallback color.
        self::assertSame('PAIR:teams:112233-D4AF37#Metros', $out);
        self::assertSame([['getFranchisePair', 3, 7]], $this->service->calls);
    }

    public function testOverLongGmNameIsRejectedBeforeLookup(): void
    {
        $out = $this->controller->handle(['op' => 'gm', 'gm' => str_repeat('x', 61)], null, null);

        self::assertSame('EMPTY:No general manager by that name.', $out);
        self::assertSame([], $this->repo->calls);
    }

    public function testBareGmOpShowsTheViewersOwnRivals(): void
    {
        self::assertSame('GM:Mover Gm', $this->controller->handle(['op' => 'gm'], '3', 'Mover Gm'));
        self::assertSame([['getTopGmRivals', 'Mover Gm', RivalriesController::TOP_LIMIT]], $this->service->calls);
    }

    public function testBareGmOpForAnonymousViewerRendersEmptyState(): void
    {
        self::assertSame('EMPTY:No general manager by that name.', $this->controller->handle(['op' => 'gm'], null, null));
        self::assertSame([], $this->service->calls);
    }

    public function testGmNamesReachServiceInStoredSpelling(): void
    {
        self::assertSame('GM:Mover Gm', $this->controller->handle(['op' => 'gm', 'gm' => 'mover gm'], null, null));
        $this->controller->handle(['op' => 'gms', 'a' => 'MOVER GM', 'b' => 'rival gm'], null, null);

        self::assertSame(
            [['getTopGmRivals', 'Mover Gm', RivalriesController::TOP_LIMIT], ['getGmPair', 'Mover Gm', 'Rival Gm']],
            $this->service->calls,
        );
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
        self::assertSame('PAIR:gms:e9eaeb-e9eaeb', $this->controller->handle(['op' => 'gms', 'a' => 'Mover Gm', 'b' => 'Rival Gm'], null, null));
    }

    public function testGmPairUsesColorFranchiseOrGrayWhenNone(): void
    {
        $this->repo->colorFranchises = ['Mover Gm' => 3];

        self::assertSame(
            'PAIR:gms:112233-e9eaeb',
            $this->controller->handle(['op' => 'gms', 'a' => 'Mover Gm', 'b' => 'Rival Gm'], null, null),
        );
        self::assertSame(
            'PAIR:gms:e9eaeb-112233',
            $this->controller->handle(['op' => 'gms', 'a' => 'Rival Gm', 'b' => 'Mover Gm'], null, null),
        );
    }

    public function testGmWithColorFranchiseLackingColorRowGetsFallbackTeamColor(): void
    {
        $this->repo->colorFranchises = ['Mover Gm' => 7];

        self::assertSame(
            'PAIR:gms:D4AF37-e9eaeb',
            $this->controller->handle(['op' => 'gms', 'a' => 'Mover Gm', 'b' => 'Rival Gm'], null, null),
        );
    }

    public function testPairPagesPassTheViewerKeyForTheirKind(): void
    {
        self::assertSame('PAIR:teams:112233-D4AF37@3#Metros', $this->controller->handle(['op' => 'teams', 'a' => '3', 'b' => '7'], '3', 'Mover Gm'));
        self::assertSame('PAIR:gms:e9eaeb-e9eaeb@Mover Gm', $this->controller->handle(['op' => 'gms', 'a' => 'Mover Gm', 'b' => 'Rival Gm'], '3', 'Mover Gm'));
    }

    public function testArrayGmParamIsRejected(): void
    {
        $out = $this->controller->handle(['op' => 'gm', 'gm' => ['x']], null, null);

        self::assertSame('EMPTY:No general manager by that name.', $out);
        self::assertSame([], $this->repo->calls);
    }

    /**
     * @return array{a: string, b: string, a_label: string, b_label: string, games: int, a_wins: int, b_wins: int, score: int}
     */
    private static function rivalry(string $a, string $b): array
    {
        return ['a' => $a, 'b' => $b, 'a_label' => $a, 'b_label' => $b, 'games' => 12, 'a_wins' => 7, 'b_wins' => 5, 'score' => 40];
    }

    public function testUnknownOpFallsBackToIndex(): void
    {
        self::assertSame('INDEX', $this->controller->handle(['op' => 'drop'], null, null));
        self::assertSame('INDEX', $this->controller->handle(['op' => ['teams']], null, null));
    }
}

final class ControllerFakeService implements RivalriesServiceInterface
{
    /** @var list<array{a: string, b: string, a_label: string, b_label: string, games: int, a_wins: int, b_wins: int, score: int}> */
    public array $franchiseRivals = [];
    /** @var list<array{a: string, b: string, a_label: string, b_label: string, games: int, a_wins: int, b_wins: int, score: int}> */
    public array $gmRivals = [];

    /** @var list<list<int|string>> */
    public array $calls = [];

    public function getLeagueTop(int $limit): array
    {
        return ['franchises' => [], 'gms' => []];
    }

    public function getTopFranchiseRivals(int $teamId, int $limit): array
    {
        $this->calls[] = ['getTopFranchiseRivals', $teamId, $limit];
        return $this->franchiseRivals;
    }

    public function getTopGmRivals(string $gmName, int $limit): array
    {
        $this->calls[] = ['getTopGmRivals', $gmName, $limit];
        return $this->gmRivals;
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
    /** @var array<string, mixed> */
    public array $teamArgs = [];
    /** @var array<array-key, string> */
    public array $gmSideColors = [];
    public ?string $gmUserGm = 'renderGm not called';

    public function renderIndex(array $league, ?string $userTeamKey, ?string $userGm): string
    {
        return 'INDEX';
    }

    public function renderPair(?array $page, string $kind, ?array $colors = null, array $teamColors = [], ?string $viewerKey = null, array $shortLabels = []): string
    {
        return 'PAIR:' . $kind . ($colors === null ? '' : ':' . $colors['a'] . '-' . $colors['b'])
            . ($viewerKey === null ? '' : '@' . $viewerKey)
            . ($shortLabels === [] ? '' : '#' . implode(',', $shortLabels));
    }

    public function renderGm(string $gmName, array $rivals, bool $known, ?string $userGm, array $sideColors = []): string
    {
        $this->gmSideColors = $sideColors;
        $this->gmUserGm = $userGm;

        return 'GM:' . $gmName;
    }

    public function renderTeam(
        string $teamLabel,
        array $teamRivals,
        array $teamSideColors,
        ?string $gmName,
        array $gmRivals,
        array $gmSideColors,
        ?string $userTeamKey,
        ?string $userGm,
        ?string $captionColor = null,
    ): string {
        $this->teamArgs = [
            'teamRivals' => $teamRivals,
            'teamSideColors' => $teamSideColors,
            'gmName' => $gmName,
            'gmRivals' => $gmRivals,
            'gmSideColors' => $gmSideColors,
            'userTeamKey' => $userTeamKey,
            'userGm' => $userGm,
            'captionColor' => $captionColor,
        ];

        return 'TEAM:' . $teamLabel . ($userTeamKey === null ? '' : '@' . $userTeamKey);
    }

    public function renderEmpty(string $message): string
    {
        return 'EMPTY:' . $message;
    }
}
