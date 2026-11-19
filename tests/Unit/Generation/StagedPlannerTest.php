<?php

namespace SwishfolioCore\Tests\Unit\Generation;

require_once __DIR__ . '/staged-stubs.php';

use SwishfolioCore\Actions\ValidationException;
use SwishfolioCore\AI\AIRequest;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\Contracts\AIProviderInterface;
use SwishfolioCore\AI\Contracts\ParallelRequestsInterface;
use SwishfolioCore\AI\Conversation\ConversationRequest;
use SwishfolioCore\AI\Generation\PlanAssembler;
use SwishfolioCore\AI\Generation\PlanSchema;
use SwishfolioCore\AI\Generation\Staged\ParallelRunner;
use SwishfolioCore\AI\Generation\Staged\SiteOutline;
use SwishfolioCore\AI\Generation\Staged\StagedAssembly;
use SwishfolioCore\AI\Generation\Staged\StagedPlanner;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\Jobs\JobProgress;

/**
 * A provider that answers the outline through generate() and each page
 * through the parallel seam, from scripted replies.
 */
class StagedFakeProvider implements AIProviderInterface, ParallelRequestsInterface
{
    public $outline;
    public $generated = [];

    public function getSlug() { return 'fake'; }
    public function getLabel() { return 'Fake'; }
    public function getRequiredFields() { return []; }
    public function getModels() { return []; }
    public function setCredentials(array $credentials) {}
    public function testConnection() { return true; }
    public function supportsTools() { return false; }
    public function converse(ConversationRequest $request) { throw new ProviderException('no', 0); }
    public function getLastError() { return null; }

    public function generate(AIRequest $request)
    {
        $this->generated[] = $request;

        return new AIResponse($this->outline, 100, 50, $request->getModel(), 'fake');
    }

    public function httpRequest(AIRequest $request)
    {
        return ['url' => 'https://fake.test', 'headers' => [], 'body' => $request->getUserPrompt(), 'cached' => $request->cachesSystem(), 'maxTokens' => $request->getMaxTokens()];
    }

    public function httpResponse(AIRequest $request, array $decoded)
    {
        if (! isset($decoded['payload']) || ! is_array($decoded['payload'])) {
            throw new ProviderException('not JSON', 200);
        }

        return new AIResponse($decoded['payload'], 10, 20, $request->getModel(), 'fake');
    }

    public function httpError($raw, $status)
    {
        return 'HTTP ' . $status;
    }
}

/**
 * Replies per page key, one per attempt: [status, payload|null].
 */
class StagedFakeTransport
{
    public $script = [];
    public $sent   = [];
    public $warm   = [];

    public function send(array $requests, $timeout, $stagger, callable $onComplete)
    {
        if (array_keys($requests) === ['cache-warm-up']) {
            $this->warm[] = $requests['cache-warm-up'];
            $onComplete('cache-warm-up', 200, json_encode(['model' => 'fake-page', 'usage' => ['input_tokens' => 10, 'output_tokens' => 1, 'cache_creation_input_tokens' => 500], 'payload' => []]), '');

            return;
        }

        $this->sent[] = ['keys' => array_keys($requests), 'timeout' => $timeout, 'stagger' => $stagger, 'requests' => $requests];

        foreach (array_keys($requests) as $key) {
            $reply = array_shift($this->script[$key]);
            $body  = $reply[1] === null ? 'not json' : json_encode(['model' => 'fake-page', 'usage' => ['input_tokens' => 10, 'output_tokens' => 20], 'payload' => $reply[1]]);
            $onComplete($key, $reply[0], $body, '');
        }
    }
}

class StagedPlannerTest extends GenerationTestCase
{
    /** @var StagedFakeProvider */
    private $provider;

    /** @var StagedFakeTransport */
    private $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['sfcore_test_actions'] = [];
        $this->provider                 = new StagedFakeProvider();
        $this->transport                = new StagedFakeTransport();
        $this->provider->outline        = $this->outlineReply();
    }

    private function outlineReply(array $overrides = [])
    {
        return array_merge([
            'schemaVersion' => PlanSchema::SCHEMA_VERSION,
            'kit'           => 'alpha',
            'summary'       => 'A joinery site.',
            'site'          => ['title' => 'Oak and Iron', 'tagline' => 'Joinery', 'frontPage' => 'home', 'navigation' => ['home', 'contact']],
            'facts'         => ['businessName' => 'Oak and Iron', 'voice' => 'plain', 'audience' => 'homeowners', 'keyMessages' => ['Built to last'], 'contact' => '+44 7700 900123'],
            'pages'         => [
                ['key' => 'home', 'title' => 'Home', 'slug' => 'home', 'purpose' => 'Say what we do.', 'sections' => [['pattern' => 'home-01-hero', 'brief' => 'Hero.']]],
                ['key' => 'contact', 'title' => 'Contact', 'slug' => 'contact', 'purpose' => 'Get in touch.', 'sections' => [['pattern' => 'contact-01-cta', 'brief' => 'Call to action.']]],
            ],
        ], $overrides);
    }

    private function pageReply($unit)
    {
        return [
            'schemaVersion' => PlanSchema::SCHEMA_VERSION,
            'kit'           => 'alpha',
            'summary'       => '',
            // The model's own key and slug are ignored: the outline's win.
            'pages'         => [['key' => 'whatever', 'title' => 'X', 'slug' => 'x', 'sections' => [$this->patternSection([], $unit)]]],
        ];
    }

    private function planner()
    {
        return new StagedPlanner(
            $this->provider,
            new PlanAssembler($this->kit(), $this->registry(), $this->tokens(), new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt')),
            new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'),
            $this->kit(),
            function ($scope) {
                return $this->context($scope);
            },
            null,
            new ParallelRunner($this->provider, $this->transport)
        );
    }

    private function brief()
    {
        return ['instruction' => 'A joinery site.', 'siteTitle' => 'Oak and Iron', 'customTemplates' => [], 'existingSlugs' => []];
    }

    private function voids()
    {
        return array_values(array_filter($GLOBALS['sfcore_test_actions'], static function ($a) {
            return $a[0] === 'swishfolio_ai_usage_void';
        }));
    }

    private function stepStatus(JobProgress $progress, $key)
    {
        foreach ($progress->steps() as $step) {
            if ($step['key'] === $key) {
                return $step['status'];
            }
        }

        return null;
    }

    public function test_outline_normalise_repairs_what_it_can()
    {
        $outline = SiteOutline::normalise($this->outlineReply([
            'site'  => ['title' => '', 'tagline' => '', 'frontPage' => 'nope', 'navigation' => ['contact', 'ghost', 'contact']],
            'pages' => [
                ['key' => 'home', 'title' => 'Home', 'slug' => 'existing', 'purpose' => '', 'sections' => [['pattern' => 'home-01-hero', 'brief' => ''], ['pattern' => 'not-in-kit', 'brief' => '']]],
                ['key' => 'Bad Key', 'title' => 'Bad', 'slug' => 'bad', 'purpose' => '', 'sections' => [['pattern' => 'home-01-hero', 'brief' => '']]],
                ['key' => 'empty', 'title' => 'Empty', 'slug' => 'empty', 'purpose' => '', 'sections' => [['pattern' => 'not-in-kit', 'brief' => '']]],
            ],
        ]), $this->kit(), $this->context('site', ['existing' => 12]));

        $this->assertSame(['home'], array_column($outline['pages'], 'key'));
        $this->assertSame([['pattern' => 'home-01-hero', 'brief' => '']], $outline['pages'][0]['sections']);
        $this->assertSame('home', $outline['site']['frontPage'], 'An unknown front page falls back to the first page.');
        $this->assertSame(['home'], $outline['site']['navigation']);
        $this->assertSame('suffix', $outline['pages'][0]['onConflict'], 'A taken slug gets a strategy.');
    }

    public function test_an_outline_with_nothing_buildable_is_refused()
    {
        $this->expectException(ValidationException::class);

        SiteOutline::normalise($this->outlineReply(['pages' => [['key' => 'x', 'title' => 'X', 'slug' => 'x', 'purpose' => '', 'sections' => [['pattern' => 'nope', 'brief' => '']]]]]), $this->kit(), $this->context('site'));
    }

    public function test_assembly_keeps_outline_order_and_drops_missing_pages_from_the_menu()
    {
        $outline = SiteOutline::normalise($this->outlineReply(), $this->kit(), $this->context('site'));
        $plan    = StagedAssembly::sitePlan($outline, ['contact' => ['key' => 'contact', 'sections' => []], 'home' => ['key' => 'home', 'sections' => []]], 'alpha');

        $this->assertSame(['home', 'contact'], array_column($plan['pages'], 'key'));
        $this->assertSame('Oak and Iron', $plan['site']['title']);

        $plan = StagedAssembly::sitePlan($outline, ['home' => ['key' => 'home', 'sections' => []]], 'alpha');
        $this->assertSame(['home'], $plan['site']['navigation']);

        $this->expectException(\InvalidArgumentException::class);
        StagedAssembly::sitePlan($outline, ['contact' => ['key' => 'contact', 'sections' => []]], 'alpha');
    }

    public function test_pages_are_written_in_parallel_into_one_valid_site_plan()
    {
        $this->transport->script = [
            'home'    => [[200, $this->pageReply('home-01-hero')]],
            'contact' => [[200, $this->pageReply('contact-01-cta')]],
        ];
        $progress = JobProgress::silent();

        $result = $this->planner()->plan($this->brief(), 'strong', 'followup', $progress);

        $this->assertCount(1, $this->transport->sent, 'Both pages in one parallel wave.');
        $this->assertSame(['home', 'contact'], $this->transport->sent[0]['keys']);
        $this->assertCount(1, $this->transport->warm, 'One tiny call writes the shared prefix to the cache first.');
        $this->assertSame(1, $this->transport->warm[0]['maxTokens']);
        $this->assertTrue($this->transport->warm[0]['cached']);
        $this->assertSame(0.0, $this->transport->sent[0]['stagger'], 'Then every page starts at once.');
        $this->assertSame(['cache-warm-up', 500], [$result['calls'][1]['call'], $result['calls'][1]['cacheWrite']], 'The warm-up is metered like any call.');
        $this->assertTrue($this->transport->sent[0]['requests']['home']['cached'], 'Page calls share a cached system prompt.');
        $this->assertSame(['home', 'contact'], array_column($result['plan']['pages'], 'key'));
        $this->assertSame('contact', $result['plan']['pages'][1]['slug'], 'Slugs come from the outline, not the page reply.');
        $this->assertEquals(['frontPage' => 'home', 'navigation' => ['home', 'contact'], 'title' => 'Oak and Iron', 'tagline' => 'Joinery'], $result['plan']['site']);
        $this->assertSame([], $result['failed']);
        $this->assertSame([], $this->voids());
        $this->assertSame('strong', $this->provider->generated[0]->getModel(), 'Outline on the strong model.');
        $this->assertSame('done', $this->stepStatus($progress, 'page:home'));
        $this->assertSame('done', $this->stepStatus($progress, 'page:contact'));
    }

    public function test_a_failing_page_is_retried_alone_and_its_failed_attempt_is_not_charged()
    {
        $this->transport->script = [
            'home'    => [[200, $this->pageReply('home-01-hero')]],
            // First reply names a section the kit does not have: invalid.
            'contact' => [[200, $this->pageReply('not-a-unit')], [200, $this->pageReply('contact-01-cta')]],
        ];

        $result = $this->planner()->plan($this->brief(), 'strong', 'followup', JobProgress::silent());

        $this->assertCount(2, $this->transport->sent);
        $this->assertSame(['contact'], $this->transport->sent[1]['keys'], 'Only the failed page is retried.');
        $this->assertSame(0.0, $this->transport->sent[1]['stagger']);
        $this->assertSame(['home', 'contact'], array_column($result['plan']['pages'], 'key'));
        $this->assertCount(1, $this->voids(), 'The rejected attempt is voided once.');
        $this->assertSame([false, true], array_column(array_values(array_filter($result['calls'], static function ($c) {
            return $c['call'] === 'page:contact';
        })), 'kept'));
    }

    public function test_a_page_that_fails_twice_is_left_out_and_reported_while_the_rest_is_built()
    {
        $this->transport->script = [
            'home'    => [[200, $this->pageReply('home-01-hero')]],
            'contact' => [[500, null], [200, null]],
        ];
        $progress = JobProgress::silent();

        $result = $this->planner()->plan($this->brief(), 'strong', 'followup', $progress);

        $this->assertSame(['home'], array_column($result['plan']['pages'], 'key'));
        $this->assertSame(['home'], $result['plan']['site']['navigation']);
        $this->assertSame('contact', $result['failed'][0]['key']);
        $this->assertSame('Contact', $result['failed'][0]['title']);
        $this->assertSame('failed', $this->stepStatus($progress, 'page:contact'));
        $this->assertCount(0, $this->voids(), 'A reply that never parsed was never metered.');
    }

    public function test_the_build_stops_when_the_front_page_cannot_be_written()
    {
        $this->transport->script = [
            'home'    => [[200, $this->pageReply('not-a-unit')], [200, $this->pageReply('not-a-unit')]],
            'contact' => [[200, $this->pageReply('contact-01-cta')]],
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->planner()->plan($this->brief(), 'strong', 'followup', JobProgress::silent());
        } finally {
            $this->assertCount(2, $this->voids());
        }
    }

    public function test_retry_page_plans_one_page_of_a_saved_outline()
    {
        $outline                 = SiteOutline::normalise($this->outlineReply(), $this->kit(), $this->context('site'));
        $this->transport->script = ['contact' => [[200, $this->pageReply('contact-01-cta')]]];

        $plan = $this->planner()->retryPage($outline, 'contact', $this->brief(), 'followup', JobProgress::silent());

        $this->assertSame(['contact'], $this->transport->sent[0]['keys']);
        $this->assertSame('contact', $plan['pages'][0]['key']);
        $this->assertArrayNotHasKey('site', $plan);
    }
}
