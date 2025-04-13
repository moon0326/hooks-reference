<?php

namespace HooksReference;

class Plugin {
    /**
     * Initialize the plugin
     */
    public function init() {
        // Register admin menu
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'registerRestEndpoints']);
        
        // Register cache clearing hooks
        add_action('upgrader_process_complete', [$this, 'clearCache'], 10, 2);
        add_action('activated_plugin', [$this, 'clearCache']);
        add_action('deactivated_plugin', [$this, 'clearCache']);

        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // Register settings
        add_action('admin_init', [$this, 'registerSettings']);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueAdminAssets($hook) {
        // Only load on our plugin page
        if ('toplevel_page_hooks-reference' !== $hook) {
            return;
        }

        // Enqueue the built script
        wp_enqueue_script(
            'hooks-reference-script',
            HOOKS_REFERENCE_PLUGIN_URL . 'build/hooks-reference.js',
            ['wp-element', 'wp-components', 'wp-i18n'],
            HOOKS_REFERENCE_VERSION,
            true
        );

        // Enqueue the built styles
        wp_register_style(
            'hooks-reference-style',
            false,
            ['wp-components'],
            HOOKS_REFERENCE_VERSION
        );
        wp_enqueue_style('hooks-reference-style');

        // Localize script with plugin data
        wp_localize_script(
            'hooks-reference-script',
            'hooksReferenceData',
            [
                'restUrl' => rest_url('hooks-reference/v1'),
                'nonce' => wp_create_nonce('wp_rest')
            ]
        );
    }
    
    /**
     * Register the admin menu
     */
    public function registerAdminMenu() {
        add_menu_page(
            __('&nbsp;', 'hooks-reference'),
            __('Hooks Reference', 'hooks-reference'),
            'manage_options',
            'hooks-reference',
            [$this, 'renderAdminPage'],
            'dashicons-search',
            9999
        );

        // Add settings submenu
        add_submenu_page(
            'hooks-reference',
            __('Settings', 'hooks-reference'),
            __('Settings', 'hooks-reference'),
            'manage_options',
            'hooks-reference-settings',
            [$this, 'renderSettingsPage']
        );
    }
    
    /**
     * Register REST API endpoints
     */
    public function registerRestEndpoints() {
        register_rest_route('hooks-reference/v1', '/hooks', [
            'methods' => 'GET',
            'callback' => [$this, 'getHooks'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'args' => [
                'plugin' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'function' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'search' => [
                    'required' => false,
                    'type' => 'string',
                ],
            ],
        ]);
        
        register_rest_route('hooks-reference/v1', '/plugins', [
            'methods' => 'GET',
            'callback' => [$this, 'getPlugins'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
        
        register_rest_route('hooks-reference/v1', '/refresh', [
            'methods' => 'POST',
            'callback' => [$this, 'refreshCache'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('hooks-reference/v1', '/clear-cache', [
            'methods' => 'POST',
            'callback' => [$this, 'clearCache'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('hooks-reference/v1', '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'getSettings'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }
    
    /**
     * Register plugin settings
     */
    public function registerSettings() {
        // Register setting
        register_setting('hooks_reference_settings', 'hooks_reference_use_cache', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);

        // Add settings section
        add_settings_section(
            'hooks_reference_settings_section',
            __('Hooks reference Settings', 'hooks-reference'),
            [$this, 'renderSettingsSection'],
            'hooks-reference-settings'
        );

        // Add settings field
        add_settings_field(
            'hooks_reference_use_cache',
            __('Use Cache', 'hooks-reference'),
            [$this, 'renderCacheSetting'],
            'hooks-reference-settings',
            'hooks_reference_settings_section'
        );
    }

    /**
     * Render settings section
     */
    public function renderSettingsSection() {
        echo '<p>' . esc_html_e('Configure Hooks reference settings.', 'hooks-reference') . '</p>';
    }

    /**
     * Render cache setting field
     */
    public function renderCacheSetting() {
        $use_cache = get_option('hooks_reference_use_cache', true);
        ?>
        <label>
            <input type="checkbox" name="hooks_reference_use_cache" value="1" <?php checked($use_cache); ?> />
            <?php esc_html_e('Enable caching of hook data', 'hooks-reference'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('When enabled, hook data will be cached for better performance. Disable to always get fresh data.', 'hooks-reference'); ?>
        </p>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function renderSettingsPage() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('hooks_reference_settings');
                do_settings_sections('hooks-reference-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Get hooks from cache or scan if not cached
     */
    public function getHooks($request) {
        $use_cache = get_option('hooks_reference_use_cache', true);
        $cache_key = 'hooks_reference_cache';
        
        if ($use_cache) {
            $hooks = get_transient($cache_key);
            if (false !== $hooks) {
                // Apply filters to cached data
                return $this->filterHooks($hooks, $request);
            }
        }
        
        // If not using cache or cache is empty, scan plugins
        $hooks = $this->scanPlugins();
        
        if ($use_cache) {
            set_transient($cache_key, $hooks, DAY_IN_SECONDS);
        }
        
        return $this->filterHooks($hooks, $request);
    }
    
    /**
     * Filter hooks based on request parameters
     */
    private function filterHooks($hooks, $request) {
        $plugin = $request->get_param('plugin');
        $function = $request->get_param('function');
        $search = $request->get_param('search');
        
        if ($plugin) {
            $hooks = array_filter($hooks, function($hook) use ($plugin) {
                return $hook['plugin'] === $plugin;
            });
        }
        
        if ($function) {
            $hooks = array_filter($hooks, function($hook) use ($function) {
                return $hook['functionName'] === $function;
            });
        }
        
        if ($search) {
            $hooks = array_filter($hooks, function($hook) use ($search) {
                return stripos($hook['name'], $search) !== false;
            });
        }
        
        return rest_ensure_response(array_values($hooks));
    }

	protected function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return \get_plugins();
	}
    
    /**
     * Get list of active plugins
     */
    public function getPlugins() {
        $plugins = array();
        $all_plugins = $this->get_plugins();
        
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $is_active = is_plugin_active($plugin_file);
            
            $plugins[] = [
                'name' => $plugin_data['Name'],
                'active' => $is_active
            ];
        }
        
        return rest_ensure_response($plugins);
    }
    
    /**
     * Refresh the cache
     */
    public function refreshCache() {
        delete_transient('hooks_reference_cache');
        $hooks = $this->scanPlugins();
        set_transient('hooks_reference_cache', $hooks, DAY_IN_SECONDS);
        
        return rest_ensure_response([
            'success' => true,
            'count' => count($hooks),
        ]);
    }
    
    /**
     * Clear the cache
     */
    public function clearCache() {
        delete_transient('hooks_reference_cache');
    }
    
    /**
     * Scan all plugins for hooks
     */
    private function scanPlugins() {
        $hooks = [];
        $plugins = $this->get_plugins();
        
        foreach ($plugins as $plugin_file => $plugin_data) {
            $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin_file);
            $plugin_hooks = $this->scanPluginDirectory($plugin_dir, $plugin_data['Name']);
            $hooks = array_merge($hooks, $plugin_hooks);
        }
        
        return $hooks;
    }
    
    /**
     * Scan a plugin directory for hooks
     */
    private function scanPluginDirectory($directory, $plugin_name) {
        $hooks = [];
        $excluded_dirs = ['vendor', 'node_modules', 'build'];
        
        // Helper function to check if a path should be excluded
        $shouldExclude = function($path) use ($excluded_dirs) {
            $path_parts = explode('/', $path);
            foreach ($path_parts as $part) {
                if (in_array($part, $excluded_dirs)) {
                    return true;
                }
            }
            return false;
        };
        
        // Helper function to scan directory recursively
        $scanDir = function($dir) use (&$scanDir, &$hooks, $plugin_name, $shouldExclude) {
            if (!is_dir($dir)) {
                return;
            }
            
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                $path = $dir . '/' . $file;
                
                // Skip excluded paths
                if ($shouldExclude($path)) {
                    continue;
                }
                
                if (is_dir($path)) {
                    $scanDir($path);
                } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                    $file_hooks = $this->scanFile($path, $plugin_name);
                    $hooks = array_merge($hooks, $file_hooks);
                }
            }
        };
        
        $scanDir($directory);
        return $hooks;
    }
    
    /**
     * Scan a PHP file for hooks
     */
    private function scanFile($file_path, $plugin_name) {
        $hooks = [];
        $content = file_get_contents($file_path);
        $lines = explode("\n", $content);
        
        // Match add_action and do_action
        preg_match_all('/add_action\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $add_actions, PREG_OFFSET_CAPTURE);
        preg_match_all('/do_action\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $do_actions, PREG_OFFSET_CAPTURE);
        
        // Match add_filter and apply_filters
        preg_match_all('/add_filter\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $add_filters, PREG_OFFSET_CAPTURE);
        preg_match_all('/apply_filters\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $apply_filters, PREG_OFFSET_CAPTURE);
        
        // Helper function to get line number from offset
        $getLineNumber = function($offset) use ($content) {
            return substr_count(substr($content, 0, $offset), "\n") + 1;
        };
        
        // Process matches
        foreach ($add_actions[1] as $match) {
            $hook = $match[0];
            $offset = $match[1];
            $line_number = $getLineNumber($offset);
            $hooks[] = [
                'name' => $hook,
                'functionName' => 'add_action',
                'plugin' => $plugin_name,
                'file' => str_replace(WP_PLUGIN_DIR, '', $file_path),
                'line' => $line_number
            ];
        }
        
        foreach ($do_actions[1] as $match) {
            $hook = $match[0];
            $offset = $match[1];
            $line_number = $getLineNumber($offset);
            $hooks[] = [
                'name' => $hook,
                'functionName' => 'do_action',
                'plugin' => $plugin_name,
                'file' => str_replace(WP_PLUGIN_DIR, '', $file_path),
                'line' => $line_number
            ];
        }
        
        foreach ($add_filters[1] as $match) {
            $hook = $match[0];
            $offset = $match[1];
            $line_number = $getLineNumber($offset);
            $hooks[] = [
                'name' => $hook,
                'functionName' => 'add_filter',
                'plugin' => $plugin_name,
                'file' => str_replace(WP_PLUGIN_DIR, '', $file_path),
                'line' => $line_number
            ];
        }
        
        foreach ($apply_filters[1] as $match) {
            $hook = $match[0];
            $offset = $match[1];
            $line_number = $getLineNumber($offset);
            $hooks[] = [
                'name' => $hook,
                'functionName' => 'apply_filters',
                'plugin' => $plugin_name,
                'file' => str_replace(WP_PLUGIN_DIR, '', $file_path),
                'line' => $line_number
            ];
        }
        
        return $hooks;
    }
    
    /**
     * Render the admin page
     */
    public function renderAdminPage() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div id="hooks-reference-app"></div>
        </div>
        <?php
    }

    /**
     * Get plugin settings
     */
    public function getSettings() {
        return rest_ensure_response([
            'use_cache' => get_option('hooks_reference_use_cache', true)
        ]);
    }
} 