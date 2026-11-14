<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the Records module entry point.
 */
final class RecordsFactory implements \Module\Contracts\ModuleFactoryInterface
{
    /** The controller takes its tab renderers from the entry point and needs no shared services. */
    public function __construct(\Module\ModuleServices $services)
    {
    }

    /**
     * @param array<string, \Closure(array<mixed>): string> $renderers One renderer per tab key
     */
    public function controller(array $renderers): \Records\RecordsController
    {
        return new \Records\RecordsController($renderers);
    }
}
