<?php
/**
 * Loads and composes prompt files.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Prompt;

/**
 * Prompts live in files rather than inline strings so they can be revised,
 * reviewed and translated independently of application code.
 */
class PromptManager
{
    /** @var string */
    private $promptDir;

    /**
     * @param string $promptDir Directory holding system/ and tasks/.
     */
    public function __construct($promptDir)
    {
        $this->promptDir = rtrim($promptDir, '/');
    }

    /**
     * @param string $name Prompt file name without extension.
     *
     * @return string
     */
    public function system($name)
    {
        return $this->load('system/' . $name);
    }

    /**
     * @param string $name Prompt file name without extension.
     * @param array  $vars Placeholder values.
     *
     * @throws \RuntimeException When a placeholder is left unreplaced.
     *
     * @return string
     */
    public function task($name, array $vars)
    {
        $template = $this->load('tasks/' . $name);

        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }

        if (preg_match('/\{\{([a-z0-9_]+)\}\}/i', $template, $matches)) {
            throw new \RuntimeException(
                sprintf('Prompt "%s" has an unreplaced placeholder: %s', $name, $matches[1])
            );
        }

        return $template;
    }

    private function load($relativePath)
    {
        $file = $this->promptDir . '/' . $relativePath . '.txt';

        if (! file_exists($file)) {
            throw new \RuntimeException(sprintf('Prompt file not found: %s', $relativePath));
        }

        return trim((string) file_get_contents($file));
    }
}
