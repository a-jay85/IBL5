<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans direct now-returning time calls outside the Clock seam so new code is
 * forced through Clock\ClockInterface and stays deterministically testable.
 *
 * Fires only on *now-returning* forms:
 *   - time()                       (always — it takes no timestamp argument)
 *   - mktime() / microtime()       only with zero arguments (implicit now)
 *   - date() / gmdate()            only with <= 1 argument (no explicit timestamp)
 *
 * It deliberately does NOT flag date('Y-m-d', $ts) / gmdate(..., $ts) — those
 * pass an explicit timestamp and are already deterministic. strtotime() is not
 * matched at all: a static rule cannot distinguish relative ('+1 day') from
 * absolute ('2024-01-01') single-string arguments, so that is left to review.
 *
 * @implements Rule<FuncCall>
 */
final class BanDirectTimeCallsRule implements Rule
{
    /**
     * Files allowed to make bare now-calls.
     *  - SystemClock.php: the single sanctioned home of time() (the seam impl).
     *  - NukeCompat.php: date("Z") is a timezone offset, not a now-read.
     *  - LegacyFunctions.php: already in phpstan.neon excludePaths (so the rule
     *    never analyzes it); listed here defensively for documentation parity.
     */
    private const ALLOWED_FILES = [
        'SystemClock.php',
        'NukeCompat.php',
        'LegacyFunctions.php',
    ];

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @param FuncCall $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $name = $node->name->toLowerString();
        $argCount = count($node->getArgs());

        $isBareNow = match ($name) {
            'time' => true,
            'mktime', 'microtime' => $argCount === 0,
            'date', 'gmdate' => $argCount <= 1,
            default => false,
        };

        if (!$isBareNow) {
            return [];
        }

        $file = $scope->getFile();

        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        foreach (self::ALLOWED_FILES as $allowedFile) {
            if (str_ends_with($file, DIRECTORY_SEPARATOR . $allowedFile)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(
                'Direct now-returning time call is banned outside the Clock seam. '
                . 'Inject Clock\\ClockInterface (constructor for instance classes; settable static '
                . 'clock with factory fallback for static classes) and call $clock->now(). '
                . 'Pass an explicit timestamp to date()/strtotime() for deterministic formatting.'
            )
                ->identifier('ibl.directTime')
                ->build(),
        ];
    }
}
