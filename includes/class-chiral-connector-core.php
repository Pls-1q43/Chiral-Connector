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

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        // According to the doc, API, Sync, Display are also core modules.
        // Their instantiation and hook registration might happen here or be managed by admin/public specific classes.
        // For now, let's assume they are loaded as dependencies and their hooks are defined within their respective areas or via the loader.
        $this->define_api_hooks();
        $this->define_sync_hooks();
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
        // The Chiral_Connector_Public class is responsible for instantiating Chiral_Connector_Display
        // and registering its specific hooks (like filters for 'the_content' or shortcodes)
        // based on plugin settings. This is handled within Chiral_Connector_Public->init_display_hooks().
        // This keeps the core class focused on loading main components and global hooks.
        // new Chiral_Connector_Display( $this->get_plugin_name(), $this->get_version() );
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
     * Node status endpoint callback.
     * Returns plugin version, related articles display status, and last disabled time.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function node_status_endpoint( WP_REST_Request $request ) {
        $options = get_option( 'chiral_connector_settings', array() );
        
        // Get plugin version
        $plugin_version = $this->get_version();
        
        // Get related articles display status
        $display_enabled = isset( $options['display_enable'] ) ? (bool) $options['display_enable'] : true;
        
        // Get last disabled time
        $last_disabled_time = null;
        if ( ! $display_enabled ) {
            $last_disabled_time = get_option( 'chiral_connector_display_last_disabled', null );
        }
        
        $response_data = array(
            'plugin_version' => $plugin_version,
            'related_articles_enabled' => $display_enabled,
            'last_disabled_time' => $last_disabled_time,
            'timestamp' => current_time( 'timestamp' ),
            'site_url' => home_url(),
        );
        
        return new WP_REST_Response( $response_data, 200 );
    }

}