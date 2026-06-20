<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans direct access to superglobals outside the sanctioned input-boundary layer.
 *
 * Each superglobal has its own allowlist because the legitimate access sites differ:
 * $_GET/$_POST are boundary-input; $_SESSION is auth/flash; $_SERVER is environment.
 * A single merged allowlist would allow any boundary file to read any superglobal,
 * defeating the purpose of narrowing the access surface.
 *
 * @implements Rule<Variable>
 */
final class BanRawSuperglobalsRule implements Rule
{
    /**
     * Per-superglobal allowlists.
     *
     * @var array<string, array{suffixes: list<string>, files: list<string>}>
     */
    private const ALLOWLIST_BY_SUPERGLOBAL = [
        '_GET' => [
            'suffixes' => ['Controller.php', 'ApiHandler.php', 'Bootstrap.php', 'Authenticator.php'],
            'files' => ['CsrfGuard.php', 'LeagueContext.php', 'TestCookieOverrides.php'],
        ],
        '_POST' => [
            'suffixes' => ['Controller.php', 'ApiHandler.php', 'Bootstrap.php', 'Authenticator.php'],
            'files' => ['CsrfGuard.php', 'TestCookieOverrides.php'],
        ],
        '_REQUEST' => [
            'suffixes' => ['Controller.php', 'ApiHandler.php', 'Bootstrap.php'],
            'files' => [],
        ],
        '_COOKIE' => [
            'suffixes' => ['Controller.php', 'ApiHandler.php', 'Bootstrap.php', 'Authenticator.php'],
            'files' => ['CsrfGuard.php', 'LeagueContext.php', 'TestCookieOverrides.php', 'DevAutoLogin.php'],
        ],
        '_SESSION' => [
            'suffixes' => ['Bootstrap.php'],
            'files' => ['CsrfGuard.php', 'AuthService.php', 'DevAutoLogin.php', 'LeagueContext.php', 'PageLayout.php', 'UserContextProcessor.php', 'DepthChartEntryController.php', 'TradingController.php', 'DebugSession.php'],
        ],
        '_SERVER' => [
            'suffixes' => ['Bootstrap.php', 'ApiHandler.php', 'Controller.php', 'Authenticator.php'],
            'files' => ['HtmxHelper.php', 'ETagHandler.php', 'LeagueContext.php', 'PageLayout.php', 'DevAutoLogin.php', 'ApiApplicationFactory.php', 'WebApplicationFactory.php'],
        ],
        '_FILES' => [
            'suffixes' => ['Bootstrap.php', 'Controller.php'],
            'files' => [],
        ],
        'GLOBALS' => [
            'suffixes' => ['Bootstrap.php'],
            'files' => ['ApiApplicationFactory.php'],
        ],
    ];

    public function getNodeType(): string
    {
        return Variable::class;
    }

    /**
     * @param Variable $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!is_string($node->name)) {
            return [];
        }

        if (!isset(self::ALLOWLIST_BY_SUPERGLOBAL[$node->name])) {
            return [];
        }

        $file = $scope->getFile();

        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        $allowlist = self::ALLOWLIST_BY_SUPERGLOBAL[$node->name];

        foreach ($allowlist['suffixes'] as $suffix) {
            if (str_ends_with($file, $suffix)) {
                return [];
            }
        }
        foreach ($allowlist['files'] as $allowedFile) {
            if (str_ends_with($file, DIRECTORY_SEPARATOR . $allowedFile)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(
                'Direct $' . $node->name . ' access is banned outside the HTTP '
                . 'boundary layer (Controllers, ApiHandlers, Bootstraps, Authenticators). '
                . 'Accept typed inputs as parameters instead.'
            )
                ->identifier('ibl.rawSuperglobal')
                ->build(),
        ];
    }
}
