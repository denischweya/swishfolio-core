<?php

namespace SwishfolioCore\Tests\Unit\Photos;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/photo-stubs.php';

/**
 * Shared HTTP mocking: every request goes through pre_http_request, answered
 * from $this->routes (URL prefix => response array or callable).
 */
abstract class PhotoTestCase extends TestCase
{
    /** @var array */
    protected $routes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_http']    = [];
        $GLOBALS['sfcore_test_filters'] = [];
        $GLOBALS['sfcore_test_actions'] = [];
        $this->routes                   = [];

        $GLOBALS['sfcore_test_filters']['pre_http_request'] = function ($pre, $args, $url) {
            foreach ($this->routes as $prefix => $answer) {
                if (strpos((string) $url, $prefix) === 0) {
                    return is_callable($answer) ? $answer($url, $args) : $answer;
                }
            }

            return false;
        };
    }

    protected function tearDown(): void
    {
        $GLOBALS['sfcore_test_filters'] = [];
        $GLOBALS['sfcore_test_http']    = [];
        parent::tearDown();
    }

    /**
     * @param int   $code Status.
     * @param mixed $body Body (arrays are JSON encoded).
     *
     * @return array
     */
    protected static function http($code, $body, array $headers = [])
    {
        return ['response' => ['code' => $code], 'headers' => $headers, 'body' => is_array($body) ? json_encode($body) : (string) $body];
    }

    /**
     * @return string[] URLs requested so far.
     */
    protected static function requested()
    {
        return array_column($GLOBALS['sfcore_test_http'], 'url');
    }

    protected static function unsplashResult($id, $alt = 'A barista pouring milk into a latte')
    {
        return [
            'id'              => $id,
            'alt_description' => $alt,
            'urls'            => [
                'raw'     => 'https://images.unsplash.com/photo-' . $id . '?ixid=abc',
                'regular' => 'https://images.unsplash.com/photo-' . $id . '?ixid=abc&w=1080&q=80',
                'small' => 'https://images.unsplash.com/photo-' . $id . '?ixid=abc&w=400',
            ],
            'links'           => [
                'html'              => 'https://unsplash.com/photos/' . $id,
                'download_location' => 'https://api.unsplash.com/photos/' . $id . '/download?ixid=abc',
            ],
            'user'            => ['name' => 'Jane Doe', 'links' => ['html' => 'https://unsplash.com/@janedoe']],
        ];
    }

    protected static function pexelsResult($id)
    {
        return [
            'id'               => $id,
            'url'              => 'https://www.pexels.com/photo/coffee-' . $id . '/',
            'photographer'     => 'Sam Lee',
            'photographer_url' => 'https://www.pexels.com/@samlee',
            'alt'              => 'Coffee beans in a scoop',
            'src'              => [
                'original' => 'https://images.pexels.com/photos/' . $id . '/pexels-photo-' . $id . '.jpeg',
                'medium'   => 'https://images.pexels.com/photos/' . $id . '/pexels-photo-' . $id . '.jpeg?h=350',
            ],
        ];
    }
}
