<?php

namespace SwishfolioCore\Tests\Unit\REST;

require_once __DIR__ . '/../Tools/wp-tools-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Credentials\WordPressCredentialStore;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\Blocks\BlockRegistry;
use SwishfolioCore\REST\ToolsController;
use SwishfolioCore\Tests\Support\InMemoryJournalRepository;
use SwishfolioCore\Tests\Unit\AI\FakeEncryptionService;
use SwishfolioCore\Tests\Unit\AI\FakeRestRequest;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolRegistry;
use SwishfolioCore\Tools\ToolRunner;

class ToolsControllerTest extends TestCase
{
    /** @var InMemoryJournalRepository */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();

        sfcore_test_tools_reset();

        $GLOBALS['sfcore_test_transients'] = [];
        $GLOBALS['sfcore_test_options']    = ['swishfolio_ai_settings' => ['enabled' => true]];

        $this->repository = new InMemoryJournalRepository();
    }

    private function controller()
    {
        $journal  = new MutationJournal($this->repository);
        $registry = ToolRegistry::withDefaultTools(
            $journal,
            new BlockRegistry([SFCORE_PLUGIN_DIR . 'blocks'])
        );

        return new ToolsController(
            new ToolRunner($registry, $journal),
            $this->repository,
            new Settings(new WordPressCredentialStore(new FakeEncryptionService()))
        );
    }

    /**
     * rest_ensure_response() is stubbed to pass the payload straight through, so
     * a controller returns a bare array here and a WP_REST_Response in WordPress.
     *
     * @param mixed $result Controller return value.
     *
     * @return array
     */
    private function data($result)
    {
        return is_array($result) ? $result : $result->get_data();
    }

    public function test_the_tool_list_describes_every_tool_for_the_model()
    {
        $tools = $this->data($this->controller()->listTools(new FakeRestRequest([])))['tools'];

        $this->assertNotEmpty($tools);
        $this->assertArrayHasKey('inputSchema', $tools[0]);
        $this->assertArrayHasKey('sideEffect', $tools[0]);
    }

    public function test_an_empty_call_list_is_a_bad_request()
    {
        $result = $this->controller()->run(new FakeRestRequest(['calls' => []]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(400, $result->get_error_data()['status']);
    }

    public function test_more_calls_than_the_cap_are_refused()
    {
        $calls = array_fill(0, ToolsController::MAX_CALLS + 1, ['name' => 'swishfolio/site-info', 'input' => []]);

        $result = $this->controller()->run(new FakeRestRequest(['calls' => $calls]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(400, $result->get_error_data()['status']);
    }

    public function test_a_read_runs_without_a_token_and_reports_the_run_id()
    {
        $data = $this->data(
            $this->controller()->run(
                new FakeRestRequest(['calls' => [['name' => 'swishfolio/site-info', 'input' => []]]])
            )
        );

        $this->assertNotSame('', $data['runId']);
        $this->assertSame('swishfolio/site-info', $data['results'][0]['name']);
    }

    public function test_a_published_write_without_a_token_is_a_conflict_not_a_server_error()
    {
        $GLOBALS['sfcore_test_wp']['posts'][60] = [
            'ID'           => 60,
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Home',
            'post_name'    => 'home',
            'post_content' => '',
            'post_author'  => 1,
        ];

        $result = $this->controller()->run(
            new FakeRestRequest(['calls' => [['name' => 'swishfolio/set-front-page', 'input' => ['postId' => 60]]]])
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(409, $result->get_error_data()['status']);
        $this->assertSame('confirm_required', $result->get_error_data()['code']);
        $this->assertSame([], $GLOBALS['sfcore_test_options']['show_on_front'] ?? []);
    }

    public function test_a_preview_hands_back_a_token_that_then_authorises_the_run()
    {
        $GLOBALS['sfcore_test_wp']['posts'][60] = [
            'ID'           => 60,
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Home',
            'post_name'    => 'home',
            'post_content' => '',
            'post_author'  => 1,
        ];

        $calls      = [['name' => 'swishfolio/set-front-page', 'input' => ['postId' => 60]]];
        $controller = $this->controller();

        $preview = $this->data($controller->preview(new FakeRestRequest(['calls' => $calls])));

        $this->assertTrue($preview['requiresConfirmation']);
        $this->assertStringContainsString('Home', $preview['previews'][0]['summary']);

        $controller->run(new FakeRestRequest(['calls' => $calls, 'confirmToken' => $preview['confirmToken']]));

        $this->assertSame('page', $GLOBALS['sfcore_test_options']['show_on_front']);
    }

    public function test_an_unknown_tool_is_a_404_naming_what_exists()
    {
        $result = $this->controller()->run(
            new FakeRestRequest(['calls' => [['name' => 'swishfolio/delete-site', 'input' => []]]])
        );

        $this->assertSame(404, $result->get_error_data()['status']);
        $this->assertSame('tool_not_found', $result->get_error_data()['code']);
        $this->assertNotEmpty($result->get_error_data()['details']['available']);
    }

    public function test_invalid_input_is_a_400_naming_the_field()
    {
        $result = $this->controller()->run(
            new FakeRestRequest(['calls' => [['name' => 'swishfolio/read-page', 'input' => []]]])
        );

        $this->assertSame(400, $result->get_error_data()['status']);
        $this->assertSame('postId', $result->get_error_data()['details']['field']);
    }

    public function test_the_routes_are_closed_when_the_owner_turns_ai_off()
    {
        $GLOBALS['sfcore_test_options']['swishfolio_ai_settings'] = ['enabled' => false];

        $result = $this->controller()->canUseTools(new FakeRestRequest([]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('sfcore_ai_disabled', $result->get_error_code());
    }

    public function test_the_routes_are_closed_to_a_user_without_the_capability()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_posts'];

        $result = $this->controller()->canUseTools(new FakeRestRequest([]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(403, $result->get_error_data()['status']);
    }

    public function test_a_runaway_loop_of_writes_is_rate_limited()
    {
        $controller = $this->controller();
        $calls      = [['name' => 'swishfolio/site-info', 'input' => []]];

        set_transient('sfcore_tool_rate_' . get_current_user_id(), ToolsController::RATE_LIMIT, 3600);

        $result = $controller->run(new FakeRestRequest(['calls' => $calls]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(429, $result->get_error_data()['status']);
    }

    public function test_the_audit_log_lists_runs_and_their_entries()
    {
        $controller = $this->controller();

        $run = $this->data(
            $controller->run(
                new FakeRestRequest(
                    [
                        'calls' => [
                            [
                                'name'  => 'swishfolio/create-page',
                                'input' => ['key' => 'home', 'title' => 'Home', 'content' => 'x'],
                            ],
                        ],
                    ]
                )
            )
        );

        $this->assertNotEmpty($this->data($controller->runs(new FakeRestRequest([])))['runs']);

        $entries = $this->data($controller->runDetail(new FakeRestRequest(['runId' => $run['runId']])))['entries'];

        $this->assertCount(2, $entries, 'One call row and one target row.');
        $this->assertFalse($entries[0]['reversible'], 'A call row is an audit record, not an undo record.');
        $this->assertTrue($entries[1]['reversible']);
        $this->assertStringContainsString('Home', $entries[0]['summary']);
    }
}
