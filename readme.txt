=== Chiral Connector ===
Contributors: Pls(评论尸)
Tags: related posts, content synchronization, network, jetpack, cross-site, content discovery
Requires at least: 5.2
Tested up to: 6.6
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Connect your WordPress site to a Chiral Hub, enabling content synchronization and discovery within the Chiral Network.

== Description ==

Chiral Connector is an innovative WordPress plugin that connects your independent WordPress site to a Chiral Hub, creating an intelligent content synchronization and discovery network. By leveraging the powerful related posts computation capabilities of WordPress.com, Chiral Connector provides high-quality cross-site related content recommendations for your visitors.

### Key Features

* **Smart Content Synchronization** - Automatically sync your posts to Chiral Hub for cross-site content indexing
* **Related Posts Recommendations** - Display related content from other sites in the network using WordPress.com algorithms
* **Seamless Integration** - Automatically display related posts after content or use shortcode for manual placement
* **Flexible Configuration** - Full control over which posts participate in sync and how related content is displayed
* **Cache Optimization** - Built-in caching mechanism ensures fast loading of related posts
* **Batch Synchronization** - One-click batch sync of existing posts to Chiral Hub
* **Failure Retry** - Intelligent sync failure retry mechanism ensures data integrity

### How It Works

1. **Connect to Chiral Hub** - Securely connect to your Chiral Hub site using Application Passwords
2. **Content Synchronization** - When you publish or update posts, the plugin automatically syncs content metadata to the Hub
3. **Jetpack Integration** - Hub leverages Jetpack to sync data to WordPress.com for intelligent indexing
4. **Related Content Retrieval** - When visitors browse posts, the plugin fetches related content from the network
5. **Smart Display** - Display related posts at the end of content or at specified locations to enhance user experience

### Use Cases

* Multi-site network operators
* Content publishers seeking increased content exposure
* Blog networks aiming to boost user engagement
* Vertical domain content platforms

== Installation ==

### Automatic Installation

1. Go to "Plugins" > "Add New" in your WordPress admin
2. Search for "Chiral Connector"
3. Click "Install Now" and then "Activate"

### Manual Installation

1. Download the plugin zip file from WordPress.org
2. Go to "Plugins" > "Add New" > "Upload Plugin" in your WordPress admin
3. Select the downloaded zip file and click "Install Now"
4. Click "Activate Plugin" once installation is complete

### Configuration Steps

1. **Set up Chiral Hub Connection**
   - Go to "Chiral Connector" settings page
   - Enter your Hub URL (e.g., https://yourhub.com)
   - Enter Hub username (chiral_porter role user)
   - Enter application password

2. **Configure Node ID**
   - Plugin will auto-generate a node ID, or you can customize it
   - Ensure node ID is unique within the same Hub network

3. **Test Connection**
   - Click "Test Connection" button to verify connection
   - Start using after confirming successful connection

4. **Configure Display Options**
   - Choose whether to enable related posts display
   - Set display count (recommended 3-6 posts)
   - Choose whether to enable caching

== Frequently Asked Questions ==

= What is Chiral Hub? =

Chiral Hub is a centralized WordPress site that serves as the core hub of the network. It collects, stores, and manages post data from multiple Connector plugins and leverages Jetpack to sync data to WordPress.com for powerful related posts computation.

= Do I need Jetpack? =

Your Connector site doesn't need Jetpack, but the Chiral Hub must have Jetpack plugin installed and activated for proper functionality.

= Will synchronization affect my site performance? =

No. Synchronization operations run asynchronously in the background and won't affect front-end page loading speed. Failed sync operations automatically retry to ensure data integrity.

= Can I control which posts get synchronized? =

Yes. Each post has a "Send to Chiral Hub?" option that you can control when editing posts or through the quick edit feature.

= What's the source of related posts data? =

Related posts data comes from other sites connected to the same Chiral Hub. The system uses WordPress.com algorithms to analyze content relevance.

= How do I use the shortcode to display related posts? =

Use the `[chiral_related_posts]` shortcode to manually display related posts at any location.

= How do I quit the Chiral Network? =

Click the "Quit Chiral Network" button on the settings page. This will delete all synced data on the Hub and deactivate the plugin. It's recommended to log into the Hub after this operation to confirm data has been cleared.

== Screenshots ==

1. Main settings page - Configure Hub connection and sync options
2. Post editing page - Control sync settings for individual posts
3. Frontend related posts display - Smart cross-site content recommendations
4. Batch sync interface - One-click sync of all existing posts

== Changelog ==

= 1.1.0 =
* New: Hub Mode - Automatically detect if the current site is a Chiral Hub Core site
* New: Automatically disable data synchronization in Hub mode to avoid cyclic synchronization
* New: In Hub mode, the related article display function uses local data instead of Hub API
* New: In Hub mode, the batch synchronization function is disabled and the corresponding prompt is displayed
* Optimization: Simplified the Hub mode status display on the management page
* Optimization: Cleaned up the debug information output to keep the interface simple
* Fixed: The problem of repeated display of related article components in Hub mode
* Fixed: Display anomalies caused by repeated hook registration in Hub mode

= 1.0.0 =
* Initial release
* Complete Chiral Hub connection functionality
* Support for automatic and manual content synchronization
* Integration with WordPress.com related posts API
* Frontend related posts display
* Caching mechanism for performance optimization
* Failure retry and error handling
* Batch sync for existing content
* Complete admin interface

== Upgrade Notice ==

= 1.0.0 =
Welcome to Chiral Connector! This is the first stable release providing complete cross-site content synchronization and discovery functionality.

== Additional Information ==

### Technical Requirements

* WordPress 5.2 or higher
* PHP 7.2 or higher
* Valid Chiral Hub site connection
* Hub site must have Chiral Hub Core plugin and Jetpack installed

### Developer Information

This plugin is developed by 评论尸(Pls) and is one of the core components of the WP Chiral Network project.

### Support

For technical support, please visit:
* Plugin homepage: https://ckc.akashio.com
* Developer blog: https://1q43.blog
* GitHub repository: https://github.com/Pls-1q43/Chiral-Connector

### Privacy Statement

The plugin only syncs basic metadata of your selected posts (title, excerpt, links, etc.) to the Chiral Hub. No personal user data or browsing behavior information is collected.

== License ==

This plugin is licensed under GPL v3 or later. For details, see: http://www.gnu.org/licenses/gpl-3.0.txt 
