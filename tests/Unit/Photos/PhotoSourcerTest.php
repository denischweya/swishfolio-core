<?php

namespace SwishfolioCore\Tests\Unit\Photos;

use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\Generation\Staged\ParallelRunner;
use SwishfolioCore\AI\Photos\PexelsPhotos;
use SwishfolioCore\AI\Photos\PhotoSourcer;
use SwishfolioCore\AI\Photos\UnsplashPhotos;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\Tests\Unit\AI\FakeProvider;
use SwishfolioCore\Tests\Unit\AI\SpyAnthropicProvider;

/**
 * A fake with the Anthropic slug: answers a scripted pick per call.
 */
class VisionFake extends FakeProvider
{
    /** @var array[] */
    public $picks = [];

    /** @var AIRequest[] */
    public $requests = [];

    public function getSlug()
    {
        return 'anthropic';
    }

    public function generate(AIRequest $request)
    {
        $this->requests[] = $request;
        $this->payload    = $this->picks ? array_shift($this->picks) : ['pick' => 0, 'alt' => ''];

        return parent::generate($request);
    }
}

/**
 * A transport that answers every request with the same body, recording what it was sent.
 */
class CannedTransport
{
    public $sent = [];
    public $body;

    public function send(array $requests, $timeout, $stagger, callable $onComplete)
    {
        foreach ($requests as $key => $request) {
            $this->sent[$key] = $request;
            $onComplete($key, 200, $this->body, '');
        }
    }
}

class PhotoSourcerTest extends PhotoTestCase
{
    /** @var array[] */
    private $imported = [];

    private function want($ref, $query = 'barista pouring latte')
    {
        list($post, $slot) = explode(':', $ref);

        return ['ref' => $ref, 'postId' => (int) $post, 'slot' => $slot, 'page' => 'Home (home page)', 'position' => 'first photo on the page', 'file' => 'octopus.jpg', 'alt' => 'Grilled octopus', 'query' => $query, 'orientation' => 'landscape'];
    }

    private function sourcer($ai, array $libraries = null, $import = null, $runner = null)
    {
        $this->imported = [];
        $import         = $import ?: function ($photo, $alt, $timeout) {
            $this->imported[] = [$photo['provider'] . ':' . $photo['id'], $alt, $timeout];

            return ['id' => 900 + count($this->imported), 'url' => 'https://site.test/wp-content/uploads/' . $photo['id'] . '.jpg'];
        };

        return new PhotoSourcer(
            $libraries !== null ? $libraries : [new UnsplashPhotos('u-key'), new PexelsPhotos('p-key')],
            $ai,
            new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'),
            $import,
            $runner
        );
    }

    private function unsplashFinds(array $ids)
    {
        $results = array_map(function ($id) {
            return self::unsplashResult($id, 'Photo ' . $id);
        }, $ids);
        $this->routes['https://api.unsplash.com/search/photos'] = self::http(200, ['results' => $results]);
        $this->routes['https://api.unsplash.com/photos/']       = self::http(200, []);
    }

    public function test_an_unsplash_pick_is_hotlinked_tracked_and_credited_never_imported()
    {
        $this->unsplashFinds(['a', 'b', 'c', 'd']);
        $ai        = new VisionFake();
        $ai->picks = [['pick' => 2, 'alt' => 'A barista pours milk into a latte']];

        $out = $this->sourcer($ai)->source([$this->want('12:i1')], 'Bean There. A small coffee shop.', 'claude-haiku-5-5');

        $this->assertSame([], $this->imported, 'Unsplash requires hotlinking: nothing goes into the media library.');
        $this->assertCount(1, $out['placements']);
        $p = $out['placements'][0];
        $this->assertSame([12, 'i1', 0, 'https://images.unsplash.com/photo-b?ixid=abc&w=1600&q=80&fit=max&fm=jpg', 'A barista pours milk into a latte'], [$p['postId'], $p['slot'], $p['id'], $p['url'], $p['alt']]);
        $this->assertSame('Jane Doe', $p['credit']['author']);
        $this->assertSame('b', $p['credit']['id']);
        $this->assertSame($p['url'], $p['credit']['url']);
        $this->assertContains('https://api.unsplash.com/photos/b/download?ixid=abc', self::requested(), 'Unsplash is told the photo was used.');

        // The vision request: cheap model, the three hotlinked thumbnails, the spot's context.
        $req = $ai->requests[0];
        $this->assertSame('claude-haiku-5-5', $req->getModel());
        $this->assertSame([
            'https://images.unsplash.com/photo-a?ixid=abc&w=400',
            'https://images.unsplash.com/photo-b?ixid=abc&w=400',
            'https://images.unsplash.com/photo-c?ixid=abc&w=400',
        ], $req->getImages());
        $this->assertStringContainsString('Bean There', $req->getUserPrompt());
        $this->assertStringContainsString('barista pouring latte', $req->getUserPrompt());
        $this->assertStringContainsString('2: Photo b', $req->getUserPrompt());
        $this->assertSame(1, $out['checked']);
        $this->assertCount(1, $out['responses']);
    }

    public function test_rejecting_every_candidate_keeps_the_existing_photo()
    {
        $this->unsplashFinds(['a', 'b']);
        $ai        = new VisionFake();
        $ai->picks = [['pick' => 0, 'alt' => '']];

        $out = $this->sourcer($ai)->source([$this->want('12:i1')], 'Cafe', 'm');

        $this->assertSame([], $out['placements']);
        $this->assertSame([], $this->imported);
        $this->assertNotContains('https://api.unsplash.com/photos/a/download?ixid=abc', self::requested());
        $this->assertSame([], PhotoSourcer::pick(['pick' => 7], 3) ?: [], 'A pick out of range is a rejection.');
    }

    public function test_a_pexels_pick_is_imported_with_a_bounded_timeout()
    {
        $this->routes['https://api.pexels.com/v1/search'] = self::http(200, ['photos' => [self::pexelsResult(7), self::pexelsResult(8)]]);
        $ai        = new VisionFake();
        $ai->picks = [['pick' => 2, 'alt' => 'Beans']];

        $out = $this->sourcer($ai, [new PexelsPhotos('p-key')])->source([$this->want('12:i1')], 'Cafe', 'm');

        $this->assertSame('pexels:8', $this->imported[0][0]);
        $this->assertLessThanOrEqual(PhotoSourcer::DOWNLOAD_TIMEOUT, $this->imported[0][2]);
        $this->assertSame(['postId' => 12, 'slot' => 'i1', 'id' => 901, 'url' => 'https://site.test/wp-content/uploads/8.jpg', 'alt' => 'Beans', 'credit' => null], $out['placements'][0]);
    }

    public function test_unsplash_failing_falls_back_to_pexels_and_notes_it_without_the_key()
    {
        $this->routes['https://api.unsplash.com/']        = self::http(403, 'Rate Limit Exceeded');
        $this->routes['https://api.pexels.com/v1/search'] = self::http(200, ['photos' => [self::pexelsResult(7)]]);
        $ai        = new VisionFake();
        $ai->picks = [['pick' => 1, 'alt' => 'Coffee beans']];

        $out = $this->sourcer($ai)->source([$this->want('3:i2')], 'Cafe', 'm');

        $this->assertSame('pexels:7', $this->imported[0][0]);
        $this->assertCount(1, $out['placements']);
        $this->assertStringContainsString('unsplash: HTTP 403', implode(' ', $out['notes']));
        $this->assertStringNotContainsString('u-key', implode(' ', $out['notes']));
    }

    public function test_failures_never_throw_and_keep_the_existing_photos()
    {
        // The vision call fails.
        $this->unsplashFinds(['a']);
        $ai        = new VisionFake();
        $ai->throw = new ProviderException('overloaded', 529);
        $out       = $this->sourcer($ai)->source([$this->want('1:i1')], 'Cafe', 'm');
        $this->assertSame([], $out['placements']);
        $this->assertStringContainsString('Photo check failed', implode(' ', $out['notes']));

        // The import fails (Pexels photos are imported).
        $this->routes['https://api.pexels.com/v1/search'] = self::http(200, ['photos' => [self::pexelsResult(7)]]);
        $ai        = new VisionFake();
        $ai->picks = [['pick' => 1, 'alt' => 'x']];
        $out       = $this->sourcer($ai, [new PexelsPhotos('p-key')], function () {
            throw new \RuntimeException('Image download returned HTTP 500.');
        })->source([$this->want('1:i1')], 'Cafe', 'm');
        $this->assertSame([], $out['placements']);
        $this->assertStringContainsString('Photo import failed', implode(' ', $out['notes']));

        // No network at all.
        $this->routes = [];
        $out          = $this->sourcer(new VisionFake())->source([$this->want('1:i1')], 'Cafe', 'm');
        $this->assertSame([], $out['placements']);
        $this->assertSame(0, $out['checked']);

        // A provider that cannot see images is not asked.
        $this->unsplashFinds(['a']);
        $plain = new FakeProvider();
        $out   = $this->sourcer($plain)->source([$this->want('1:i1')], 'Cafe', 'm');
        $this->assertNull($plain->lastRequest);
        $this->assertStringContainsString('needs the Anthropic provider', implode(' ', $out['notes']));
    }

    public function test_one_photo_is_used_once_per_site()
    {
        $this->unsplashFinds(['a', 'b']);
        $ai        = new VisionFake();
        $ai->picks = [['pick' => 1, 'alt' => 'one'], ['pick' => 1, 'alt' => 'two']];

        $out = $this->sourcer($ai)->source([$this->want('1:i1'), $this->want('2:i1', 'other query')], 'Cafe', 'm');

        $this->assertCount(1, $out['placements']);
        $this->assertSame(1, count(array_filter(self::requested(), function ($u) {
            return strpos($u, '/download?') !== false;
        })), 'Tracked once.');
    }

    public function test_vision_calls_go_out_as_anthropic_image_blocks_and_are_metered()
    {
        $this->unsplashFinds(['a', 'b']);
        $ai        = new SpyAnthropicProvider();
        $ai->setCredentials(['api_key' => 'sk-test']);
        $transport = new CannedTransport();
        $transport->body = json_encode([
            'model'       => 'claude-haiku-5-5',
            'stop_reason' => 'end_turn',
            'content'     => [['type' => 'text', 'text' => '{"pick":1,"alt":"A latte"}']],
            'usage'       => ['input_tokens' => 420, 'output_tokens' => 12],
        ]);

        $out = $this->sourcer($ai, null, null, new ParallelRunner($ai, $transport))->source([$this->want('5:i1')], 'Cafe', 'claude-haiku-5-5');

        $body    = json_decode($transport->sent['5:i1']['body'], true);
        $content = $body['messages'][0]['content'];
        $this->assertSame('claude-haiku-5-5', $body['model']);
        $this->assertSame(['type' => 'image', 'source' => ['type' => 'url', 'url' => 'https://images.unsplash.com/photo-a?ixid=abc&w=400']], $content[0]);
        $this->assertSame('image', $content[1]['type']);
        $this->assertSame('text', $content[2]['type']);
        $this->assertSame('low', $body['output_config']['effort'] ?? null);

        // Metering: the same swishfolio_ai_response seam every other AI call fires.
        $metered = array_values(array_filter($GLOBALS['sfcore_test_actions'], function ($a) {
            return $a[0] === 'swishfolio_ai_response';
        }));
        $this->assertCount(1, $metered);
        $this->assertSame(420, $metered[0][1][0]['usage']['input_tokens']);
        $this->assertCount(1, $out['placements']);
    }
}
