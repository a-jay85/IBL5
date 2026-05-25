<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Echo_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Echo_>
 */
final class BanEchoInNonViewClassesRule implements Rule
{
    private const ALLOWED_FILES = [
        'PageLayout.php',
        'DebugOutput.php',
        'LegacyFunctions.php',
        'BulkImportRunner.php',
        'BulkImportSummary.php',
        'Ratings.php',
        'Per36Minutes.php',
        'SeasonAverages.php',
        'SeasonTotals.php',
        'PeriodAverages.php',
        'SplitStats.php',
        'JsonResponder.php',
        'HtmlResponder.php',
        'FreeAgencyFormComponents.php',
        'PlayerSeasonTableRenderer.php',
        'Contracts.php',
        'GenerateSeasonAwardsStep.php',
    ];

    private const ALLOWED_FILE_SUFFIXES = [
        'View.php',
        'Bootstrap.php',
        'Updater.php',
        'UpdaterController.php',
    ];

    public function getNodeType(): string
    {
        return Echo_::class;
    }

    /**
     * @param Echo_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
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
                'echo is banned outside View classes, Responders, and Bootstrap/Updater emitters. '
                . 'Move HTML/text output into a *View.php (composed by the Controller) '
                . 'or a Responder (composed by the ApiHandler).'
            )
                ->identifier('ibl.echoInNonView')
                ->build(),
        ];
    }
}
