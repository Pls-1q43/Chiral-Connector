(function( $ ) {
    'use strict';

    $(function() {
        // Check if chiralConnectorAdmin is defined
        if (typeof chiralConnectorAdmin === 'undefined') {
            console.log('chiralConnectorAdmin is not defined, skipping Chiral Connector admin scripts');
            return;
        }

        // Test Connection Button
        $('#chiral-connector-test-connection').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $status = $('#chiral-connector-test-status');

            $status.removeClass('success error').text(chiralConnectorAdmin.text.testingConnection || 'Testing connection...').show();
            $button.prop('disabled', true);

            var hubUrl = $('input[name="chiral_connector_settings[hub_url]"]').val();
            var username = $('input[name="chiral_connector_settings[hub_username]"]').val();
            var password = $('input[name="chiral_connector_settings[hub_app_password]"]').val();

            $.ajax({
                url: chiralConnectorAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'chiral_connector_test_connection',
                    nonce: chiralConnectorAdmin.nonce,
                    hub_url: hubUrl,
                    username: username,
                    password: password
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text(response.data.message + ' Please click the "Save Settings" button at the bottom of the page to save your configuration!');
                        // 高亮 Save Settings 按钮
                        $('input[type="submit"][name="submit"]').css({
                            'background-color': '#2271b1',
                            'border-color': '#2271b1',
                            'animation': 'pulse 2s infinite',
                            'box-shadow': '0 0 10px rgba(34, 113, 177, 0.5)'
                        });
                        // 添加脉冲动画
                        if (!$('#chiral-connector-pulse-animation').length) {
                            $('head').append('<style id="chiral-connector-pulse-animation">@keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(34, 113, 177, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(34, 113, 177, 0); } 100% { box-shadow: 0 0 0 0 rgba(34, 113, 177, 0); } }</style>');
                        }
                        // 高亮Save Settings区域
                        $('.chiral-connector-save-section').addClass('highlight');
                        // 滚动到Save Settings按钮
                        setTimeout(function() {
                            $('html, body').animate({
                                scrollTop: $('input[type="submit"][name="submit"]').offset().top - 100
                            }, 1000);
                        }, 1000);
                    } else {
                        $status.addClass('error').text(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    $status.addClass('error').text((chiralConnectorAdmin.text.error || 'An error occurred') + ' ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });

        // Batch Sync Button
        $('#chiral-connector-batch-sync').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $status = $('#chiral-connector-batch-sync-status');

            if (!confirm((chiralConnectorAdmin.text.startingSync || 'Starting batch sync...') + ' This might take a while. Are you sure?')) {
                return;
            }

            $status.removeClass('success error').text(chiralConnectorAdmin.text.startingSync || 'Starting batch sync...').show();
            $button.prop('disabled', true);

            $.ajax({
                url: chiralConnectorAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'chiral_connector_trigger_batch_sync',
                    nonce: chiralConnectorAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text(response.data.message);
                    } else {
                        $status.addClass('error').text(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    $status.addClass('error').text((chiralConnectorAdmin.text.error || 'An error occurred') + ' ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });

        // Clear Cache Button
        $('#chiral-connector-clear-cache').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $status = $('#chiral-connector-clear-cache-status');

            $status.removeClass('success error').text('Clearing cache...').show();
            $button.prop('disabled', true);

            $.ajax({
                url: chiralConnectorAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'chiral_connector_clear_cache',
                    nonce: chiralConnectorAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text(response.data.message);
                    } else {
                        $status.addClass('error').text(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    $status.addClass('error').text((chiralConnectorAdmin.text.error || 'An error occurred') + ' ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });

        // Handle "Quit Chiral Network" button click
        $('#chiral-connector-quit-network-button').on('click', function() {
            console.log('Chiral Connector: Quit Network button clicked');
            
            var $button = $(this);
            var $status = $('#chiral-connector-quit-status');

            // Check if required objects are available
            if (typeof chiralConnectorAdmin === 'undefined') {
                console.error('Chiral Connector: chiralConnectorAdmin object is undefined');
                alert('Error: Admin script configuration is missing.');
                return;
            }

            console.log('Chiral Connector: Starting confirmation dialogs');

            // Confirmation dialogs
            var confirm1 = confirm(chiralConnectorAdmin.text.quitNetworkConfirm1 || 'Are you absolutely sure you want to quit the Chiral Network? This will request deletion of all your data on the Hub.');
            if (!confirm1) {
                console.log('Chiral Connector: User cancelled at first confirmation');
                return;
            }

            var confirm2 = confirm(chiralConnectorAdmin.text.quitNetworkConfirm2 || 'This will also clear your local Chiral Connector settings and deactivate the plugin. This action cannot be undone. Proceed?');
            if (!confirm2) {
                console.log('Chiral Connector: User cancelled at second confirmation');
                return;
            }
            
            var confirm3 = confirm(chiralConnectorAdmin.text.quitNetworkConfirm3 || 'After this process, you MUST manually log in to your Chiral Hub account to verify that all your data has been removed. The plugin will attempt to delete the data, but verification is your responsibility. Continue?');
            if (!confirm3) {
                console.log('Chiral Connector: User cancelled at third confirmation');
                return;
            }

            console.log('Chiral Connector: All confirmations passed, starting AJAX request');

            $button.prop('disabled', true);
            $status.text(chiralConnectorAdmin.text.quittingNetwork || 'Processing... Please wait.').css('color', 'orange');

            var ajaxData = {
                action: 'chiral_connector_quit_network',
                nonce: chiralConnectorAdmin.nonce
            };
            
            console.log('Chiral Connector: AJAX data:', ajaxData);
            console.log('Chiral Connector: AJAX URL:', chiralConnectorAdmin.ajax_url);

            $.ajax({
                url: chiralConnectorAdmin.ajax_url,
                type: 'POST',
                data: ajaxData,
                timeout: 60000, // 60 second timeout
                success: function(response) {
                    console.log('Chiral Connector: AJAX success response:', response);
                    if (response.success) {
                        $status.text(response.data.message || 'Successfully quit. Plugin deactivated.').css('color', 'green');
                        alert(response.data.message || 'Plugin deactivated. Please refresh the page or navigate away. You should verify data deletion on the Hub.');
                        $button.text(chiralConnectorAdmin.text.quitNetworkPluginDeactivated || 'Plugin Deactivated');
                    } else {
                        var errorMessage = response.data || (chiralConnectorAdmin.text.error || 'An unknown error occurred.');
                        if (typeof response.data === 'object' && response.data.message) {
                            errorMessage = response.data.message;
                        }
                        console.error('Chiral Connector: Server returned error:', errorMessage);
                        $status.text(errorMessage).css('color', 'red');
                        alert('Error: ' + errorMessage);
                        $button.prop('disabled', false);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Chiral Connector: AJAX error:', {
                        status: jqXHR.status,
                        statusText: jqXHR.statusText,
                        textStatus: textStatus,
                        errorThrown: errorThrown,
                        responseText: jqXHR.responseText
                    });
                    var errorMsg = (chiralConnectorAdmin.text.ajaxError || 'AJAX error: ');
                    $status.text(errorMsg + textStatus + ' - ' + errorThrown).css('color', 'red');
                    alert(errorMsg + textStatus + ' - ' + errorThrown);
                    $button.prop('disabled', false);
                }
            });
        });
        
    });

    // Quick Edit functionality for 'Send to Chiral?'
    $(document).on('click', '#the-list .editinline', function() {
        // Get the post ID from the row
        var post_id = $(this).closest('tr').attr('id');
        if (post_id) {
            post_id = post_id.replace('post-', '');
        } else {
            // Try to get from the button itself if the <tr> doesn't have the ID directly (less common)
            var $button = $(this);
            post_id = $button.data('id'); // WordPress might add data-id to the button sometimes.
            if(!post_id) return; // Cannot determine post ID
        }
        
        // Find our chiral quick edit input within the quick edit row for this post
        // The quick edit row ID is 'edit-' + post_id
        var $edit_row = $('#edit-' + post_id);
        var $chiral_checkbox = $edit_row.find('input[name="chiral_send_to_hub_quick_edit"]');

        if ($chiral_checkbox.length) {
            var $rowData = $('#post-' + post_id);
            var is_checked = true; // Default to true (checked)

            // Try to get value from data attribute first (more robust)
            var $status_span = $rowData.find('.column-chiral_send_to_hub span[data-chiral-send-status]');
            if ($status_span.length) {
                var chiral_send_val = $status_span.data('chiral-send-status');
                is_checked = (chiral_send_val === 'yes');
            } else {
                // Fallback: If data attribute is not found (e.g. due to an issue or older version),
                // try to infer from text content of the column, using localized values if possible.
                var $column_data_cell = $rowData.find('.column-chiral_send_to_hub');
                if ($column_data_cell.length > 0) {
                    var status_text = $column_data_cell.text().trim();
                    var yes_string = (typeof chiralConnectorAdmin !== 'undefined' && chiralConnectorAdmin.text && chiralConnectorAdmin.text.QuickEditYes) ? chiralConnectorAdmin.text.QuickEditYes : 'Yes';
                    // If it's not explicitly 'No', assume 'Yes' or default state.
                    // This is because an empty or non-'No' string often implies the default checked state.
                    var no_string = (typeof chiralConnectorAdmin !== 'undefined' && chiralConnectorAdmin.text && chiralConnectorAdmin.text.QuickEditNo) ? chiralConnectorAdmin.text.QuickEditNo : 'No';
                    if (status_text.toLowerCase() === no_string.toLowerCase()) {
                         is_checked = false;
                    } else {
                         is_checked = true; // Default to true if not 'No'
                    }
                } else {
                    // If column data cell is also not found, keep default true or consider logging an issue.
                    // console.log('Chiral Connector: Quick edit could not determine current status for post ' + post_id);
                }
            }
            $chiral_checkbox.prop('checked', is_checked);
        }
    });

    // WordPress core handles saving via its own AJAX. We just need to make sure our field is submitted.
    // The `save_post` hook on the PHP side will pick up `$_POST['chiral_send_to_hub_quick_edit']`.
    
    // Optional: If we need to do something before WordPress saves, we can hook into `inline γνωστόsave`.
    // $(document).on('save', '#inline-edit', function() { ... });

})( jQuery );