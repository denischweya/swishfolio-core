<?php
/**
 * The few WordPress functions KitArt::rewrite() needs, for KitArtTest.
 */

if (! function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null, $create = true)
    {
        return ['basedir' => \SwishfolioCore\Tests\Unit\Onboarding\KitArtTest::$uploads, 'baseurl' => 'https://uploads.test'];
    }
}

if (! function_exists('untrailingslashit')) {
    function untrailingslashit($value)
    {
        return rtrim((string) $value, '/\\');
    }
}

if (! function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($dir)
    {
        return is_dir($dir) || mkdir($dir, 0777, true);
    }
}
