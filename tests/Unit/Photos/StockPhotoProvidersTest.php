<?php

namespace SwishfolioCore\Tests\Unit\Photos;

use SwishfolioCore\AI\Photos\PexelsPhotos;
use SwishfolioCore\AI\Photos\StockPhotos;
use SwishfolioCore\AI\Photos\UnsplashPhotos;

class StockPhotoProvidersTest extends PhotoTestCase
{
    public function test_unsplash_search_sends_the_key_in_a_header_and_normalises_results()
    {
        $seen = null;
        $this->routes['https://api.unsplash.com/search/photos'] = function ($url, $args) use (&$seen) {
            $seen = ['url' => $url, 'args' => $args];

            return self::http(200, ['results' => [self::unsplashResult('a1'), ['id' => 'broken'], self::unsplashResult('b2')]]);
        };

        $photos = (new UnsplashPhotos('unsplash-test-key', 'myapp'))->search('barista latte', 'square', 4, 3);

        $this->assertSame('Client-ID unsplash-test-key', $seen['args']['headers']['Authorization']);
        $this->assertSame('v1', $seen['args']['headers']['Accept-Version']);
        $this->assertSame(3, $seen['args']['timeout']);
        $this->assertStringNotContainsString('unsplash-test-key', $seen['url']);
        $this->assertStringContainsString('query=barista%20latte', $seen['url']);
        $this->assertStringContainsString('orientation=squarish', $seen['url']);
        $this->assertStringContainsString('content_filter=high', $seen['url']);

        $this->assertCount(2, $photos, 'A result without urls or author is dropped.');
        $p = $photos[0];
        $this->assertSame('unsplash', $p['provider']);
        $this->assertSame('a1', $p['id']);
        $this->assertSame('https://images.unsplash.com/photo-a1?ixid=abc&w=400', $p['thumb'], 'The vision check sees the hotlinked API URL.');
        $this->assertSame('https://images.unsplash.com/photo-a1?ixid=abc&w=1600&q=80&fit=max&fm=jpg', $p['hotlink'], 'urls.regular, resized to 1600 wide, used in place.');
        $this->assertSame('', $p['download'], 'Unsplash photos are never copied.');
        $this->assertSame('Jane Doe', $p['author']);
        $this->assertSame('https://unsplash.com/@janedoe?utm_source=myapp&utm_medium=referral', $p['authorUrl']);
        $this->assertSame('https://unsplash.com/?utm_source=myapp&utm_medium=referral', $p['sourceUrl']);
        $this->assertSame('https://api.unsplash.com/photos/a1/download?ixid=abc', $p['track']);
    }

    public function test_unsplash_track_use_calls_the_download_endpoint_with_the_key()
    {
        $hit = null;
        $this->routes['https://api.unsplash.com/photos/'] = function ($url, $args) use (&$hit) {
            $hit = [$url, $args['headers']['Authorization']];

            return self::http(200, ['url' => 'https://images.unsplash.com/x']);
        };
        $u = new UnsplashPhotos('k1');
        $u->trackUse($u->normalise(self::unsplashResult('a1')), 2);

        $this->assertSame(['https://api.unsplash.com/photos/a1/download?ixid=abc', 'Client-ID k1'], $hit);

        // Never to another host, even if a reply carried one.
        $GLOBALS['sfcore_test_http'] = [];
        $u->trackUse(['track' => 'https://evil.example/collect'], 2);
        $this->assertSame([], self::requested());
    }

    public function test_an_api_error_returns_nothing_and_a_message_without_the_key()
    {
        $this->routes['https://api.unsplash.com/'] = self::http(401, ['errors' => ['OAuth error: The access token is invalid']]);
        $u = new UnsplashPhotos('secret-key-123');

        $this->assertSame([], $u->search('bread', 'landscape', 3, 3));
        $this->assertStringContainsString('401', $u->lastError());
        $this->assertStringContainsString('check the API key', $u->lastError());
        $this->assertStringNotContainsString('secret-key-123', $u->lastError());

        // Network down.
        $this->routes = [];
        $this->assertSame([], $u->search('bread', 'landscape', 3, 3));
        $this->assertStringContainsString('No network', $u->lastError());
    }

    public function test_pexels_search_uses_the_bare_key_and_attribution_fields()
    {
        $seen = null;
        $this->routes['https://api.pexels.com/v1/search'] = function ($url, $args) use (&$seen) {
            $seen = ['url' => $url, 'args' => $args];

            return self::http(200, ['photos' => [self::pexelsResult(42)]]);
        };

        $photos = (new PexelsPhotos('pexels-test-key'))->search('coffee beans', 'square', 3, 4);

        $this->assertSame('pexels-test-key', $seen['args']['headers']['Authorization']);
        $this->assertStringNotContainsString('pexels-test-key', $seen['url']);
        $this->assertStringContainsString('orientation=square', $seen['url']);
        $this->assertCount(1, $photos);
        $this->assertSame('https://images.pexels.com/photos/42/pexels-photo-42.jpeg?auto=compress&cs=tinysrgb&w=1600', $photos[0]['download']);
        $this->assertSame('Sam Lee', $photos[0]['author']);
        $this->assertSame('https://www.pexels.com/@samlee', $photos[0]['authorUrl']);
        $this->assertSame('Pexels', $photos[0]['sourceName']);
        $this->assertSame('', $photos[0]['track'], 'Pexels has no download endpoint.');
        $this->assertSame('', $photos[0]['hotlink'], 'Pexels photos are imported.');
    }

    public function test_no_key_means_no_providers_and_unsplash_comes_first()
    {
        $this->assertSame([], StockPhotos::providers());
        $this->assertFalse(StockPhotos::enabled());

        $GLOBALS['sfcore_test_filters']['swishfolio_stock_photo_credentials'] = function () {
            return ['unsplash' => '', 'pexels' => ''];
        };
        $this->assertFalse(StockPhotos::enabled(), 'Empty keys are off too.');

        $GLOBALS['sfcore_test_filters']['swishfolio_stock_photo_credentials'] = function () {
            return ['unsplash' => 'u', 'pexels' => 'p', 'app' => 'swishfolio'];
        };
        $slugs = array_map(function ($p) {
            return $p->slug();
        }, StockPhotos::providers());
        $this->assertSame(['unsplash', 'pexels'], $slugs);
    }
}
