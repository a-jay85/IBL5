<?php

declare(strict_types=1);

namespace Module\Factories;

use GMContactList\GMContactListRepository;
use GMContactList\GMContactListView;

/**
 * Composition for the GMContactList module entry point.
 */
final class GMContactListFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function repository(): GMContactListRepository
    {
        return new GMContactListRepository($this->services->db());
    }

    public function view(): GMContactListView
    {
        return new GMContactListView();
    }
}
