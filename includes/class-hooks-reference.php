<?php

namespace HooksReference;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    HooksReference
 * @subpackage HooksReference/includes
 * @author     Your Name <email@example.com>
 */
class Plugin {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if ( defined( 'HOOKS_DISCOVERY_VERSION' ) ) {
            $this->version = HOOKS_DISCOVERY_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'wp-hooks-discovery';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->register_rest_routes();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Loader. Orchestrates the hooks of the plugin.
     * - i18n. Defines internationalization functionality.
     * - Admin. Defines all hooks for the admin area.
     * - Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        $this->loader = new Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new i18n();
        $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new Admin( $this->get_plugin_name(), $this->get_version() );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function init() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Register REST API routes.
     *
     * @since    1.0.0
     */
    public function register_rest_routes() {
        register_rest_route('hooks-discovery/v1', '/hooks', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_hooks'),
            'permission_callback' => array($this, 'check_permissions')
        ));

        register_rest_route('hooks-discovery/v1', '/plugins', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_plugins'),
            'permission_callback' => array($this, 'check_permissions')
        ));

        register_rest_route('hooks-discovery/v1', '/refresh', array(
            'methods' => 'POST',
            'callback' => array($this, 'refresh_hooks'),
            'permission_callback' => array($this, 'check_permissions')
        ));

        register_rest_route('hooks-discovery/v1', '/clear-cache', array(
            'methods' => 'POST',
            'callback' => array($this, 'clear_cache'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }

    /**
     * Get plugins.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_plugins($request) {
        $cache_key = 'hooks_discovery_plugins';
        $plugins = get_transient($cache_key);

        if (false === $plugins) {
            $plugins = array();
            $all_plugins = get_plugins();
            
            foreach ($all_plugins as $plugin_file => $plugin_data) {
                if (is_plugin_active($plugin_file)) {
                    $plugins[] = $plugin_data['Name'];
                }
            }
            
            set_transient($cache_key, $plugins, DAY_IN_SECONDS);
        }

        return rest_ensure_response($plugins);
    }

    /**
     * Get hooks.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_hooks($request) {
        $plugin = $request->get_param('plugin');
        $cache_key = 'hooks_discovery_data';
        $hooks = get_transient($cache_key);

        if (false === $hooks) {
            $hooks = $this->scan_plugins();
            set_transient($cache_key, $hooks, DAY_IN_SECONDS);
        }

        // Filter by plugin if specified
        if ($plugin) {
            $hooks = array_filter($hooks, function($hook) use ($plugin) {
                return $hook['plugin'] === $plugin;
            });
        }

        return rest_ensure_response(array_values($hooks));
    }

    /**
     * Check permissions.
     *
     * @return bool
     */
    public function check_permissions() {
        return current_user_can('manage_options');
    }

    /**
     * Refresh hooks.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function refresh_hooks($request) {
        $hooks = $this->scan_plugins();
        $cache_key = 'hooks_discovery_data';
        set_transient($cache_key, $hooks, DAY_IN_SECONDS);
        return rest_ensure_response(array('success' => true));
    }

    /**
     * Clear cache.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function clear_cache($request) {
        delete_transient('hooks_discovery_data');
        delete_transient('hooks_discovery_plugins');
        return rest_ensure_response(array('success' => true));
    }

    /**
     * Scan plugins for hooks.
     *
     * @return array
     */
    private function scan_plugins() {
        $hooks = array();
        $all_plugins = get_plugins();
        
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            if (!is_plugin_active($plugin_file)) {
                continue;
            }

            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
            $plugin_dir = dirname($plugin_path);
            
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($plugin_dir)
            );
            
            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    
                    // Match add_action and add_filter calls
                    preg_match_all('/add_(?:action|filter)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i', $content, $matches, PREG_SET_ORDER);
                    
                    foreach ($matches as $match) {
                        $hook_name = $match[1];
                        $callback = $match[2];
                        $hooks[] = array(
                            'hook' => $hook_name,
                            'callback' => $callback,
                            'plugin' => $plugin_data['Name'],
                            'file' => str_replace($plugin_dir . '/', '', $file->getPathname())
                        );
                    }
                }
            }
        }
        
        return $hooks;
    }
} 