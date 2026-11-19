<?php

namespace SwishfolioCore\Tests\Unit\Review;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Conversation\ConversationRequest;
use SwishfolioCore\AI\Review\ProcessRunner;
use SwishfolioCore\AI\Review\ReviewSiteInterface;
use SwishfolioCore\AI\Review\ScreenshotRenderer;
use SwishfolioCore\AI\Review\VisualReview;
use SwishfolioCore\AI\Review\VisualReviewConfig;

class VisualReviewTest extends TestCase
{
    /** @var string */
    private $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/sfcore-vr-test-' . getmypid();
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->dir . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function config($mode, $chromeFound = true)
    {
        return new VisualReviewConfig(['mode' => $mode], static function ($path) use ($chromeFound) {
            return $chromeFound;
        });
    }

    private function review(VisualReviewConfig $config, ProcessRunner $runner, FakeReviewSite $site, $provider)
    {
        return (new VisualReview($config, new ScreenshotRenderer($config, $runner), $site, $provider, 'claude-strong', 'claude-cheap'))
            ->withBrief('Rosa, a social media manager')
            ->keepShotsIn($this->dir);
    }

    public function test_off_in_settings_skips_without_rendering_or_calling_the_model()
    {
        $runner   = new FakeRunner();
        $site     = new FakeReviewSite();
        $provider = new FakeVisionProvider();

        $result = $this->review($this->config('off'), $runner, $site, $provider)->run();

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('off', $result['reason']);
        $this->assertSame(0, $runner->calls);
        $this->assertSame([], $provider->requests);
        $this->assertSame('skipped', $site->notes[0]['status']);
        $this->assertSame([], $site->applied);
    }

    public function test_auto_without_chrome_is_off_and_journaled()
    {
        $runner = new FakeRunner();
        $site   = new FakeReviewSite();

        $result = $this->review($this->config('auto', false), $runner, $site, new FakeVisionProvider())->run();

        $this->assertSame('off', $result['reason']);
        $this->assertSame(0, $runner->calls);
        $this->assertCount(1, $site->notes);
    }

    public function test_a_renderer_that_fails_falls_back_to_a_journaled_skip()
    {
        $runner           = new FakeRunner();
        $runner->response = ['exit' => 1, 'stdout' => '{"ok":false,"error":"chrome failed to start: no usable sandbox"}', 'stderr' => '', 'timedOut' => false];
        $site             = new FakeReviewSite();
        $provider         = new FakeVisionProvider();

        $result = $this->review($this->config('on'), $runner, $site, $provider)->run();

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('render-failed', $result['reason']);
        $this->assertSame(1, $runner->calls);
        $this->assertSame([], $provider->requests);
        $this->assertStringContainsString('chrome failed to start', $site->notes[0]['summary']);
    }

    public function test_a_renderer_that_hangs_is_cut_off_and_skipped()
    {
        $runner           = new FakeRunner();
        $runner->response = ['exit' => -1, 'stdout' => '', 'stderr' => '', 'timedOut' => true];
        $provider         = new FakeVisionProvider();

        $result = $this->review($this->config('on'), $runner, new FakeReviewSite(), $provider)->run();

        $this->assertSame('render-failed', $result['reason']);
        $this->assertSame([], $provider->requests);
        $this->assertSame(ScreenshotRenderer::TIMEOUT, $runner->lastTimeout);
    }

    public function test_a_provider_without_vision_skips()
    {
        $provider       = new FakeVisionProvider();
        $provider->slug = 'openai';
        $runner         = new FakeRunner();

        $result = $this->review($this->config('on'), $runner, new FakeReviewSite(), $provider)->run();

        $this->assertSame('no-vision-provider', $result['reason']);
        $this->assertSame(0, $runner->calls);
    }

    public function test_screenshots_go_to_the_model_and_only_valid_fixes_are_applied()
    {
        $runner           = new FakeRunner();
        $runner->writeShots = true;
        $site             = new FakeReviewSite();
        $provider         = new FakeVisionProvider();
        $provider->payload = ['verdict' => 'needs-fixes', 'issues' => ['Steak photo on a marketer site'], 'fixes' => [
            ['type' => 'swap_image', 'page' => 'home', 'slot' => 'i2', 'image' => 'neutral', 'reason' => 'steak'],
            ['type' => 'set_custom_css', 'css' => 'body{display:none}', 'reason' => 'x'],
        ]];

        $result = $this->review($this->config('on'), $runner, $site, $provider)->run();

        $this->assertSame('done', $result['status']);
        $this->assertSame('claude-strong', $provider->requests[0]->getModel());
        $images = $provider->requests[0]->getImages();
        $this->assertCount(3, $images);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $images[0]);
        $this->assertSame(['swap_image'], array_column($site->applied, 'type'));
        $this->assertCount(1, $result['rejected']);
        $this->assertSame(['in' => 9000, 'out' => 700, 'calls' => 1], $result['usage']);
        $this->assertSame('done', end($site->notes)['status']);
    }

    public function test_a_provider_error_is_journaled_as_failed_and_nothing_is_written()
    {
        $runner             = new FakeRunner();
        $runner->writeShots = true;
        $site               = new FakeReviewSite();
        $provider           = new FakeVisionProvider();
        $provider->throw    = new \SwishfolioCore\AI\ProviderException('overloaded', 529);

        $result = $this->review($this->config('on'), $runner, $site, $provider)->run();

        $this->assertSame('failed', $result['status']);
        $this->assertSame([], $site->applied);
        $this->assertSame('failed', end($site->notes)['status']);
    }
}

class FakeRunner extends ProcessRunner
{
    public $calls = 0;
    public $lastTimeout = 0;
    public $writeShots = false;
    public $response = ['exit' => 0, 'stdout' => '', 'stderr' => '', 'timedOut' => false];

    public function run(array $argv, $timeout)
    {
        $this->calls++;
        $this->lastTimeout = $timeout;
        if (! $this->writeShots) {
            return $this->response;
        }
        $job   = json_decode(file_get_contents($argv[2]), true);
        $shots = [];
        foreach ($job['shots'] as $s) {
            $file = $job['out'] . '/' . $s['name'] . '.jpg';
            file_put_contents($file, "\xFF\xD8\xFF\xE0fakejpeg");
            $shots[] = ['name' => $s['name'], 'file' => $file, 'width' => 720, 'height' => 2000, 'pageHeight' => 4000];
        }

        return ['exit' => 0, 'stdout' => "chrome noise\n" . json_encode(['ok' => true, 'shots' => $shots]), 'stderr' => '', 'timedOut' => false];
    }
}

class FakeReviewSite implements ReviewSiteInterface
{
    public $notes = [];
    public $applied = [];

    public function pages()
    {
        return [
            ['key' => 'home', 'kitKey' => 'home', 'postId' => 10, 'title' => 'Home', 'url' => 'https://rosa.example/', 'front' => true],
            ['key' => 'page', 'kitKey' => 'services', 'postId' => 11, 'title' => 'Services', 'url' => 'https://rosa.example/services/', 'front' => false],
        ];
    }

    public function kit()
    {
        return null;
    }

    public function facts()
    {
        return [
            'pages'     => ['home' => ['postId' => 10, 'title' => 'Home', 'url' => 'https://rosa.example/', 'sections' => [1 => ['unit' => '', 'role' => 'hero', 'form' => false, 'alternatives' => []]], 'texts' => [], 'images' => ['i2' => ['file' => 'steak.jpg', 'alt' => '', 'section' => 1]]]],
            'kitImages' => [],
            'tokens'    => ['base' => '#ffffff'],
        ];
    }

    public function composeSection($unit, array $texts)
    {
        return '';
    }

    public function apply(array $fixes)
    {
        $this->applied = $fixes;

        return $fixes;
    }

    public function note($status, $summary, array $data = [])
    {
        $this->notes[] = ['status' => $status, 'summary' => $summary, 'data' => $data];
    }
}

class FakeVisionProvider implements AIProviderInterface
{
    public $slug = 'anthropic';
    public $payload = ['verdict' => 'good', 'issues' => [], 'fixes' => []];
    public $requests = [];
    public $throw;

    public function getSlug()
    {
        return $this->slug;
    }

    public function getLabel()
    {
        return 'Fake';
    }

    public function getRequiredFields()
    {
        return [];
    }

    public function getModels()
    {
        return [];
    }

    public function setCredentials(array $credentials)
    {
    }

    public function testConnection()
    {
        return true;
    }

    public function generate(AIRequest $request)
    {
        $this->requests[] = $request;
        if ($this->throw) {
            throw $this->throw;
        }

        return new AIResponse($this->payload, 9000, 700, $request->getModel(), $this->slug);
    }

    public function supportsTools()
    {
        return false;
    }

    public function converse(ConversationRequest $request)
    {
        throw new \LogicException('not used');
    }

    public function getLastError()
    {
        return null;
    }
}
