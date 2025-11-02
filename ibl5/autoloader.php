<?php
/**
 * Autoloader for IBL5 classes
 * 
 * This file contains the autoloader function that is used by both the main
 * application (via mainfile.php) and PHPUnit tests (via tests/bootstrap.php).
 * 
 * The autoloader follows these conventions:
 * - Classes are stored in the classes/ directory
 * - Namespaces map to subdirectories (e.g., Draft\DraftRepository -> classes/Draft/DraftRepository.php)
 * - Underscores in class names map to subdirectories (e.g., Trading_Manager -> classes/Trading/Manager.php)
 */

function mlaphp_autoloader($class)
{
    // strip off any leading namespace separator from PHP 5.3
    $class = ltrim($class, '\\');

    // the eventual file path
    $subpath = '';

    // is there a PHP 5.3 namespace separator?
    $pos = strrpos($class, '\\');
    if ($pos !== false) {
        // convert namespace separators to directory separators
        $ns = substr($class, 0, $pos);
        $subpath = str_replace('\\', DIRECTORY_SEPARATOR, $ns)
            . DIRECTORY_SEPARATOR;
        // remove the namespace portion from the final class name portion
        $class = substr($class, $pos + 1);
    }

    // convert underscores in the class name to directory separators
    $subpath .= str_replace('_', DIRECTORY_SEPARATOR, $class);

    // the path to our central class directory location
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'classes';

    // Special handling for Services namespace (ibl5/classes/Services)
    if (strpos($subpath, 'Services' . DIRECTORY_SEPARATOR) === 0) {
        $file = $dir . DIRECTORY_SEPARATOR . $subpath . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }

    // Default: prefix with the central directory location and suffix with .php,
    // then require it.
    $file = $dir . DIRECTORY_SEPARATOR . $subpath . '.php';
    if (file_exists($file)) {
        require $file;
    }
}

// register it with SPL
spl_autoload_register('mlaphp_autoloader');
