<?php
/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Chiral_Connector
 * @subpackage Chiral_Connector/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Connector_i18n {

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'chiral-connector',
            false,
            dirname( plugin_basename( CHIRAL_CONNECTOR_PLUGIN_DIR ) ) . '/languages/'
        );
    }
}