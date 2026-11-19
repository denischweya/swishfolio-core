<?php

namespace SwishfolioCore\Tests\Unit\Photos;

use SwishfolioCore\AI\Credentials\WordPressCredentialStore;
use SwishfolioCore\AI\ModelRouter;
use SwishfolioCore\AI\Onboarding\CopySlots;
use SwishfolioCore\AI\Onboarding\KitImages;
use SwishfolioCore\AI\Photos\PhotoCredits;
use SwishfolioCore\AI\Photos\PhotoSideloader;
use SwishfolioCore\AI\Photos\UnsplashPhotos;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\Tests\Support\InMemoryJournalRepository;
use SwishfolioCore\Tests\Unit\AI\FakeEncryptionService;
use SwishfolioCore\Tests\Unit\AI\FakeProvider;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;

/**
 * The images stage's photo sourcing hooks, attribution and routing.
 */
class PhotoPiecesTest extends PhotoTestCase
{
    private function slots()
    {
        return [
            ['ref' => '10:i1', 'postId' => 10, 'slot' => 'i1', 'page' => 'Home (home page)', 'position' => 'first photo on the page', 'file' => 'hero-tuna.jpg', 'alt' => 'A chef presents tuna'],
            ['ref' => '10:i2', 'postId' => 10, 'slot' => 'i2', 'page' => 'Home (home page)', 'position' => 'photo 2 on the page', 'file' => 'chef-portrait.jpg', 'alt' => 'Chef Tomás Valadares plating'],
            ['ref' => '11:i1', 'postId' => 11, 'slot' => 'i1', 'page' => 'About', 'position' => 'first photo on the page', 'file' => 'portrait-river-1024x1536.jpg', 'alt' => 'A coach by a river'],
        ];
    }

    private function placer(FakeProvider $ai)
    {
        return new KitImages($ai, new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'), new ToolRunner(new ToolRegistry([]), new MutationJournal(new InMemoryJournalRepository())));
    }

    public function test_with_photo_sourcing_off_the_images_call_is_unchanged()
    {
        $ai          = new FakeProvider();
        $ai->payload = ['placements' => [], 'unfit' => []];

        $out = $this->placer($ai)->plan($this->slots(), [], 'Cafe', 'm', []);

        $this->assertSame(KitImages::schema(), $ai->lastRequest->getSchema());
        $this->assertArrayNotHasKey('stock', $ai->lastRequest->getSchema()['properties']);
        $this->assertStringNotContainsString('stock', $ai->lastRequest->getUserPrompt());
        $this->assertStringEndsWith('Return the placements and the unfit photos.', $ai->lastRequest->getUserPrompt());
        $this->assertSame(3000, $ai->lastRequest->getMaxTokens());
        $this->assertSame([], $ai->lastRequest->getImages());
        $this->assertSame([], $out['stock']);
    }

    public function test_with_photo_sourcing_on_the_same_call_proposes_queries()
    {
        $ai          = new FakeProvider();
        $ai->payload = [
            'placements' => [['slot' => '10:i1', 'attachmentId' => 5, 'alt' => 'Our cafe']],
            'unfit'      => [],
            'stock'      => [
                ['slot' => '10:i1', 'query' => 'already has the owner photo', 'orientation' => 'landscape'],
                ['slot' => '11:i1', 'query' => '  latte   art <b>close up</b> ', 'orientation' => 'portrait'],
                ['slot' => '99:i9', 'query' => 'not a slot', 'orientation' => 'landscape'],
                ['slot' => '10:i2', 'query' => 'coffee beans', 'orientation' => 'sideways'],
            ],
        ];

        $out = $this->placer($ai)->plan($this->slots(), [['id' => 5, 'role' => 'photo', 'note' => '', 'size' => '']], 'Cafe', 'm', [], true);

        $this->assertContains('stock', $ai->lastRequest->getSchema()['required']);
        $this->assertStringContainsString('stock photo search query', $ai->lastRequest->getUserPrompt());
        $this->assertSame(['11:i1', '10:i2'], array_column($out['stock'], 'ref'));
        $this->assertSame('latte art close up', $out['stock'][0]['query']);
        $this->assertSame('portrait', $out['stock'][0]['orientation']);
        $this->assertSame('landscape', $out['stock'][1]['orientation'], 'An unknown orientation falls back to landscape.');
        $this->assertSame('About', $out['stock'][0]['page']);
    }

    public function test_demo_portraits_are_found_by_name_or_by_the_demo_persona()
    {
        $assets = [
            'hero-tuna'     => ['file' => 'images/hero-tuna.jpg', 'alt' => 'A chef presents a plated raw tuna dish'],
            'chef-portrait' => ['file' => 'images/chef-portrait.jpg', 'alt' => 'Chef Tomás Valadares plating a course'],
            'portrait-river' => ['file' => 'images/portrait-river.jpg', 'alt' => 'A coach by a river'],
            'oyster'        => ['file' => 'images/oyster.jpg', 'alt' => 'Tomás Valadares preparing an oyster'],
        ];

        $refs = array_column(KitImages::portraitSlots($this->slots(), $assets, ['Tomás Valadares']), 'ref');

        $this->assertSame(['10:i2', '11:i1'], $refs);
        $this->assertSame(['10:i2', '11:i1'], array_column(KitImages::portraitSlots($this->slots(), $assets, []), 'ref'), 'Named "portrait" is enough.');
    }

    public function test_attribution_meta_and_the_credit_line()
    {
        $photo = (new UnsplashPhotos('k', 'swishfolio'))->normalise(self::unsplashResult('xyz'));
        $meta  = PhotoSideloader::creditMeta($photo);

        $this->assertSame([
            'provider'  => 'unsplash',
            'id'        => 'xyz',
            'author'    => 'Jane Doe',
            'authorUrl' => 'https://unsplash.com/@janedoe?utm_source=swishfolio&utm_medium=referral',
            'pageUrl'   => 'https://unsplash.com/photos/xyz?utm_source=swishfolio&utm_medium=referral',
            'source'    => 'Unsplash',
            'sourceUrl' => 'https://unsplash.com/?utm_source=swishfolio&utm_medium=referral',
        ], $meta);
        $this->assertSame('Photo by Jane Doe on Unsplash', PhotoSideloader::creditLine($meta));

        $html = PhotoCredits::html([$meta, ['author' => 'Sam <Lee>', 'source' => 'Pexels', 'authorUrl' => 'https://www.pexels.com/@samlee', 'sourceUrl' => 'https://www.pexels.com']]);
        $this->assertStringContainsString('Photos: <a href="https://unsplash.com/@janedoe?utm_source=swishfolio&amp;utm_medium=referral"', $html);
        $this->assertStringContainsString('>Unsplash</a>', $html);
        $this->assertStringContainsString('Sam &lt;Lee&gt;</a> on <a href="https://www.pexels.com"', $html);
        $this->assertStringNotContainsString('—', $html);
        $this->assertSame('', PhotoCredits::html([]));

        // Hotlinked photos: the credit is kept on the page and shown while the photo is there.
        $stored  = ['unsplash:xyz' => $meta + ['url' => $photo['hotlink']]];
        $content = '<img src="https://images.unsplash.com/photo-xyz?ixid=abc&amp;w=1600&amp;q=80&amp;fit=max&amp;fm=jpg" alt=""/>';
        $this->assertSame(['unsplash:xyz'], array_keys(PhotoCredits::hotlinked($stored, $content)));
        $this->assertSame([], PhotoCredits::hotlinked($stored, '<img src="https://images.unsplash.com/photo-other?w=1600"/>'), 'Removed photo, no credit.');

        $this->assertSame([901, 77], PhotoCredits::attachmentIds('<!-- wp:image {"id":901,"sizeSlug":"full"} --><figure><img class="wp-image-901"/></figure><!-- /wp:image --><!-- wp:cover {"url":"x","id":77} -->'));
    }

    public function test_only_the_providers_own_image_hosts_are_downloaded()
    {
        $this->assertFalse(PhotoSideloader::allowedUrl(['provider' => 'unsplash', 'download' => 'https://images.unsplash.com/photo-1?w=1600']), 'Unsplash photos are hotlinked, never copied.');
        $this->assertTrue(PhotoSideloader::allowedUrl(['provider' => 'pexels', 'download' => 'https://images.pexels.com/photos/1/p.jpeg?w=1600']));
        $this->assertFalse(PhotoSideloader::allowedUrl(['provider' => 'unsplash', 'download' => 'https://images.pexels.com/photos/1/p.jpeg']));
        $this->assertFalse(PhotoSideloader::allowedUrl(['provider' => 'unsplash', 'download' => 'http://images.unsplash.com/photo-1']));
        $this->assertFalse(PhotoSideloader::allowedUrl(['provider' => 'other', 'download' => 'https://169.254.169.254/latest']));

        $this->expectException(\RuntimeException::class);
        (new PhotoSideloader())->sideload(['provider' => 'pexels', 'id' => '1', 'download' => 'https://evil.example/x.jpg'], 'alt', 3);
    }

    public function test_a_failed_download_throws_before_touching_the_media_library()
    {
        $this->routes['https://images.pexels.com/'] = self::http(500, 'oops');

        try {
            (new PhotoSideloader())->sideload(['provider' => 'pexels', 'id' => '1', 'download' => 'https://images.pexels.com/photos/1/p.jpeg?w=1600'], 'alt', 3);
            $this->fail('Expected an exception.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HTTP 500', $e->getMessage());
        }

        $this->routes['https://images.pexels.com/'] = self::http(200, '<html>', ['content-type' => 'text/html']);
        $this->expectExceptionMessage('not an image');
        (new PhotoSideloader())->sideload(['provider' => 'pexels', 'id' => '1', 'download' => 'https://images.pexels.com/photos/1/p.jpeg?w=1600'], 'alt', 3);
    }

    public function test_an_image_without_an_attachment_drops_the_old_attachment_class()
    {
        require_once SFCORE_PLUGIN_DIR . 'tests/Unit/Tools/wp-tools-stubs.php';
        $hotlink = 'https://images.unsplash.com/photo-zz?w=1600&fm=jpg';

        $one   = '<!-- wp:image {"id":5,"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="https://x.test/old.jpg" alt="Old" class="wp-image-5"/></figure><!-- /wp:image -->';
        $after = $this->applyImage($one, ['id' => 0, 'kitAsset' => true, 'url' => $hotlink, 'alt' => 'A latte']);
        $this->assertStringNotContainsString('wp-image-5', $after);
        $this->assertStringNotContainsString('class=""', $after);
        $this->assertStringNotContainsString('"id":5', $after, 'Block attrs and markup agree: no id anywhere.');
        $this->assertStringContainsString('<img src="https://images.unsplash.com/photo-zz?w=1600&amp;fm=jpg" alt="A latte"/>', $after);

        // Other classes stay.
        $two   = '<!-- wp:image {"id":5} --><figure class="wp-block-image"><img src="https://x.test/old.jpg" alt="" class="is-round wp-image-5"/></figure><!-- /wp:image -->';
        $this->assertStringContainsString('class="is-round"', $this->applyImage($two, ['id' => 0, 'kitAsset' => true, 'url' => $hotlink, 'alt' => 'x']));

        // With an attachment the class follows the new id, as before.
        $this->assertStringContainsString('class="wp-image-9"', $this->applyImage($one, ['id' => 9, 'url' => 'https://x.test/new.jpg', 'alt' => 'x']));
    }

    private function applyImage($content, array $image)
    {
        $slots = new CopySlots($content);
        foreach ($slots->slots() as $id => $slot) {
            if ($slot['type'] === 'image') {
                return $slots->apply(['images' => [$id => $image]]);
            }
        }
        $this->fail('No image slot.');
    }

    public function test_photo_check_routes_to_the_cheap_model_and_nothing_else_changes()
    {
        $GLOBALS['sfcore_test_options']                                  = [];
        $GLOBALS['sfcore_test_filters']['swishfolio_ai_shared_credential'] = function () {
            return ['provider' => 'anthropic', 'api_key' => 'sk', 'model' => 'claude-opus-5', 'followup_model' => 'claude-sonnet-5-5'];
        };
        $router = new ModelRouter(new Settings(new WordPressCredentialStore(new FakeEncryptionService())));

        $this->assertSame('claude-haiku-5-5', $router->modelFor(ModelRouter::KIND_PHOTO_CHECK));
        $this->assertFalse($router->isDesignHeavy(ModelRouter::KIND_PHOTO_CHECK));
        $this->assertSame('claude-sonnet-5-5', $router->modelFor(KitImages::KIND));
        $this->assertSame('claude-opus-5', $router->modelFor(ModelRouter::KIND_SITE_DESIGN));
        $this->assertSame('claude-sonnet-5-5', $router->modelFor(ModelRouter::KIND_EDIT));

        // Another provider never gets a Claude model id.
        $GLOBALS['sfcore_test_filters']['swishfolio_ai_shared_credential'] = function () {
            return ['provider' => 'openai', 'api_key' => 'sk', 'model' => 'gpt-x', 'followup_model' => ''];
        };
        $this->assertSame('gpt-x', $router->modelFor(ModelRouter::KIND_PHOTO_CHECK));
    }
}
