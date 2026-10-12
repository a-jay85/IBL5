<?php

declare(strict_types=1);

namespace Extension\Contracts;

/**
 * ExtensionControllerInterface - Contract for the contract-extension POST handler
 *
 * Owns the gate order of the extension form submission and returns the PRG target;
 * the module shim performs the actual redirect.
 */
interface ExtensionControllerInterface
{
    /**
     * Run the contract-extension POST gates in order and return the PRG target.
     *
     * Gate order (do not reorder): $csrfValid -> $isUser -> $resolveUsername
     * -> session team null/''/Free Agents -> posted team === session team
     * -> processExtension(). A failing gate never invokes a later one.
     *
     * @param \Closure(): bool $csrfValid CSRF gate (CsrfGuard 'extension')
     * @param \Closure(): bool $isUser Auth gate (is_user)
     * @param \Closure(): string $resolveUsername cookiedecode() + current username
     * @param array<array-key, mixed> $post Raw POST body
     * @return string Redirect URL (always; every branch is a PRG redirect)
     */
    public function handleSubmission(\Closure $csrfValid, \Closure $isUser, \Closure $resolveUsername, array $post): string;
}
