<?php
/**
 * Reassembles a streamed Anthropic Messages response.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

/**
 * Fed raw server-sent-event bytes as they arrive, it tells a listener what the
 * model is doing (thinking, writing text) and, once the stream ends, rebuilds
 * the same body shape a non-streamed request returns, so everything downstream
 * (refusal checks, usage metering) reads it unchanged.
 */
class AnthropicStream
{
    /** @var callable|null */
    private $listener;

    /** @var string */
    private $buffer = '';

    /** @var array */
    private $message = [];

    /** @var array index => content block */
    private $blocks = [];

    /** @var array */
    private $usage = [];

    /** @var string */
    private $stopReason = '';

    /** @var array|null */
    private $stopDetails = null;

    /** @var array|null */
    private $error = null;

    /** @var bool */
    private $sawEvent = false;

    /**
     * @param callable|null $listener Progress listener.
     */
    public function __construct($listener = null)
    {
        $this->listener = $listener;
    }

    /**
     * @param string $chunk Raw bytes.
     *
     * @return void
     */
    public function feed($chunk)
    {
        $this->buffer = str_replace("\r\n", "\n", $this->buffer . (string) $chunk);

        while (($pos = strpos($this->buffer, "\n\n")) !== false) {
            $raw          = substr($this->buffer, 0, $pos);
            $this->buffer = (string) substr($this->buffer, $pos + 2);
            $this->event($raw);
        }
    }

    /**
     * @return void
     */
    public function finish()
    {
        if (trim($this->buffer) !== '') {
            $this->event($this->buffer);
        }

        $this->buffer = '';
    }

    /**
     * @return bool Whether any event was parsed.
     */
    public function sawEvent()
    {
        return $this->sawEvent;
    }

    /**
     * @return array|null Error event payload.
     */
    public function error()
    {
        return $this->error;
    }

    /**
     * @return array Body in the non-streamed response shape.
     */
    public function toDecoded()
    {
        ksort($this->blocks);

        $decoded = $this->message;

        $decoded['content']     = array_values($this->blocks);
        $decoded['stop_reason'] = $this->stopReason;
        $decoded['usage']       = $this->usage;

        if ($this->stopDetails !== null) {
            $decoded['stop_details'] = $this->stopDetails;
        }

        return $decoded;
    }

    /**
     * @param string $raw One event.
     *
     * @return void
     */
    private function event($raw)
    {
        $data = '';

        foreach (explode("\n", $raw) as $line) {
            if (strpos($line, 'data:') === 0) {
                $data .= ltrim(substr($line, 5));
            }
        }

        $event = $data === '' ? null : json_decode($data, true);

        if (! is_array($event) || ! isset($event['type'])) {
            return;
        }

        $this->sawEvent = true;

        switch ($event['type']) {
            case 'message_start':
                $message = isset($event['message']) ? (array) $event['message'] : [];
                $this->usage = isset($message['usage']) ? (array) $message['usage'] : [];
                unset($message['content'], $message['usage']);
                $this->message = $message;
                break;

            case 'content_block_start':
                $index = (int) (isset($event['index']) ? $event['index'] : 0);
                $block = isset($event['content_block']) ? (array) $event['content_block'] : [];

                $this->blocks[$index] = $block;

                if (isset($block['type']) && in_array($block['type'], ['thinking', 'redacted_thinking'], true)) {
                    $this->emit(['type' => 'thinking']);
                }
                break;

            case 'content_block_delta':
                $index = (int) (isset($event['index']) ? $event['index'] : 0);
                $delta = isset($event['delta']) ? (array) $event['delta'] : [];
                $type  = isset($delta['type']) ? $delta['type'] : '';

                if ($type === 'text_delta') {
                    $text = isset($delta['text']) ? (string) $delta['text'] : '';
                    $this->blocks[$index]['text'] = (isset($this->blocks[$index]['text']) ? $this->blocks[$index]['text'] : '') . $text;
                    $this->emit(['type' => 'text', 'delta' => $text]);
                } elseif ($type === 'thinking_delta') {
                    $this->emit(['type' => 'thinking']);
                }
                break;

            case 'message_delta':
                $delta = isset($event['delta']) ? (array) $event['delta'] : [];

                if (isset($delta['stop_reason'])) {
                    $this->stopReason = (string) $delta['stop_reason'];
                }

                if (isset($delta['stop_details'])) {
                    $this->stopDetails = (array) $delta['stop_details'];
                }

                if (isset($event['usage'])) {
                    // message_delta usage is cumulative.
                    $this->usage = array_merge($this->usage, (array) $event['usage']);
                }
                break;

            case 'error':
                $this->error = isset($event['error']) ? (array) $event['error'] : ['message' => 'Stream error.'];
                break;
        }
    }

    /**
     * @param array $event Progress event.
     *
     * @return void
     */
    private function emit(array $event)
    {
        if ($this->listener !== null) {
            call_user_func($this->listener, $event);
        }
    }
}
