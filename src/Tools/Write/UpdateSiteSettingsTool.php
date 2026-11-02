<?php
/**
 * Changes the handful of site settings an AI may touch.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolInterface;

/**
 * An allowlist of two, and the allowlist is the whole design. "Let the AI change
 * site options" with a free-text option name is a privilege-escalation tool: the
 * options table holds the home URL, the admin email, the active theme and every
 * plugin's settings. So the schema names the two fields a website builder
 * actually needs, and nothing can widen it from the outside.
 *
 * write_published: the title is on every page and in the browser tab.
 */
class UpdateSiteSettingsTool extends AbstractWriteTool
{
    public function getName()
    {
        return 'swishfolio/update-site-settings';
    }

    public function getTitle()
    {
        return __('Update site name', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Change the site title and tagline. These appear on every page and in the browser tab, so '
            . 'this must be confirmed. No other site setting can be changed through this tool.';
    }

    public function getSideEffect()
    {
        return ToolInterface::EFFECT_WRITE_PUBLISHED;
    }

    public function getInputSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [
                'title' => [
                    'type'        => 'string',
                    'maxLength'   => 200,
                    'description' => 'The site title.',
                ],
                'tagline' => [
                    'type'        => 'string',
                    'maxLength'   => 300,
                    'description' => 'The short description under the title.',
                ],
            ],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        if (! current_user_can('manage_options')) {
            $this->forbid(__('You cannot change site settings.', 'swishfolio-core'));
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $written = [];

        $fields = [
            'title'   => ['blogname', __('Set the site title to "%s"', 'swishfolio-core')],
            'tagline' => ['blogdescription', __('Set the tagline to "%s"', 'swishfolio-core')],
        ];

        foreach ($fields as $field => $target) {
            if (! isset($input[$field])) {
                continue;
            }

            $value = sanitize_text_field((string) $input[$field]);

            $this->journal->updateOption(
                $context,
                $this->getName(),
                $target[0],
                $value,
                sprintf($target[1], $value)
            );

            $written[$field] = $value;
        }

        return ['updated' => $written];
    }
}
