<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

/**
 * Fired during plugin activation
 *
 * @link       https://www.chiral.com/
 * @since      1.0.0
 *
 * @package    Chiral_Connector
 * @subpackage Chiral_Connector/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Chiral_Connector
 * @subpackage Chiral_Connector/includes
 * @author     Chiral Software <support@chiral.com>
 */
class Chiral_Connector_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
        // Activation code here.
        // Example: Set default options, create custom tables, etc.

        // The Chiral Hub Core plugin check has been removed to allow Chiral Connector to operate independently.
        // It is now the user's responsibility to ensure a Chiral Hub is available and configured for the connector to function correctly.

        // Set default options if they don't exist
        $options = get_option( 'chiral_connector_settings' );
        if ( false === $options ) {
            $default_options = array(
                'hub_url' => '',
                'api_key' => '',
                'node_id' => Chiral_Connector_Utils::generate_node_id_from_url(),
                'sync_on_publish' => true,
                'sync_on_trash' => true,
                'batch_sync_posts_per_page' => 20,
                'display_related_posts' => true,
                'display_enable' => true,
                'related_posts_title' => esc_html__( 'Related Content', 'chiral-connector' ),
                'related_posts_count' => 5,
                'related_posts_placeholder_text' => esc_html__( 'Loading related posts...', 'chiral-connector' ),
                'enable_cache' => true,
            );
            update_option( 'chiral_connector_settings', $default_options );
        } else {
            // Ensure node_id is present if options already exist (e.g. from a previous version)
            if ( ! isset( $options['node_id'] ) || empty( $options['node_id'] ) ) {
                $options['node_id'] = Chiral_Connector_Utils::generate_node_id_from_url();
            }
            
            // 确保缓存设置存在，如果不存在则设置为默认值（开启）
            if ( ! isset( $options['enable_cache'] ) ) {
                $options['enable_cache'] = true;
            }
            
            // 确保相关文章显示设置存在，如果不存在则设置为默认值（开启）
            if ( ! isset( $options['display_enable'] ) ) {
                $options['display_enable'] = true;
            }
            
            update_option( 'chiral_connector_settings', $options );
        }

        // Schedule cron jobs if not already scheduled
        if ( ! wp_next_scheduled( 'chiral_connector_retry_failed_syncs' ) ) {
            wp_schedule_event( time(), 'hourly', 'chiral_connector_retry_failed_syncs' );
        }

        // Flush rewrite rules (optional, if you're adding custom post types or taxonomies that need it)
        // flush_rewrite_rules();

        Chiral_Connector_Utils::log_message( 'Chiral Connector plugin activated.' );
	}

}