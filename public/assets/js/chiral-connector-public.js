(function( $ ) {
    'use strict';

    // Use localized texts passed from PHP via chiralConnectorPublicAjax.texts
    // Fallback defaults are kept for safety but shouldn't be needed if localization is correct.
    var defaultTexts = {
        loading: 'Loading related Chiral data...',
        relatedTitle: 'Related Posts',
        noData: 'No related Chiral data found at the moment.',
        fetchError: 'Error fetching related data',
        configError: 'Chiral Connector: Configuration error for related posts.',
        source: 'Source: %s',
        fromChiralNetwork: 'From Chiral Network: %s'
    };

    // Basic HTML escaping helper functions (consistent with previous version)
    function escapeHtml(text) {
        if (typeof text !== 'string') return '';
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    function encodeURIPath(url) { // Renamed to avoid confusion with full encodeURI
        if (typeof url !== 'string') return '';
        // This is a simplified version. Proper URL encoding, especially for query params, is more complex.
        // For paths in href/src, this often suffices.
        return url.replace(/ /g, '%20').replace(/"/g, '%22').replace(/'/g, '%27');
    }


    function renderRelatedPostItem(post, texts, configuredHubUrl) {
        var itemHtml = '<li>'; // Direct li as per old structure
        
        // Determine the source URL for the related post (logic from old .js)
        var sourceUrl = '#'; // Default
        if (post.url && typeof post.url === 'string' && post.url.trim() !== '') {
            sourceUrl = post.url;
        } else if (post.metadata && Array.isArray(post.metadata)) { 
            var chiralSourceMeta = post.metadata.find(function(meta) {
                return meta.key === 'chiral_source_url';
            });
            if (chiralSourceMeta && chiralSourceMeta.value) {
                sourceUrl = chiralSourceMeta.value;
            }
        }

        if (post.featured_image_url) {
            itemHtml += '<div class="related-post-thumbnail">'; 
            itemHtml += '<a href="' + encodeURIPath(sourceUrl) + '" target="_blank" rel="noopener noreferrer">';
            itemHtml += '<img src="' + encodeURIPath(post.featured_image_url) + '" alt="' + escapeHtml(post.title) + '">';
            itemHtml += '</a>';
            itemHtml += '</div>';
        }
        itemHtml += '<div class="related-post-content">'; 
        itemHtml += '<h4><a href="' + encodeURIPath(sourceUrl) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(post.title) + '</a></h4>';
        
        if (post.excerpt) {
            itemHtml += '<div class="related-post-excerpt">' + post.excerpt + '</div>'; 
        }

        var sourceLabel = '';
        var isHubUrl = false;
        if (configuredHubUrl && post.url && post.url.indexOf(configuredHubUrl) === 0 && post.url.indexOf('/chiral_data/') > -1) {
            isHubUrl = true;
        }

        // Adjusted logic for sourceLabel to match old version's priority
        if (post.author_name && post.author_name !== 'N/A') {
            sourceLabel = texts.source.replace('%s', escapeHtml(post.author_name));
        } else if (isHubUrl) {
            try {
                var hubHostname = new URL(configuredHubUrl).hostname; 
                sourceLabel = texts.source.replace('%s', escapeHtml(hubHostname) + ' (Hub)');
            } catch (e) {
                sourceLabel = texts.source.replace('%s', 'Chiral Hub'); // Fallback for Hub
            }
        } else if (sourceUrl && sourceUrl !== '#') {
            try {
                var sourceHostname = new URL(sourceUrl).hostname;
                sourceLabel = texts.source.replace('%s', escapeHtml(sourceHostname));
            } catch (e) { /* console.warn('Chiral Connector: Could not parse source URL for hostname', sourceUrl, e); */ }
        }
        // post.network_name is not used for individual item source label here, it's for the global subtitle.

        if (sourceLabel) {
            itemHtml += '<small class="related-post-source">' + sourceLabel + '</small>'; // Old class name
        }

        itemHtml += '</div>'; // .related-post-content
        itemHtml += '</li>';
        return itemHtml;
    }

    $(document).ready(function() {
        var $container = $('#chiral-connector-related-posts'); // This ID is from the PHP placeholder

        if ($container.length === 0) {
            return; 
        }

        var postUrl = $container.data('post-url');
        var nodeId = $container.data('node-id');
        var configuredHubUrl = $container.data('hub-url'); // Used for subtitle link and source check
        var count = parseInt($container.data('count'), 10) || 5;

        var texts = (typeof chiralConnectorPublicAjax !== 'undefined' && typeof chiralConnectorPublicAjax.texts !== 'undefined') 
                    ? {...defaultTexts, ...chiralConnectorPublicAjax.texts}
                    : defaultTexts;

        if (!postUrl || !nodeId) { // configuredHubUrl can be empty if not set, but essential for some source logic.
            $container.html('<p class="chiral-no-related-posts">' + texts.configError + '</p>'); // Old class
            return;
        }
        
        $container.html('<p class="chiral-loading">' + texts.loading + '</p>'); // New class, can be styled

        $.ajax({
            url: chiralConnectorPublicAjax.ajax_url,
            type: 'POST', 
            data: {
                action: 'chiral_fetch_related_posts',
                nonce: chiralConnectorPublicAjax.nonce,
                source_url: postUrl,        
                requesting_node_id: nodeId, 
                count: count                
            },
            dataType: 'json', // Expect JSON response
            success: function(response) {
                var html = '';
                var finalHubDisplayName = texts.fromChiralNetwork.split('%s')[0].trim() || 'From Network:'; // Default part
                var finalHubDisplayLink = configuredHubUrl;

                // Attempt to get Hub Name if configuredHubUrl is valid (simplified from old JS)
                var siteIdentifierForHubName = '';
                if (configuredHubUrl) {
                    try { siteIdentifierForHubName = new URL(configuredHubUrl).hostname; } catch(e) {}
                }

                // Subtitle logic similar to old version, but displayed once.
                var subtitleHtml = '';
                var networkNameFromFirstPost = '';
                if (response.success && response.data && Array.isArray(response.data) && response.data.length > 0 && response.data[0].network_name) {
                    networkNameFromFirstPost = response.data[0].network_name;
                }

                if (networkNameFromFirstPost && networkNameFromFirstPost.trim() !== '') {
                    finalHubDisplayName = networkNameFromFirstPost;
                } else if (siteIdentifierForHubName) {
                    // If no specific network name from posts, use a generic based on Hub URL's domain.
                    // The old JS tried to fetch Hub's site title here. That's an extra API call.
                    // For simplicity, we'll use the domain or a generic placeholder if that's too complex here.
                    // Let's use the siteIdentifierForHubName as the display name for now if no specific network name.
                    finalHubDisplayName = siteIdentifierForHubName; 
                }
                // else finalHubDisplayName remains the default part of texts.fromChiralNetwork or 'Chiral Hub'
                
                if (finalHubDisplayLink && finalHubDisplayLink.trim() !== '') {
                    subtitleHtml = '<small class="chiral-hub-name-subtitle">' + texts.fromChiralNetwork.replace('%s', '<a href="' + encodeURIPath(finalHubDisplayLink) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(finalHubDisplayName) + '</a>') + '</small>';
                } else {
                    subtitleHtml = '<small class="chiral-hub-name-subtitle">' + texts.fromChiralNetwork.replace('%s', escapeHtml(finalHubDisplayName)) + '</small>';
                }

                if (response.success && response.data && Array.isArray(response.data) && response.data.length > 0) {
                    html = '<div class="chiral-connector-related-posts-list">'; // Old class
                    html += '<h3>' + texts.relatedTitle + '</h3>';
                    html += subtitleHtml;
                    html += '<ul>';
                    $.each(response.data, function(index, post) {
                        html += renderRelatedPostItem(post, texts, configuredHubUrl);
                    });
                    html += '</ul></div>';
                    $container.html(html);
                } else if (response.success && response.data && Array.isArray(response.data) && response.data.length === 0) {
                    html = '<div class="chiral-connector-related-posts-list">'; // Old class
                    html += '<h3>' + texts.relatedTitle + '</h3>';
                    html += subtitleHtml; // Show subtitle even if no posts
                    html += '<p class="chiral-no-related-posts">' + texts.noData + '</p></div>'; // Old class
                    $container.html(html);
                } else {
                    var errorMsg = texts.fetchError;
                    if(response.data && response.data.message){
                        errorMsg += ': ' + escapeHtml(response.data.message);
                    } else if (response.data === false ){
                         errorMsg = texts.noData; 
                    }
                    html = '<div class="chiral-connector-related-posts-list">'; // Old class
                    html += '<h3>' + texts.relatedTitle + '</h3>';
                     html += subtitleHtml; // Show subtitle even on error
                    html += '<p class="chiral-no-related-posts">' + errorMsg + '</p></div>'; // Old class
                    $container.html(html);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var errorMsg = texts.fetchError;
                // Simplified error display from old JS for brevity
                if (errorThrown) { errorMsg += ': ' + escapeHtml(errorThrown); }
                $container.html('<div class="chiral-connector-related-posts-list"><h3>' + texts.relatedTitle + '</h3><p class="chiral-no-related-posts">' + errorMsg + '</p></div>');
            }
        });
    });

})( jQuery );