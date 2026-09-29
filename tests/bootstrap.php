<?php
/**
 * Unit test bootstrap.
 *
 * Composer provides autoloading for both SwishfolioCore\ (src/) and
 * SwishfolioCore\Tests\ (tests/); no custom autoloader is needed here.
 *
 * @package SwishfolioCore
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/stubs/wp-functions.php';

if (! defined('SFCORE_PLUGIN_DIR')) {
    define('SFCORE_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (! defined('SFCORE_VERSION')) {
    define('SFCORE_VERSION', '1.1.0');
}

$GLOBALS['sfcore_test_transients'] = [];
