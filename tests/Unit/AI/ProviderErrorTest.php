<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\ProviderError;

/**
 * BYOK means the most common failures are the site owner's account problems,
 * not our bugs. "Your credit balance is too low" surfaced as a generic provider
 * error during the first real call, which tells the user nothing about what to
 * do. Each case here maps to an action they can take.
 */
class ProviderErrorTest extends TestCase
{
    public function test_an_exhausted_balance_says_to_add_credit_not_to_check_the_key()
    {
        $guidance = ProviderError::guidance(400, 'Your credit balance is too low to access the Anthropic API.');

        $this->assertSame(ProviderError::NO_CREDIT, $guidance['code']);
        $this->assertStringContainsString('credit', strtolower($guidance['message']));
        $this->assertStringNotContainsString('invalid', strtolower($guidance['message']));
    }

    public function test_an_openai_quota_message_maps_to_the_same_guidance()
    {
        $guidance = ProviderError::guidance(429, 'You exceeded your current quota, please check your plan and billing details.');

        $this->assertSame(ProviderError::NO_CREDIT, $guidance['code']);
    }

    public function test_an_unauthorised_status_says_the_key_is_wrong()
    {
        foreach ([401, 403] as $status) {
            $guidance = ProviderError::guidance($status, 'invalid x-api-key');

            $this->assertSame(ProviderError::BAD_KEY, $guidance['code'], 'status ' . $status);
            $this->assertStringContainsString('key', strtolower($guidance['message']));
        }
    }

    public function test_a_rate_limit_says_to_wait_rather_than_to_change_anything()
    {
        $guidance = ProviderError::guidance(429, 'Number of requests has exceeded your rate limit.');

        $this->assertSame(ProviderError::RATE_LIMITED, $guidance['code']);
    }

    public function test_an_unknown_model_names_the_setting_to_change()
    {
        $guidance = ProviderError::guidance(404, 'model: claude-nope-5 not found');

        $this->assertSame(ProviderError::BAD_MODEL, $guidance['code']);
        $this->assertStringContainsString('model', strtolower($guidance['message']));
    }

    public function test_a_server_error_is_reported_as_the_provider_being_down()
    {
        $guidance = ProviderError::guidance(503, 'overloaded_error');

        $this->assertSame(ProviderError::PROVIDER_DOWN, $guidance['code']);
    }

    public function test_a_transport_failure_is_reported_as_a_network_problem()
    {
        $guidance = ProviderError::guidance(0, 'cURL error 28: Connection timed out');

        $this->assertSame(ProviderError::NETWORK, $guidance['code']);
    }

    public function test_an_unrecognised_error_falls_back_without_inventing_advice()
    {
        $guidance = ProviderError::guidance(418, 'I am a teapot');

        $this->assertSame(ProviderError::UNKNOWN, $guidance['code']);
        $this->assertSame('I am a teapot', $guidance['detail']);
    }

    public function test_the_raw_provider_message_is_kept_as_detail_for_disclosure()
    {
        $raw      = 'Your credit balance is too low to access the Anthropic API.';
        $guidance = ProviderError::guidance(400, $raw);

        $this->assertSame($raw, $guidance['detail'], 'The raw text must stay available behind a disclosure.');
        $this->assertNotSame($raw, $guidance['message'], 'But it must not be the headline.');
    }
}
