<?php
/**
 * A named provider that speaks the OpenAI wire format.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Providers;

/**
 * Every major provider except Anthropic speaks OpenAI's chat-completions shape,
 * so a preset is configuration — a base URL, a label, a model list — rather than
 * a transport. That is also why tool calling works on all of them the day they
 * are added: converse() is inherited.
 *
 * The difference from OpenAICompatibleProvider is who supplies the base URL.
 * There, the owner types it, which makes it the escape hatch for self-hosted and
 * unlisted endpoints and means nothing can be known about its capabilities.
 * Here the URL is fixed and known, so the preset can honestly declare that tool
 * calling works.
 *
 * What a preset cannot promise is structured output. The editing and generation
 * paths send response_format with a JSON schema, and compatible endpoints vary
 * in how strictly they honour it. A provider that ignores it still returns
 * prose, which generate() already rejects with "the AI returned a reply that was
 * not valid JSON" — a clear failure rather than corrupt page content.
 */
abstract class AbstractOpenAICompatiblePreset extends OpenAIProvider
{
    /**
     * @return string Fixed endpoint base, without a trailing slash.
     */
    abstract protected function presetBaseUrl();

    /**
     * Only a key: the endpoint is known, so asking for it would be asking the
     * owner to retype something that cannot be anything else.
     *
     * @return array
     */
    public function getRequiredFields()
    {
        return ['api_key'];
    }

    protected function getBaseUrl()
    {
        return rtrim($this->presetBaseUrl(), '/');
    }
}
