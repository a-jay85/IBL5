<?php

declare(strict_types=1);

namespace Auth\Contracts;

interface AuthRepositoryInterface
{
    /**
     * Find a user's roles_mask by username.
     *
     * @return array{roles_mask: int}|null
     */
    public function findUserRolesByUsername(string $username): ?array;

    /**
     * Find user info (id, username, email) by username.
     *
     * @return array{user_id: int, username: string, user_email: string}|null
     */
    public function findUserInfo(string $username): ?array;
}
