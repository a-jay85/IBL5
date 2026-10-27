<?php

declare(strict_types=1);

namespace Tests\Module;

use Bootstrap\Container;
use Module\ModuleServices;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Module\Fixtures\FixtureModuleFactory;
use Tests\WideUnit\Mocks\MockDatabase;

class ModuleServicesTest extends TestCase
{
    protected function tearDown(): void
    {
        ModuleServices::clearCurrent();
        parent::tearDown();
    }

    /**
     * A container holding the base services plus the shared services, the way
     * the front controller wires it.
     */
    private function buildContainer(): Container
    {
        $c = new Container();
        $c->set('mysqli_db', static fn (): \mysqli => new MockDatabase());
        $c->set('season', static fn (): \Season\Season => new \Season\Season(new MockDatabase()));
        ModuleServices::registerSharedServices($c);

        return $c;
    }

    public function testCurrentThrowsWhenNothingPublished(): void
    {
        ModuleServices::clearCurrent();

        try {
            ModuleServices::current();
        } catch (\LogicException $e) {
            $this->assertStringContainsString('ModuleServices not published', $e->getMessage());
            return;
        }

        self::fail('current() must throw when nothing was published');
    }

    public function testCurrentReturnsPublishedInstance(): void
    {
        $services = new ModuleServices(new Container());

        ModuleServices::publish($services);

        $this->assertSame($services, ModuleServices::current());

        ModuleServices::clearCurrent();

        $this->expectException(\LogicException::class);
        ModuleServices::current();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sharedServiceProvider(): array
    {
        return [
            'teamIdentity' => ['teamIdentity'],
            'salaryCap' => ['salaryCap'],
            'playerLookup' => ['playerLookup'],
            'nukeCompat' => ['nukeCompat'],
            'request' => ['request'],
            'season' => ['season'],
        ];
    }

    #[DataProvider('sharedServiceProvider')]
    public function testSharedServiceIsBuiltOncePerRequest(string $accessor): void
    {
        $services = new ModuleServices($this->buildContainer());

        $resolve = static fn (): object => match ($accessor) {
            'teamIdentity' => $services->teamIdentity(),
            'salaryCap' => $services->salaryCap(),
            'playerLookup' => $services->playerLookup(),
            'nukeCompat' => $services->nukeCompat(),
            'request' => $services->request(),
            'season' => $services->season(),
            default => throw new \InvalidArgumentException($accessor),
        };

        $first = $resolve();
        $second = $resolve();

        $this->assertSame($first, $second);
    }

    public function testRegisterSharedServicesConstructsNothing(): void
    {
        $dbBuilds = new class {
            public int $count = 0;
        };
        $c = new Container();
        $c->set('mysqli_db', static function () use ($dbBuilds): \mysqli {
            $dbBuilds->count++;
            throw new \RuntimeException('mysqli_db must not be resolved at registration');
        });

        ModuleServices::registerSharedServices($c);

        $this->assertSame(0, $dbBuilds->count);
        foreach (['repo.team_identity', 'repo.salary_cap', 'repo.player_lookup', 'nuke_compat', 'http.request'] as $id) {
            $this->assertTrue($c->has($id), "expected '{$id}' to be registered");
        }

        // Resolving a repository is what finally builds the connection.
        $services = new ModuleServices($c);
        try {
            $services->teamIdentity();
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('mysqli_db must not be resolved', $e->getMessage());
        }
        $this->assertSame(1, $dbBuilds->count);
    }

    public function testTypedAccessorRejectsWrongServiceType(): void
    {
        $c = new Container();
        $c->set('mysqli_db', new \stdClass());
        $services = new ModuleServices($c);

        try {
            $services->db();
        } catch (\LogicException $e) {
            $this->assertStringContainsString("'mysqli_db'", $e->getMessage());
            return;
        }

        self::fail('db() must reject a service that is not a mysqli');
    }

    public function testFactoryIsResolvedByClassNameAndCached(): void
    {
        $services = new ModuleServices(new Container());

        $factory = $services->factory(FixtureModuleFactory::class);

        $this->assertSame($services, $factory->services());
        $this->assertSame($factory, $services->factory(FixtureModuleFactory::class));
    }

    public function testAuthAndLeagueContextComeFromContainer(): void
    {
        $auth = self::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $leagueContext = self::createStub(\League\LeagueContext::class);
        $c = new Container();
        $c->set('authService', $auth);
        $c->set('leagueContext', $leagueContext);
        $services = new ModuleServices($c);

        $this->assertSame($auth, $services->auth());
        $this->assertSame($leagueContext, $services->leagueContext());
    }
}
