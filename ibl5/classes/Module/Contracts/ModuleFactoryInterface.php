<?php

declare(strict_types=1);

namespace Module\Contracts;

/**
 * Contract for a per-module composition factory.
 *
 * Declaring the constructor makes `new $factoryClass($services)` type-sound in
 * `ModuleServices::factory()`. A factory builds object graphs from the shared
 * services and nothing else: no superglobals, no auth interrogation, no actor
 * identity parameters.
 */
interface ModuleFactoryInterface
{
    public function __construct(\Module\ModuleServices $services);
}
