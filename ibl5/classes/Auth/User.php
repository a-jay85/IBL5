<?php

declare(strict_types=1);

namespace Auth;

/**
 * User model for Laravel Auth integration
 *
 * Represents a user in the IBL5 system with role-based permissions
 * and team ownership capabilities.
 */
class User
{
    public const ROLE_SPECTATOR = 'spectator';
    public const ROLE_OWNER = 'owner';
    public const ROLE_COMMISSIONER = 'commissioner';

    private int $id;
    private string $name;
    private string $email;
    private ?string $password;
    private ?string $legacyPassword;
    private string $role;
    /** @var array<string> */
    private array $teamsOwned;
    private ?int $nukeUserId;
    private ?\DateTimeInterface $migratedAt;
    private ?\DateTimeInterface $emailVerifiedAt;
    private ?string $rememberToken;
    private ?\DateTimeInterface $createdAt;
    private ?\DateTimeInterface $updatedAt;

    /**
     * @param array<string, mixed> $data User data from database
     */
    public function __construct(array $data = [])
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->name = (string) ($data['name'] ?? '');
        $this->email = (string) ($data['email'] ?? '');
        $this->password = $data['password'] ?? null;
        $this->legacyPassword = $data['legacy_password'] ?? null;
        $this->role = (string) ($data['role'] ?? self::ROLE_SPECTATOR);
        $this->teamsOwned = $this->parseTeamsOwned($data['teams_owned'] ?? null);
        $this->nukeUserId = isset($data['nuke_user_id']) ? (int) $data['nuke_user_id'] : null;
        $this->migratedAt = $this->parseDateTime($data['migrated_at'] ?? null);
        $this->emailVerifiedAt = $this->parseDateTime($data['email_verified_at'] ?? null);
        $this->rememberToken = $data['remember_token'] ?? null;
        $this->createdAt = $this->parseDateTime($data['created_at'] ?? null);
        $this->updatedAt = $this->parseDateTime($data['updated_at'] ?? null);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getLegacyPassword(): ?string
    {
        return $this->legacyPassword;
    }

    public function hasLegacyPassword(): bool
    {
        return $this->legacyPassword !== null && $this->legacyPassword !== '';
    }

    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @return array<string>
     */
    public function getTeamsOwned(): array
    {
        return $this->teamsOwned;
    }

    public function getNukeUserId(): ?int
    {
        return $this->nukeUserId;
    }

    public function getMigratedAt(): ?\DateTimeInterface
    {
        return $this->migratedAt;
    }

    public function isMigrated(): bool
    {
        return $this->migratedAt !== null;
    }

    public function getEmailVerifiedAt(): ?\DateTimeInterface
    {
        return $this->emailVerifiedAt;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Check if user is an admin (commissioner)
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_COMMISSIONER;
    }

    /**
     * Check if user is a team owner
     */
    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER || $this->role === self::ROLE_COMMISSIONER;
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        // Commissioners have all roles
        if ($this->role === self::ROLE_COMMISSIONER) {
            return true;
        }

        // Owners have owner and spectator roles
        if ($this->role === self::ROLE_OWNER) {
            return in_array($role, [self::ROLE_OWNER, self::ROLE_SPECTATOR], true);
        }

        // Spectators only have spectator role
        return $role === self::ROLE_SPECTATOR;
    }

    /**
     * Check if user owns a specific team
     *
     * @param int|string $teamId Team ID or abbreviation
     */
    public function ownsTeam(int|string $teamId): bool
    {
        // Commissioners can manage all teams
        if ($this->role === self::ROLE_COMMISSIONER) {
            return true;
        }

        return in_array((string) $teamId, $this->teamsOwned, true);
    }

    /**
     * Parse teams_owned JSON field
     *
     * @param mixed $teamsOwned JSON string or array
     * @return array<string>
     */
    private function parseTeamsOwned(mixed $teamsOwned): array
    {
        if ($teamsOwned === null) {
            return [];
        }

        if (is_array($teamsOwned)) {
            return array_map('strval', $teamsOwned);
        }

        if (is_string($teamsOwned)) {
            $decoded = json_decode($teamsOwned, true);
            if (is_array($decoded)) {
                return array_map('strval', $decoded);
            }
            // Legacy: comma-separated string
            if (str_contains($teamsOwned, ',')) {
                return array_map('trim', explode(',', $teamsOwned));
            }
            return $teamsOwned !== '' ? [$teamsOwned] : [];
        }

        return [];
    }

    /**
     * Parse datetime string to DateTimeInterface
     */
    private function parseDateTime(mixed $datetime): ?\DateTimeInterface
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }

        if ($datetime instanceof \DateTimeInterface) {
            return $datetime;
        }

        try {
            return new \DateTimeImmutable((string) $datetime);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Convert to array for database operations
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'legacy_password' => $this->legacyPassword,
            'role' => $this->role,
            'teams_owned' => json_encode($this->teamsOwned),
            'nuke_user_id' => $this->nukeUserId,
            'migrated_at' => $this->migratedAt?->format('Y-m-d H:i:s'),
            'email_verified_at' => $this->emailVerifiedAt?->format('Y-m-d H:i:s'),
            'remember_token' => $this->rememberToken,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
