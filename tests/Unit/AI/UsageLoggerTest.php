<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\AIResponse;
use SwishfolioCore\AI\UsageLogger;

class UsageLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_options'] = [];
    }

    private function response()
    {
        return new AIResponse(['actions' => []], 1200, 90, 'gpt-4o', 'openai');
    }

    public function test_counters_accumulate_across_requests()
    {
        $logger = new UsageLogger();
        $logger->record($this->response(), 3, 820, 'applied');
        $logger->record($this->response(), 2, 640, 'applied');

        $counters = $logger->getUsage()['counters'];

        $this->assertSame(2, $counters['requests']);
        $this->assertSame(2400, $counters['inputTokens']);
        $this->assertSame(180, $counters['outputTokens']);
        $this->assertSame(5, $counters['actionsProposed']);
    }

    public function test_failures_are_counted_by_stage()
    {
        $logger = new UsageLogger();
        $logger->recordFailure('allowlist', 'openai', 'gpt-4o', 300);
        $logger->recordFailure('allowlist', 'openai', 'gpt-4o', 250);
        $logger->recordFailure('value', 'openai', 'gpt-4o', 200);

        $this->assertSame(2, $logger->getUsage()['counters']['failures']['allowlist']);
        $this->assertSame(1, $logger->getUsage()['counters']['failures']['value']);
    }

    public function test_recent_window_is_capped_at_one_hundred()
    {
        $logger = new UsageLogger();

        for ($i = 0; $i < 105; $i++) {
            $logger->record($this->response(), 1, 100, 'applied');
        }

        $usage = $logger->getUsage();

        $this->assertCount(100, $usage['recent']);
        $this->assertSame(105, $usage['counters']['requests']);
    }

    public function test_no_prompt_or_content_text_is_ever_stored()
    {
        $logger   = new UsageLogger();
        $sentinel = 'SENSITIVE_PROMPT_TEXT';

        $logger->record(
            new AIResponse(['summary' => $sentinel, 'actions' => []], 10, 10, 'gpt-4o', 'openai'),
            1,
            100,
            'applied'
        );

        $this->assertStringNotContainsString(
            $sentinel,
            json_encode($GLOBALS['sfcore_test_options'][UsageLogger::OPTION])
        );
    }
}
