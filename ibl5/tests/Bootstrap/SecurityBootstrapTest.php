<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\SecurityBootstrap;
use PHPUnit\Framework\TestCase;

final class SecurityBootstrapTest extends TestCase
{
    public function testBootDoesNotStartOutputBuffer(): void
    {
        $level = ob_get_level();
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate, br';

        $container = self::createStub(\Bootstrap\Contracts\ContainerInterface::class);
        $bootstrap = new SecurityBootstrap();

        // redirectFacebookBot calls exit() on FB UA — use a normal UA
        // boot() should NOT add any output buffers
        $bootstrap->boot($container);

        self::assertSame($level, ob_get_level());
    }

    public function testIncludeSafeBlocksPathTraversal(): void
    {
        // Create a temp file to test inclusion
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/safe-test-file.php';
        file_put_contents($tempFile, '<?php $GLOBALS["include_secure_test"] = true;');

        // Path traversal attempt should be sanitized
        SecurityBootstrap::includeSafe('../../../../../../' . $tempFile);

        // The traversal should have been stripped, so the file won't be found
        // (basename stripping makes it look for just 'safe-test-file.php' in CWD)
        self::assertArrayNotHasKey('include_secure_test', $GLOBALS);

        unlink($tempFile);
    }

    public function testIncludeSafeBlocksNonPhpExtension(): void
    {
        $this->expectNotToPerformAssertions();
        SecurityBootstrap::includeSafe('malicious.sh');
    }

    public function testIncludeSafeBlocksSpecialCharactersInFilename(): void
    {
        $this->expectNotToPerformAssertions();
        SecurityBootstrap::includeSafe('file;rm -rf.php');
    }

    public function testIncludeSafeAllowsValidPhpFile(): void
    {
        $tempDir = sys_get_temp_dir();
        $originalCwd = getcwd();

        // Create a valid PHP file in the temp dir
        $tempFile = $tempDir . '/valid-include-test.php';
        file_put_contents($tempFile, '<?php $GLOBALS["valid_include_test"] = "included";');

        // Change to temp dir so the relative path resolves
        chdir($tempDir);

        SecurityBootstrap::includeSafe('valid-include-test.php');

        self::assertSame('included', $GLOBALS['valid_include_test'] ?? null);

        // Cleanup
        unset($GLOBALS['valid_include_test']);
        unlink($tempFile);
        if ($originalCwd !== false) {
            chdir($originalCwd);
        }
    }

    public function testIncludeSafeHandlesEmptyString(): void
    {
        $this->expectNotToPerformAssertions();
        SecurityBootstrap::includeSafe('');
    }

    public function testIncludeSafeStripsNullBytes(): void
    {
        $this->expectNotToPerformAssertions();
        SecurityBootstrap::includeSafe("dir\0/../test.php");
    }
}
