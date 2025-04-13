<?php

namespace HooksReference;

use WP_CLI;
use WP_CLI_Command;

/**
 * WP-CLI commands for the Hooks Discovery plugin.
 *
 * @package    HooksReference
 * @subpackage HooksReference/includes
 * @author     Your Name <email@example.com>
 */
class HooksReferenceCLI extends WP_CLI_Command {

    /**
     * Export hooks to a file
     *
     * ## OPTIONS
     *
     * [--plugin=<plugin>]
     * : Filter by plugin name
     *
     * [--function=<function>]
     * : Filter by function name (add_action, do_action, add_filter, apply_filters)
     *
     * [--output=<output>]
     * : Output file path (default: hooks-export.json)
     *
     * ## EXAMPLES
     *
     *     wp hooks-discovery export --plugin=WooCommerce
     *     wp hooks-discovery export --plugin=WooCommerce --function=add_action
     *     wp hooks-discovery export --plugin=WooCommerce --output=custom-hooks.json
     *
     * @when after_wp_load
     */
    public function export($args, $assoc_args) {
        $plugin = isset($assoc_args['plugin']) ? $assoc_args['plugin'] : '';
        $function = isset($assoc_args['function']) ? $assoc_args['function'] : '';
        $output = isset($assoc_args['output']) ? $assoc_args['output'] : 'hooks-export.json';

        // Create a mock request object
        $request = new \WP_REST_Request('GET', '/hooks-discovery/v1/hooks');
        $request->set_param('plugin', $plugin);
        $request->set_param('function', $function);

        // Get hooks using the existing method
        $plugin_instance = new Plugin();
        $response = $plugin_instance->getHooks($request);
        $hooks = $response->get_data();

        if (empty($hooks)) {
            WP_CLI::warning('No hooks found matching your criteria.');
            return;
        }

        // Group hooks by name
        $grouped_hooks = [];
        foreach ($hooks as $hook) {
            if (!isset($grouped_hooks[$hook['name']])) {
                $grouped_hooks[$hook['name']] = [];
            }
            $grouped_hooks[$hook['name']][] = $hook;
        }

        // Write to file
        $json = json_encode($grouped_hooks, JSON_PRETTY_PRINT);
        if (file_put_contents($output, $json)) {
            WP_CLI::success(sprintf('Exported %d hooks to %s', count($hooks), $output));
        } else {
            WP_CLI::error('Failed to write to output file');
        }
    }
}

// Register the command
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('hooks-discovery', 'HooksReference\HooksReferenceCLI');
} 