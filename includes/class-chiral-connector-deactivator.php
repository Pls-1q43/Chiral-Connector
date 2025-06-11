<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://www.chiral.com/
 * @since      1.0.0
 *
 * @package    Chiral_Connector
 * @subpackage Chiral_Connector/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Chiral_Connector
 * @subpackage Chiral_Connector/includes
 * @author     Chiral Software <support@chiral.com>
 */
class Chiral_Connector_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
        // Deactivation code here.
        // Example: Remove options, clear scheduled cron jobs, etc.

        // Clear scheduled cron jobs
        wp_clear_scheduled_hook( 'chiral_connector_retry_failed_syncs' );

        // Example of conditional settings removal (can be kept or removed based on plugin philosophy)
        // $settings = get_option( 'chiral_connector_settings' );
        // if ( isset( $settings['remove_settings_on_deactivation'] ) && $settings['remove_settings_on_deactivation'] ) {
        //     self::clear_all_plugin_data(); 
        // }

        // Flush rewrite rules (optional, if the plugin added CPTs or taxonomies that are now removed)
        // flush_rewrite_rules();

        if (class_exists('Chiral_Connector_Utils')) {
            Chiral_Connector_Utils::log_message( 'Chiral Connector plugin deactivated.' );
        }
	}

	/**
	 * Clears all known plugin options and data.
	 * This is a more aggressive cleanup than the default deactivation might do.
	 *
	 * @since 1.1.0
	 */
	public static function clear_all_plugin_data() {
        delete_option( 'chiral_connector_settings' );
        delete_option( 'chiral_connector_failed_sync_queue' );
        // Add any other plugin-specific options that need to be cleaned up.
        // For example, if the plugin stored version info or transient data separately:
        // delete_option( 'chiral_connector_version' );
        // delete_transient( 'chiral_connector_some_transient' );

        // 清除所有相关文章缓存
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_chiral_related_cache_%',
                '_transient_timeout_chiral_related_cache_%'
            )
        );

        wp_clear_scheduled_hook( 'chiral_connector_retry_failed_syncs' );

        if (class_exists('Chiral_Connector_Utils')) {
            Chiral_Connector_Utils::log_message( 'All Chiral Connector plugin data and settings cleared.', 'info' );
        }
	}

}