<?php

namespace SwishfolioCore\Tests\Unit\AI;

use SwishfolioCore\Actions\ActionValidator;
use SwishfolioCore\Actions\ValidationContext;
use SwishfolioCore\Actions\ValidationException;
use SwishfolioCore\AI\AIManager;
use SwishfolioCore\AI\Context\ContextAssembler;
use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Blocks\BlockRegistry;
use SwishfolioCore\Tests\Unit\Actions\StageTestCase;

class AIManagerTest extends StageTestCase
{
    /** @var FakeProvider */
    private $provider;

    /** @var UsageLogger */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_options'] = [];
        $this->provider = new FakeProvider();
        $this->logger   = new UsageLogger();
    }

    private function manager()
    {
        $registry = $this->registry();

        return new AIManager(
            $this->provider,
            new ContextAssembler(
                $registry,
                $this->tokens(),
                new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt')
            ),
            ActionValidator::withDefaultStages(),
            $this->logger
        );
    }

    private function selection()
    {
        return [
            'clientId'    => 'hero-1',
            'blockName'   => 'swishfolio-core/hero',
            'attributes'  => ['heading' => 'Welcome'],
            'innerBlocks' => [],
            'parent'      => null,
            'siblings'    => [],
            'postType'    => 'page',
        ];
    }

    public function test_valid_round_trip_returns_validated_actions()
    {
        $this->provider->payload = [
            'schemaVersion' => '1.0',
            'summary'       => 'Tightened the hero.',
            'actions'       => [$this->updateAction('hero-1', ['backgroundColor' => 'accent-2'])],
        ];

        $result = $this->manager()->edit($this->selection(), 'Make it pop', $this->context('hero-1'), 'fake-1');

        $this->assertSame(
            'var(--wp--preset--color--accent-2)',
            $result['actions'][0]['changes']['backgroundColor']
        );
    }

    public function test_usage_is_recorded_on_success()
    {
        $this->manager()->edit($this->selection(), 'Make it pop', $this->context('hero-1'), 'fake-1');

        $counters = $this->logger->getUsage()['counters'];

        $this->assertSame(1, $counters['requests']);
        $this->assertSame(1000, $counters['inputTokens']);
    }

    public function test_validation_failure_is_recorded_by_stage_and_rethrown()
    {
        $this->provider->payload = [
            'schemaVersion' => '1.0',
            'summary'       => 'Nope.',
            'actions'       => [$this->updateAction('hero-1', ['decoShape1Color' => 'accent-1'])],
        ];

        try {
            $this->manager()->edit($this->selection(), 'Do it', $this->context('hero-1'), 'fake-1');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('allowlist', $e->getStage());
        }

        $this->assertSame(1, $this->logger->getUsage()['counters']['failures']['allowlist']);
    }

    public function test_provider_failure_propagates()
    {
        $this->provider->throw = new ProviderException('Invalid API key', 401);

        $this->expectException(ProviderException::class);
        $this->manager()->edit($this->selection(), 'Do it', $this->context('hero-1'), 'fake-1');
    }

    public function test_the_model_reaches_the_provider()
    {
        $this->manager()->edit($this->selection(), 'Do it', $this->context('hero-1'), 'fake-1');

        $this->assertSame('fake-1', $this->provider->lastRequest->getModel());
    }
}
