<?php

declare(strict_types=1);

namespace Tests\Module {

    use Auth\Contracts\AuthServiceInterface;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\Attributes\PreserveGlobalState;
    use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
    use PHPUnit\Framework\TestCase;

    /**
     * Characterization and type-boundary tests for the real themes/IBL/theme.php.
     *
     * Runs in a separate process because tests/Module/EntryPoints/theme-stubs.php
     * defines guarded stand-ins for the same function names. This file is
     * strict_types, like the real callers (NewsView, categories.php, article.php),
     * so a wrong native type in theme.php surfaces as a TypeError here.
     *
     * Regenerate goldens (only from an unedited theme.php):
     *   THEME_GOLDEN_WRITE=1 vendor/bin/phpunit --filter OutputMatchesGolden tests/Module/ThemeIblOutputTest.php
     */
    #[RunTestsInSeparateProcesses]
    #[PreserveGlobalState(false)]
    class ThemeIblOutputTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            chdir(dirname(__DIR__, 2));

            $GLOBALS['tipath'] = 'images/topics/';
            $GLOBALS['anonymous'] = 'Anonymous';
            $_SERVER['SERVER_NAME'] = 'localhost';
            $_SERVER['REQUEST_URI'] = '/ibl5/index.php';

            $constants = [
                '_READS' => 'reads',
                '_NOTE' => 'Note',
                '_WRITES' => 'Writes',
                '_POSTEDON' => 'Posted on',
                '_CONTRIBUTEDBY' => 'Contributed by',
                '_TOPIC' => 'Topic',
            ];
            foreach ($constants as $name => $value) {
                if (!defined($name)) {
                    define($name, $value);
                }
            }

            // theme.php assigns its colour variables at file scope; bind them to the
            // globals the functions read via `global`.
            global $bgcolor1, $bgcolor2, $bgcolor3, $textcolor1, $textcolor2;

            // themeheader() calls is_user()/is_admin(); the real ones read $authService.
            require_once dirname(__DIR__, 2) . '/classes/Bootstrap/LegacyFunctions.php';
            require_once dirname(__DIR__, 2) . '/themes/IBL/theme.php';
        }

        /**
         * Resolve by name at runtime: tests/Module/EntryPoints/theme-stubs.php declares a
         * two-parameter themecenterbox() stand-in that PHPStan would check literal calls against.
         */
        private function themeFunction(string $name): callable
        {
            return $name;
        }

        /**
         * @param callable():void $fn
         */
        private function capture(callable $fn): string
        {
            ob_start();
            try {
                $fn();
            } finally {
                $output = (string) ob_get_clean();
            }

            return $output;
        }

        private function assertGolden(string $name, string $actual): void
        {
            $path = __DIR__ . '/fixtures/theme-ibl/' . $name . '.html';

            if (getenv('THEME_GOLDEN_WRITE') === '1') {
                file_put_contents($path, $actual);
                $this->assertFileExists($path);
                return;
            }

            $this->assertFileExists($path);
            $this->assertSame(file_get_contents($path), $actual);
        }

        /**
         * @return array<string, array{0: string, 1: list<mixed>}>
         */
        public static function themeindexGoldenProvider(): array
        {
            return [
                'index-transaction-with-icon' => [
                    'index-transaction-with-icon',
                    ['GM', 'GM', 1700000000, '<font class="storycat">Cat</font> Big Trade', 12, 3, null, '', 'more', 'Trade Rumors', '33small.gif', 'Trade Rumors'],
                ],
                'index-plain-with-note-and-attribution' => [
                    'index-plain-with-note-and-attribution',
                    ['GM', 'Other', 1700000000, 'Plain Title', 5, 4, '<p>Body</p>', '<b>n</b>', 'more', 'Announcements', 'does-not-exist.png', 'Announcements'],
                ],
                'index-empty-topictext' => [
                    'index-empty-topictext',
                    ['GM', 'GM', '1700000000', 'No Topic', 0, 5, 'text', '', '', '', 'does-not-exist.png', ''],
                ],
            ];
        }

        /**
         * @param list<mixed> $args
         */
        #[DataProvider('themeindexGoldenProvider')]
        public function testThemeindexOutputMatchesGolden(string $name, array $args): void
        {
            $output = $this->capture(static function () use ($args): void {
                themeindex(...$args);
            });

            $this->assertGolden($name, $output);
        }

        /**
         * @return array<string, array{0: string, 1: list<mixed>}>
         */
        public static function themearticleGoldenProvider(): array
        {
            return [
                'article-int-time-informant-differs' => [
                    'article-int-time-informant-differs',
                    ['GM', 'Other', 1700000000, 'Article Title', '<p>Body</p>', 3, 'Trades', '33small.gif', 'Trade Rumors'],
                ],
                'article-string-datetime-anonymous' => [
                    'article-string-datetime-anonymous',
                    ['GM', '', '2024-01-02 03:04:05', 'Anon Title', null, 3, 'Trades', 'does-not-exist.png', 'Announcements'],
                ],
                'article-same-author-no-contributor' => [
                    'article-same-author-no-contributor',
                    ['GM', 'GM', 1700000000, 'Same Author', 'Body', 3, 'Trades', '33small.gif', 'Trade Rumors'],
                ],
            ];
        }

        /**
         * @param list<mixed> $args
         */
        #[DataProvider('themearticleGoldenProvider')]
        public function testThemearticleOutputMatchesGolden(string $name, array $args): void
        {
            $output = $this->capture(static function () use ($args): void {
                themearticle(...$args);
            });

            $this->assertGolden($name, $output);
        }

        /**
         * @return array<string, array{0: string, 1: string, 2: string, 3: ?string}>
         */
        public static function themecenterboxGoldenProvider(): array
        {
            return [
                'centerbox-explicit-leaders' => ['centerbox-explicit-leaders', 'Title', '<p>c</p>', 'leaders'],
                'centerbox-explicit-injury' => ['centerbox-explicit-injury', 'Title', '<p>c</p>', 'injury'],
                'centerbox-explicit-news' => ['centerbox-explicit-news', 'Title', '<p>c</p>', 'news'],
                'centerbox-legacy-wrapper' => ['centerbox-legacy-wrapper', 'Tom & Jerry', '<p>plain</p>', null],
            ];
        }

        #[DataProvider('themecenterboxGoldenProvider')]
        public function testThemecenterboxOutputMatchesGolden(string $name, string $title, string $content, ?string $type): void
        {
            $themecenterbox = $this->themeFunction('themecenterbox');
            $output = $this->capture(static function () use ($themecenterbox, $title, $content, $type): void {
                $themecenterbox($title, $content, $type);
            });

            $this->assertGolden($name, $output);
        }

        public function testThemeindexAcceptsNullThetext(): void
        {
            $args = ['GM', 'GM', 1700000000, 'Title', 1, 3, null, '', '', 'Trades', 'does-not-exist.png', 'Trades'];

            $output = $this->capture(static function () use ($args): void {
                themeindex(...$args);
            });
            $normalized = (string) preg_replace('/\s+/', ' ', $output);
            $this->assertStringContainsString('<div class="news-article__body"></div>', $normalized);

            $args[6] = 'Some body';
            $output = $this->capture(static function () use ($args): void {
                themeindex(...$args);
            });
            $normalized = (string) preg_replace('/\s+/', ' ', $output);
            $this->assertStringContainsString('<div class="news-article__body">Some body</div>', $normalized);
        }

        /**
         * @return array<string, array{0: string, 1: string}>
         */
        public static function nullTypeFallbackProvider(): array
        {
            return [
                'leaders-tabbed' => ['<div class="leaders-tabbed">x</div>', 'leaders-grid-item'],
                'injury-block' => ['<div class="injury-block">x</div>', '<div class="centerbox-item">'],
                'news-block' => ['<div class="news-block">x</div>', 'centerbox-item centerbox-item--spaced'],
                'plain' => ['<p>x</p>', 'ibl-centerbox'],
            ];
        }

        #[DataProvider('nullTypeFallbackProvider')]
        public function testThemecenterboxNullTypeFallbackSniffsContent(string $content, string $expected): void
        {
            $themecenterbox = $this->themeFunction('themecenterbox');
            $output = $this->capture(static function () use ($themecenterbox, $content): void {
                $themecenterbox('Title', $content, null);
            });

            $this->assertStringContainsString($expected, $output);
        }

        /**
         * @return array<string, array{0: int, 1: mixed}>
         */
        public static function themeindexWrongTypeProvider(): array
        {
            return [
                'string counter' => [4, '12'],
                'string topic' => [5, '3'],
                'int thetext' => [6, 42],
                'null aid' => [0, null],
                'float time' => [2, 1.5],
            ];
        }

        #[DataProvider('themeindexWrongTypeProvider')]
        public function testThemeindexRejectsWrongTypedArguments(int $index, mixed $value): void
        {
            $args = ['GM', 'GM', 1700000000, 'Title', 12, 3, 'text', '', '', 'Trades', 'does-not-exist.png', 'Trades'];
            $args[$index] = $value;

            $this->expectException(\TypeError::class);
            $this->capture(static function () use ($args): void {
                themeindex(...$args);
            });
        }

        /**
         * @return array<string, array{0: int, 1: mixed}>
         */
        public static function themearticleWrongTypeProvider(): array
        {
            return [
                'string topic' => [5, '3'],
                'int thetext' => [4, 42],
                'null informant' => [1, null],
                'float datetime' => [2, 1.5],
            ];
        }

        #[DataProvider('themearticleWrongTypeProvider')]
        public function testThemearticleRejectsWrongTypedArguments(int $index, mixed $value): void
        {
            $args = ['GM', 'Other', 1700000000, 'Title', 'text', 3, 'Trades', 'does-not-exist.png', 'Trades'];
            $args[$index] = $value;

            $this->expectException(\TypeError::class);
            $this->capture(static function () use ($args): void {
                themearticle(...$args);
            });
        }

        /**
         * The nav login form embeds a per-request CSRF token; mask it so the golden is stable.
         */
        private function captureHeader(): string
        {
            $output = $this->capture(static function (): void {
                themeheader();
            });

            return (string) preg_replace('/(name="_csrf_token" value=")[0-9a-f]+(")/', '$1TOKEN$2', $output);
        }

        private function primeHeaderGlobals(): void
        {
            $GLOBALS['user'] = null;
            $GLOBALS['mysqli_db'] = null;
            $GLOBALS['authService'] = self::createStub(AuthServiceInterface::class);
            $GLOBALS['leagueContext'] = new \League\LeagueContext();
        }

        public function testThemeheaderLoggedOutOutputMatchesGolden(): void
        {
            $this->primeHeaderGlobals();

            $output = $this->captureHeader();

            $this->assertGolden('header-logged-out', $output);
        }

        public function testThemeheaderThrowsWhenLeagueContextMissing(): void
        {
            $this->primeHeaderGlobals();
            $GLOBALS['leagueContext'] = null;

            $this->expectException(\LogicException::class);
            $this->capture(static function (): void {
                themeheader();
            });
        }

        public function testThemeheaderIgnoresNonStringDebugCookie(): void
        {
            $this->primeHeaderGlobals();
            $_COOKIE[\Debug\DebugSession::COOKIE_NAME] = ['x'];

            $output = $this->captureHeader();

            $this->assertGolden('header-logged-out', $output);
        }
    }
}
