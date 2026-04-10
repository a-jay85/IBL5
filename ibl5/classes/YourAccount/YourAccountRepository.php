<?php

declare(strict_types=1);

namespace YourAccount;

use YourAccount\Contracts\YourAccountRepositoryInterface;

/**
 * @see YourAccountRepositoryInterface
 */
class YourAccountRepository extends \BaseMysqliRepository implements YourAccountRepositoryInterface
{
}
