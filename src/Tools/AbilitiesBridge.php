<?php
/**
 * Registers the tool set with the WordPress Abilities API.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools;

use SwishfolioCore\AI\Settings;
use SwishfolioCore\REST\AIController;

/**
 * Registers every tool, and exposes none of them.
 *
 * That is the point. The Abilities API landed in core 6.9 and this plugin's
 * floor is 6.7, so on most installs the API is absent and this bridge does
 * nothing. Where it is present, each tool is registered with show_in_rest false
 * and meta.mcp.public false, so neither core's REST controllers nor the MCP
 * Adapter will serve it.
 *
 * What that buys is proof. The ability definition is built from the tool's own
 * name, description, schema and permission callback, so if the shape were wrong
 * — a schema the API rejects, a callback with the wrong signature — it fails
 * here, now, rather than in the slice that turns exposure on. Opening the tools
 * to external MCP clients later is then a question of flipping two flags per
 * tool, not of rewriting the contract.
 */
class AbilitiesBridge
{
    /** @var ToolRegistry */
    private $registry;

    /** @var ToolRunner */
    private $runner;

    /**
     * @param ToolRegistry $registry Tools to register.
     * @param ToolRunner   $runner   Pipeline every ability call goes through.
     */
    /** @var Settings|null */
    private $settings;

    public function __construct(ToolRegistry $registry, ToolRunner $runner, ?Settings $settings = null)
    {
        $this->registry = $registry;
        $this->runner   = $runner;
        $this->settings = $settings;
    }

    /**
     * @return void
     */
    public function boot()
    {
        add_action('abilities_api_init', [$this, 'register']);
    }

    /**
     * @return bool Whether the Abilities API is available on this install.
     */
    public static function isAvailable()
    {
        return function_exists('wp_register_ability');
    }

    /**
     * @return void
     */
    public function register()
    {
        if (! self::isAvailable()) {
            return;
        }

        foreach ($this->registry->all() as $tool) {
            wp_register_ability($tool->getName(), $this->definitionFor($tool));
        }
    }

    /**
     * The ability definition for one tool.
     *
     * Exposed as its own method so the shape can be asserted without a
     * registration function to call: on the 6.7 floor there is none.
     *
     * @param ToolInterface $tool Tool to describe.
     *
     * @return array
     */
    public function definitionFor(ToolInterface $tool)
    {
        $runner   = $this->runner;
        $settings = $this->settings;

        return [
            'label'               => $tool->getTitle(),
            'description'         => $tool->getDescription(),
            'input_schema'        => $tool->getInputSchema(),
            'output_schema'       => ['type' => 'object'],
            'permission_callback' => static function ($input = []) use ($tool, $settings) {
                // Same gate as the REST routes: AI switched on for the site
                // and the user allowed to use it, before any tool check.
                if (! current_user_can(AIController::CAPABILITY) || (null !== $settings && ! $settings->get()['enabled'])) {
                    return new \WP_Error('sfcore_ai_forbidden', __('AI is not available to you on this site.', 'swishfolio-core'), ['status' => 403]);
                }

                try {
                    $tool->authorize(is_array($input) ? $input : [], ToolContext::current());
                } catch (ToolException $e) {
                    return new \WP_Error($e->getError()->getCode(), $e->getMessage(), ['status' => 403]);
                }

                return true;
            },
            // Routed through the runner rather than calling the tool, so an
            // ability call gets the same validation, confirmation gate and
            // journalling as every other caller. A bypass here would be a way
            // to write without a journal row.
            'execute_callback'    => static function ($input = []) use ($tool, $runner) {
                try {
                    return $runner->run($tool->getName(), is_array($input) ? $input : [], ToolContext::current());
                } catch (ToolException $e) {
                    return new \WP_Error($e->getError()->getCode(), $e->getMessage(), $e->getError()->getDetails());
                }
            },
            'meta'                => [
                'show_in_rest' => false,
                'mcp'          => ['public' => false],
                'side_effect'  => $tool->getSideEffect(),
            ],
        ];
    }
}
