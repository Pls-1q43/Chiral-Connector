<?php
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
 * @package    Chiral_Connector
 * @subpackage Chiral_Connector/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Connector_Core {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Chiral_Connector_Loader    $loader    Maintains and registers all hooks for the plugin.
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
     * Whether this connector is running in Hub mode.
     *
     * @since    1.0.0
     * @access   protected
     * @var      bool    $is_hub_mode    True if running on a Chiral Hub Core site.
     */
    protected $is_hub_mode;

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
        if ( defined( 'CHIRAL_CONNECTOR_VERSION' ) ) {
            $this->version = CHIRAL_CONNECTOR_VERSION;
        }
        else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'chiral-connector';
        
        // Detect if we're running in Hub mode
        $this->is_hub_mode = $this->detect_hub_mode();
        
        // Log Hub mode detection for debugging
        if ( class_exists( 'Chiral_Connector_Utils' ) ) {
            Chiral_Connector_Utils::log_message( 
                sprintf( 'Chiral Connector initialized. Hub mode: %s', 
                    $this->is_hub_mode ? 'true' : 'false' 
                ), 
                'debug' 
            );
        }

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        
        // Only load sync hooks if not in Hub mode
        if ( ! $this->is_hub_mode ) {
            $this->define_sync_hooks();
        }
        
        // Always load API and display hooks (needed for both modes)
        $this->define_api_hooks();
        $this->define_display_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Chiral_Connector_Loader. Orchestrates the hooks of the plugin.
     * - Chiral_Connector_i18n. Defines internationalization functionality.
     * - Chiral_Connector_Admin. Defines all hooks for the admin area.
     * - Chiral_Connector_Public. Defines all hooks for the public side of the site.
     * - Chiral_Connector_API. Handles API communication.
     * - Chiral_Connector_Sync. Handles content synchronization.
     * - Chiral_Connector_Display. Handles front-end display of related data.
     * - Chiral_Connector_Utils. Utility functions.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-chiral-connector-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-chiral-connector-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-chiral-connector-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-chiral-connector-public.php';

        /**
         * The class responsible for API interactions.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-chiral-connector-api.php';

        /**
         * The class responsible for content synchronization.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-chiral-connector-sync.php';

        /**
         * The class responsible for displaying related data.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-chiral-connector-display.php';

        /**
         * The class responsible for utility functions.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-chiral-connector-utils.php';

        $this->loader = new Chiral_Connector_Loader();

    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Chiral_Connector_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {

        $plugin_i18n = new Chiral_Connector_i18n();

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

        $plugin_admin = new Chiral_Connector_Admin( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
        $this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );

        // AJAX hooks
        $this->loader->add_action( 'wp_ajax_chiral_connector_test_connection', $plugin_admin, 'ajax_test_connection' );
        $this->loader->add_action( 'wp_ajax_chiral_connector_batch_sync', $plugin_admin, 'ajax_batch_sync' );

    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {

        $plugin_public = new Chiral_Connector_Public( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
        
        // Display hooks are conditionally added in Chiral_Connector_Public based on settings
        $plugin_public->init_display_hooks( $this->loader );

    }

    /**
     * Register hooks related to API functionality.
     * @since 1.0.0
     * @access private
     */
    private function define_api_hooks() {
        // Register REST API routes for node status checking
        $this->loader->add_action( 'rest_api_init', $this, 'register_node_status_routes' );
    }

    /**
     * Register hooks related to Sync functionality.
     * @since 1.0.0
     * @access private
     */
    private function define_sync_hooks() {
        $plugin_sync = new Chiral_Connector_Sync( $this->get_plugin_name(), $this->get_version() );

        $settings = get_option('chiral_connector_settings');

        // Check if settings are loaded and valid
        if (is_array($settings)) {
            if ( ! empty( $settings['sync_on_publish'] ) ) {
                // Handles new posts and updates to existing posts that are published
                $this->loader->add_action( 'transition_post_status', $plugin_sync, 'sync_post_on_publish_or_update', 10, 3 );
            }
            if ( ! empty( $settings['sync_on_trash'] ) ) {
                $this->loader->add_action( 'wp_trash_post', $plugin_sync, 'delete_post_from_hub_on_trash', 10, 1 );
                // Also handle permanent deletion for completeness, though trash is the primary hook
                $this->loader->add_action( 'delete_post', $plugin_sync, 'delete_post_from_hub_on_delete', 10, 1 ); 
            }
        }

        // Cron hook for retrying failed syncs - this should always be active
        // $this->loader->add_action( 'chiral_connector_retry_failed_syncs', $plugin_sync, 'retry_failed_syncs' );
        $this->loader->add_action( 'chiral_connector_retry_sync_event', $plugin_sync, 'handle_retry_sync_event', 10, 4 );
    }

    /**
     * Register hooks related to Display functionality.
     * @since 1.0.0
     * @access private
     */
    private function define_display_hooks() {
        // Display hooks are already registered in define_public_hooks() through 
        // the public class instance. This method is kept for organizational clarity
        // but no additional action is needed to avoid duplicate hook registration.
        
        // Note: Previously this method was empty, then we added duplicate registration.
        // The proper place for display hooks registration is in define_public_hooks()
        // where the main public instance is created and init_display_hooks() is called.
    }


    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
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
     * @return    Chiral_Connector_Loader    Orchestrates the hooks of the plugin.
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
     * Register REST API routes for node status checking.
     *
     * @since 1.0.0
     */
    public function register_node_status_routes() {
        register_rest_route( 'chiral-connector/v1', '/node-status', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array( $this, 'node_status_endpoint' ),
            'permission_callback' => '__return_true', // Public endpoint
        ) );
    }

    /**
     * Check if the current node is properly connected to the Hub API endpoint.
     *
     * @since 1.0.0
     * @return array An array with status and message.
     */
    public function node_status_endpoint( WP_REST_Request $request ) {
        // Get node configuration from settings
        $options = get_option( 'chiral_connector_settings', array() );
        $node_id = isset( $options['node_id'] ) ? $options['node_id'] : '';
        $hub_url = isset( $options['hub_url'] ) ? $options['hub_url'] : '';
        $hub_username = isset( $options['hub_username'] ) ? $options['hub_username'] : '';
        $hub_app_password = isset( $options['hub_app_password'] ) ? $options['hub_app_password'] : '';

        $status = array(
            'node_id' => $node_id,
            'hub_configured' => !empty($hub_url) && !empty($hub_username) && !empty($hub_app_password),
            'hub_url' => $hub_url,
            'is_hub_mode' => $this->is_hub_mode
        );

        return new WP_REST_Response( $status, 200 );
    }

    /**
     * Detect if this connector is running on a Chiral Hub Core site.
     *
     * @since 1.0.0
     * @return bool True if Hub Core plugin is active, false otherwise.
     */
    private function detect_hub_mode() {
        // Manual override for testing (remove in production)
        if ( defined( 'CHIRAL_FORCE_HUB_MODE' ) && CHIRAL_FORCE_HUB_MODE ) {
            return true;
        }
        
        // Method 1: Check if Chiral Hub Core plugin is active
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Get all active plugins
        $active_plugins = get_option( 'active_plugins', array() );
        
        // Check for common Hub Core plugin patterns
        foreach ( $active_plugins as $plugin ) {
            if ( strpos( $plugin, 'chiral-hub-core' ) !== false ) {
                return true;
            }
        }
        
        // Method 2: Check for Hub Core specific constants
        if ( defined( 'CHIRAL_HUB_CORE_VERSION' ) || defined( 'CHIRAL_HUB_CORE_PLUGIN_FILE' ) ) {
            return true;
        }
        
        // Method 3: Check for Hub Core specific classes (delayed check)
        add_action( 'plugins_loaded', array( $this, 'delayed_hub_mode_check' ), 99 );
        
        // Method 4: Check for Hub Core specific functions
        if ( function_exists( 'run_chiral_hub_core' ) || function_exists( 'chiral_hub_core_activate' ) ) {
            return true;
        }
        
        // Method 5: Check for chiral_data post type (this should exist if Hub Core is active)
        // Use a delayed check since post types might not be registered yet
        if ( post_type_exists( 'chiral_data' ) ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Delayed check for Hub mode after all plugins are loaded.
     *
     * @since 1.0.0
     */
    public function delayed_hub_mode_check() {
        $was_hub_mode = $this->is_hub_mode;
        $is_now_hub_mode = false;
        
        // Check for Hub Core specific classes
        if ( class_exists( 'Chiral_Hub_Core' ) || class_exists( 'Chiral_Hub_CPT' ) ) {
            $is_now_hub_mode = true;
        }
        
        // Check for chiral_data post type
        if ( post_type_exists( 'chiral_data' ) ) {
            $is_now_hub_mode = true;
        }
        
        // If Hub mode status changed, update it
        if ( $is_now_hub_mode !== $was_hub_mode ) {
            $this->is_hub_mode = $is_now_hub_mode;
            
            // Log the change for debugging
            if ( class_exists( 'Chiral_Connector_Utils' ) ) {
                Chiral_Connector_Utils::log_message( 
                    sprintf( 'Hub mode status changed from %s to %s after plugins_loaded', 
                        $was_hub_mode ? 'true' : 'false', 
                        $is_now_hub_mode ? 'true' : 'false' 
                    ), 
                    'debug' 
                );
            }
        }
    }

    /**
     * Check if the connector is running in Hub mode.
     *
     * @since 1.0.0
     * @return bool True if in Hub mode, false otherwise.
     */
    public function is_hub_mode() {
        return $this->is_hub_mode;
    }
    


}