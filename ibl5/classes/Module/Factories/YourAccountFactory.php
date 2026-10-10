<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/YourAccount/index.php.
 *
 * The site settings are legacy globals read at the boundary; index.php passes them in.
 */
final class YourAccountFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function service(
        string $siteUrl,
        string $siteName,
        string $adminEmail,
        int $minPasswordLength
    ): \YourAccount\YourAccountService {
        return new \YourAccount\YourAccountService(
            $this->services->auth(),
            $this->services->teamIdentity(),
            \Mail\MailService::fromConfig(),
            $siteUrl,
            $siteName,
            $adminEmail,
            $minPasswordLength
        );
    }

    public function view(): \YourAccount\YourAccountView
    {
        return new \YourAccount\YourAccountView();
    }
}
