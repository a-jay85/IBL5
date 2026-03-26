<?php

declare(strict_types=1);

namespace YourAccount;

use YourAccount\Contracts\YourAccountRepositoryInterface;

/**
 * @see YourAccountRepositoryInterface
 */
class YourAccountRepository extends \BaseMysqliRepository implements YourAccountRepositoryInterface
{
    /**
     * @see YourAccountRepositoryInterface::updateLastLoginIp()
     */
    public function updateLastLoginIp(string $username, string $ipAddress): void
    {
        $this->execute(
            "UPDATE nuke_users SET last_ip = ? WHERE username = ?",
            "ss",
            $ipAddress,
            $username,
        );
    }
}
