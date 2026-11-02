<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the Privacy and Terms module entry points.
 */
final class LegalFactory implements \Module\Contracts\ModuleFactoryInterface
{
    /** The static pages need no shared services. */
    public function __construct(\Module\ModuleServices $services)
    {
    }

    public function view(): \Legal\LegalView
    {
        return new \Legal\LegalView();
    }
}
