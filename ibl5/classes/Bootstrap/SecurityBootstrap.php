<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/**
 * Security bootstrap: FB bot redirect, include_secure(), gzip, IBL5_ROOT constant.
 *
 * Extracted from mainfile.php lines 15-85.
 */
class SecurityBootstrap implements BootstrapStepInterface
{
    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        $this->redirectFacebookBot();
        $this->defineConstants();
        $this->startGzipCompression();
    }

    private function redirectFacebookBot(): void
    {
        $rawUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ua = is_string($rawUa) ? $rawUa : '';
        if (preg_match('/facebookexternalhit/si', $ua) === 1) {
            http_response_code(403);
            header('Content-Type: text/plain');
            echo 'Forbidden';
            exit();
        }
    }

    private function defineConstants(): void
    {
        if (!defined('END_TRANSACTION')) {
            define('END_TRANSACTION', 2);
        }

        if (!defined('IBL5_ROOT')) {
            define('IBL5_ROOT', dirname(__DIR__, 2));
        }
    }

    private function startGzipCompression(): void
    {
        $rawUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ua = is_string($rawUa) ? $rawUa : '';
        $rawEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        $acceptEncoding = is_string($rawEncoding) ? $rawEncoding : '';

        if (str_contains($ua, 'compatible')) {
            if (extension_loaded('zlib')) {
                @ob_end_clean();
                ob_start('ob_gzhandler');
            }
        } elseif ($acceptEncoding !== '') {
            if (str_contains($acceptEncoding, 'gzip') && extension_loaded('zlib')) {
                $GLOBALS['do_gzip_compress'] = true;
                ob_start('ob_gzhandler', 5);
                ob_implicit_flush(false);
                if (str_contains($ua, 'MSIE')) {
                    header('Content-Encoding: gzip');
                }
            }
        }
    }

    /**
     * Safe include function with path traversal protection.
     *
     * This is a standalone utility — called as include_secure() by legacy code.
     * It is NOT a container service; it's registered as a global function
     * in LegacyFunctions.php.
     *
     * @param string $fileName The file path to include safely.
     */
    public static function includeSafe(string $fileName): void
    {
        // Remove any path traversal attempts
        $fileName = preg_replace("/\.[\.\/]*\//", "", $fileName) ?? '';

        // Use basename to strip directory components
        $baseName = basename($fileName);

        // Only allow alphanumeric, underscore, dash, and .php extension
        if (preg_match('/^[a-zA-Z0-9_\-]+\.php$/', $baseName) !== 1) {
            return;
        }

        // Reconstruct with just the directory and safe basename
        $dir = dirname($fileName);
        if ($dir === '.' || $dir === '') {
            $safePath = $baseName;
        } else {
            // Validate directory doesn't contain traversal
            $dir = str_replace(['..', "\0"], '', $dir);
            $safePath = $dir . '/' . $baseName;
        }

        if (file_exists($safePath)) {
            include_once $safePath;
        }
    }
}
