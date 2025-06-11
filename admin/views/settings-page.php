<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch
/**
 * Provide a admin area view for the plugin
 */

// Check hub configuration status
$options = get_option( 'chiral_connector_settings' );
$hub_url = isset($options['hub_url']) ? trim($options['hub_url']) : '';
$username = isset($options['hub_username']) ? trim($options['hub_username']) : '';
$app_password = isset($options['hub_app_password']) ? trim($options['hub_app_password']) : '';
$is_hub_configured = !empty($hub_url) && !empty($username) && !empty($app_password);
?>
<div class="wrap <?php echo !$is_hub_configured ? 'chiral-connector-unconfigured' : 'chiral-connector-configured'; ?>">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <?php if (!$is_hub_configured): ?>
    <div class="notice notice-info chiral-connector-setup-guide" style="margin: 20px 0; padding: 15px; border-left: 4px solid #72aee6;">
        <h3 style="margin-top: 0;">🚀 <?php esc_html_e('Welcome to Chiral Connector!', 'chiral-connector'); ?></h3>
        <p><strong><?php esc_html_e('Quick Setup Guide:', 'chiral-connector'); ?></strong></p>
        <ol>
            <li><?php esc_html_e('Fill in your Hub connection details below', 'chiral-connector'); ?></li>
            <li><?php esc_html_e('Click "Test Connection" to verify the settings', 'chiral-connector'); ?></li>
            <li><strong><?php esc_html_e('Click "Save Settings" at the bottom of this page to save your configuration', 'chiral-connector'); ?></strong></li>
        </ol>
        <p><em><?php esc_html_e('Note: Testing the connection successfully does NOT automatically save your settings. You must click "Save Settings" to complete the setup.', 'chiral-connector'); ?></em></p>
    </div>
    <?php endif; ?>
    
    <form method="post" action="options.php">
        <?php
        // Output security fields for the registered setting section
        settings_fields( 'chiral_connector_option_group' );

        // Output setting sections and their fields
        do_settings_sections( 'chiral-connector-settings-page' );

        // Output save settings button with enhanced styling
        ?>
        <div class="chiral-connector-save-section" style="background: #f0f6fc; border: 1px solid #c3dafe; border-radius: 6px; padding: 15px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #1d4ed8;"><?php esc_html_e('💾 Save Your Configuration', 'chiral-connector'); ?></h3>
            <p><?php esc_html_e('After making changes above, click this button to save your settings:', 'chiral-connector'); ?></p>
            <?php submit_button( __( 'Save Settings', 'chiral-connector' ), 'primary large', 'submit', false, array('style' => 'font-size: 16px; padding: 8px 16px; height: auto;') ); ?>
        </div>
        <?php
        ?>
    </form>

    <hr>
    <h2><?php esc_html_e( 'Plugin Status & Logs', 'chiral-connector' ); ?></h2>
    <p><?php esc_html_e( 'Review synchronization status and error logs here (basic implementation).', 'chiral-connector' ); ?></p>
    <div id="chiral-connector-log-display" style="height: 200px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px; background-color: #f9f9f9;">
        <?php 
        // This is a placeholder. A more robust solution would read from a log file or a dedicated log option.
        // For now, let's imagine Chiral_Connector_Utils::log_message() could also store recent logs in a transient or option.
        $logs = get_transient('chiral_connector_recent_logs');
        if ($logs && is_array($logs)) {
            foreach (array_reverse($logs) as $log_entry) {
                echo esc_html($log_entry) . "<br>";
            }
        } else {
            esc_html_e('No recent log entries.', 'chiral-connector');
        }
        // A real log viewer might be more complex, fetching logs via AJAX or from a file.
        ?>
    </div>

    <div class="wrap">
        <h2><?php esc_html_e( 'Danger Zone', 'chiral-connector' ); ?></h2>
        <p><?php esc_html_e( 'These actions are critical and may result in data loss or plugin deactivation. Please proceed with caution.', 'chiral-connector' ); ?></p>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php esc_html_e( 'Quit Chiral Network', 'chiral-connector' ); ?></th>
                <td>
                    <p><?php esc_html_e( 'This action will attempt to delete all of your Chiral Data from the connected Hub, clear all local Chiral Connector settings, and then deactivate the plugin.', 'chiral-connector' ); ?></p>
                    <p><strong><?php esc_html_e( 'Important:', 'chiral-connector' ); ?></strong> <?php esc_html_e( 'After this process completes, you should manually log in to your Chiral Hub account to verify that all your data has been successfully removed. This plugin will be deactivated automatically.', 'chiral-connector' ); ?></p>
                    <button id="chiral-connector-quit-network-button" class="button button-secondary" style="background-color: #dc3232; border-color: #dc3232; color: #fff;">
                        <?php esc_html_e( 'Quit Chiral Network & Delete Data', 'chiral-connector' ); ?>
                    </button>
                    <span id="chiral-connector-quit-status" style="margin-left: 10px;"></span>
                </td>
            </tr>
        </table>
    </div>

</div>