<?php
/**
 * The admin-specific functionality of the plugin.
 * Cache bust: 2024-12-19-v2
 * @since      1.0.0
 * @package    Chiral_Connector
 * @subpackage Chiral_Connector/admin
 * @author     Your Name <email@example.com>
 */
class Chiral_Connector_Admin {

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
     * Instance of Chiral_Connector_Api.
     *
     * @since    1.0.0
     * @access   private
     * @var      Chiral_Connector_Api $api    Instance of the API handler class.
     */
    private $api;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->load_dependencies();
        $this->define_hooks();
    }

    /**
     * Load dependencies.
     */
    private function load_dependencies() {
        if ( ! class_exists( 'Chiral_Connector_Api' ) ) {
            require_once CHIRAL_CONNECTOR_PLUGIN_DIR . 'includes/class-chiral-connector-api.php';
        }
        $this->api = new Chiral_Connector_Api($this->plugin_name, $this->version);

        if ( ! class_exists( 'Chiral_Connector_Utils' ) ) {
            require_once CHIRAL_CONNECTOR_PLUGIN_DIR . 'includes/class-chiral-connector-utils.php';
        }
    }

    /**
     * Define WordPress hooks for admin area.
     */
    private function define_hooks() {
        add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // AJAX hook for testing connection
        add_action( 'wp_ajax_chiral_connector_test_connection', array( $this, 'ajax_test_connection' ) );
        // AJAX hook for batch sync (can be triggered from settings page)
        add_action( 'wp_ajax_chiral_connector_trigger_batch_sync', array( $this, 'ajax_trigger_batch_sync' ) );
        // AJAX hook for quitting the network
        add_action( 'wp_ajax_chiral_connector_quit_network', array( $this, 'ajax_quit_network' ) );
        // AJAX hook for clearing cache
        add_action( 'wp_ajax_chiral_connector_clear_cache', array( $this, 'ajax_clear_cache' ) );

        // Hooks for the 'Send to Chiral?' metabox
        add_action( 'add_meta_boxes_post', array( $this, 'add_chiral_send_metabox' ) ); // For 'post' post type
        add_action( 'save_post', array( $this, 'save_chiral_send_metabox_data' ) );

        // Hook for Quick Edit
        add_action( 'quick_edit_custom_box', array( $this, 'add_chiral_quick_edit_fields' ), 10, 2 );
        // Hook to add custom column to post list (optional, but good for visibility)
        add_filter( 'manage_post_posts_columns', array( $this, 'add_chiral_send_column' ) );
        add_action( 'manage_post_posts_custom_column', array( $this, 'render_chiral_send_column_content' ), 10, 2 );
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_styles( $hook_suffix ) {
        // Only load on our plugin's settings page
        if ( 'toplevel_page_chiral-connector-settings' !== $hook_suffix && 'chiral-connector_page_chiral-connector-settings' !== $hook_suffix && !str_contains($hook_suffix, 'chiral-connector-settings') ) {
            // The hook_suffix can vary depending on where it's added (top-level or sub-menu)
            // A more robust check might be needed or ensure the menu slug is consistent.
            // For now, this covers a common case for top-level and sub-menu pages.
            // error_log('Chiral Connector Admin CSS not loaded. Hook: ' . $hook_suffix);
            // return;
        }
        wp_enqueue_style( $this->plugin_name, CHIRAL_CONNECTOR_PLUGIN_URL . 'admin/assets/css/chiral-connector-admin.css', array(), $this->version, 'all' );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_scripts( $hook_suffix ) {
        // if ( 'toplevel_page_chiral-connector-settings' !== $hook_suffix && 'chiral-connector_page_chiral-connector-settings' !== $hook_suffix && !str_contains($hook_suffix, 'chiral-connector-settings')) {
        //     error_log('Chiral Connector Admin JS not loaded. Hook: ' . $hook_suffix);
        //     // return;
        // }
        wp_enqueue_script( $this->plugin_name, CHIRAL_CONNECTOR_PLUGIN_URL . 'admin/assets/js/chiral-connector-admin.js', array( 'jquery', 'inline-edit-post' ), $this->version, true ); // Added 'inline-edit-post' as dependency and set last param to true
        wp_localize_script( $this->plugin_name, 'chiralConnectorAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'chiral_connector_admin_nonce' ),
            'text'     => array(
                'testingConnection' => __( 'Testing connection...', 'chiral-connector' ),
                'startingSync' => __( 'Starting batch sync...', 'chiral-connector' ),
                'syncComplete' => __( 'Batch sync initiated. Check logs for details.', 'chiral-connector' ),
                'error' => __( 'An error occurred.', 'chiral-connector' ),
                'ajaxError' => __( 'AJAX request failed.', 'chiral-connector' ), // General AJAX error
                'quitNetworkConfirm1' => __( 'Are you absolutely sure you want to quit the Chiral Network? This will request deletion of all your data on the Hub.', 'chiral-connector' ),
                'quitNetworkConfirm2' => __( 'This will also clear your local Chiral Connector settings and deactivate the plugin. This action cannot be undone. Proceed?', 'chiral-connector' ),
                'quitNetworkConfirm3' => __( 'After this process, you MUST manually log in to your Chiral Hub account to verify that all your data has been removed. The plugin will attempt to delete the data, but verification is your responsibility. Continue?', 'chiral-connector' ),
                'quittingNetwork' => __( 'Processing... Quitting network. Please wait.', 'chiral-connector' ),
                'quitNetworkPluginDeactivated' => __( 'Plugin Deactivated', 'chiral-connector' ),
                'QuickEditYes' => __( 'Yes', 'chiral-connector' ), // Added for JS quick edit check
                'QuickEditNo' => __( 'No', 'chiral-connector' )   // Added for JS quick edit check
            )
        ) );
    }

    /**
     * Add an options page under settings.
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            __( 'Chiral Connector Settings', 'chiral-connector' ), // Page Title
            __( 'Chiral Connector', 'chiral-connector' ),    // Menu Title
            'manage_options',                               // Capability
            'chiral-connector-settings',                    // Menu Slug
            array( $this, 'display_plugin_setup_page' ),   // Callback function
            'dashicons-rss',                                // Icon URL
            76                                              // Position
        );
    }

    /**
     * Display the plugin setup page
     */
    public function display_plugin_setup_page() {
        include_once CHIRAL_CONNECTOR_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'chiral_connector_option_group', // Option group
            'chiral_connector_settings',     // Option name
            array( $this, 'sanitize_settings' ) // Sanitize callback
        );

        // Hub Connection Section
        add_settings_section(
            'chiral_connector_hub_section',
            __( 'Hub Connection Settings', 'chiral-connector' ),
            array( $this, 'hub_section_callback' ),
            'chiral-connector-settings-page' // Page slug where this section will be shown
        );

        add_settings_field(
            'hub_url',
            __( 'Hub URL', 'chiral-connector' ),
            array( $this, 'hub_url_render' ),
            'chiral-connector-settings-page',
            'chiral_connector_hub_section'
        );
        add_settings_field(
            'hub_username',
            __( 'Hub Username', 'chiral-connector' ),
            array( $this, 'hub_username_render' ),
            'chiral-connector-settings-page',
            'chiral_connector_hub_section'
        );
        add_settings_field(
            'hub_app_password',
            __( 'Hub Application Password', 'chiral-connector' ),
            array( $this, 'hub_app_password_render' ),
            'chiral-connector-settings-page',
            'chiral_connector_hub_section'
        );

        // Sync Settings Section
        add_settings_section(
            'chiral_connector_sync_section',
            __( 'Synchronization Settings', 'chiral-connector' ),
            array( $this, 'sync_section_callback' ),
            'chiral-connector-settings-page'
        );
        add_settings_field(
            'node_id',
            __( 'Current Node ID', 'chiral-connector' ),
            array( $this, 'node_id_render' ),
            'chiral-connector-settings-page',
            'chiral_connector_sync_section'
        );
        add_settings_field(
            'batch_sync',
            __( 'Batch Synchronization', 'chiral-connector' ),
            array( $this, 'batch_sync_render' ),
            'chiral-connector-settings-page',
            'chiral_connector_sync_section'
        );

        // Display Settings Section
        add_settings_section(
            'chiral_connector_display_section',
            __( 'Display Settings', 'chiral-connector' ),
            array( $this, 'display_section_callback' ),
            'chiral-connector-settings-page'
        );
        add_settings_field(
            'display_enable',
            __( 'Enable Related Posts Display', 'chiral-connector' ),
            array( $this, 'display_enable_render' ),
            'chiral-connector-settings-page',
            'chiral_connector_display_section'
        );
        add_settings_field(
            'display_count',
            __( 'Number of Related Posts to Show', 'chiral-connector' ),
            array( $this, 'display_count_render' ),
            'chiral-connector-settings-page',
            'chiral_connector_display_section'
        );
        
        // 添加缓存设置字段
        add_settings_field(
            'enable_cache',
            __( 'Enable Related Posts Cache', 'chiral-connector' ),
            array( $this, 'enable_cache_render' ),
            'chiral-connector-settings-page',
            'chiral_connector_display_section'
        );
        
        add_settings_field(
            'clear_cache',
            __( 'Clear Cache', 'chiral-connector' ),
            array( $this, 'clear_cache_render' ),
            'chiral-connector-settings-page',
            'chiral_connector_display_section'
        );

        // Debugging Settings Section
        add_settings_section(
            'chiral_connector_debug_section',
            __( 'Debugging Settings', 'chiral-connector' ),
            array( $this, 'debug_section_callback' ),
            'chiral-connector-settings-page'
        );

        add_settings_field(
            'enable_debug_logging',
            __( 'Enable Debug Logging', 'chiral-connector' ),
            array( $this, 'enable_debug_logging_render' ),
            'chiral-connector-settings-page',
            'chiral_connector_debug_section'
        );
    }

    /**
     * Sanitize each setting field as needed.
     *
     * @param array $input Contains all settings fields as array keys
     * @return array Sanitized input.
     */
    public function sanitize_settings( $input ) {
        $sanitized_input = array();
        if ( isset( $input['hub_url'] ) ) {
            $sanitized_input['hub_url'] = esc_url_raw( trim( $input['hub_url'] ) );
        }
        if ( isset( $input['hub_username'] ) ) {
            $sanitized_input['hub_username'] = sanitize_text_field( $input['hub_username'] );
        }
        if ( isset( $input['hub_app_password'] ) ) {
            // App passwords can be complex, basic sanitization for now.
            // Avoid stripping characters that might be valid in a password.
            $sanitized_input['hub_app_password'] = trim( $input['hub_app_password'] );
        }
        if ( isset( $input['hub_user_id'] ) ) {
            $sanitized_input['hub_user_id'] = absint( $input['hub_user_id'] );
        }
        if ( isset( $input['node_id'] ) ) {
            $sanitized_input['node_id'] = sanitize_text_field( $input['node_id'] );
        }
        if ( isset( $input['display_enable'] ) ) {
            $existing_settings = get_option( 'chiral_connector_settings', array() );
            $previous_display_enable = isset( $existing_settings['display_enable'] ) ? (bool) $existing_settings['display_enable'] : true;
            $new_display_enable = (bool) $input['display_enable'];
            
            $sanitized_input['display_enable'] = $new_display_enable;
            
            // If display was enabled before and is now being disabled, record the timestamp
            if ( $previous_display_enable && ! $new_display_enable ) {
                update_option( 'chiral_connector_display_last_disabled', current_time( 'timestamp' ) );
            }
        } else {
            // 复选框未勾选时，浏览器不会发送该字段，所以这里应该设置为false
            $existing_settings = get_option( 'chiral_connector_settings', array() );
            $previous_display_enable = isset( $existing_settings['display_enable'] ) ? (bool) $existing_settings['display_enable'] : true;
            
            $sanitized_input['display_enable'] = false;
            
            // If display was enabled before and is now being disabled, record the timestamp
            if ( $previous_display_enable ) {
                update_option( 'chiral_connector_display_last_disabled', current_time( 'timestamp' ) );
            }
        }
        if ( isset( $input['display_count'] ) ) {
            $sanitized_input['display_count'] = absint( $input['display_count'] );
        }
        if ( isset( $input['enable_debug_logging'] ) ) {
            $sanitized_input['enable_debug_logging'] = (bool) $input['enable_debug_logging'];
        } else {
            // 复选框未勾选时，浏览器不会发送该字段，所以这里应该设置为false
            $sanitized_input['enable_debug_logging'] = false;
        }
        
        // 处理缓存设置
        if ( isset( $input['enable_cache'] ) ) {
            $sanitized_input['enable_cache'] = (bool) $input['enable_cache'];
        } else {
            // 复选框未勾选时，浏览器不会发送该字段，所以这里应该设置为false
            $sanitized_input['enable_cache'] = false;
        }
        
        // Preserve existing hub_user_id if not in input (to avoid losing it during regular settings saves)
        $existing_settings = get_option( 'chiral_connector_settings', array() );
        if ( ! isset( $input['hub_user_id'] ) && isset( $existing_settings['hub_user_id'] ) ) {
            $sanitized_input['hub_user_id'] = absint( $existing_settings['hub_user_id'] );
        }
        
        return $sanitized_input;
    }

    // --- Section Callbacks ---
    public function hub_section_callback() {
        $options = get_option( 'chiral_connector_settings' );
        $hub_url = isset($options['hub_url']) ? trim($options['hub_url']) : '';
        $username = isset($options['hub_username']) ? trim($options['hub_username']) : '';
        $app_password = isset($options['hub_app_password']) ? trim($options['hub_app_password']) : '';
        
        $is_hub_configured = !empty($hub_url) && !empty($username) && !empty($app_password);
        
        // Check if we're in Hub mode
        global $chiral_connector_core;
        $is_hub_mode = false;
        
        if ( isset( $chiral_connector_core ) && method_exists( $chiral_connector_core, 'is_hub_mode' ) ) {
            $is_hub_mode = $chiral_connector_core->is_hub_mode();
        }
        
        echo '<p>' . esc_html__( 'Enter the connection details for your Chiral Hub.', 'chiral-connector' ) . '</p>';
        
        if ( $is_hub_mode ) {
            echo '<div class="notice notice-info" style="margin: 10px 0; padding: 12px;"><p>';
            echo '<strong>' . esc_html__('🏠 Hub Mode Detected:', 'chiral-connector') . '</strong> ';
            echo esc_html__('This Connector is running on a Chiral Hub Core site. Connection settings are optional, and data synchronization is automatically disabled. Related articles display remains active to show content from your entire network.', 'chiral-connector');
            echo '</p></div>';
        } elseif (!$is_hub_configured) {
            echo '<div class="notice notice-warning" style="margin: 10px 0; padding: 12px;"><p>';
            echo '<strong>' . esc_html__('⚠️ Setup Required:', 'chiral-connector') . '</strong> ';
            echo esc_html__('Please complete the Hub connection settings above, then test the connection. After a successful test, remember to click the "Save Settings" button at the bottom of this page to save your configuration.', 'chiral-connector');
            echo '</p></div>';
        }
        
        echo '<p class="description">' . esc_html__( 'Note: After entering your credentials, please click "Test Connection" to verify the connection and automatically retrieve your Hub User ID. This ID is required for advanced features like data deletion.', 'chiral-connector' ) . '</p>';
        echo '<button type="button" id="chiral-connector-test-connection" class="button">' . esc_html__( 'Test Connection', 'chiral-connector' ) . '</button>';
        if (!$is_hub_configured) {
            echo '<span class="description" style="margin-left: 10px; font-style: italic;">' . esc_html__('After testing successfully, scroll down and click "Save Settings"', 'chiral-connector') . '</span>';
        }
        echo '<span id="chiral-connector-test-status" style="margin-left: 10px;"></span>';
    }

    public function sync_section_callback() {
        echo '<p>' . esc_html__( 'Configure synchronization settings for this node.', 'chiral-connector' ) . '</p>';
    }

    public function display_section_callback() {
        echo '<p>' . esc_html__( 'Configure how related Chiral data is displayed on your site.', 'chiral-connector' ) . '</p>';
    }

    public function debug_section_callback() {
        echo '<p>' . esc_html__( 'Configure debugging options for the plugin.', 'chiral-connector' ) . '</p>';
    }

    // --- Field Renderers ---
    public function hub_url_render() {
        $options = get_option( 'chiral_connector_settings' );
        ?>
        <input type='url' name='chiral_connector_settings[hub_url]' value='<?php echo isset($options['hub_url']) ? esc_url( $options['hub_url'] ) : ''; ?>' class='regular-text'>
        <?php
    }

    public function hub_username_render() {
        $options = get_option( 'chiral_connector_settings' );
        ?>
        <input type='text' name='chiral_connector_settings[hub_username]' value='<?php echo isset($options['hub_username']) ? esc_attr( $options['hub_username'] ) : ''; ?>' class='regular-text'>
        <?php
    }

    public function hub_app_password_render() {
        $options = get_option( 'chiral_connector_settings' );
        ?>
        <input type='password' name='chiral_connector_settings[hub_app_password]' value='<?php echo isset($options['hub_app_password']) ? esc_attr( $options['hub_app_password'] ) : ''; ?>' class='regular-text'>
        <p class="description"><?php esc_html_e( 'It is highly recommended to use an Application Password for the Hub connection.', 'chiral-connector' ); ?></p>
        <?php
    }

    public function node_id_render() {
        $options = get_option( 'chiral_connector_settings' );
        $node_id = isset($options['node_id']) ? $options['node_id'] : '';
        if (empty($node_id)) {
            $node_id = Chiral_Connector_Utils::generate_node_id_from_url();
        }
        ?>
        <input type='text' name='chiral_connector_settings[node_id]' value='<?php echo esc_attr( $node_id ); ?>' class='regular-text'>
        <p class="description"><?php esc_html_e( 'A unique identifier for this site (node) within the Chiral Network. If left empty, a hash of the site URL will be used.', 'chiral-connector' ); ?></p>
        <?php
    }

    public function batch_sync_render() {
        // Check if we're in Hub mode
        global $chiral_connector_core;
        $is_hub_mode = false;
        if ( isset( $chiral_connector_core ) && method_exists( $chiral_connector_core, 'is_hub_mode' ) ) {
            $is_hub_mode = $chiral_connector_core->is_hub_mode();
        }
        
        if ( $is_hub_mode ) {
            ?>
            <div class="chiral-batch-sync-guidance" style="background: #e8f4f8; border: 1px solid #2196F3; padding: 12px; margin-bottom: 15px; border-radius: 4px;">
                <p style="margin: 0; color: #1976D2;">
                    <strong>🏠 Hub Mode Detected:</strong> <?php esc_html_e( 'Batch synchronization is not needed on the Hub site. The Hub already contains all the centralized data from your network.', 'chiral-connector' ); ?>
                </p>
            </div>
            <button type="button" class="button" disabled><?php esc_html_e( 'Batch Sync (Disabled in Hub Mode)', 'chiral-connector' ); ?></button>
            <span style="margin-left: 10px; color: #666; font-style: italic;"><?php esc_html_e( 'Not applicable in Hub mode', 'chiral-connector' ); ?></span>
            <?php
            return;
        }
        
        // Original non-Hub mode UI
        ?>
        <div class="chiral-batch-sync-guidance" style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 12px; margin-bottom: 15px; border-radius: 4px;">
            <p style="margin: 0 0 8px 0;"><strong><?php esc_html_e( '📝 When to use Batch Sync:', 'chiral-connector' ); ?></strong></p>
            <ul style="margin: 0 0 8px 20px; padding: 0;">
                <li style="margin-bottom: 4px;"><?php esc_html_e( '🔄 After first-time setup: Run once to sync all existing posts to the Hub', 'chiral-connector' ); ?></li>
                <li style="margin-bottom: 4px;"><?php esc_html_e( '🛠️ After resolving sync errors: Use to retry failed synchronizations', 'chiral-connector' ); ?></li>
                <li style="margin-bottom: 4px;"><?php esc_html_e( '📊 For bulk updates: When you need to update many posts at once', 'chiral-connector' ); ?></li>
            </ul>
            <p style="margin: 0; font-style: italic; color: #6c757d;"><?php esc_html_e( '⚠️ Note: If synchronization is working normally, you don\'t need to run batch sync regularly. New posts sync automatically when published.', 'chiral-connector' ); ?></p>
        </div>
        
        <button type="button" id="chiral-connector-batch-sync" class="button"><?php esc_html_e( 'Batch Sync All Posts', 'chiral-connector' ); ?></button>
        <span id="chiral-connector-batch-sync-status" style="margin-left: 10px;"></span>
        <?php
    }

    public function display_enable_render() {
        $options = get_option( 'chiral_connector_settings' );
        ?>
        <input type='checkbox' name='chiral_connector_settings[display_enable]' <?php checked( isset($options['display_enable']) ? $options['display_enable'] : 1, 1 ); ?> value='1'>
        <?php
    }

    public function display_count_render() {
        $options = get_option( 'chiral_connector_settings' );
        ?>
        <input type='number' name='chiral_connector_settings[display_count]' value='<?php echo isset($options['display_count']) ? esc_attr( $options['display_count'] ) : 5; ?>' min='1' max='10'>
        <p class="description"><?php esc_html_e( 'Number of related items to fetch and display.', 'chiral-connector' ); ?></p>
        <?php
    }

    public function enable_cache_render() {
        $options = get_option( 'chiral_connector_settings' );
        $enable_cache = isset($options['enable_cache']) ? $options['enable_cache'] : 1; // 默认开启
        ?>
        <input type='checkbox' name='chiral_connector_settings[enable_cache]' <?php checked( $enable_cache, 1 ); ?> value='1'>
        <p class="description"><?php esc_html_e( 'Enable related posts cache mechanism. Recommended to keep enabled for performance optimization. Cache expires after 12 hours. Only disable for debugging purposes.', 'chiral-connector' ); ?></p>
        <?php
    }

    public function clear_cache_render() {
        ?>
        <button type="button" id="chiral-connector-clear-cache" class="button"><?php esc_html_e( 'Clear All Cache', 'chiral-connector' ); ?></button>
        <span id="chiral-connector-clear-cache-status" style="margin-left: 10px;"></span>
        <p class="description"><?php esc_html_e( 'Click this button to manually clear all related posts cache data.', 'chiral-connector' ); ?></p>
        <?php
    }

    public function enable_debug_logging_render() {
        $options = get_option( 'chiral_connector_settings' );
        ?>
        <input type='checkbox' name='chiral_connector_settings[enable_debug_logging]' <?php checked( isset($options['enable_debug_logging']) ? $options['enable_debug_logging'] : false, 1 ); ?> value='1'>
        <p class="description"><?php esc_html_e( 'Enable this to log detailed information for debugging purposes. It is recommended to keep this off during normal operation.', 'chiral-connector' ); ?></p>
        <?php
    }

    /**
     * AJAX handler for testing hub connection.
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'chiral_connector_admin_nonce', 'nonce' );

        $hub_url = isset( $_POST['hub_url'] ) ? esc_url_raw( wp_unslash( $_POST['hub_url'] ) ) : '';
        $username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
        $app_password = isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '';

        if ( empty( $hub_url ) || empty( $username ) || empty( $app_password ) ) {
            wp_send_json_error( array( 'message' => __( 'Hub URL, Username, and Application Password are required.', 'chiral-connector' ) ) );
        }

        $result = $this->api->test_hub_connection( $hub_url, $username, $app_password );

        if ( $result === true ) {
            // Connection successful, now get and save the hub_user_id
            $hub_user_id = $this->get_and_save_hub_user_id( $hub_url, $username, $app_password );
            
            if ( $hub_user_id ) {
                wp_send_json_success( array( 'message' => __( '✅ Connection successful! User ID saved.', 'chiral-connector' ) ) );
            } else {
                wp_send_json_success( array( 'message' => __( '✅ Connection successful! (Warning: Could not retrieve user ID)', 'chiral-connector' ) ) );
            }
        } elseif ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => sprintf(
                /* translators: %1$s: Error message from connection test */
                __( 'Connection failed: %1$s', 'chiral-connector' ), 
                $result->get_error_message()
            ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Connection failed with an unknown error.', 'chiral-connector' ) ) );
        }
    }

    /**
     * Get the current user's ID on the Hub and save it to settings.
     *
     * @since 1.1.0
     * @param string $hub_url      The URL of the Chiral Hub.
     * @param string $username     The username for hub authentication.
     * @param string $app_password The application password for hub authentication.
     * @return int|false The user ID on success, false on failure.
     */
    private function get_and_save_hub_user_id( $hub_url, $username, $app_password ) {
        $endpoint = rtrim( $hub_url, '/' ) . '/wp-json/wp/v2/users/me?context=edit';
        
        $args = array(
            'method'  => 'GET',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $app_password ),
            ),
            'timeout' => 15,
        );

        $response = wp_remote_request( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            Chiral_Connector_Utils::log_message( 'Failed to get user ID from Hub: ' . $response->get_error_message(), 'error' );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            Chiral_Connector_Utils::log_message( 'Failed to get user ID from Hub. Response code: ' . $response_code, 'error' );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $user_data = json_decode( $body, true );

        if ( ! $user_data || ! isset( $user_data['id'] ) ) {
            Chiral_Connector_Utils::log_message( 'Invalid user data received from Hub', 'error' );
            return false;
        }

        $user_id = $user_data['id'];
        
        // Save the user ID to settings
        $settings = get_option( 'chiral_connector_settings', array() );
        $settings['hub_user_id'] = $user_id;
        update_option( 'chiral_connector_settings', $settings );
        
        Chiral_Connector_Utils::log_message( 'Hub user ID saved: ' . $user_id, 'info' );
        
        return $user_id;
    }

    /**
     * AJAX handler to trigger batch sync.
     */
    public function ajax_trigger_batch_sync() {
        check_ajax_referer( 'chiral_connector_admin_nonce', 'nonce' );

        // Check if we're in Hub mode
        global $chiral_connector_core;
        if ( isset( $chiral_connector_core ) && method_exists( $chiral_connector_core, 'is_hub_mode' ) && $chiral_connector_core->is_hub_mode() ) {
            wp_send_json_error( array( 'message' => __( 'Batch sync is not available in Hub mode. The Hub already contains all centralized data.', 'chiral-connector' ) ) );
            return;
        }

        // Check if sync is already running or scheduled
        if (get_transient('chiral_connector_batch_sync_running')) {
            wp_send_json_error(array('message' => __('A batch sync is already in progress or scheduled. Please wait for it to complete.', 'chiral-connector')));
            return;
        }

        // Set a transient to indicate that batch sync is initiated and scheduled.
        // This transient will be deleted by the batch sync process itself upon completion.
        set_transient('chiral_connector_batch_sync_running', true, HOUR_IN_SECONDS); // Lock for 1 hour

        // Schedule an action for true background processing
        $scheduled = wp_schedule_single_event(time() + 5, 'chiral_connector_batch_sync_posts'); // Schedule to run in 5 seconds

        if (false === $scheduled) {
            // If scheduling fails, delete the transient and inform the user.
            delete_transient('chiral_connector_batch_sync_running');
            Chiral_Connector_Utils::log_message('Failed to schedule batch sync background task.', 'error');
            wp_send_json_error(array('message' => __('Failed to schedule the batch sync process. Please try again.', 'chiral-connector')));
            return;
        }
        
        Chiral_Connector_Utils::log_message('Batch sync process has been successfully scheduled.', 'info');
        wp_send_json_success( array( 'message' => __( 'Batch sync process has been scheduled to run in the background. This may take some time. Check server logs for progress.', 'chiral-connector' ) ) );
    }

    /**
     * AJAX handler for quitting the Chiral Network.
     * Deletes all node data from the hub, clears local settings, and deactivates the plugin.
     *
     * @since 1.1.0
     */
    public function ajax_quit_network() {
        check_ajax_referer( 'chiral_connector_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have permission to perform this action.', 'chiral-connector' ), 403 );
            return;
        }

        $settings = Chiral_Connector_Utils::get_setting();
        if ( ! $settings ) {
            Chiral_Connector_Utils::log_message( 'Chiral Connector settings not found in ajax_quit_network', 'error' );
            wp_send_json_error( __( 'Chiral Connector settings not found.', 'chiral-connector' ), 500 );
            return;
        }

        $hub_url = isset( $settings['hub_url'] ) ? $settings['hub_url'] : null;
        $username = isset( $settings['hub_username'] ) ? $settings['hub_username'] : null;
        $app_password = isset( $settings['hub_app_password'] ) ? $settings['hub_app_password'] : null;
        // IMPORTANT: Assume 'hub_user_id' is stored in settings after a successful connection/setup.
        // This ID is the current WordPress user's ID on the Hub instance.
        $user_id_on_hub = isset( $settings['hub_user_id'] ) ? $settings['hub_user_id'] : null;

        // Debug logging
        Chiral_Connector_Utils::log_message( 'ajax_quit_network: Settings check - hub_url: ' . ($hub_url ? 'set' : 'not set') . ', username: ' . ($username ? 'set' : 'not set') . ', app_password: ' . ($app_password ? 'set' : 'not set') . ', user_id_on_hub: ' . ($user_id_on_hub ? $user_id_on_hub : 'not set'), 'info' );

        if ( ! $hub_url || ! $username || ! $app_password || ! $user_id_on_hub ) {
            $missing_fields = array();
            if ( ! $hub_url ) $missing_fields[] = 'hub_url';
            if ( ! $username ) $missing_fields[] = 'username';
            if ( ! $app_password ) $missing_fields[] = 'app_password';
            if ( ! $user_id_on_hub ) $missing_fields[] = 'hub_user_id';
            
            $error_message = sprintf( 
                /* translators: %s: Comma-separated list of missing configuration fields */
                __( 'Hub connection details or User ID on Hub are incomplete. Missing: %s. Please use the "Test Connection" button first to automatically retrieve and save your Hub User ID.', 'chiral-connector' ), 
                implode(', ', $missing_fields) 
            );
            Chiral_Connector_Utils::log_message( 'ajax_quit_network: ' . $error_message, 'error' );
            wp_send_json_error( $error_message, 400 );
            return;
        }

        // Ensure API class is loaded (it should be via constructor, but double check)
        if ( ! isset( $this->api ) || ! ( $this->api instanceof Chiral_Connector_Api ) ) {
            if ( ! class_exists( 'Chiral_Connector_Api' ) ) {
                 require_once CHIRAL_CONNECTOR_PLUGIN_DIR . 'includes/class-chiral-connector-api.php';
            }
            $this->api = new Chiral_Connector_Api( $this->plugin_name, $this->version );
        }

        // 1. Get all Chiral Data IDs from the Hub for this node
        $node_data_ids = $this->api->get_all_node_data_ids_from_hub( $hub_url, $username, $app_password, $user_id_on_hub );

        if ( is_wp_error( $node_data_ids ) ) {
            Chiral_Connector_Utils::log_message( 'Failed to get node data IDs from Hub: ' . $node_data_ids->get_error_message(), 'error' );
            wp_send_json_error( sprintf(
                /* translators: %1$s: Error message from Hub */
                __( 'Failed to retrieve data list from Hub: %1$s', 'chiral-connector' ), 
                $node_data_ids->get_error_message()
            ), 500 );
            return;
        }

        $deleted_count = 0;
        $error_count = 0;
        $deletion_errors = array();

        // 2. Delete each piece of Chiral Data from the Hub
        if ( ! empty( $node_data_ids ) ) {
            foreach ( $node_data_ids as $hub_cpt_id ) {
                $delete_result = $this->api->delete_data_from_hub( $hub_cpt_id, $hub_url, $username, $app_password );
                if ( is_wp_error( $delete_result ) ) {
                    $error_count++;
                    $deletion_errors[] = "ID {$hub_cpt_id}: " . $delete_result->get_error_message();
                    Chiral_Connector_Utils::log_message( "Failed to delete Hub CPT ID {$hub_cpt_id}: " . $delete_result->get_error_message(), 'error' );
                } else {
                    $deleted_count++;
                }
            }
        }

        if ( $error_count > 0 ) {
            $error_message = sprintf(
                /* translators: %1$d: Number of errors, %2$d: Number of successfully deleted items, %3$s: List of error messages */
                __( 'Finished deleting data from Hub with %1$d error(s). %2$d items successfully deleted. Errors: %3$s', 'chiral-connector' ),
                $error_count,
                $deleted_count,
                implode("; ", $deletion_errors)
            );
            // Decide if we should stop or continue. For now, let's log and proceed to clear local settings and deactivate.
             Chiral_Connector_Utils::log_message( $error_message, 'warning' );
            // We will proceed to local cleanup and deactivation even if some Hub deletions failed, 
            // as the user explicitly wants to quit.
        }

        // 3. Clear local Chiral Connector settings
        // It's better to have a dedicated method in Deactivator or Utils for this.
        if ( ! class_exists( 'Chiral_Connector_Deactivator' ) ) {
            require_once CHIRAL_CONNECTOR_PLUGIN_DIR . 'includes/class-chiral-connector-deactivator.php';
        }
        
        if ( class_exists( 'Chiral_Connector_Deactivator' ) ) {
            Chiral_Connector_Deactivator::clear_all_plugin_data();
        } else {
            // Fallback if class isn't loaded for some reason, though it should be.
            delete_option( 'chiral_connector_settings' );
            delete_option( 'chiral_connector_failed_sync_queue' );
            wp_clear_scheduled_hook( 'chiral_connector_retry_failed_syncs' );
            Chiral_Connector_Utils::log_message( 'Chiral_Connector_Deactivator class not found, used direct delete_option calls.', 'warning' );
        }

        Chiral_Connector_Utils::log_message( "Local Chiral Connector settings cleared. {$deleted_count} items reported deleted from Hub.", 'info' );

        // 4. Deactivate the plugin
        // Note: Deactivating the plugin here means the success JSON response might not always reach the client
        // if the page reloads too quickly due to deactivation hooks or other processes.
        // The client-side JS should be prepared for this and perhaps inform the user to manually check/refresh.
        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        deactivate_plugins( CHIRAL_CONNECTOR_PLUGIN_BASENAME );

        wp_send_json_success( 
            array(
                'message' => __( 'Successfully disconnected from Chiral Network. All your data on the Hub has been requested for deletion. Plugin settings cleared and plugin deactivated. Please verify data deletion on the Hub manually.', 'chiral-connector' ),
                'deleted_on_hub' => $deleted_count,
                'hub_deletion_errors' => $error_count > 0 ? $deletion_errors : null
            )
        );
    }

    /**
     * Adds the meta box to the post edit screen.
     *
     * @since 1.0.1
     */
    public function add_chiral_send_metabox() {
        add_meta_box(
            'chiral_send_to_hub_metabox',                 // ID
            __( 'Chiral Network Sync', 'chiral-connector' ), // Title
            array( $this, 'render_chiral_send_metabox' ),  // Callback function
            'post',                                       // Screen (post type)
            'side',                                       // Context (normal, side, advanced)
            'default'                                     // Priority
        );
    }

    /**
     * Renders the content of the 'Send to Chiral?' meta box.
     *
     * @since 1.0.1
     * @param WP_Post $post The post object.
     */
    public function render_chiral_send_metabox( $post ) {
        // Add a nonce field so we can check for it later.
        wp_nonce_field( 'chiral_send_metabox_save', 'chiral_send_metabox_nonce' );

        // Get the current value of the meta field.
        $send_to_hub = get_post_meta( $post->ID, '_chiral_send_to_hub', true );

        // Default to checked if no value is set yet (new post or first time)
        if ( '' === $send_to_hub ) {
            $send_to_hub = 'yes';
        }
        ?>
        <p>
            <label for="chiral_send_to_hub_checkbox">
                <input type="checkbox" id="chiral_send_to_hub_checkbox" name="chiral_send_to_hub_checkbox" value="yes" <?php checked( $send_to_hub, 'yes' ); ?> />
                <?php esc_html_e( 'Send to Chiral Hub?', 'chiral-connector' ); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Saves the 'Send to Chiral?' meta box data.
     *
     * @since 1.0.1
     * @param int $post_id The ID of the post being saved.
     */
    public function save_chiral_send_metabox_data( $post_id ) {
        // Check if our nonce is set from the metabox.
        // For quick edit, WordPress handles its own nonce ('_inline_edit').
        if ( ! isset( $_POST['chiral_send_metabox_nonce'] ) && ! isset( $_POST['_inline_edit'] ) ) {
            return;
        }

        // Verify that the nonce is valid if it's from the metabox.
        if ( isset( $_POST['chiral_send_metabox_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['chiral_send_metabox_nonce'] ) ), 'chiral_send_metabox_save' ) ) {
            return;
        }
        
        // Verify nonce for quick edit
        if ( isset( $_POST['_inline_edit'] ) && ! check_ajax_referer( 'inlineeditnonce', '_inline_edit', false ) ) {
             // error_log('Chiral Connector: Quick edit nonce verification failed.');
            return;
        }


        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        // This check is more relevant for the full edit screen. Quick edit typically doesn't trigger autosave in the same way.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE && !isset($_POST['_inline_edit']) ) {
            return;
        }

        // Check the user's permissions.
        // For quick edit, WordPress has already checked this for 'edit_post'.
        // We might need to re-check if we have more granular capabilities.
        if ( isset( $_POST['post_type'] ) && 'post' == sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) ) { // 'post_type' is available in metabox save
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }
        } elseif ( isset( $_POST['post_ID'] ) ) { // Quick edit passes post_ID
             if ( ! current_user_can( 'edit_post', $post_id ) ) { // $post_id is correctly passed to save_post
                return;
            }
        } else {
            // Could be a different context, or an issue.
            return;
        }
        
        $post_type_from_quick_edit = isset($_POST['post_type']) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : null;
        if (isset($_POST['_inline_edit']) && $post_type_from_quick_edit !== 'post') {
            // error_log('Chiral Connector: Quick edit save_post called for wrong post type: ' . $post_type_from_quick_edit);
            return; // Ensure we only process for 'post' post type in quick edit
        }


        // Sanitize user input.
        // For quick edit, the field name will be what we set in `add_chiral_quick_edit_fields`.
        // For metabox, it's 'chiral_send_to_hub_checkbox'.
        $new_send_to_hub_value = 'no'; // Default to 'no'
        if ( isset( $_POST['chiral_send_to_hub_checkbox'] ) ) { // From metabox
            $new_send_to_hub_value = 'yes';
        } elseif ( isset( $_POST['chiral_send_to_hub_quick_edit'] ) ) { // From quick edit
             $quick_edit_value = sanitize_text_field( wp_unslash( $_POST['chiral_send_to_hub_quick_edit'] ) );
             $new_send_to_hub_value = ( $quick_edit_value === 'yes' ) ? 'yes' : 'no';
        }


        $current_send_to_hub_value = get_post_meta( $post_id, '_chiral_send_to_hub', true );
        // Ensure default value for current state if it was never set (important for the first save)
        if ($current_send_to_hub_value === '') {
            // If it was a new post, and checkbox was default checked (yes), and user unchecks it,
            // then new_send_to_hub_value will be 'no'. current_send_to_hub_value was ''.
            // To correctly trigger deletion logic (if it was somehow synced before this save_post, though unlikely for new post),
            // we might need to consider what '' means. For our logic, '' usually implies 'yes' if we just added the feature.
            // Let's assume if it's '', it hasn't been explicitly set to 'no'.
            // For the deletion logic to work correctly when unchecking a default 'yes':
            // If $current_send_to_hub_value is '', it means it was effectively 'yes' (default checked).
            // So, if $new_send_to_hub_value is 'no', and $current_send_to_hub_value is '', it's a change from 'yes' to 'no'.
            // This assignment makes the logic below simpler.
            $current_send_to_hub_value = 'yes';
        }

        $hub_cpt_id = get_post_meta( $post_id, '_chiral_hub_cpt_id', true );

        // Update the meta field in the database.
        update_post_meta( $post_id, '_chiral_send_to_hub', $new_send_to_hub_value );

        // --- Derivative Logic: Delete from Hub if unchecked and previously synced ---
        if ( $current_send_to_hub_value !== 'no' && $new_send_to_hub_value === 'no' && !empty($hub_cpt_id) ) {
            // User unchecked the box (or it was default checked and now saved as 'no'),
            // and the post was previously synced (has a hub_cpt_id)

            // Ensure $this->api is initialized
            if ( ! $this->api instanceof Chiral_Connector_Api) {
                if ( ! class_exists( 'Chiral_Connector_Api' ) ) {
                     require_once CHIRAL_CONNECTOR_PLUGIN_DIR . 'includes/class-chiral-connector-api.php';
                }
                $this->api = new Chiral_Connector_Api($this->plugin_name, $this->version);
            }

            if ($this->api) {
                $settings = get_option('chiral_connector_settings');
                $hub_url = isset($settings['hub_url']) ? $settings['hub_url'] : '';
                $username = isset($settings['hub_username']) ? $settings['hub_username'] : '';
                $app_password = isset($settings['hub_app_password']) ? $settings['hub_app_password'] : '';

                if ($hub_url && $username && $app_password) {
                    $this->api->delete_data_from_hub($hub_cpt_id, $hub_url, $username, $app_password);
                    // error_log("Chiral Connector: Post $post_id ($hub_cpt_id) deleted from Hub due to unchecking 'Send to Chiral'.");
                } else {
                    // error_log('Chiral Connector: Cannot delete from Hub. Missing connection settings. Post ID: ' . $post_id);
                }
            } else {
                // error_log('Chiral Connector: API class not available for deleting from Hub. Post ID: ' . $post_id);
            }
        }
    }
    
    /**
     * Adds custom column to the post list table.
     *
     * @since 1.0.1
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_chiral_send_column( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $title ) {
            $new_columns[$key] = $title;
            if ( $key === 'title' ) { // Add after title column, or choose another position
                $new_columns['chiral_send_to_hub'] = __( 'Send to Chiral?', 'chiral-connector' );
            }
        }
        // If 'title' wasn't found, add to the end as a fallback
        if ( !isset($new_columns['chiral_send_to_hub']) && isset($columns['date']) ) {
             // Try to insert before 'date' if 'title' wasn't there (should not happen for posts)
            $new_columns = array();
            foreach($columns as $key => $value) {
                if($key == 'date') {
                    $new_columns['chiral_send_to_hub'] = __( 'Send to Chiral?', 'chiral-connector' );
                }
                $new_columns[$key] = $value;
            }
        } elseif (!isset($new_columns['chiral_send_to_hub'])) {
            $new_columns['chiral_send_to_hub'] = __( 'Send to Chiral?', 'chiral-connector' );
        }
        return $new_columns;
    }

    /**
     * Renders the content for the custom column.
     *
     * @since 1.0.1
     * @param string $column_name The name of the column.
     * @param int    $post_id     The ID of the current post.
     */
    public function render_chiral_send_column_content( $column_name, $post_id ) {
        if ( $column_name === 'chiral_send_to_hub' ) {
            $send_to_hub = get_post_meta( $post_id, '_chiral_send_to_hub', true );
            // Default to 'yes' if meta is not set or empty, for consistency with checkbox default
            $status_value = ( $send_to_hub === 'no' ) ? 'no' : 'yes'; 
            $display_text = ( $status_value === 'no' ) ? esc_html__( 'No', 'chiral-connector' ) : esc_html__( 'Yes', 'chiral-connector' );
            
            echo '<span data-chiral-send-status="' . esc_attr( $status_value ) . '">' . $display_text . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }


    /**
     * Displays the custom fields in the Quick Edit box.
     *
     * @since 1.0.1
     * @param string $column_name Name of the column to be edited.
     * @param string $post_type   The post type.
     */
    public function add_chiral_quick_edit_fields( $column_name, $post_type ) {
        // We only want to add this to our custom column's quick edit panel,
        // but WordPress quick edit shows all fields in one panel.
        // So, we check if it's the 'chiral_send_to_hub' column to add our field.
        // However, the hook is called once per column in the "inline-edit-col" div structure.
        // It's better to add our fields once, e.g., when a common column like 'title' or 'date' is processed,
        // or use a guard to ensure it's only added once.
        
        // Use a static variable to ensure the field is only output once per quick edit row
        static $chiral_quick_edit_rendered = 0;
        // The hook fires for each column, $column_name tells us which one.
        // We only need to output our custom field once. Let's pick a common column like 'title'.
        if ( $column_name !== 'title' || $post_type !== 'post' ) { // Or 'tags', 'categories' etc.
             // If we are targeting our own column:
             // if ($column_name === 'chiral_send_to_hub') { ... }
             // But since WP generates the quick edit panel with all fields together,
             // it's better to hook into a standard column that appears early.
            if ($column_name !== 'chiral_send_to_hub') return; // Only output when it's our column's turn, or a specific one like title.
        }

        // Check if this is the first time for this specific post's quick edit row
        // This is tricky with static in a class method called multiple times for different posts in a list.
        // A better way is to ensure the HTML structure is correct.
        // The `quick_edit_custom_box` action is called for each column in the quick edit form.
        // We should only output our field once. Let's do it when $column_name is 'taxonomy-category' or similar standard.
        // A simpler way: only output if it hasn't been output yet for the current post.
        // This can be managed by the JS that populates it. Here, we just provide the field.

        // Nonce field for security. WordPress adds its own for the quick edit action itself.
        // We don't need a specific nonce here as `save_post` will check `_inline_edit` nonce.
        // wp_nonce_field( 'chiral_quick_edit_save_' . get_the_ID(), 'chiral_quick_edit_nonce_' . get_the_ID() );
        // The nonce check should be `check_ajax_referer( 'inlineeditnonce', '_inline_edit' )` in save_post.

        // We need to output this within a <fieldset><div class="inline-edit-group"> structure for proper layout
        // The hook `quick_edit_custom_box` is usually called *inside* such a structure for custom taxonomies.
        // For a simple option, we can output it directly. The JS will handle placement if needed, or we style it.
        
        // Let's ensure this runs only once for the 'post' post type's quick edit.
        // The action fires for *each* column that calls `column_default()` or is a custom column.
        // To avoid duplicate output, we check a specific column name.
        // For 'post' type, 'tags' is a common one.
        if ($column_name != 'tags' && $post_type == 'post') { // Or 'categories', but 'tags' is often last of the defaults.
            // return; // Let's try to output it when it is our column
        }
         if ($column_name != 'chiral_send_to_hub' && $post_type == 'post') {
            return;
        }


        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <div class="inline-edit-group wp-clearfix chiral-quick-edit-group">
                    <label class="alignleft">
                        <input type="checkbox" name="chiral_send_to_hub_quick_edit" value="yes" />
                        <span class="checkbox-title"><?php esc_html_e( 'Send to Chiral Hub?', 'chiral-connector' ); ?></span>
                    </label>
                </div>
            </div>
        </fieldset>
        <?php
        // Note: The actual value will be populated by JavaScript.
        // The name 'chiral_send_to_hub_quick_edit' will be used in save_post.
    }

    /**
     * AJAX handler for clearing related posts cache.
     */
    public function ajax_clear_cache() {
        check_ajax_referer( 'chiral_connector_admin_nonce', 'nonce' );

        // 使用WordPress transients API清除相关缓存
        global $wpdb;
        
        // 删除所有以chiral_related_cache_开头的transients
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_chiral_related_cache_%',
                '_transient_timeout_chiral_related_cache_%'
            )
        );

        // 清除对象缓存（如果使用了对象缓存）
        wp_cache_flush();

        wp_send_json_success( array( 'message' => __( 'Related posts cache has been successfully cleared.', 'chiral-connector' ) ) );
    }

} // End Class