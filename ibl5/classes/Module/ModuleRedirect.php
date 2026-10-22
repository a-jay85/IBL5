<?php

declare(strict_types=1);

namespace Module;

/**
 * Constant 302 targets for modules whose content moved into a host page.
 *
 * send() targets never derive from the request, so an old URL's query string is
 * dropped. passthroughUrl() reflects only whitelisted,
 * non-empty string params that pass their validator, RFC 3986-encoded.
 */
final class ModuleRedirect
{
    /** @var array<string, string> old module name => new app-relative URL */
    public const TARGETS = [
        'PlayerExportGuide'  => 'modules.php?name=ApiKeys',
        'VotingResults'      => 'modules.php?name=Voting',
        'AllStarAppearances' => 'modules.php?name=RecordHolders&op=allstar',
        'CapSpace'           => 'modules.php?name=Contracts&tab=teams',
        'ContractList'       => 'modules.php?name=Contracts&tab=players',
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

        (new \Api\Response\RedirectResponder())->redirect($target);
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
}
