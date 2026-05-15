<?php

declare(strict_types=1);

namespace Auth;

use Auth\Contracts\AuthRepositoryInterface;

final class AuthRepository extends \BaseMysqliRepository implements AuthRepositoryInterface
{
    /**
     * @see AuthRepositoryInterface::findUserRolesByUsername()
     */
    public function findUserRolesByUsername(string $username): ?array
    {
        /** @var array{roles_mask: int}|null */
        return $this->fetchOne(
            "SELECT `roles_mask` FROM `auth_users` WHERE `username` = ?",
            's',
            $username
        );
    }

    /**
     * @see AuthRepositoryInterface::findUserInfo()
     */
    public function findUserInfo(string $username): ?array
    {
        /** @var array{user_id: int, username: string, user_email: string}|null */
        return $this->fetchOne(
            "SELECT `id` AS `user_id`, `username`, `email` AS `user_email` FROM `auth_users` WHERE `username` = ?",
            's',
            $username
        );
    }
}
