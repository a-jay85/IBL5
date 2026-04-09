<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans `require`, `require_once`, `include`, and `include_once` statements in
 * files under classes/. All classes in ibl5/classes/ are autoloaded via Composer
 * PSR-4 mapping from the `""` namespace, so manual includes are unnecessary and
 * defeat the autoloader.
 *
 * @implements Rule<Include_>
 */
final class BanRequireOnceRule implements Rule
{
    /** @var array<int, string> */
    private const TYPE_LABELS = [
        Include_::TYPE_INCLUDE => 'include',
        Include_::TYPE_INCLUDE_ONCE => 'include_once',
        Include_::TYPE_REQUIRE => 'require',
        Include_::TYPE_REQUIRE_ONCE => 'require_once',
    ];

    public function getNodeType(): string
    {
        return Include_::class;
    }

    /**
     * @param Include_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Only enforce in classes/
        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        $label = self::TYPE_LABELS[$node->type] ?? 'require_once';

        return [
            RuleErrorBuilder::message(
                'Direct `' . $label . '` is banned in classes/. All classes under '
                . 'ibl5/classes/ autoload via Composer PSR-4. Remove the statement '
                . 'and rely on autoload instead.'
            )
                ->identifier('ibl.requireOnce')
                ->build(),
        ];
    }
}
