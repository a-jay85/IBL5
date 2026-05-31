<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<String_>
 */
final class BanHardcodedEnvironmentStringsRule implements Rule
{
    private const BANNED_VALUES = [
        'localhost',
        '127.0.0.1',
        'iblhoops.net',
        'www.iblhoops.net',
        'main.localhost',
    ];

    private const ALLOWED_FILE_SUFFIXES = [
        // Bootstrap classes are the env boundary — they read SERVER_NAME and decide
        // production vs. local (HeadersBootstrap, TestConfigBootstrap, etc.).
        'Bootstrap.php',
    ];

    private const ALLOWED_FILES = [
        // Canonical dev-only auto-login gate, keyed on localhost.
        'DevAutoLogin.php',
        // Canonical production env-check home (isProduction()).
        'Discord.php',
    ];

    public function getNodeType(): string
    {
        return String_::class;
    }

    /**
     * @param String_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!in_array($node->value, self::BANNED_VALUES, true)) {
            return [];
        }

        $file = $scope->getFile();

        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        foreach (self::ALLOWED_FILE_SUFFIXES as $suffix) {
            if (str_ends_with($file, $suffix)) {
                return [];
            }
        }

        foreach (self::ALLOWED_FILES as $allowedFile) {
            if (str_ends_with($file, DIRECTORY_SEPARATOR . $allowedFile)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(
                'Hardcoded environment string "' . $node->value . '" is banned. '
                . 'Inject an environment/config flag instead of branching on a literal host name.'
            )
                ->identifier('ibl.hardcodedEnvString')
                ->build(),
        ];
    }
}
