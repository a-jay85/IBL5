<?php

declare(strict_types=1);

namespace Module;

use Utilities\HtmxHelper;

/**
 * Constant 302 targets for modules whose content moved into a host page.
 *
 * send() targets never derive from the request, so an old URL's query string is
 * dropped. passthroughUrl()/sendWithPassthrough() reflect only whitelisted,
 * non-empty string params that pass their validator, RFC 3986-encoded.
 */
final class ModuleRedirect
{
    /** @var array<string, string> old module name => new app-relative URL */
    public const TARGETS = [
        'PlayerExportGuide'  => 'modules.php?name=ApiKeys',
        'VotingResults'      => 'modules.php?name=Voting',
        'AllStarAppearances' => 'modules.php?name=RecordHolders&op=allstar',
    ];

    public static function targetFor(string $moduleName): ?string
    {
        return self::TARGETS[$moduleName] ?? null;
    }

    /** Sends the 302 for a retired module. Caller must `return` immediately after. */
    public static function send(string $moduleName): void
    {
        $target = self::targetFor($moduleName);
        if ($target === null) {
            return;
        }

        header('Location: ' . $target, true, 302);
    }

    /**
     * Builds $target plus the whitelisted request params that survive filtering.
     *
     * A param is kept only when it is present, is a string, is non-empty, and
     * (when a validator is registered for it) the validator returns true.
     * $target must already carry a `?`; kept params are joined with `&`.
     *
     * @param array<int, string> $whitelist param names allowed through
     * @param array<array-key, mixed> $request the request params to filter
     * @param array<string, callable(string): bool>|null $validators per-param predicates
     */
    public static function passthroughUrl(
        string $target,
        array $whitelist,
        array $request,
        ?array $validators = null
    ): string {
        $params = [];
        foreach ($whitelist as $param) {
            $value = $request[$param] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }
            $validator = $validators[$param] ?? null;
            if ($validator !== null && $validator($value) !== true) {
                continue;
            }
            $params[$param] = $value;
        }

        if ($params === []) {
            return $target;
        }

        return $target . '&' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Redirects to passthroughUrl() built from the supplied $request array.
     * Pass `$_GET + $_POST` (GET wins on a key collision) at the call site so
     * this method stays free of direct superglobal access.
     * HTMX requests get HX-Redirect; others get a Location header with $status.
     * Caller must `return` immediately after.
     *
     * @param array<int, string> $whitelist
     * @param array<array-key, mixed> $request merged request params (e.g. $_GET + $_POST)
     * @param array<string, callable(string): bool>|null $validators
     */
    public static function sendWithPassthrough(
        string $target,
        array $whitelist,
        array $request,
        ?array $validators = null,
        int $status = 302
    ): void {
        $url = self::passthroughUrl($target, $whitelist, $request, $validators);

        if (HtmxHelper::isHtmxRequest()) {
            header('HX-Redirect: ' . $url);
            return;
        }

        header('Location: ' . $url, true, $status);
    }
}
