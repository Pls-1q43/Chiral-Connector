<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch
/**
 * Handles the display of related Chiral data on the front-end.
 *
 * @package    Chiral_Connector
 * @subpackage Chiral_Connector/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Connector_Display {

    /**
     * The plugin name.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The plugin name.
     */
    private $plugin_name;

    /**
     * The plugin version.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The plugin version.
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
     * Constructor.
     *
     * @since 1.0.0
     * @param string $plugin_name The plugin name.
     * @param string $version The plugin version.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;

        $this->load_dependencies();
        // Hooks will be added by Chiral_Connector_Public or Chiral_Connector_Core using the loader.
        // e.g., add_filter( 'the_content', array( $this, 'append_related_posts_to_content' ) );
        // add_shortcode( 'chiral_related_posts', array( $this, 'render_related_posts_shortcode' ) );
    }

    /**
     * Load dependencies.
     */
    private function load_dependencies() {
        if ( ! class_exists( 'Chiral_Connector_Api' ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'class-chiral-connector-api.php';
        }
        $this->api = new Chiral_Connector_Api($this->plugin_name, $this->version);
    }

    /**
     * Appends the related posts display to the content of single posts.
     *
     * @since 1.0.0
     * @param string $content The post content.
     * @return string The modified post content.
     */
    public function append_related_posts_to_content( $content ) {
        $options = get_option('chiral_connector_settings');
        $enable_display = isset($options['display_enable']) ? $options['display_enable'] : true;

        if ( is_single() && is_main_query() && $enable_display ) {
            global $post;
            if ($post) {
                $send_to_hub = get_post_meta( $post->ID, '_chiral_send_to_hub', true );
                // If meta is 'no', do not display related posts for this article.
                // If meta is empty or 'yes', proceed.
                if ( $send_to_hub === 'no' ) {
                    return $content; // Do not append related posts placeholder
                }
            }

            // The actual fetching and display might be handled by JS for AJAX loading.
            // This PHP function could output a placeholder div that JS will populate.
            $related_posts_html = $this->get_related_posts_html_placeholder();
            $content .= $related_posts_html;
        }
        return $content;
    }

    /**
     * Returns a placeholder HTML for the related posts section.
     * JavaScript will target this to inject the actual content.
     *
     * @since 1.0.0
     * @return string HTML placeholder.
     */
    private function get_related_posts_html_placeholder() {
        // This ID or class will be used by public JS to inject content.
        // It can also contain data attributes for JS like post_id, node_id, hub_url etc.
        global $post;
        $current_post_id = $post ? $post->ID : 0;
        $options = get_option('chiral_connector_settings');
        $node_id = isset($options['node_id']) ? $options['node_id'] : '';
        $hub_url = isset($options['hub_url']) ? $options['hub_url'] : '';
        $display_count = isset($options['display_count']) ? intval($options['display_count']) : 5;

        // Check if we're in Hub mode
        global $chiral_connector_core;
        $is_hub_mode = false;
        if ( isset( $chiral_connector_core ) && method_exists( $chiral_connector_core, 'is_hub_mode' ) ) {
            $is_hub_mode = $chiral_connector_core->is_hub_mode();
        }

        if (!$current_post_id) {
            return '<!-- Chiral Connector: Missing current post ID -->';
        }

        // In Hub mode, we don't require hub_url configuration
        if ( $is_hub_mode ) {
            // Use current site URL as hub_url in Hub mode
            $hub_url = home_url();
            // Node ID can be optional or auto-generated in Hub mode
            if ( empty( $node_id ) ) {
                $node_id = 'hub-local';
            }
        } else {
            // In normal Node mode, we require hub_url and node_id
            if ( empty($node_id) || empty($hub_url) ) {
                return '<!-- Chiral Connector: Missing configuration for related posts (node_id or hub_url) -->';
            }
        }

        return sprintf(
            '<div id="chiral-connector-related-posts" class="chiral-connector-related-posts-container" data-post-url="%s" data-node-id="%s" data-hub-url="%s" data-count="%d" data-hub-mode="%s">%s</div>',
            esc_url(get_permalink($current_post_id)),
            esc_attr($node_id),
            esc_url($hub_url),
            esc_attr($display_count),
            $is_hub_mode ? 'true' : 'false',
            esc_html__( 'Loading related Chiral data...', 'chiral-connector' )
        );
    }

    /**
     * Handles the [chiral_related_posts] shortcode.
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the shortcode.
     */
    public function render_related_posts_shortcode( $atts ) {
        $options = get_option('chiral_connector_settings');
        $enable_display = isset($options['display_enable']) ? $options['display_enable'] : true;

        if (!$enable_display) return '';

        global $post;
        if ($post) {
            $send_to_hub = get_post_meta( $post->ID, '_chiral_send_to_hub', true );
            if ( $send_to_hub === 'no' ) {
                return ''; // Do not render shortcode output
            }
        }

        // Shortcode attributes can override global settings
        // For example: [chiral_related_posts count="3"]
        $atts = shortcode_atts(
            array(
                'count' => isset($options['display_count']) ? intval($options['display_count']) : 5,
            ),
            $atts,
            'chiral_related_posts'
        );

        global $post;
        $current_post_id = $post ? $post->ID : 0;
        $node_id = isset($options['node_id']) ? $options['node_id'] : '';
        $hub_url = isset($options['hub_url']) ? $options['hub_url'] : '';

        // Check if we're in Hub mode
        global $chiral_connector_core;
        $is_hub_mode = false;
        if ( isset( $chiral_connector_core ) && method_exists( $chiral_connector_core, 'is_hub_mode' ) ) {
            $is_hub_mode = $chiral_connector_core->is_hub_mode();
        }

        if (!$current_post_id) {
            return '<!-- Chiral Connector Shortcode: Missing current post ID -->';
        }

        // In Hub mode, we don't require hub_url configuration
        if ( $is_hub_mode ) {
            // Use current site URL as hub_url in Hub mode
            $hub_url = home_url();
            // Node ID can be optional or auto-generated in Hub mode
            if ( empty( $node_id ) ) {
                $node_id = 'hub-local';
            }
        } else {
            // In normal Node mode, we require hub_url and node_id
            if ( empty($node_id) || empty($hub_url) ) {
                return '<!-- Chiral Connector Shortcode: Missing configuration (node_id or hub_url) -->';
            }
        }

        // Similar to the_content filter, output a placeholder for JS to populate.
        return sprintf(
            '<div class="chiral-connector-related-posts-container chiral-connector-shortcode" data-post-url="%s" data-node-id="%s" data-hub-url="%s" data-count="%d" data-hub-mode="%s">%s</div>',
            esc_url(get_permalink($current_post_id)),
            esc_attr($node_id),
            esc_url($hub_url),
            esc_attr(intval($atts['count'])),
            $is_hub_mode ? 'true' : 'false',
            esc_html__( 'Loading related Chiral data...', 'chiral-connector' )
        );
    }

    /**
     * AJAX handler to fetch and return related posts HTML.
     * This is called by public/assets/js/chiral-connector-public.js
     * Note: The documentation mentions JS directly calling the HUB API.
     * If so, this PHP AJAX handler might not be needed, or it could act as a proxy if direct JS calls are problematic (e.g. CORS, auth exposure).
     * For now, let's assume JS calls the Hub directly as per doc: "通过 public/assets/js/chiral-connector-public.js 中的 JavaScript，向目标枢纽的自定义API端点...发送异步 GET 请求"
     * This means the actual rendering logic from related-posts-display.php will be primarily used by the JS.
     * However, if we wanted a PHP fallback or a server-side rendered version via AJAX, this is where it would go.
     */
    public function ajax_fetch_related_posts() {
        check_ajax_referer('chiral_connector_related_posts_nonce', 'nonce');

        $current_post_url = isset($_POST['source_url']) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '';
        // Changed 'node_id' to 'requesting_node_id' to match JS parameter and Hub API expectation
        $requesting_node_id = isset($_POST['requesting_node_id']) ? sanitize_text_field( wp_unslash( $_POST['requesting_node_id'] ) ) : '';
        $count = isset($_POST['count']) ? intval($_POST['count']) : 5;

        if (empty($current_post_url) || empty($requesting_node_id)) {
            wp_send_json_error(array(
                'message' => esc_html__('Missing parameters (source_url or requesting_node_id).', 'chiral-connector')
            ));
            return; // Important to return here
        }

        $options = get_option('chiral_connector_settings');
        $hub_url = isset($options['hub_url']) ? $options['hub_url'] : '';
        $hub_api_user = isset($options['hub_username']) ? $options['hub_username'] : '';
        $hub_api_pass = isset($options['hub_app_password']) ? $options['hub_app_password'] : '';

        // Check if we're in Hub mode
        global $chiral_connector_core;
        $is_hub_mode = false;
        if ( isset( $chiral_connector_core ) && method_exists( $chiral_connector_core, 'is_hub_mode' ) ) {
            $is_hub_mode = $chiral_connector_core->is_hub_mode();
        }

        // In Hub mode, credentials are optional
        if ( ! $is_hub_mode ) {
            if (empty($hub_url) || empty($hub_api_user) || empty($hub_api_pass)) {
                wp_send_json_error(array(
                    'message' => esc_html__('Hub connection details are not configured in Chiral Connector settings.', 'chiral-connector')
                ));
                return; // Important to return here
            }
        }

        // 检查是否启用缓存
        $enable_cache = isset($options['enable_cache']) ? $options['enable_cache'] : true; // 默认开启
        
        // 生成缓存键
        $cache_key = 'chiral_related_cache_' . md5($current_post_url . '_' . $requesting_node_id . '_' . $count);
        
        // 如果启用缓存，先尝试从缓存获取数据
        if ($enable_cache) {
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                // 从缓存返回数据
                if (class_exists('Chiral_Connector_Utils')) {
                    Chiral_Connector_Utils::log_message('Chiral Connector: Returning cached related data for: ' . $current_post_url, 'debug');
                }
                wp_send_json_success($cached_data);
                return;
            }
        }

        // Ensure the API handler is loaded and available
        if ( ! isset($this->api) || ! method_exists($this->api, 'get_related_data_from_hub') ) {
             if ( ! class_exists( 'Chiral_Connector_Api' ) ) {
                require_once plugin_dir_path( __FILE__ ) . 'class-chiral-connector-api.php';
            }
            // Re-initialize if it wasn't, though it should be by constructor
            $this->api = new Chiral_Connector_Api($this->plugin_name, $this->version); 
        }
        
        $related_data = $this->api->get_related_data_from_hub($current_post_url, $requesting_node_id, $count, $hub_url, $hub_api_user, $hub_api_pass);

        // Debug: Log the received data
        if (class_exists('Chiral_Connector_Utils')) {
            Chiral_Connector_Utils::log_message('Chiral Connector: Received related data: ' . print_r($related_data, true), 'debug');
        }

        if (is_wp_error($related_data)) {
            wp_send_json_error(array(
                'message' => $related_data->get_error_message(),
                'code' => $related_data->get_error_code(),
                // Send all data, could include response body from Hub for better debugging
                'data' => $related_data->get_error_data() 
            ));
        } elseif (empty($related_data)) {
            // wp_send_json_success expects an array for 'data' field when using wp_send_json_success($data);
            // So, to send an empty array of posts, it should be wp_send_json_success(array());
            wp_send_json_success(array()); 
        } else {
            // 如果启用缓存且成功获取到数据，将数据存储到缓存中（12小时有效期）
            if ($enable_cache && !empty($related_data)) {
                set_transient($cache_key, $related_data, 12 * HOUR_IN_SECONDS);
                if (class_exists('Chiral_Connector_Utils')) {
                    Chiral_Connector_Utils::log_message('Chiral Connector: Cached related data for: ' . $current_post_url, 'debug');
                }
            }
            
            wp_send_json_success($related_data); // $related_data should be an array of posts
        }
        // WordPress AJAX handlers should die or exit after sending JSON response.
        // wp_send_json_success and wp_send_json_error handle this automatically.
    }

    /**
     * Renders the related posts list using the partial template.
     * This function would be called by JavaScript if it fetches data and then asks PHP to render the template,
     * or if PHP itself fetches the data (e.g. for shortcode, or non-AJAX display).
     *
     * @param array $related_posts Array of related post data from the hub.
     * @return string HTML of the related posts list.
     */
    public static function render_related_posts_html( $related_posts_data ) {
        if ( empty( $related_posts_data ) ) {
            return '<p>' . esc_html__( 'No related Chiral data found.', 'chiral-connector' ) . '</p>';
        }

        ob_start();
        // Make $related_posts_data available to the template
        // Note: The variable name in the template file must match 'related_posts_data'
        include CHIRAL_CONNECTOR_PLUGIN_DIR . 'public/partials/related-posts-display.php';
        return ob_get_clean();
    }
}