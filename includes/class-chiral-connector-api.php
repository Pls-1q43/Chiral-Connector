<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch
/**
 * Handles all API communication with the Chiral Hub.
 * 
 * 重要提醒：WordPress.com 相关文章API配置说明
 * =============================================
 * 
 * WordPress.com的相关文章API (/sites/$site/posts/$post/related) 默认行为：
 * - 如果不指定algorithm参数，API只会返回同一作者的文章
 * - 这会导致相关文章推荐范围过于狭窄
 * 
 * 解决方案：
 * - 必须设置 filter[terms][post_type] 来指定要搜索的文章类型
 * - 建议设置 pretty: true 便于调试API响应
 * 
 * 这些配置已在 get_related_post_ids_from_wp_api() 方法中正确设置。
 * 未来的开发者请勿移除这些参数，否则会回到只推荐同作者文章的问题。
 *
 * @package    Chiral_Connector
 * @subpackage Chiral_Connector/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Connector_Api {

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
     * Constructor.
     *
     * @since 1.0.0
     * @param string $plugin_name The plugin name.
     * @param string $version The plugin version.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;
    }

    /**
     * Send data to the Chiral Hub.
     *
     * @since 1.0.0
     * @param array  $post_data    The data to send.
     * @param string $hub_url      The URL of the Chiral Hub.
     * @param string $username     The username for hub authentication.
     * @param string $app_password The application password for hub authentication.
     * @param int|null $hub_cpt_id Optional. The ID of the CPT on the hub if updating.
     * @return array|WP_Error The response from the hub or WP_Error on failure.
     */
    public function send_data_to_hub( $post_data, $hub_url, $username, $app_password, $hub_cpt_id = null ) {
        $endpoint = rtrim( $hub_url, '/' ) . '/wp-json/wp/v2/chiral_data';
        $method   = 'POST';

        if ( ! empty( $hub_cpt_id ) ) {
            $endpoint .= '/' . $hub_cpt_id;
            // WordPress REST API uses POST for updates too, or PUT. Let's stick to POST as per doc for simplicity.
        }

        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $app_password ),
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $post_data ),
            'timeout' => 60, // seconds
        );

        $response = wp_remote_request( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code >= 200 && $response_code < 300 ) {
            return $data; // Success, return decoded JSON body (should contain hub_cpt_id)
        } else {
            $error_message = sprintf(
                /* translators: %1$s: Error message from Hub, %2$s: HTTP response code */
                __( 'Error communicating with Chiral Hub: %1$s (Code: %2$s)', 'chiral-connector' ),
                wp_remote_retrieve_response_message( $response ),
                $response_code
            );
            $error_details = array(
                'status' => $response_code,
                'response_message' => wp_remote_retrieve_response_message( $response ),
                'response_body' => $data, // This is the JSON decoded body
                'response_headers' => wp_remote_retrieve_headers( $response )->getAll() // Get all headers as an array
            );

            // Log the detailed error
            if (class_exists('Chiral_Connector_Utils')) {
                Chiral_Connector_Utils::log_message($error_message . ' Details: ' . print_r($error_details, true), 'error');
            }

            return new WP_Error(
                'hub_api_error',
                $error_message,
                $error_details
            );
        }
    }

    /**
     * Delete data from the Chiral Hub.
     *
     * @since 1.0.0
     * @param int    $hub_cpt_id   The ID of the CPT on the hub to delete.
     * @param string $hub_url      The URL of the Chiral Hub.
     * @param string $username     The username for hub authentication.
     * @param string $app_password The application password for hub authentication.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function delete_data_from_hub( $hub_cpt_id, $hub_url, $username, $app_password ) {
        $endpoint = rtrim( $hub_url, '/' ) . '/wp-json/wp/v2/chiral_data/' . $hub_cpt_id;

        $args = array(
            'method'  => 'DELETE',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $app_password ),
            ),
            'timeout' => 30,
        );

        $response = wp_remote_request( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code === 200 || $response_code === 204 ) { // 204 No Content is also a success for DELETE
            return true;
        } else {
             $body = wp_remote_retrieve_body( $response );
             $data = json_decode( $body, true );
            return new WP_Error(
                'hub_api_delete_error',
                /* translators: %1$s: Error message from Hub, %2$s: HTTP response code */
                sprintf( __( 'Error deleting data from Chiral Hub: %1$s (Code: %2$s)', 'chiral-connector' ), wp_remote_retrieve_response_message( $response ), $response_code ),
                array( 'status' => $response_code, 'response_body' => $data )
            );
        }
    }

    /**
     * Get related data from the Chiral Hub.
     *
     * @since 1.0.0
     * @param string $current_post_url The URL of the current post.
     * @param string $node_id          The ID of the current node.
     * @param int    $count            The number of related items to fetch.
     * @param string $hub_url_param    Optional. The URL of the Chiral Hub.
     * @param string $username         Optional. The username for hub authentication if API requires it.
     * @param string $app_password     Optional. The application password for hub authentication if API requires it.
     * @return array|WP_Error The related data or WP_Error on failure.
     */
    public function get_related_data_from_hub( $current_post_url, $node_id, $count, $hub_url_param = '', $username = '', $app_password = '' ) {
        // $hub_url_param, $username, and $app_password from params are now ignored for fetching related data.
        // We will use configured Hub URL for the domain and expect the Hub CPT ID to be available.

        // Get Hub URL from settings
        $options = get_option('chiral_connector_settings');
        $configured_hub_url = isset($options['hub_url']) ? $options['hub_url'] : '';

        if ( empty( $configured_hub_url ) ) {
            if (class_exists('Chiral_Connector_Utils')) {
                Chiral_Connector_Utils::log_message('Hub URL is not configured. Cannot fetch related posts via WP API.', 'error');
            }
            return new WP_Error('hub_url_not_configured', __('Chiral Hub URL is not configured in settings.', 'chiral-connector'));
        }

        $parsed_hub_url = wp_parse_url( $configured_hub_url );
        if ( ! $parsed_hub_url || empty( $parsed_hub_url['host'] ) ) {
            if (class_exists('Chiral_Connector_Utils')) {
                Chiral_Connector_Utils::log_message('Could not parse Hub URL to get host: ' . $configured_hub_url, 'error');
            }
            return new WP_Error('hub_url_parse_error', __('Could not parse the configured Chiral Hub URL.', 'chiral-connector'));
        }
        $hub_domain = $parsed_hub_url['host'];

        // Get the current post ID on the Node site
        global $post;
        $current_node_post_id = 0;
        if (is_singular() && isset($post->ID)) {
            $current_node_post_id = $post->ID;
        } else {
            $post_id_from_url = url_to_postid($current_post_url); // $current_post_url is passed by the AJAX call
            if ($post_id_from_url) {
                $current_node_post_id = $post_id_from_url;
            }
        }

        if ( ! $current_node_post_id ) {
            if (class_exists('Chiral_Connector_Utils')) {
                Chiral_Connector_Utils::log_message('Could not determine the current Node post ID.', 'error');
            }
            return new WP_Error('missing_node_post_id', __('Could not determine the current post ID on this site.', 'chiral-connector'));
        }

        // Get the Hub CPT ID for the current Node post
        $hub_cpt_id = get_post_meta( $current_node_post_id, '_chiral_hub_cpt_id', true );
        if ( empty( $hub_cpt_id ) ) {
            if (class_exists('Chiral_Connector_Utils')) {
                Chiral_Connector_Utils::log_message('Missing _chiral_hub_cpt_id for Node post ID: ' . $current_node_post_id . '. Cannot fetch related posts from Hub via WP API.', 'warning');
            }
            return array(); // No CPT ID, so no way to find related posts for it on the Hub via this method.
        }

        // Now, $hub_domain is the '$site' and $hub_cpt_id is the '$post' for the /related API call against the Hub.
        $related_hub_post_ids_data = $this->get_related_post_ids_from_wp_api( $hub_domain, (int)$hub_cpt_id, $count );

        // Keep this log to see what get_related_post_ids_from_wp_api returned
        if (class_exists('Chiral_Connector_Utils')) {
            Chiral_Connector_Utils::log_message('[DEBUG CC API] Data received by get_related_data_from_hub from get_related_post_ids_from_wp_api: ' . print_r($related_hub_post_ids_data, true), 'debug');
        }

        if ( is_wp_error( $related_hub_post_ids_data ) ) {
            return $related_hub_post_ids_data;
        }

        if ( empty( $related_hub_post_ids_data['results'] ) ) {
            // error_log('[DEBUG CC API] get_related_data_from_hub: $related_hub_post_ids_data[results] IS EMPTY or not set after check.'); // Reduced
            return array(); 
        }
        // error_log('[DEBUG CC API] get_related_data_from_hub: $related_hub_post_ids_data[results] IS NOT EMPTY. Processing items: ' . print_r($related_hub_post_ids_data['results'], true)); // Reduced

        $related_posts_details = array();
        foreach ( $related_hub_post_ids_data['results'] as $related_item ) {
            // error_log('[DEBUG CC API] Processing related_item: ' . print_r($related_item, true)); // Reduced

            if ( isset( $related_item['fields']['post_id'] ) ) {
                $hub_related_post_id = $related_item['fields']['post_id'];
                if (class_exists('Chiral_Connector_Utils')) {
                    Chiral_Connector_Utils::log_message('[DEBUG CC API] Fetching details for Hub Post ID: ' . $hub_related_post_id . ' from Hub Domain: ' . $hub_domain, 'debug');
                }

                $post_details = $this->get_post_details_from_wp_api( $hub_domain, $hub_related_post_id );
                // error_log('[DEBUG CC API] Details for Hub Post ID ' . $hub_related_post_id . ': ' . print_r($post_details, true)); // Reduced, too verbose for general case

                if ( ! is_wp_error( $post_details ) && ! empty( $post_details ) ) {
                    $final_url = isset($post_details['URL']) ? $post_details['URL'] : '#'; // Default to Hub URL
                    $source_url_from_meta_array = '';

                    // 备用方案：优先检查 other_URLs 字段
                    if ( isset( $post_details['metadata'] ) && is_array( $post_details['metadata'] ) ) {
                        foreach ( $post_details['metadata'] as $meta_item ) {
                            $current_key = '';
                            $current_value = '';
                            if ( is_object( $meta_item ) && isset( $meta_item->key ) ) {
                                $current_key = $meta_item->key;
                                $current_value = isset( $meta_item->value ) ? $meta_item->value : '';
                            } elseif ( is_array( $meta_item ) && isset( $meta_item['key'] ) ) {
                                $current_key = $meta_item['key'];
                                $current_value = isset( $meta_item['value'] ) ? $meta_item['value'] : '';
                            }

                            // 备用方案：优先检查 other_URLs
                            if ( 'other_URLs' === $current_key && !empty( $current_value ) ) {
                                $other_urls_data = json_decode( $current_value, true );
                                if ( is_array( $other_urls_data ) && isset( $other_urls_data['source'] ) && !empty( $other_urls_data['source'] ) ) {
                                    $source_url_from_meta_array = $other_urls_data['source'];
                                    break;
                                }
                            }
                            
                            // 如果 other_URLs 没有找到，继续检查 chiral_source_url
                            if ( empty( $source_url_from_meta_array ) && 'chiral_source_url' === $current_key && !empty( $current_value ) ) {
                                $source_url_from_meta_array = $current_value;
                            }
                        }
                    }

                    // 如果在 metadata 数组中没有找到，检查标准 WP REST API v2 'meta' 字段作为备用
                    $source_url_from_meta_object = '';
                    if ( empty( $source_url_from_meta_array ) && isset( $post_details['meta'] ) && is_array( $post_details['meta'] ) ) {
                        // 优先检查 other_URLs
                        if ( !empty( $post_details['meta']['other_URLs'] ) ) {
                            $other_urls_data = json_decode( $post_details['meta']['other_URLs'], true );
                            if ( is_array( $other_urls_data ) && isset( $other_urls_data['source'] ) && !empty( $other_urls_data['source'] ) ) {
                                $source_url_from_meta_object = $other_urls_data['source'];
                            }
                        }
                        // 如果 other_URLs 没有找到，检查 chiral_source_url
                        if ( empty( $source_url_from_meta_object ) && !empty( $post_details['meta']['chiral_source_url'] ) ) {
                            $source_url_from_meta_object = $post_details['meta']['chiral_source_url'];
                        }
                    }
                    
                    // 确定最终使用的URL
                    if ( !empty( $source_url_from_meta_array ) && filter_var( $source_url_from_meta_array, FILTER_VALIDATE_URL) ) {
                        $final_url = $source_url_from_meta_array;
                    } elseif ( !empty( $source_url_from_meta_object ) && filter_var( $source_url_from_meta_object, FILTER_VALIDATE_URL) ) {
                        $final_url = $source_url_from_meta_object;
                    }
                    // 如果都没有找到有效的源URL，则使用默认的Hub URL

                    $network_name_from_meta = '';
                    // 从 post_details[metadata] 提取 chiral_network_name
                    if ( isset( $post_details['metadata'] ) && is_array( $post_details['metadata'] ) ) {
                        foreach ( $post_details['metadata'] as $meta_item ) {
                            $current_key = '';
                            $current_value = '';
                            if ( is_object( $meta_item ) && isset( $meta_item->key ) ) {
                                $current_key = $meta_item->key;
                                $current_value = isset( $meta_item->value ) ? $meta_item->value : '';
                            } elseif ( is_array( $meta_item ) && isset( $meta_item['key'] ) ) {
                                $current_key = $meta_item['key'];
                                $current_value = isset( $meta_item['value'] ) ? $meta_item['value'] : '';
                            }

                            if ( 'chiral_network_name' === $current_key && !empty( $current_value ) ) {
                                $network_name_from_meta = $current_value;
                                break; 
                            }
                        }
                    }
                    // 备用方案：如果 metadata 中没有，尝试从 post_details[meta][chiral_network_name] (标准 WP REST API 格式)
                    if ( empty( $network_name_from_meta ) && isset( $post_details['meta'] ) && is_array( $post_details['meta'] ) && !empty( $post_details['meta']['chiral_network_name'] ) ) {
                         $network_name_from_meta = $post_details['meta']['chiral_network_name'];
                    }

                    $related_posts_details[] = array(
                        'title' => isset($post_details['title']) ? $post_details['title'] : __('Untitled', 'chiral-connector'),
                        'url' => $final_url, // 使用确定的最终URL
                        'excerpt' => isset($post_details['excerpt']) ? $post_details['excerpt'] : '',
                        'featured_image_url' => isset($post_details['featured_image']) ? $post_details['featured_image'] : '',
                        'original_post_id' => $hub_related_post_id, 
                        'hub_url' => isset($post_details['URL']) ? $post_details['URL'] : '#', 
                        'author_name' => isset($post_details['author']['name']) ? $post_details['author']['name'] : (isset($post_details['author']['login']) ? $post_details['author']['login'] : 'N/A'),
                        'source_type' => 'hub_wordpress_api',
                        'network_name' => $network_name_from_meta, // 添加 network_name
                    );
                } else {
                    // error_log('[DEBUG CC API] Failed to get details or details empty for Hub Post ID: ' . $hub_related_post_id . '. Error: ' . (is_wp_error($post_details) ? $post_details->get_error_message() : 'Empty response'));
                    if (class_exists('Chiral_Connector_Utils')) {
                        Chiral_Connector_Utils::log_message('[DEBUG CC API] Failed to get details or details empty for Hub Post ID: ' . $hub_related_post_id . '. Error: ' . (is_wp_error($post_details) ? $post_details->get_error_message() : 'Empty response'), 'debug');
                    }
                }
            } else {
                 // error_log('[DEBUG CC API] related_item does not have fields[post_id]: ' . print_r($related_item, true)); // Keep
                 if (class_exists('Chiral_Connector_Utils')) {
                    Chiral_Connector_Utils::log_message('[DEBUG CC API] related_item does not have fields[post_id]: ' . print_r($related_item, true), 'debug');
                 }
            }
        }
        // error_log('[DEBUG CC API] Final $related_posts_details to be returned by get_related_data_from_hub: ' . print_r($related_posts_details, true)); // Keep
        if (class_exists('Chiral_Connector_Utils')) {
            Chiral_Connector_Utils::log_message('[DEBUG CC API] Final $related_posts_details to be returned by get_related_data_from_hub: ' . print_r($related_posts_details, true), 'debug');
        }
        return $related_posts_details;
    }

    /**
     * Get related post IDs from WordPress.com API.
     * POST /sites/$site/posts/$post/related
     * 
     * 重要说明：WordPress.com 相关文章API默认只返回同作者的文章。
     * 为了获得更广泛的相关文章，必须设置以下参数：
     * - algorithm: 'semantic' (使用语义算法而非默认的同作者算法)
     * - filter[terms][post_type]: 指定要搜索的文章类型
     * - pretty: true (格式化输出，便于调试)
     * 
     * 这些参数确保API返回基于内容相似性的文章，而不仅仅是同一作者的文章。
     *
     * @since 1.0.0
     * @param string $site_identifier Site ID or domain.
     * @param int    $post_id         The post ID.
     * @param int    $size            Number of results to return.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function get_related_post_ids_from_wp_api( $site_identifier, $post_id, $size = 5 ) {
        $api_url = sprintf(
            'https://public-api.wordpress.com/rest/v1.1/sites/%s/posts/%d/related',
            rawurlencode( $site_identifier ), // Site identifier can be a domain, needs to be URL encoded.
            $post_id
        );

        $args = array(
            'method'  => 'POST', // As per WP API documentation
            'timeout' => 30,
            'body'    => array( // Request parameters
                'size' => $size,
                'pretty' => true, // 格式化输出，便于调试
                'filter' => array(
                    'terms' => array(
                        // 指定要搜索的文章类型，包括普通文章和chiral_data类型
                        'post_type' => array('post', 'chiral_data') 
                    )
                ),
            ),
            // This API does not require authentication for public sites/posts.
        );
        
        // error_log('[DEBUG CC API] Preparing to call WP API /related.'); // Reduced
        if (class_exists('Chiral_Connector_Utils')) {
            Chiral_Connector_Utils::log_message('[DEBUG CC API] Request URL: ' . $api_url, 'debug');
            Chiral_Connector_Utils::log_message('[DEBUG CC API] Request ARGS: ' . print_r($args, true), 'debug');
        }

        $response = wp_remote_post( $api_url, $args ); // Using wp_remote_post for clarity

        // error_log('[DEBUG CC API] Response from WP API /related (raw object): ' . print_r($response, true)); // Reduced, too verbose

        $body = wp_remote_retrieve_body( $response );
        // error_log('[DEBUG CC API] Response BODY from WP API /related: ' . $body); // Reduced, $data is more useful

        $data = json_decode( $body, true );
        if (class_exists('Chiral_Connector_Utils')) {
            Chiral_Connector_Utils::log_message('[DEBUG CC API] JSON DECODED data from WP API /related: ' . print_r($data, true), 'debug');
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            // error_log('[DEBUG CC API] JSON Decode Error: ' . json_last_error_msg()); // Keep
            if (class_exists('Chiral_Connector_Utils')) {
                Chiral_Connector_Utils::log_message('[DEBUG CC API] JSON Decode Error: ' . json_last_error_msg(), 'debug');
            }
        }

        $response_code = wp_remote_retrieve_response_code( $response );

        // Check for a successful HTTP response code first
        if ( $response_code >= 200 && $response_code < 300 ) {
            // The API docs say 'results', but logs show 'hits'. Let's check for 'hits' and ensure it's an array.
            // Also, an empty 'hits' array is a valid success response meaning no related items were found.
            if ( isset( $data['hits'] ) && is_array( $data['hits'] ) ) {
                // Return the entire $data array as it might contain other useful info like 'total', 'max_score'
                // The calling function get_related_data_from_hub expects a structure with a key containing the items.
                // We will adapt $data to look like the previous structure $related_post_ids_data['results']
                return array(
                    'results' => $data['hits'], // Map 'hits' to 'results' for consistency with downstream code
                    'total'   => isset($data['total']) ? $data['total'] : count($data['hits']),
                    'max_score' => isset($data['max_score']) ? $data['max_score'] : null,
                );
            } else {
                // Response is 2xx but doesn't contain the expected 'hits' array or it's not an array.
                // This could be an unexpected success response format.
                $error_message = __( 'WordPress API returned a 2xx response for related posts, but the format was unexpected.', 'chiral-connector' );
                $error_data = array( 'status' => $response_code, 'response_body' => $data, 'api_url' => $api_url, 'request_args' => $args );
                if (class_exists('Chiral_Connector_Utils')) {
                    Chiral_Connector_Utils::log_message($error_message . ' Details: ' . print_r($error_data, true), 'warning');
                }
                // Treat as no results found for now, but log it.
                return array('results' => array(), 'total' => 0); 
            }
        } else {
            $error_message = sprintf(
                /* translators: %1$s: Error message from WordPress API, %2$s: HTTP response code */
                __( 'Error fetching related post IDs from WordPress API: %1$s (Code: %2$s)', 'chiral-connector' ),
                wp_remote_retrieve_response_message( $response ),
                $response_code
            );
            $error_data = array( 'status' => $response_code, 'response_body' => $data, 'api_url' => $api_url, 'request_args' => $args );
            if (class_exists('Chiral_Connector_Utils')) {
                Chiral_Connector_Utils::log_message($error_message . ' Details: ' . print_r($error_data, true), 'error');
            }
            return new WP_Error( 'wp_api_related_ids_error', $error_message, $error_data );
        }
    }

    /**
     * Get single post details from WordPress.com API.
     * GET /sites/$site/posts/$post_ID
     *
     * @since 1.0.0
     * @param string $site_identifier Site ID or domain.
     * @param int    $post_id         The post ID.
     * @return array|WP_Error The API response (post object) or WP_Error on failure.
     */
    public function get_post_details_from_wp_api( $site_identifier, $post_id ) {
        $api_url = sprintf(
            'https://public-api.wordpress.com/rest/v1.1/sites/%s/posts/%d',
            rawurlencode( $site_identifier ),
            $post_id
        );

        // Specify fields to reduce response size and get only what's needed.
        // Common fields: ID, title, URL, excerpt, date, featured_image
        $query_args = array(
            'fields' => 'ID,title,URL,excerpt,date,featured_image,tags,categories,author,metadata', // Added author and metadata
        );
        $api_url_with_fields = add_query_arg( $query_args, $api_url );

        $args = array(
            'method'  => 'GET',
            'timeout' => 30,
            // This API does not require authentication for public sites/posts.
        );

        $response = wp_remote_get( $api_url_with_fields, $args );

        if ( is_wp_error( $response ) ) {
            if (class_exists('Chiral_Connector_Utils')) {
                Chiral_Connector_Utils::log_message('WP API (post_details) error: ' . $response->get_error_message(), 'error');
            }
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code >= 200 && $response_code < 300 && ! empty( $data ) ) {
            return $data; // Expected: a post object/array
        } else {
             $error_message = sprintf(
                /* translators: %1$s: Error message from WordPress API, %2$s: HTTP response code */
                __( 'Error fetching post details from WordPress API: %1$s (Code: %2$s)', 'chiral-connector' ),
                wp_remote_retrieve_response_message( $response ),
                $response_code
            );
            $error_data = array( 'status' => $response_code, 'response_body' => $data, 'api_url' => $api_url_with_fields, 'request_args' => $args );
            if (class_exists('Chiral_Connector_Utils')) {
                Chiral_Connector_Utils::log_message($error_message . ' Details: ' . print_r($error_data, true), 'error');
            }
            return new WP_Error( 'wp_api_post_details_error', $error_message, $error_data);
        }
    }

    /**
     * Test the connection to the Chiral Hub.
     *
     * @since 1.0.0
     * @param string $hub_url      The URL of the Chiral Hub.
     * @param string $username     The username for hub authentication.
     * @param string $app_password The application password for hub authentication.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function test_hub_connection( $hub_url, $username, $app_password ) {
        // Example endpoint: /chiral-network/v1/ping
        $endpoint = rtrim( $hub_url, '/' ) . '/wp-json/chiral-network/v1/ping'; // Adjusted to include /wp-json

        $args = array(
            'method'  => 'GET', // Or POST, depending on how the ping endpoint is implemented
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $app_password ),
            ),
            'timeout' => 15,
        );

        $response = wp_remote_request( $endpoint, $args ); // Using wp_remote_request for flexibility if ping is POST

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );

        // Assuming a 2xx response code means success for ping
        if ( $response_code >= 200 && $response_code < 300 ) {
            // Optionally, check the response body for a specific success message if the ping endpoint returns one.
            // $body = wp_remote_retrieve_body( $response );
            // $data = json_decode( $body, true );
            // if ( isset( $data['status'] ) && $data['status'] === 'success' ) return true;
            return true;
        } else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            return new WP_Error(
                'hub_api_ping_error',
                /* translators: %1$s: Error message from Hub, %2$s: HTTP response code */
                sprintf( __( 'Failed to connect to Chiral Hub: %1$s (Code: %2$s)', 'chiral-connector' ), wp_remote_retrieve_response_message( $response ), $response_code ),
                array( 'status' => $response_code, 'response_body' => $data )
            );
        }
    }

    /**
     * Get all Chiral Data post IDs for the current node from the Chiral Hub.
     *
     * @since 1.1.0
     * @param string $hub_url        The URL of the Chiral Hub.
     * @param string $username       The username for hub authentication.
     * @param string $app_password   The application password for hub authentication.
     * @param int    $user_id_on_hub The user ID of this node on the Hub.
     * @return array|WP_Error An array of post IDs on success, WP_Error on failure.
     */
    public function get_all_node_data_ids_from_hub( $hub_url, $username, $app_password, $user_id_on_hub ) {
        $all_ids = array();
        $page    = 1;
        $per_page = 100; // Max per_page for WP REST API is typically 100

        if ( empty( $user_id_on_hub ) ) {
            return new WP_Error( 'missing_user_id', __( 'User ID on Hub is required to fetch its data.', 'chiral-connector' ) );
        }

        $endpoint = rtrim( $hub_url, '/' ) . '/wp-json/wp/v2/chiral_data';

        while ( true ) {
            $request_args = array(
                'author'   => $user_id_on_hub,
                'per_page' => $per_page,
                'page'     => $page,
                '_fields'  => 'id', // Only fetch post IDs
                'status'   => 'any', // Fetch posts with any status, as they might be in trash on hub
            );
            $paginated_endpoint = add_query_arg( $request_args, $endpoint );

            $args = array(
                'method'  => 'GET',
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $username . ':' . $app_password ),
                ),
                'timeout' => 30,
            );

            $response = wp_remote_request( $paginated_endpoint, $args );

            if ( is_wp_error( $response ) ) {
                // Log the error if possible
                if ( class_exists( 'Chiral_Connector_Utils' ) ) {
                    Chiral_Connector_Utils::log_message( 'API error fetching node data IDs: ' . $response->get_error_message(), 'error' );
                }
                return $response; // Return the WP_Error
            }

            $body = wp_remote_retrieve_body( $response );
            $posts = json_decode( $body, true );
            $response_code = wp_remote_retrieve_response_code( $response );

            if ( $response_code < 200 || $response_code >= 300 ) {
                $error_message = sprintf(
                    /* translators: %1$s: Error message from Hub, %2$s: HTTP response code */
                    __( 'Error fetching data IDs from Chiral Hub: %1$s (Code: %2$s)', 'chiral-connector' ),
                    wp_remote_retrieve_response_message( $response ),
                    $response_code
                );
                if ( class_exists( 'Chiral_Connector_Utils' ) ) {
                    Chiral_Connector_Utils::log_message( $error_message . ' Body: ' . $body, 'error' );
                }
                return new WP_Error( 'hub_api_fetch_ids_error', $error_message, array( 'status' => $response_code, 'response_body' => $posts ) );
            }

            if ( empty( $posts ) ) {
                break; // No more posts found
            }

            foreach ( $posts as $post ) {
                if ( isset( $post['id'] ) ) {
                    $all_ids[] = $post['id'];
                }
            }

            // If the number of posts returned is less than per_page, it's the last page
            if ( count( $posts ) < $per_page ) {
                break;
            }

            $page++;
        }

        return $all_ids;
    }
}