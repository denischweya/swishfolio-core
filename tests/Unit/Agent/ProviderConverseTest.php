<?php

namespace SwishfolioCore\Tests\Unit\Agent;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Conversation\ConversationRequest;
use SwishfolioCore\AI\Conversation\Message;
use SwishfolioCore\AI\Conversation\ToolCall;
use SwishfolioCore\AI\Conversation\ToolDefinition;
use SwishfolioCore\Tests\Unit\AI\SpyAnthropicProvider;
use SwishfolioCore\Tests\Unit\AI\SpyOpenAIProvider;

/**
 * The two wire formats differ in a way that matters: Anthropic carries tool
 * results as content blocks inside one user message, OpenAI as separate
 * messages addressed by id. Getting the Anthropic side wrong does not error —
 * it teaches the model to stop issuing parallel calls, which only shows up
 * later as the agent being slow.
 */
class ProviderConverseTest extends TestCase
{
    private function tools()
    {
        return [
            new ToolDefinition(
                'swishfolio/site-info',
                'Read the site.',
                ['type' => 'object', 'additionalProperties' => false, 'properties' => []]
            ),
        ];
    }

    private function conversationWithParallelResults()
    {
        return [
            Message::user('What is on my site?'),
            Message::assistant(
                'Let me look.',
                [
                    new ToolCall('call_a', 'swishfolio/site-info', []),
                    new ToolCall('call_b', 'swishfolio/list-content', ['postType' => 'page']),
                ]
            ),
            Message::toolResult('call_a', '{"name":"Acme"}'),
            Message::toolResult('call_b', '{"items":[]}'),
        ];
    }

    private function request(array $messages)
    {
        return new ConversationRequest('You manage a site.', $messages, $this->tools(), 'test-model', 1024);
    }

    private function anthropicSpy(array $content, $stopReason = 'tool_use')
    {
        $spy = new SpyAnthropicProvider();

        $spy->nextResponse = [
            'response' => ['code' => 200],
            'body'     => wp_json_encode(
                [
                    'content'     => $content,
                    'stop_reason' => $stopReason,
                    'model'       => 'test-model',
                    'usage'       => ['input_tokens' => 11, 'output_tokens' => 7],
                ]
            ),
        ];

        return $spy;
    }

    public function test_anthropic_advertises_tools_with_provider_safe_names()
    {
        $spy = $this->anthropicSpy([['type' => 'text', 'text' => 'Done.']], 'end_turn');

        $spy->converse($this->request([Message::user('hello')]));

        $this->assertSame('swishfolio__site-info', $spy->lastBody['tools'][0]['name']);
        $this->assertSame('object', $spy->lastBody['tools'][0]['input_schema']['type']);
    }

    public function test_anthropic_merges_parallel_tool_results_into_one_user_message()
    {
        $spy = $this->anthropicSpy([['type' => 'text', 'text' => 'Done.']], 'end_turn');

        $spy->converse($this->request($this->conversationWithParallelResults()));

        $messages = $spy->lastBody['messages'];
        $last     = end($messages);

        $this->assertSame('user', $last['role']);
        $this->assertCount(2, $last['content'], 'Both results belong to one message.');
        $this->assertSame('tool_result', $last['content'][0]['type']);
        $this->assertSame('call_a', $last['content'][0]['tool_use_id']);
        $this->assertSame('call_b', $last['content'][1]['tool_use_id']);
    }

    public function test_anthropic_sends_the_assistants_calls_as_tool_use_blocks()
    {
        $spy = $this->anthropicSpy([['type' => 'text', 'text' => 'Done.']], 'end_turn');

        $spy->converse($this->request($this->conversationWithParallelResults()));

        $assistant = $spy->lastBody['messages'][1];

        $this->assertSame('assistant', $assistant['role']);
        $this->assertSame('text', $assistant['content'][0]['type']);
        $this->assertSame('tool_use', $assistant['content'][1]['type']);
        $this->assertSame('swishfolio__site-info', $assistant['content'][1]['name']);
    }

    public function test_anthropic_decodes_tool_use_blocks_into_calls_with_canonical_names()
    {
        $spy = $this->anthropicSpy(
            [
                ['type' => 'text', 'text' => 'Looking now.'],
                [
                    'type'  => 'tool_use',
                    'id'    => 'toolu_1',
                    'name'  => 'swishfolio__read-page',
                    'input' => ['postId' => 12],
                ],
            ]
        );

        $reply = $spy->converse($this->request([Message::user('read my home page')]));

        $this->assertSame('Looking now.', $reply->getText());
        $this->assertTrue($reply->hasToolCalls());
        $this->assertSame('swishfolio/read-page', $reply->getToolCalls()[0]->getName());
        $this->assertSame(['postId' => 12], $reply->getToolCalls()[0]->getInput());
        $this->assertSame('toolu_1', $reply->getToolCalls()[0]->getId());
        $this->assertSame(11, $reply->getInputTokens());
    }

    public function test_anthropic_reports_a_refusal_rather_than_an_empty_reply()
    {
        $spy = new SpyAnthropicProvider();

        $spy->nextResponse = [
            'response' => ['code' => 200],
            'body'     => wp_json_encode(
                ['content' => [], 'stop_reason' => 'refusal', 'stop_details' => ['category' => 'cyber']]
            ),
        ];

        $this->expectException(\SwishfolioCore\AI\ProviderException::class);

        $spy->converse($this->request([Message::user('hello')]));
    }

    public function test_an_error_result_is_flagged_for_anthropic()
    {
        $spy = $this->anthropicSpy([['type' => 'text', 'text' => 'ok']], 'end_turn');

        $spy->converse(
            $this->request(
                [
                    Message::user('do it'),
                    Message::assistant('', [new ToolCall('c1', 'swishfolio/read-page', [])]),
                    Message::toolResult('c1', '{"code":"schema_invalid"}', true),
                ]
            )
        );

        $messages = $spy->lastBody['messages'];
        $last     = end($messages);

        $this->assertTrue($last['content'][0]['is_error']);
    }

    private function openAiSpy(array $message, $finish = 'tool_calls')
    {
        $spy = new SpyOpenAIProvider();

        $spy->nextResponse = [
            'response' => ['code' => 200],
            'body'     => wp_json_encode(
                [
                    'choices' => [['message' => $message, 'finish_reason' => $finish]],
                    'model'   => 'test-model',
                    'usage'   => ['prompt_tokens' => 21, 'completion_tokens' => 9],
                ]
            ),
        ];

        return $spy;
    }

    public function test_openai_advertises_tools_as_functions()
    {
        $spy = $this->openAiSpy(['content' => 'Done.'], 'stop');

        $spy->converse($this->request([Message::user('hello')]));

        $this->assertSame('function', $spy->lastBody['tools'][0]['type']);
        $this->assertSame('swishfolio__site-info', $spy->lastBody['tools'][0]['function']['name']);
    }

    public function test_openai_sends_each_tool_result_as_its_own_message()
    {
        $spy = $this->openAiSpy(['content' => 'Done.'], 'stop');

        $spy->converse($this->request($this->conversationWithParallelResults()));

        $roles = array_column($spy->lastBody['messages'], 'role');

        $this->assertSame(['system', 'user', 'assistant', 'tool', 'tool'], $roles);
        $this->assertSame('call_a', $spy->lastBody['messages'][3]['tool_call_id']);
        $this->assertSame('call_b', $spy->lastBody['messages'][4]['tool_call_id']);
    }

    public function test_openai_serialises_call_arguments_as_a_json_string()
    {
        $spy = $this->openAiSpy(['content' => 'Done.'], 'stop');

        $spy->converse($this->request($this->conversationWithParallelResults()));

        $calls = $spy->lastBody['messages'][2]['tool_calls'];

        $this->assertIsString($calls[1]['function']['arguments'], 'OpenAI takes arguments as a JSON string.');
        $this->assertSame(['postType' => 'page'], json_decode($calls[1]['function']['arguments'], true));
    }

    public function test_openai_decodes_tool_calls_from_their_json_string_arguments()
    {
        $spy = $this->openAiSpy(
            [
                'content'    => null,
                'tool_calls' => [
                    [
                        'id'       => 'call_x',
                        'type'     => 'function',
                        'function' => ['name' => 'swishfolio__read-page', 'arguments' => '{"postId":12}'],
                    ],
                ],
            ]
        );

        $reply = $spy->converse($this->request([Message::user('read it')]));

        $this->assertSame('swishfolio/read-page', $reply->getToolCalls()[0]->getName());
        $this->assertSame(['postId' => 12], $reply->getToolCalls()[0]->getInput());
        $this->assertSame(21, $reply->getInputTokens());
    }

    public function test_openai_tolerates_empty_arguments_for_a_tool_that_takes_none()
    {
        $spy = $this->openAiSpy(
            [
                'content'    => null,
                'tool_calls' => [
                    [
                        'id'       => 'call_y',
                        'type'     => 'function',
                        'function' => ['name' => 'swishfolio__site-info', 'arguments' => ''],
                    ],
                ],
            ]
        );

        $reply = $spy->converse($this->request([Message::user('what is here')]));

        $this->assertSame([], $reply->getToolCalls()[0]->getInput());
    }

    public function test_openai_makes_a_failure_legible_in_the_content()
    {
        // There is no is_error flag in this format, so a rejection that reads as
        // an ordinary empty result would be taken as success.
        $spy = $this->openAiSpy(['content' => 'ok'], 'stop');

        $spy->converse(
            $this->request(
                [
                    Message::user('do it'),
                    Message::assistant('', [new ToolCall('c1', 'swishfolio/read-page', [])]),
                    Message::toolResult('c1', '{"code":"forbidden"}', true),
                ]
            )
        );

        $messages = $spy->lastBody['messages'];
        $last     = end($messages);

        $this->assertStringStartsWith('ERROR:', $last['content']);
    }
}
