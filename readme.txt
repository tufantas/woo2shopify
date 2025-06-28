=== Woo2Shopify - WooCommerce to Shopify Migration Tool ===
Contributors: tufantas
Tags: woocommerce, shopify, migration, ecommerce, products, videos
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: Tufan Taş
Author URI: mailto:tufantas@gmail.com

Comprehensive tool to migrate products, images, videos, and data from WooCommerce to Shopify with ease.

== Description ==

Woo2Shopify is a powerful WordPress plugin that enables seamless migration of your WooCommerce store data to Shopify. Whether you're switching platforms or maintaining multiple stores, this tool ensures your products, images, categories, and metadata are transferred accurately and efficiently.

= Key Features =

* **Complete Product Migration**: Transfer all product data including titles, descriptions, prices, SKUs, and inventory
* **Image Migration**: Automatically migrate product images with optimization options
* **Category & Tag Support**: Convert WooCommerce categories to Shopify collections and preserve tags
* **Variation Support**: Handle product variations and their specific attributes
* **Batch Processing**: Process large catalogs efficiently with configurable batch sizes
* **Progress Tracking**: Real-time progress monitoring with detailed logs
* **Error Handling**: Comprehensive error reporting and retry mechanisms
* **Safe Migration**: Test connections and validate data before migration
* **Flexible Configuration**: Customizable settings for different migration needs

= What Gets Migrated =

* Product titles and descriptions
* Product images (featured and gallery images)
* Prices (regular and sale prices)
* SKUs and inventory levels
* Product categories (as Shopify collections)
* Product tags
* Product variations and attributes
* SEO metadata
* Product status (published/draft)

= Requirements =

* WordPress 5.0 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* Active Shopify store with Admin API access
* Sufficient server memory for batch processing

= Shopify Setup =

1. Create a private app in your Shopify admin
2. Generate Admin API access token with required permissions
3. Configure the plugin with your store URL and access token

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/woo2shopify/` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to WooCommerce > Woo2Shopify in your admin panel
4. Configure your Shopify store settings
5. Test the connection and start your migration

== Frequently Asked Questions ==

= Do I need a Shopify store to use this plugin? =

Yes, you need an active Shopify store with Admin API access to migrate your products.

= Will this plugin modify my WooCommerce data? =

No, this plugin only reads data from WooCommerce. Your original store data remains unchanged.

= Can I migrate product variations? =

Yes, the plugin supports WooCommerce product variations and converts them to Shopify variants.

= What happens if the migration fails? =

The plugin includes comprehensive error handling and logging. Failed items can be retried individually.

= Can I test the migration before running it? =

Yes, you can test your Shopify connection and review migration settings before starting the actual migration.

= How long does migration take? =

Migration time depends on the number of products and images. The plugin processes items in batches to ensure reliability.

== Screenshots ==

1. Main migration dashboard with progress tracking
2. Shopify connection settings and testing
3. Migration configuration options
4. Detailed migration logs and error reporting
5. Batch processing progress with real-time updates

== Changelog ==

= 1.0.0 =
* Initial release
* Complete product migration functionality
* Image migration with optimization
* Batch processing system
* Progress tracking and logging
* Admin interface with configuration options
* Shopify API integration
* Error handling and retry mechanisms

== Upgrade Notice ==

= 1.0.0 =
Initial release of Woo2Shopify migration tool.

== Support ==

For support, feature requests, or bug reports:
- GitHub Issues: https://github.com/tufantas/woo2shopify/issues
- Email: tufantas@gmail.com
- Plugin settings page for system information

== Privacy Policy ==

This plugin connects to the Shopify API to transfer your product data. No data is sent to third parties other than your configured Shopify store. All API communications are encrypted and secure.

== Credits ==

Developed by Tufan Taş with love for the WordPress and eCommerce community.
