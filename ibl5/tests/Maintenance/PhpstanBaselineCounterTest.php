<?php

declare(strict_types=1);

namespace Tests\Maintenance;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Maintenance\PhpstanBaselineCounter;

final class PhpstanBaselineCounterTest extends TestCase
{
    private PhpstanBaselineCounter $counter;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->counter = new PhpstanBaselineCounter();
        $this->tmpDir = sys_get_temp_dir() . '/phpstan-baseline-counter-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        rmdir($this->tmpDir);
    }

    #[Test]
    public function countsByIdentifier(): void
    {
        $file = $this->createBaseline([
            ['identifier' => 'ibl.unescapedOutput', 'count' => 2, 'path' => 'classes/Foo.php'],
            ['identifier' => 'ibl.unescapedOutput', 'count' => 3, 'path' => 'classes/Bar.php'],
            ['identifier' => 'ibl.requireOnce', 'count' => 1, 'path' => 'classes/Baz.php'],
        ]);

        $result = $this->counter->countByIdentifier($file);

        self::assertSame(['ibl.requireOnce' => 1, 'ibl.unescapedOutput' => 2], $result);
    }

    #[Test]
    public function returnsEmptyArrayForEmptyBaseline(): void
    {
        $file = $this->tmpDir . '/empty.neon';
        file_put_contents($file, "parameters:\n\tignoreErrors:\n");

        $result = $this->counter->countByIdentifier($file);

        self::assertSame([], $result);
    }

    #[Test]
    public function handlesBaselineWithNoIdentifiers(): void
    {
        $file = $this->tmpDir . '/no-ids.neon';
        file_put_contents($file, "parameters:\n\tignoreErrors:\n\t\t-\n\t\t\tmessage: 'some error'\n\t\t\tcount: 1\n\t\t\tpath: foo.php\n");

        $result = $this->counter->countByIdentifier($file);

        self::assertSame([], $result);
    }

    #[Test]
    public function countsTotalEntries(): void
    {
        $file = $this->createBaseline([
            ['identifier' => 'ibl.unescapedOutput', 'count' => 10, 'path' => 'classes/Foo.php'],
            ['identifier' => 'ibl.requireOnce', 'count' => 3, 'path' => 'classes/Bar.php'],
        ]);

        $result = $this->counter->countByIdentifier($file);

        self::assertSame(2, array_sum($result));
    }

    #[Test]
    public function throwsForNonexistentFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('not found');

        $this->counter->countByIdentifier('/nonexistent/baseline.neon');
    }

    #[Test]
    public function countsWithMultilineMessages(): void
    {
        $file = $this->tmpDir . '/multiline.neon';
        $content = <<<'NEON'
parameters:
	ignoreErrors:
		-
			rawMessage: '''
				Some really long
				multiline error message
			'''
			identifier: method.deprecatedClass
			count: 2
			path: classes/Foo.php

		-
			rawMessage: 'Simple message'
			identifier: ibl.requireOnce
			count: 1
			path: classes/Bar.php
NEON;
        file_put_contents($file, $content);

        $result = $this->counter->countByIdentifier($file);

        self::assertSame(['ibl.requireOnce' => 1, 'method.deprecatedClass' => 1], $result);
    }

    /**
     * @param list<array{identifier: string, count: int, path: string}> $entries
     */
    private function createBaseline(array $entries): string
    {
        $file = $this->tmpDir . '/baseline.neon';
        $content = "parameters:\n\tignoreErrors:\n";
        foreach ($entries as $entry) {
            $content .= "\t\t-\n";
            $content .= "\t\t\trawMessage: 'Test error'\n";
            $content .= "\t\t\tidentifier: {$entry['identifier']}\n";
            $content .= "\t\t\tcount: {$entry['count']}\n";
            $content .= "\t\t\tpath: {$entry['path']}\n\n";
        }
        file_put_contents($file, $content);

        return $file;
    }
}
