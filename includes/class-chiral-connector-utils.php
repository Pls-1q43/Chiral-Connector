<?php
/**
 * Utility functions for the Chiral Connector plugin.
 *
 * @package    Chiral_Connector
 * @subpackage Chiral_Connector/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Connector_Utils {

    /**
     * Log a message (if logging is enabled or WP_DEBUG is on).
     *
     * @since 1.0.0
     * @param string $message The message to log.
     * @param string $level   Optional. Log level (e.g., 'info', 'warning', 'error'). Default 'info'.
     */
    public static function log_message( $message, $level = 'info' ) {
        // Prioritize plugin setting for logging, fallback to WP_DEBUG only if setting doesn't exist
        $enable_logging = self::get_setting( 'enable_debug_logging', null );

        // If plugin setting is explicitly true, log
        // If plugin setting doesn't exist (null) and WP_DEBUG is true, log
        // If plugin setting is explicitly false, do NOT log (even if WP_DEBUG is true)
        if ( $enable_logging === true || ( $enable_logging === null && defined( 'WP_DEBUG' ) && WP_DEBUG === true ) ) {
            if ( is_array( $message ) || is_object( $message ) ) {
                error_log( '[' . strtoupper( $level ) . ' - Chiral Connector] ' . print_r( $message, true ) );
            }
            else {
                error_log( '[' . strtoupper( $level ) . ' - Chiral Connector] ' . $message );
            }
        }
        // Optionally, add more sophisticated logging, e.g., to a dedicated file or using a logging library,
        // based on plugin settings.
    }

    /**
     * Get a specific plugin setting or all settings.
     *
     * @since 1.0.0
     * @param string|null $key     The specific setting key to retrieve. Null to get all settings.
     * @param mixed       $default Optional. Default value if the setting is not found.
     * @return mixed The setting value or array of all settings.
     */
    public static function get_setting( $key = null, $default = null ) {
        $options = get_option( 'chiral_connector_settings' );

        if ( null === $key ) {
            return $options;
        }

        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }

    /**
     * Sanitize a URL for use in API requests or display.
     *
     * @since 1.0.0
     * @param string $url The URL to sanitize.
     * @return string Sanitized URL.
     */
    public static function sanitize_url( $url ) {
        return esc_url_raw( trim( $url ) );
    }

    /**
     * Generate a unique node ID based on site URL (example implementation).
     * This might be used if the user doesn't provide one.
     *
     * @since 1.0.0
     * @return string A hashed representation of the site URL.
     */
    public static function generate_node_id_from_url() {
        return 'node_' . md5( get_site_url() );
    }

    // Add other utility functions as needed, e.g.:
    // - Data validation functions
    // - Helper for checking if hub connection details are complete
    // - etc.
}