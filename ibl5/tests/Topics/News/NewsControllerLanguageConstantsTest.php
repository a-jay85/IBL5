<?php

declare(strict_types=1);

namespace Tests\Topics\News;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class NewsControllerLanguageConstantsTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testControllerDefinesAllNineNewsConstantsWithoutLanguageFile(): void
    {
        $this->assertFalse(defined('_NOINFO4TOPIC'), '_NOINFO4TOPIC leaked in from test scaffolding');

        foreach (get_included_files() as $file) {
            $this->assertFalse(
                str_ends_with($file, 'modules/News/language/lang-english.php'),
                'language file was already loaded'
            );
        }

        $this->assertTrue(class_exists(\Topics\News\NewsController::class));

        $expected = [
            '_READMORE'       => 'Read More...',
            '_BYTESMORE'      => 'bytes more',
            '_COMMENTSQ'      => 'comments?',
            '_COMMENT'        => 'comment',
            '_NOINFO4TOPIC'   => "Sorry, there isn't information for the selected topic.",
            '_GOTONEWSINDEX'  => 'Go to News Index',
            '_SELECTNEWTOPIC' => 'Select a New Topic',
            '_SEARCHONTOPIC'  => 'Search on This Topic',
            '_GOTOHOME'       => 'Go to Home',
        ];

        foreach ($expected as $name => $value) {
            $this->assertSame($value, constant($name), "Constant $name has wrong value");
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNoInfoForTopicResolvesForNonexistentTopicBranch(): void
    {
        $this->assertTrue(class_exists(\Topics\News\NewsController::class));

        $this->assertSame(
            "Sorry, there isn't information for the selected topic.Go to News IndexSelect a New Topic",
            _NOINFO4TOPIC . _GOTONEWSINDEX . _SELECTNEWTOPIC
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPreDefinedConstantIsNotRedefined(): void
    {
        define('_READMORE', 'sentinel');

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        });

        $this->assertTrue(class_exists(\Topics\News\NewsController::class));

        restore_error_handler();

        $this->assertSame('sentinel', _READMORE);
        $this->assertEmpty($warnings, 'Unexpected warnings: ' . implode(', ', $warnings));
    }
}
