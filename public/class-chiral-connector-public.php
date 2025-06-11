<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch
/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for enqueueing
 * the public-facing stylesheet and JavaScript.
 *
 * @package    Chiral_Connector
 * @subpackage Chiral_Connector/public
 * @author     Your Name <email@example.com>
 */
class Chiral_Connector_Public {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Instance of Chiral_Connector_Display.
     *
     * @since    1.0.0
     * @access   private
     * @var      Chiral_Connector_Display $display_handler    Instance of the display handler class.
     */
    private $display_handler;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of the plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->load_dependencies();
        // define_hooks will be called by the loader, or we can call init_display_hooks directly if a loader is passed.
    }

    /**
     * Load dependencies.
     */
    private function load_dependencies() {
        if ( ! class_exists( 'Chiral_Connector_Display' ) ) {
            require_once CHIRAL_CONNECTOR_PLUGIN_DIR . 'includes/class-chiral-connector-display.php';
        }
        $this->display_handler = new Chiral_Connector_Display($this->plugin_name, $this->version);
    }

    /**
     * Define WordPress hooks for public area.
     */
    private function define_hooks() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Display hooks are now initialized via init_display_hooks if a loader is provided,
        // or directly if this class manages its own hooks (which it does for enqueueing).
        // For consistency with how Chiral_Connector_Core calls it, we'll keep display hook logic
        // in a separate method that can accept a loader.
    }

    /**
     * Initialize display-related hooks using the provided loader.
     *
     * @since    1.0.0
     * @param    object    $loader    The Chiral_Connector_Loader instance.
     */
    public function init_display_hooks( $loader ) {
        $options = get_option('chiral_connector_settings');
        $enable_display = isset($options['display_enable']) ? $options['display_enable'] : true;

        if ($enable_display) {
            $loader->add_filter( 'the_content', $this->display_handler, 'append_related_posts_to_content' );
            add_shortcode( 'chiral_related_posts', array( $this->display_handler, 'render_related_posts_shortcode' ) );
            
            // Add AJAX hooks for fetching related posts
            // $this->display_handler is an instance of Chiral_Connector_Display
            $loader->add_action( 'wp_ajax_nopriv_chiral_fetch_related_posts', $this->display_handler, 'ajax_fetch_related_posts' );
            $loader->add_action( 'wp_ajax_chiral_fetch_related_posts', $this->display_handler, 'ajax_fetch_related_posts' );
        }
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        $options = get_option('chiral_connector_settings');
        $enable_display = isset($options['display_enable']) ? $options['display_enable'] : true;

        // Only enqueue if display is enabled and on single posts (or where shortcode might be used)
        if ( $enable_display && (is_single() || has_shortcode( get_the_content(), 'chiral_related_posts' )) ) {
            wp_enqueue_style( $this->plugin_name, CHIRAL_CONNECTOR_PLUGIN_URL . 'public/assets/css/chiral-connector-public.css', array(), $this->version, 'all' );
        }
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        $options = get_option('chiral_connector_settings');
        $enable_display = isset($options['display_enable']) ? $options['display_enable'] : true;

        if ( $enable_display && (is_single() || has_shortcode( get_the_content(), 'chiral_related_posts' )) ) {
            wp_enqueue_script( $this->plugin_name, CHIRAL_CONNECTOR_PLUGIN_URL . 'public/assets/js/chiral-connector-public.js', array( 'jquery' ), $this->version, true );

            // Localize script with data needed for AJAX calls to WordPress AJAX handler
            wp_localize_script( $this->plugin_name, 'chiralConnectorPublicAjax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'chiral_connector_related_posts_nonce' ),
                // Pass existing texts so JS doesn't break
                // These texts are defaults in the JS file but localizing them here is more robust
                'texts'    => array(
                    'loading' => esc_html__( 'Loading related Chiral data...', 'chiral-connector' ),
                    'relatedTitle' => esc_html__( 'Related Content', 'chiral-connector' ),
                    'noData' => esc_html__( 'No related Chiral data found at the moment.', 'chiral-connector' ),
                    'fetchError' => esc_html__( 'Error fetching related data', 'chiral-connector' ),
                    'configError' => esc_html__( 'Chiral Connector: Configuration error for related posts.', 'chiral-connector' ),
                    /* translators: %s: Domain name of the source website */
                    'source' => esc_html__( 'Source: %s', 'chiral-connector' ),
                    /* translators: %s: Name of the Chiral Network */
                    'fromChiralNetwork' => esc_html__( 'From Chiral Network: %s', 'chiral-connector' )
                )
            ));
        }
    }
}