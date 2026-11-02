<?php
/**
 * One turn of a tool-using conversation.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Conversation;

/**
 * One class for all three shapes — a user's words, an assistant's reply with its
 * tool calls, and the result of one of those calls — because providers interleave
 * them in a single ordered list. Separate classes would only have to be merged
 * back into that list, and the ordering is the part that matters: a tool result
 * that does not immediately follow its call is a protocol error at both
 * providers.
 */
class Message
{
    const ROLE_USER      = 'user';
    const ROLE_ASSISTANT = 'assistant';
    const ROLE_TOOL      = 'tool_result';

    /** @var string */
    private $role;

    /** @var string */
    private $text;

    /** @var ToolCall[] */
    private $toolCalls;

    /** @var string */
    private $toolCallId;

    /** @var bool */
    private $isError = false;

    /**
     * @param string     $role       One of the ROLE_* constants.
     * @param string     $text       Message text, or the tool result payload.
     * @param ToolCall[] $toolCalls  Calls the assistant asked for.
     * @param string     $toolCallId Call this result answers.
     * @param bool       $isError    Whether a tool result reports a failure.
     */
    public function __construct($role, $text = '', array $toolCalls = [], $toolCallId = '', $isError = false)
    {
        $this->role       = (string) $role;
        $this->text       = (string) $text;
        $this->toolCalls  = $toolCalls;
        $this->toolCallId = (string) $toolCallId;
        $this->isError    = (bool) $isError;
    }

    /**
     * @param string $text What the user said.
     *
     * @return self
     */
    public static function user($text)
    {
        return new self(self::ROLE_USER, $text);
    }

    /**
     * @param string     $text      What the assistant said.
     * @param ToolCall[] $toolCalls Calls it asked for.
     *
     * @return self
     */
    public static function assistant($text, array $toolCalls = [])
    {
        return new self(self::ROLE_ASSISTANT, $text, $toolCalls);
    }

    /**
     * @param string $toolCallId Call being answered.
     * @param string $payload    Result as JSON, or the error.
     * @param bool   $isError    Whether the call failed.
     *
     * @return self
     */
    public static function toolResult($toolCallId, $payload, $isError = false)
    {
        return new self(self::ROLE_TOOL, $payload, [], $toolCallId, $isError);
    }

    public function getRole()
    {
        return $this->role;
    }

    public function getText()
    {
        return $this->text;
    }

    /**
     * @return ToolCall[]
     */
    public function getToolCalls()
    {
        return $this->toolCalls;
    }

    public function getToolCallId()
    {
        return $this->toolCallId;
    }

    public function isError()
    {
        return $this->isError;
    }

    /**
     * @return array Serialisable form, for handing history back to the client.
     */
    public function toArray()
    {
        $out = ['role' => $this->role];

        if ('' !== $this->text) {
            $out['text'] = $this->text;
        }

        if ($this->toolCalls !== []) {
            $out['toolCalls'] = array_map(
                static function (ToolCall $call) {
                    return $call->toArray();
                },
                $this->toolCalls
            );
        }

        if ('' !== $this->toolCallId) {
            $out['toolCallId'] = $this->toolCallId;
        }

        if ($this->isError) {
            $out['isError'] = true;
        }

        return $out;
    }

    /**
     * Rebuilds a message the client sent back as history.
     *
     * History arrives from the browser, so nothing in it is trusted as
     * instructions — it is replayed as conversation, and every tool call in it
     * is re-validated and re-authorised when it runs.
     *
     * @param array $data Serialised message.
     *
     * @return self|null Null when the shape is unusable.
     */
    public static function fromArray(array $data)
    {
        $role = isset($data['role']) ? (string) $data['role'] : '';

        if (! in_array($role, [self::ROLE_USER, self::ROLE_ASSISTANT, self::ROLE_TOOL], true)) {
            return null;
        }

        $calls = [];

        if (isset($data['toolCalls']) && is_array($data['toolCalls'])) {
            foreach ($data['toolCalls'] as $call) {
                if (! is_array($call)) {
                    continue;
                }

                $calls[] = new ToolCall(
                    isset($call['id']) ? $call['id'] : '',
                    isset($call['name']) ? $call['name'] : '',
                    isset($call['input']) && is_array($call['input']) ? $call['input'] : []
                );
            }
        }

        return new self(
            $role,
            isset($data['text']) ? (string) $data['text'] : '',
            $calls,
            isset($data['toolCallId']) ? (string) $data['toolCallId'] : '',
            ! empty($data['isError'])
        );
    }
}
