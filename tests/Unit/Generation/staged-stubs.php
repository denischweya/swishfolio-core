<?php
/**
 * Hook functions the staged planner calls that the shared stubs lack.
 *
 * @package SwishfolioCore
 */

if (! function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1)
    {
        return true;
    }
}

if (! function_exists('remove_action')) {
    function remove_action($hook, $callback, $priority = 10)
    {
        return true;
    }
}

if (! function_exists('remove_filter')) {
    function remove_filter($hook, $callback, $priority = 10)
    {
        return true;
    }
}
