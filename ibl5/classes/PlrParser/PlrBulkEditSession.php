<?php

declare(strict_types=1);

namespace PlrParser;

/**
 * Shared open/write/backup discipline for the bulk in-place IBL5.plr editor scripts.
 *
 * Dry run opens the file read-only and turns writes into forward seeks, so a script's own
 * relative fseek arithmetic lands on the same offsets as a live run. A live run first copies
 * the file to a size- and sha256-verified backup, then opens it read-write.
 */
final class PlrBulkEditSession
{
    public const DRY_RUN_FLAG = '--dry-run';

    private ?string $backupPath = null;

    /**
     * @param \Closure(string, string): bool $copier
     */
    private function __construct(
        private readonly string $scriptTag,
        private readonly bool $dryRun,
        private readonly string $plrPath,
        private readonly \Closure $copier,
        private readonly string $timestamp,
    ) {
    }

    /**
     * @param list<string> $args argv minus argv[0]
     * @param (\Closure(string, string): bool)|null $copier test seam; default wraps copy()
     * @throws \InvalidArgumentException on any arg other than exactly zero args or one '--dry-run'
     */
    public static function fromCliArgs(
        string $scriptTag,
        array $args,
        string $plrPath = 'IBL5.plr',
        ?\Closure $copier = null,
        ?string $timestamp = null,
    ): self {
        if ($args !== [] && $args !== [self::DRY_RUN_FLAG]) {
            throw new \InvalidArgumentException('Usage: php <script> [--dry-run]');
        }

        return new self(
            $scriptTag,
            $args === [self::DRY_RUN_FLAG],
            $plrPath,
            $copier ?? static fn (string $from, string $to): bool => @copy($from, $to),
            $timestamp ?? date('Ymd-His', (new \Clock\SystemClock())->now()),
        );
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * Path of the verified backup, or null (dry run, or before open()).
     */
    public function backupPath(): ?string
    {
        return $this->backupPath;
    }

    /**
     * @return resource
     * @throws \RuntimeException source missing/unreadable, backup failed, or fopen failed
     */
    public function open()
    {
        if (!is_file($this->plrPath) || !is_readable($this->plrPath)) {
            throw new \RuntimeException("PLR file not found or unreadable: {$this->plrPath}");
        }

        if (!$this->dryRun) {
            $this->createVerifiedBackup();
        }

        $handle = @fopen($this->plrPath, $this->dryRun ? 'rb' : 'rb+');
        if ($handle === false) {
            throw new \RuntimeException("Could not open PLR file: {$this->plrPath}");
        }

        return $handle;
    }

    /**
     * @param resource $handle
     * @throws \RuntimeException when a live write is short
     */
    public function write($handle, string $bytes): void
    {
        if ($this->dryRun) {
            fseek($handle, strlen($bytes), SEEK_CUR);

            return;
        }

        if (fwrite($handle, $bytes) !== strlen($bytes)) {
            throw new \RuntimeException("Short write to PLR file: {$this->plrPath}");
        }
    }

    private function createVerifiedBackup(): void
    {
        $backupPath = dirname($this->plrPath) . '/' . basename($this->plrPath, '.plr')
            . '.pre-' . $this->scriptTag . '-' . $this->timestamp . '.plr';

        if (file_exists($backupPath)) {
            throw new \RuntimeException("Backup already exists, refusing to overwrite: $backupPath");
        }

        if (($this->copier)($this->plrPath, $backupPath) !== true) {
            throw new \RuntimeException("Could not copy {$this->plrPath} to $backupPath");
        }

        clearstatcache();
        $sourceSize = filesize($this->plrPath);
        $backupSize = filesize($backupPath);
        if ($backupSize !== $sourceSize) {
            @unlink($backupPath);
            throw new \RuntimeException("Backup size mismatch: source $sourceSize, backup " . var_export($backupSize, true));
        }

        $sourceHash = hash_file('sha256', $this->plrPath);
        $backupHash = hash_file('sha256', $backupPath);
        if ($backupHash !== $sourceHash) {
            @unlink($backupPath);
            throw new \RuntimeException("Backup hash mismatch: source $sourceHash, backup " . var_export($backupHash, true));
        }

        $this->backupPath = $backupPath;
    }
}
