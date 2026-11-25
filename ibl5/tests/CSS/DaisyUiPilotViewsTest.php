<?php

declare(strict_types=1);

namespace Tests\CSS;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that the daisyUI pilot views dropped the legacy ibl-* component
 * classes and render daisyUI d-* components instead.
 */
class DaisyUiPilotViewsTest extends TestCase
{
    /**
     * Mutation: revert one `d-btn d-btn-primary` to `ibl-btn ibl-btn--primary`.
     */
    #[DataProvider('pilotViewProvider')]
    public function testPilotViewUsesNoLegacyIblComponentClass(string $filePath): void
    {
        $content = self::read($filePath);

        preg_match_all('/\bibl-(btn|card|alert|label|input|filter-form|filter-fieldset)\b/', $content, $matches);

        $this->assertSame([], $matches[0], basename($filePath) . ' still uses legacy ibl-* component classes');
    }

    /**
     * Mutation: restore the master view (it has no d-* classes).
     */
    #[DataProvider('pilotViewProvider')]
    public function testPilotViewUsesDaisyUiClasses(string $filePath): void
    {
        $content = self::read($filePath);

        $this->assertMatchesRegularExpression('/\bd-(btn|label|card|alert)\b/', $content);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pilotViewProvider(): array
    {
        $classes = dirname(__DIR__, 2) . '/classes';

        return [
            'PlayerSearchView' => [$classes . '/PlayerSearch/PlayerSearchView.php'],
            'FreeAgencyOfferView' => [$classes . '/FreeAgency/FreeAgencyOfferView.php'],
            'FreeAgencyFormView' => [$classes . '/FreeAgency/FreeAgencyFormView.php'],
            'uploadDraftClass' => [dirname(__DIR__, 2) . '/uploadDraftClass.php'],
            'TransactionHistoryView' => [$classes . '/TransactionHistory/TransactionHistoryView.php'],
            'LeagueConfigView' => [$classes . '/LeagueConfig/LeagueConfigView.php'],
            'PageLayout' => [$classes . '/PageLayout/PageLayout.php'],
            'DepthChartController' => [$classes . '/DepthChart/DepthChartController.php'],
            'LastSimRecapView' => [$classes . '/LastSimRecap/LastSimRecapView.php'],
            'LoginView' => [$classes . '/YourAccount/LoginView.php'],
            'RegistrationView' => [$classes . '/YourAccount/RegistrationView.php'],
            'PasswordResetView' => [$classes . '/YourAccount/PasswordResetView.php'],
            'ActivationView' => [$classes . '/YourAccount/ActivationView.php'],
            'AwardHistoryView' => [$classes . '/AwardHistory/AwardHistoryView.php'],
            'CareerLeaderboardsView' => [$classes . '/CareerLeaderboards/CareerLeaderboardsView.php'],
            'SeasonLeaderboardsView' => [$classes . '/SeasonLeaderboards/SeasonLeaderboardsView.php'],
            'ComparePlayersView' => [$classes . '/ComparePlayers/ComparePlayersView.php'],
        ];
    }

    /**
     * Views whose form controls are converted but whose ibl-card / ibl-alert
     * result markup belongs to a later batch. Move an entry into
     * pilotViewProvider() when that batch converts the cards.
     *
     * Mutation: revert the gameid input to `ibl-input ibl-input--sm`.
     */
    #[DataProvider('filterFormOnlyViewProvider')]
    public function testFilterFormOnlyViewUsesNoLegacyFormClass(string $filePath): void
    {
        $content = self::read($filePath);

        preg_match_all('/\bibl-(btn|label|input|select|filter-form|filter-fieldset)\b/', $content, $matches);

        $this->assertSame([], $matches[0], basename($filePath) . ' still uses legacy ibl-* form-control classes');
    }

    /**
     * Mutation: strip `d-btn` from both OneOnOneGame submit buttons.
     */
    #[DataProvider('filterFormOnlyViewProvider')]
    public function testFilterFormOnlyViewUsesDaisyUiFormClasses(string $filePath): void
    {
        $content = self::read($filePath);

        $this->assertMatchesRegularExpression('/\bd-btn\b/', $content);
        $this->assertMatchesRegularExpression('/\bd-(select|input)\b/', $content);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function filterFormOnlyViewProvider(): array
    {
        return [
            'OneOnOneGameView' => [dirname(__DIR__, 2) . '/classes/OneOnOneGame/OneOnOneGameView.php'],
        ];
    }

    private static function read(string $filePath): string
    {
        self::assertFileExists($filePath);
        $content = file_get_contents($filePath); // phpunit-hygiene-allow: the property is the view's class vocabulary, which no render seam exposes per file
        self::assertIsString($content);

        return $content;
    }
}
