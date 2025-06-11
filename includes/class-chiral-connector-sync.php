<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch
/**
 * Handles content synchronization with the Chiral Hub.
 *
 * @package    Chiral_Connector
 * @subpackage Chiral_Connector/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Connector_Sync {

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
        // It's good practice to inject dependencies or get them from a service locator/container.
        // For simplicity, we might instantiate it here or expect it to be passed if Chiral_Connector_Core manages instances.
        // $this->api = new Chiral_Connector_Api( $this->plugin_name, $this->version ); 
        // Let's assume Chiral_Connector_Core will pass the loader, and we add hooks there.
        // Or, we add hooks directly here using add_action/add_filter.

        $this->load_dependencies();
        $this->define_hooks();
    }

    /**
     * Load dependencies.
     * For now, mainly the API class if not already loaded globally or injected.
     */
    private function load_dependencies() {
        // Ensure API class is available
        if ( ! class_exists( 'Chiral_Connector_Api' ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'class-chiral-connector-api.php';
        }
        // Instantiate API client - this assumes API class doesn't have complex dependencies itself for constructor
        // Or better, get it from the main plugin class if it's already instantiated there.
        // For now, direct instantiation for simplicity, though dependency injection is preferred.
        $this->api = new Chiral_Connector_Api($this->plugin_name, $this->version);
    }

    /**
     * Define WordPress hooks for synchronization.
     */
    private function define_hooks() {
        add_action( 'publish_post', array( $this, 'sync_on_publish_post' ), 10, 2 );
        add_action( 'save_post', array( $this, 'sync_on_save_post' ), 10, 3 ); // For updates to already published posts
        add_action( 'wp_trash_post', array( $this, 'sync_on_trash_post' ), 10, 1 );
        add_action( 'delete_post', array( $this, 'sync_on_delete_post' ), 10, 1 );

        // Hook for batch sync, to be called from admin class
        add_action( 'chiral_connector_batch_sync_posts', array( $this, 'batch_sync_posts' ) );
    }

    /**
     * Handles post synchronization when a post is published.
     *
     * @since 1.0.0
     * @param int     $post_id The ID of the post.
     * @param WP_Post $post    The post object.
     */
    public function sync_on_publish_post( $post_id, $post ) {
        // Check if this is an auto-save or revision
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        // Check post type if we only want to sync 'post' or specific CPTs
        if ( $post->post_type !== 'post' ) { // Example: only sync 'post' type
            return;
        }
        // Check if post status is 'publish'
        if ( $post->post_status !== 'publish' ) {
            return;
        }
        $this->sync_post_to_hub( $post_id );
    }

    /**
     * Handles post synchronization when an existing post is saved (updated).
     *
     * @since 1.0.0
     * @param int     $post_id The ID of the post.
     * @param WP_Post $post    The post object.
     * @param bool    $update  Whether this is an existing post being updated or not.
     */
    public function sync_on_save_post( $post_id, $post, $update ) {
        if ( ! $update ) { // Only proceed if this is an update to an existing post
            return;
        }
        // Check if this is an auto-save or revision
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        // Check post type
        if ( $post->post_type !== 'post' ) { // Example: only sync 'post' type
            return;
        }
        // Check if post status is 'publish' (we only care about published posts for sync)
        if ( $post->post_status !== 'publish' ) {
            // If a published post is updated to a non-published status (e.g., draft), consider deleting from hub?
            // For now, only sync updates to published posts.
            return;
        }
        $this->sync_post_to_hub( $post_id );
    }

    /**
     * Sync a single post to the Chiral Hub.
     *
     * @since 1.0.0
     * @param int $post_id The ID of the post to sync.
     */
    private function sync_post_to_hub( $post_id ) {
        // Check if we're in Hub mode - skip sync if true
        global $chiral_connector_core;
        if ( isset( $chiral_connector_core ) && method_exists( $chiral_connector_core, 'is_hub_mode' ) && $chiral_connector_core->is_hub_mode() ) {
            if (class_exists('Chiral_Connector_Utils')) {
                Chiral_Connector_Utils::log_message('Hub mode detected - skipping sync for post ID: ' . $post_id, 'debug');
            }
            return;
        }
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        // Check the 'Send to Chiral?' option
        $send_to_hub = get_post_meta( $post_id, '_chiral_send_to_hub', true );
        // If meta is not set (e.g. for a brand new post or plugin just activated),
        // default behavior is to send (as checkbox is default checked).
        // So, only stop if explicitly set to 'no'.
        if ( $send_to_hub === 'no' ) {
            Chiral_Connector_Utils::log_message( 'Post ID: ' . $post_id . ' is marked not to send to Hub. Skipping sync.' );
            return;
        }

        // Get hub connection details from settings
        $options = get_option( 'chiral_connector_settings' );
        $hub_url = isset( $options['hub_url'] ) ? $options['hub_url'] : '';
        $username = isset( $options['hub_username'] ) ? $options['hub_username'] : '';
        $app_password = isset( $options['hub_app_password'] ) ? $options['hub_app_password'] : '';
        $node_id = isset( $options['node_id'] ) ? $options['node_id'] : ''; // Current node ID

        if ( empty( $hub_url ) || empty( $username ) || empty( $app_password ) || empty( $node_id ) ) {
            // Log error: Hub connection details not configured
            Chiral_Connector_Utils::log_message( 'Hub connection details not configured. Cannot sync post ID: ' . $post_id );
            return;
        }

        // Prepare data according to hub API requirements
        $post_data = array(
            'title'   => $post->post_title,
            'content' => $post->post_content, // Or apply_filters('the_content', $post->post_content) if needed
            'excerpt' => $post->post_excerpt, // Use original excerpt without modification
            'date_gmt' => gmdate( 'Y-m-d\TH:i:s', strtotime( $post->post_date_gmt ) ), // Format to ISO 8601 for top-level field
            'meta'    => array(
                'chiral_source_url'                 => get_permalink( $post_id ),
                '_chiral_data_original_post_id'         => (string) $post_id, // Ensure it's a string
                '_chiral_node_id'                       => $node_id, // ID of this node - MODIFIED KEY
                '_chiral_data_original_title'           => $post->post_title, // Add original title
                '_chiral_data_original_categories'      => wp_json_encode( wp_get_post_categories( $post_id, array( 'fields' => 'names' ) ) ), // Add categories as JSON
                '_chiral_data_original_tags'            => wp_json_encode( wp_get_post_tags( $post_id, array( 'fields' => 'names' ) ) ), // Add tags as JSON
                '_chiral_data_original_featured_image_url' => get_the_post_thumbnail_url( $post_id, 'full' ) ?: '', // Ensure empty string if no thumbnail
                '_chiral_data_original_publish_date'    => gmdate( 'Y-m-d H:i:s', strtotime( $post->post_date_gmt ) ), // Format to Y-m-d H:i:s for this meta field - MODIFIED KEY
                // Add categories and tags
                // '_chiral_data_categories' => wp_get_post_categories( $post_id, array( 'fields' => 'names' ) ),
                // '_chiral_data_tags'       => wp_get_post_tags( $post_id, array( 'fields' => 'names' ) ),
            )
        );

        // Get existing hub CPT ID if this post was synced before
        $hub_cpt_id = get_post_meta( $post_id, '_chiral_hub_cpt_id', true );

        $response = $this->api->send_data_to_hub( $post_data, $hub_url, $username, $app_password, $hub_cpt_id );

        if ( is_wp_error( $response ) ) {
            $error_data = $response->get_error_data();
            // Check if the error is 'rest_post_invalid_id' and we were attempting an update
            if ( ! empty( $hub_cpt_id ) &&
                 isset( $error_data['response_body']['code'] ) &&
                 $error_data['response_body']['code'] === 'rest_post_invalid_id' ) {

                Chiral_Connector_Utils::log_message( 'Hub CPT ID ' . $hub_cpt_id . ' for post ID ' . $post_id . ' is invalid. Attempting to sync as a new post.' );
                delete_post_meta( $post_id, '_chiral_hub_cpt_id' );

                // Retry sending as a new post (without hub_cpt_id)
                $retry_response = $this->api->send_data_to_hub( $post_data, $hub_url, $username, $app_password, null );

                if ( is_wp_error( $retry_response ) ) {
                    Chiral_Connector_Utils::log_message( 'Error re-syncing post ID ' . $post_id . ' as new to hub: ' . $retry_response->get_error_message() );
                    $this->schedule_retry_sync( $post_id, 'send' );
                } else {
                    if ( isset( $retry_response['id'] ) ) {
                        update_post_meta( $post_id, '_chiral_hub_cpt_id', $retry_response['id'] );
                        Chiral_Connector_Utils::log_message( 'Successfully re-synced post ID ' . $post_id . ' as new to hub. Hub CPT ID: ' . $retry_response['id'] );
                    } else {
                        Chiral_Connector_Utils::log_message( 'Re-synced post ID ' . $post_id . ' as new but hub response did not contain an ID. Response: ' . print_r($retry_response, true) );
                    }
                }
            } else {
                // Original error handling for other errors or if it was an initial creation attempt
                Chiral_Connector_Utils::log_message( 'Error syncing post ID ' . $post_id . ' to hub: ' . $response->get_error_message() . ' Details: ' . print_r($error_data, true) );
                $this->schedule_retry_sync( $post_id, 'send' );
            }
        } else {
            // Assuming response contains the hub_cpt_id
            if ( isset( $response['id'] ) ) {
                update_post_meta( $post_id, '_chiral_hub_cpt_id', $response['id'] );
                Chiral_Connector_Utils::log_message( 'Successfully synced post ID ' . $post_id . ' to hub. Hub CPT ID: ' . $response['id'] );
            } else {
                 Chiral_Connector_Utils::log_message( 'Synced post ID ' . $post_id . ' but hub response did not contain an ID. Response: ' . print_r($response, true) );
            }
        }
    }

    /**
     * Handles post deletion when a post is trashed.
     *
     * @since 1.0.0
     * @param int $post_id The ID of the post being trashed.
     */
    public function sync_on_trash_post( $post_id ) {
        // Check post type if necessary
        $post = get_post($post_id);
        if ($post && $post->post_type !== 'post') return;

        $this->delete_post_from_hub( $post_id );
    }

    /**
     * Handles post deletion when a post is permanently deleted.
     * This is called after wp_trash_post if trashing, or directly if force deleting.
     *
     * @since 1.0.0
     * @param int $post_id The ID of the post being deleted.
     */
    public function sync_on_delete_post( $post_id ) {
        // Check post type if necessary
        // Note: $post object might not be available here as it's already deleted.
        // We rely on the _chiral_hub_cpt_id meta if it exists.
        // If not, we can't do much. This hook is a bit tricky for post_type checking.
        // For simplicity, assume if it has _chiral_hub_cpt_id, it was a synced post.

        $this->delete_post_from_hub( $post_id );
    }

    /**
     * Delete a post from the Chiral Hub.
     *
     * @since 1.0.0
     * @param int $post_id The ID of the post to delete from the hub.
     */
    private function delete_post_from_hub( $post_id ) {
        // Check if we're in Hub mode - skip deletion if true
        global $chiral_connector_core;
        if ( isset( $chiral_connector_core ) && method_exists( $chiral_connector_core, 'is_hub_mode' ) && $chiral_connector_core->is_hub_mode() ) {
            if (class_exists('Chiral_Connector_Utils')) {
                Chiral_Connector_Utils::log_message('Hub mode detected - skipping delete for post ID: ' . $post_id, 'debug');
            }
            return;
        }
        
        // Get the hub CPT ID from post meta
        $hub_cpt_id = get_post_meta( $post_id, '_chiral_hub_cpt_id', true );
        if ( empty( $hub_cpt_id ) ) {
            // Log warning: No CPT ID found, nothing to delete
            Chiral_Connector_Utils::log_message( 'No Hub CPT ID found for post ID: ' . $post_id . '. Nothing to delete.' );
            return;
        }

        // Get hub connection details
        $options = get_option( 'chiral_connector_settings' );
        $hub_url = isset( $options['hub_url'] ) ? $options['hub_url'] : '';
        $username = isset( $options['hub_username'] ) ? $options['hub_username'] : '';
        $app_password = isset( $options['hub_app_password'] ) ? $options['hub_app_password'] : '';

        if ( empty( $hub_url ) || empty( $username ) || empty( $app_password ) ) {
            Chiral_Connector_Utils::log_message( 'Hub connection details not configured. Cannot delete post ID: ' . $post_id . ' (Hub ID: ' . $hub_cpt_id . ') from hub.' );
            return;
        }

        $response = $this->api->delete_data_from_hub( $hub_cpt_id, $hub_url, $username, $app_password );

        if ( is_wp_error( $response ) ) {
            Chiral_Connector_Utils::log_message( 'Error deleting post ID ' . $post_id . ' (Hub ID: ' . $hub_cpt_id . ') from hub: ' . $response->get_error_message() );
            // Implement retry logic for deletion?
            $this->schedule_retry_sync( $post_id, 'delete', $hub_cpt_id );
        } else {
            Chiral_Connector_Utils::log_message( 'Successfully deleted post ID ' . $post_id . ' (Hub ID: ' . $hub_cpt_id . ') from hub.' );
            // Remove the meta key as it's no longer relevant
            delete_post_meta( $post_id, '_chiral_hub_cpt_id' );
        }
    }

    /**
     * Schedule a retry for a failed sync operation using WordPress cron.
     *
     * @since 1.0.0
     * @param int    $post_id    The local post ID.
     * @param string $action     'send' or 'delete'.
     * @param int|null $hub_cpt_id Optional. The hub CPT ID, needed for delete retries.
     */
    private function schedule_retry_sync( $post_id, $action, $hub_cpt_id = null ) {
        // Simple retry: schedule a single event in 5 minutes.
        // A more robust system would track retry attempts and use exponential backoff.
        $retry_count = get_post_meta( $post_id, '_chiral_sync_retry_count', true );
        $retry_count = $retry_count ? (int) $retry_count : 0;

        if ($retry_count >= 3) { // Max 3 retries
            Chiral_Connector_Utils::log_message("Max retries reached for post ID {$post_id}, action {$action}.");
            delete_post_meta( $post_id, '_chiral_sync_retry_count');
            return;
        }

        $args = array( $post_id, $action, $hub_cpt_id, $retry_count + 1 );

        if ( ! wp_next_scheduled( 'chiral_connector_retry_sync_event', $args ) ) {
            wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, 'chiral_connector_retry_sync_event', $args );
            update_post_meta( $post_id, '_chiral_sync_retry_count', $retry_count + 1 );
            Chiral_Connector_Utils::log_message("Scheduled retry for post ID {$post_id}, action {$action}, attempt " . ($retry_count + 1) );
        }
    }

    /**
     * Handles the cron event for retrying sync.
     *
     * @since 1.0.0
     * @param int    $post_id    The local post ID.
     * @param string $action     'send' or 'delete'.
     * @param int|null $hub_cpt_id Optional. The hub CPT ID.
     * @param int    $attempt    The current retry attempt number.
     */
    public function handle_retry_sync_event( $post_id, $action, $hub_cpt_id = null, $attempt = 1 ) {
        Chiral_Connector_Utils::log_message("Executing retry for post ID {$post_id}, action {$action}, attempt {$attempt}");
        delete_post_meta( $post_id, '_chiral_sync_retry_count'); // Clear current attempt count before trying

        if ( 'send' === $action ) {
            $this->sync_post_to_hub( $post_id );
        } elseif ( 'delete' === $action && $hub_cpt_id ) {
            // Re-fetch connection details for the retry
            $options = get_option( 'chiral_connector_settings' );
            $hub_url = isset( $options['hub_url'] ) ? $options['hub_url'] : '';
            $username = isset( $options['hub_username'] ) ? $options['hub_username'] : '';
            $app_password = isset( $options['hub_app_password'] ) ? $options['hub_app_password'] : '';

            if ( empty( $hub_url ) || empty( $username ) || empty( $app_password ) ) {
                Chiral_Connector_Utils::log_message( 'Hub connection details not configured for retry. Cannot delete post ID: ' . $post_id );
                return;
            }
            $response = $this->api->delete_data_from_hub( $hub_cpt_id, $hub_url, $username, $app_password );
            if ( is_wp_error( $response ) ) {
                Chiral_Connector_Utils::log_message( 'Retry failed for deleting post ID ' . $post_id . ': ' . $response->get_error_message() );
                $this->schedule_retry_sync( $post_id, 'delete', $hub_cpt_id ); // Schedule again if failed
            } else {
                Chiral_Connector_Utils::log_message( 'Retry successful for deleting post ID ' . $post_id );
                delete_post_meta( $post_id, '_chiral_hub_cpt_id' );
            }
        }
    }

    /**
     * Batch sync all published posts to the hub.
     * This is typically triggered from an admin interface.
     */
    public function batch_sync_posts() {
        // Check if we're in Hub mode - batch sync is not needed in Hub mode
        global $chiral_connector_core;
        if ( isset( $chiral_connector_core ) && method_exists( $chiral_connector_core, 'is_hub_mode' ) && $chiral_connector_core->is_hub_mode() ) {
            Chiral_Connector_Utils::log_message( 'Batch sync skipped: Running in Hub mode. Synchronization is not needed on the Hub site itself.', 'info' );
            delete_transient('chiral_connector_batch_sync_running'); // Clean up the transient
            return;
        }
        
        // Ensure the transient is set at the beginning of the actual batch process.
        // This covers cases where cron might run after the initial transient from AJAX call has expired,
        // or if the process is triggered by other means.
        set_transient('chiral_connector_batch_sync_running', true, HOUR_IN_SECONDS);

        Chiral_Connector_Utils::log_message( 'Batch sync process started (actual execution via cron or direct call).' );
        $options = get_option( 'chiral_connector_settings' );
        // ... hub connection details ... as in the original method

        $posts_per_page = isset($options['batch_sync_posts_per_page']) ? (int)$options['batch_sync_posts_per_page'] : 20;
        if ($posts_per_page <= 0) {
            $posts_per_page = 20; // Default to 20 if invalid value
        }

        $paged = 1;
        $total_synced_all_pages = 0;
        $total_errors_all_pages = 0;
        $grand_total_processed_this_run = 0;
        $initial_total_posts_to_sync = 0; // Will be set by the first query

        do {
            $args = array(
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => $posts_per_page,
                'paged'          => $paged,
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'meta_query'     => array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_chiral_send_to_hub',
                        'value'   => 'yes',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_chiral_send_to_hub',
                        'compare' => 'NOT EXISTS',
                    )
                )
            );

            $query = new WP_Query( $args );

            if ( $paged === 1 ) {
                $initial_total_posts_to_sync = $query->found_posts;
                Chiral_Connector_Utils::log_message( 'Found ' . $initial_total_posts_to_sync . ' posts to potentially sync in batches.' );
            }
            
            $synced_this_page = 0;
            $errors_this_page = 0;

            if ( $query->have_posts() ) {
                if ($paged > 1) { // Log for subsequent pages
                    Chiral_Connector_Utils::log_message( 'Batch sync: Moving to page ' . $paged . ' of posts.' );
                }
                while ( $query->have_posts() ) {
                    $query->the_post();
                    $post_id = get_the_ID();

                    $send_to_hub_check = get_post_meta( $post_id, '_chiral_send_to_hub', true );
                    if ( $send_to_hub_check === 'no' ) {
                        Chiral_Connector_Utils::log_message( 'Batch sync: Post ID ' . $post_id . ' meta _chiral_send_to_hub is \'no\'. Skipping.' );
                        continue;
                    }

                    Chiral_Connector_Utils::log_message( 'Batch syncing post ID: ' . $post_id );
                    $current_post_obj = get_post( $post_id );
                    if ( ! $current_post_obj ) {
                        $errors_this_page++;
                        Chiral_Connector_Utils::log_message( 'Batch sync: Could not retrieve post object for ID ' . $post_id . '. Skipping.' );
                        continue;
                    }

                    $hub_url = isset( $options['hub_url'] ) ? $options['hub_url'] : '';
                    $username = isset( $options['hub_username'] ) ? $options['hub_username'] : '';
                    $app_password = isset( $options['hub_app_password'] ) ? $options['hub_app_password'] : '';
                    $node_id = isset( $options['node_id'] ) ? $options['node_id'] : '';

                    if ( empty( $hub_url ) || empty( $username ) || empty( $app_password ) || empty( $node_id ) ) {
                        Chiral_Connector_Utils::log_message( 'Batch sync: Hub connection details not configured. Skipping post ID: ' . $post_id );
                        $errors_this_page++;
                        // Consider if we should break the entire process if config is missing
                        // For now, it skips and attempts next post, which might also fail if config remains missing.
                        continue; 
                    }

                    $post_data = array(
                        'title'   => $current_post_obj->post_title,
                        'content' => $current_post_obj->post_content,
                        'excerpt' => $current_post_obj->post_excerpt, // Use original excerpt without modification
                        'date_gmt' => gmdate( 'Y-m-d\TH:i:s', strtotime( $current_post_obj->post_date_gmt ) ),
                        'meta'    => array(
                            'chiral_source_url'                 => get_permalink( $post_id ),
                            '_chiral_data_original_post_id'         => (string) $post_id,
                            '_chiral_node_id'                       => $node_id,
                            '_chiral_data_original_title'           => $current_post_obj->post_title,
                            '_chiral_data_original_categories'      => wp_json_encode( wp_get_post_categories( $post_id, array( 'fields' => 'names' ) ) ),
                            '_chiral_data_original_tags'            => wp_json_encode( wp_get_post_tags( $post_id, array( 'fields' => 'names' ) ) ),
                            '_chiral_data_original_featured_image_url' => get_the_post_thumbnail_url( $post_id, 'full' ) ?: '',
                            '_chiral_data_original_publish_date'    => gmdate( 'Y-m-d H:i:s', strtotime( $current_post_obj->post_date_gmt ) ),
                        )
                    );
                    $hub_cpt_id = get_post_meta( $post_id, '_chiral_hub_cpt_id', true );
                    $response = $this->api->send_data_to_hub( $post_data, $hub_url, $username, $app_password, $hub_cpt_id );

                    if ( is_wp_error( $response ) ) {
                        $error_data = $response->get_error_data();
                        if ( ! empty( $hub_cpt_id ) &&
                             isset( $error_data['response_body']['code'] ) &&
                             $error_data['response_body']['code'] === 'rest_post_invalid_id' ) {

                            Chiral_Connector_Utils::log_message( 'Batch sync: Hub CPT ID ' . $hub_cpt_id . ' for post ID ' . $post_id . ' is invalid. Attempting to sync as a new post.' );
                            delete_post_meta( $post_id, '_chiral_hub_cpt_id' );
                            
                            $retry_response = $this->api->send_data_to_hub( $post_data, $hub_url, $username, $app_password, null );

                            if ( is_wp_error( $retry_response ) ) {
                                Chiral_Connector_Utils::log_message( 'Batch sync: Error re-syncing post ID ' . $post_id . ' as new to hub: ' . $retry_response->get_error_message() . ' Details: ' . print_r($retry_response->get_error_data(), true) );
                                $errors_this_page++;
                            } else {
                                if ( isset( $retry_response['id'] ) ) {
                                    update_post_meta( $post_id, '_chiral_hub_cpt_id', $retry_response['id'] );
                                    Chiral_Connector_Utils::log_message( 'Batch sync: Successfully re-synced post ID ' . $post_id . ' as new to hub. Hub CPT ID: ' . $retry_response['id'] );
                                    $synced_this_page++;
                                } else {
                                    Chiral_Connector_Utils::log_message( 'Batch sync: Re-synced post ID ' . $post_id . ' as new but hub response did not contain an ID. Response: ' . print_r($retry_response, true) );
                                    $errors_this_page++;
                                }
                            }
                        } else {
                            Chiral_Connector_Utils::log_message( 'Batch sync: Error syncing post ID ' . $post_id . ' to hub: ' . $response->get_error_message() . ' Details: ' . print_r($error_data, true) );
                            $errors_this_page++;
                        }
                    } else {
                        if ( isset( $response['id'] ) ) {
                            update_post_meta( $post_id, '_chiral_hub_cpt_id', $response['id'] );
                            Chiral_Connector_Utils::log_message( 'Batch sync: Successfully synced post ID ' . $post_id . '. Hub ID: ' . $response['id'] );
                            $synced_this_page++;
                        } else {
                            Chiral_Connector_Utils::log_message( 'Batch sync: Synced post ID ' . $post_id . ' but hub response did not contain an ID. Response: ' . print_r($response, true));
                            $errors_this_page++;
                        }
                    }
                    // sleep(1); // Optional delay
                } // end while have_posts for current page
                wp_reset_postdata();

                Chiral_Connector_Utils::log_message( 
                    'Batch sync page ' . $paged . ' finished. Synced this page: ' . $synced_this_page . 
                    ', Errors this page: ' . $errors_this_page . '. Total processed this page: ' . $query->post_count . '.'
                );
                $total_synced_all_pages += $synced_this_page;
                $total_errors_all_pages += $errors_this_page;
                $grand_total_processed_this_run += $query->post_count;

                $paged++;
            } else {
                // No posts found for the current $paged, or $initial_total_posts_to_sync was 0
                if ($paged === 1 && $initial_total_posts_to_sync === 0) {
                     Chiral_Connector_Utils::log_message( 'No posts found to sync in batch mode.' );
                } else if ($paged > 1) {
                    // This means the previous page was the last one with posts
                    Chiral_Connector_Utils::log_message( 'Batch sync: No more posts on subsequent pages.' );
                }
                // Break the do...while loop
                break; 
            }
        } while (true); // Loop controlled by break statement when no more posts or WP_Query returns no posts

        Chiral_Connector_Utils::log_message( 
            'Batch sync process completed. Total Synced: ' . $total_synced_all_pages . 
            ', Total Errors: ' . $total_errors_all_pages . 
            '. Total posts processed: ' . $grand_total_processed_this_run . 
            ' (out of ' . $initial_total_posts_to_sync . ' initially matching criteria).'
        );

        // Delete the transient after the batch process is fully completed.
        delete_transient('chiral_connector_batch_sync_running');
        Chiral_Connector_Utils::log_message( 'Batch sync process finished and transient deleted.' );
    }
}

// The cron hook needs to be registered with the loader or globally
// add_action( 'chiral_connector_retry_sync_event', array( 'Chiral_Connector_Sync', 'handle_retry_sync_event_static_wrapper' ), 10, 4 );
// function chiral_connector_retry_sync_event_static_wrapper($post_id, $action, $hub_cpt_id = null, $attempt = 1) {
//    $sync = new Chiral_Connector_Sync( 'chiral-connector', CHIRAL_CONNECTOR_VERSION ); // Or get instance
//    $sync->handle_retry_sync_event($post_id, $action, $hub_cpt_id, $attempt);
// }
// This static wrapper is needed if the cron callback is not on an instantiated object's method.
// However, if Chiral_Connector_Core instantiates Chiral_Connector_Sync and adds its methods to the loader,
// the loader would handle this. For now, let's assume the hook for 'chiral_connector_retry_sync_event'
// will be added in Chiral_Connector_Core, pointing to an instance of Chiral_Connector_Sync->handle_retry_sync_event.

?>