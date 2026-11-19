<?php

namespace SwishfolioCore\Tests\Unit\REST;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Credentials\WordPressCredentialStore;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Blocks\BlockRegistry;
use SwishfolioCore\REST\AIController;
use SwishfolioCore\Tests\Unit\AI\FakeEncryptionService;
use SwishfolioCore\Tests\Unit\AI\FakeRestRequest;

/**
 * Applying happens client-side, so the accept rate is only measurable if the
 * editor reports back. UsageLogger::recordAccepted() existed but nothing ever
 * called it, leaving "Changes applied" permanently zero.
 */
class AppliedEndpointTest extends TestCase
{
    /** @var UsageLogger */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_options'] = [];
        $GLOBALS['sfcore_test_transients'] = [];
        unset($GLOBALS['sfcore_test_wp']['caps']);

        $this->logger = new UsageLogger();
    }

    private function controller()
    {
        $settings = new Settings(new WordPressCredentialStore(new FakeEncryptionService()));

        return new AIController(
            new BlockRegistry([SFCORE_PLUGIN_DIR . 'blocks']),
            $settings,
            new ProviderRegistry($settings),
            $this->logger
        );
    }

    public function test_it_records_the_applied_count()
    {
        $this->controller()->applied(new FakeRestRequest(['count' => 3]));

        $this->assertSame(3, $this->logger->getUsage()['counters']['actionsAccepted']);
    }

    public function test_counts_accumulate_across_edits()
    {
        $controller = $this->controller();
        $controller->applied(new FakeRestRequest(['count' => 2]));
        $controller->applied(new FakeRestRequest(['count' => 4]));

        $this->assertSame(6, $this->logger->getUsage()['counters']['actionsAccepted']);
    }

    public function test_a_wild_count_is_capped_so_a_bad_client_cannot_inflate_the_statistic()
    {
        $response = $this->controller()->applied(new FakeRestRequest(['count' => 100000]));

        $this->assertSame(25, $response['recorded']);
        $this->assertSame(25, $this->logger->getUsage()['counters']['actionsAccepted']);
    }

    public function test_zero_and_negative_counts_record_nothing()
    {
        $controller = $this->controller();
        $controller->applied(new FakeRestRequest(['count' => 0]));
        $controller->applied(new FakeRestRequest(['count' => -5]));

        $this->assertSame(0, $this->logger->getUsage()['counters']['actionsAccepted']);
    }

    public function test_it_requires_the_ai_capability()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = ['edit_posts'];

        $this->assertFalse($this->controller()->canUseAi());
    }

    public function test_a_user_with_the_capability_may_report()
    {
        $GLOBALS['sfcore_test_wp']['caps'] = [AIController::CAPABILITY];

        $this->assertTrue($this->controller()->canUseAi());
    }
}
