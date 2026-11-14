<?php

declare(strict_types=1);

namespace Module\Factories;

use DraftInfo\DraftInfoView;

/**
 * Composition for modules/DraftInfo/index.php.
 *
 * The admin gate lives in index.php above every factory call and reads the
 * request's shared auth service. This class never reads auth or request state.
 */
final class DraftInfoFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function tabs(): \UI\Components\PageTabs
    {
        return new \UI\Components\PageTabs(DraftInfoView::TABS, DraftInfoView::DEFAULT_TAB);
    }

    public function view(): DraftInfoView
    {
        return new DraftInfoView();
    }

    public function teamIdentity(): \Repositories\TeamIdentityRepository
    {
        return $this->services->teamIdentity();
    }
}
