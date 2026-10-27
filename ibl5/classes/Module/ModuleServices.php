<?php

declare(strict_types=1);

namespace Module;

use Bootstrap\Contracts\ContainerInterface;

/**
 * Request-scoped, typed facade over the service container for module entry points.
 *
 * modules.php publishes one instance after the page-cache HIT exit, so a cache hit
 * constructs nothing. Module factories resolve their shared collaborators here
 * instead of calling `new` in index.php. One build per request comes from the
 * container's lazy caching, never from a second cache in this class.
 */
final class ModuleServices
{
    private static ?self $current = null;

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public static function publish(self $services): void
    {
        self::$current = $services;
    }

    public static function current(): self
    {
        if (self::$current === null) {
            throw new \LogicException(
                'ModuleServices not published: modules.php publishes it before including a module file'
            );
        }

        return self::$current;
    }

    public static function clearCurrent(): void
    {
        self::$current = null;
    }

    /**
     * Register the lazy shared services. No closure runs at registration.
     */
    public static function registerSharedServices(ContainerInterface $c): void
    {
        $c->set('repo.team_identity', static function (ContainerInterface $c): \Repositories\TeamIdentityRepository {
            $db = $c->get('mysqli_db');
            if (!$db instanceof \mysqli) {
                throw new \LogicException("Container entry 'mysqli_db' is not a mysqli");
            }
            return new \Repositories\TeamIdentityRepository($db);
        });

        $c->set('repo.salary_cap', static function (ContainerInterface $c): \Repositories\SalaryCapRepository {
            $db = $c->get('mysqli_db');
            if (!$db instanceof \mysqli) {
                throw new \LogicException("Container entry 'mysqli_db' is not a mysqli");
            }
            return new \Repositories\SalaryCapRepository($db);
        });

        $c->set('repo.player_lookup', static function (ContainerInterface $c): \Repositories\PlayerLookupRepository {
            $db = $c->get('mysqli_db');
            if (!$db instanceof \mysqli) {
                throw new \LogicException("Container entry 'mysqli_db' is not a mysqli");
            }
            return new \Repositories\PlayerLookupRepository($db);
        });

        $c->set('nuke_compat', static fn (): \Utilities\NukeCompat => new \Utilities\NukeCompat());

        $c->set('http.request', static fn (): \Http\HttpRequest => \Http\HttpRequest::fromGlobals());
    }

    public function db(): \mysqli
    {
        return $this->typed('mysqli_db', \mysqli::class);
    }

    public function season(): \Season\Season
    {
        return $this->typed('season', \Season\Season::class);
    }

    public function teamIdentity(): \Repositories\TeamIdentityRepository
    {
        return $this->typed('repo.team_identity', \Repositories\TeamIdentityRepository::class);
    }

    public function salaryCap(): \Repositories\SalaryCapRepository
    {
        return $this->typed('repo.salary_cap', \Repositories\SalaryCapRepository::class);
    }

    public function playerLookup(): \Repositories\PlayerLookupRepository
    {
        return $this->typed('repo.player_lookup', \Repositories\PlayerLookupRepository::class);
    }

    public function nukeCompat(): \Utilities\NukeCompat
    {
        return $this->typed('nuke_compat', \Utilities\NukeCompat::class);
    }

    public function request(): \Http\HttpRequest
    {
        return $this->typed('http.request', \Http\HttpRequest::class);
    }

    public function auth(): \Auth\Contracts\AuthServiceInterface
    {
        return $this->typed('authService', \Auth\Contracts\AuthServiceInterface::class);
    }

    public function leagueContext(): \League\LeagueContext
    {
        return $this->typed('leagueContext', \League\LeagueContext::class);
    }

    /**
     * Resolve a module factory by class name, building it once per request.
     *
     * @template T of Contracts\ModuleFactoryInterface
     * @param class-string<T> $factoryClass
     * @return T
     */
    public function factory(string $factoryClass): Contracts\ModuleFactoryInterface
    {
        if (!$this->container->has($factoryClass)) {
            $this->container->set($factoryClass, fn (): Contracts\ModuleFactoryInterface => new $factoryClass($this));
        }

        return $this->typed($factoryClass, $factoryClass);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    private function typed(string $id, string $type): object
    {
        $service = $this->container->get($id);
        if (!$service instanceof $type) {
            throw new \LogicException(
                "Container entry '{$id}' is not an instance of {$type}"
            );
        }

        return $service;
    }
}
