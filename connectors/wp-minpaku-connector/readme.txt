=== WP Minpaku Connector ===
Contributors: yato1214
Tags: minpaku, vacation rental, booking, calendar, properties
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to Minpaku Suite portal to display property listings and availability calendars with real-time pricing and booking integration.

== Description ==

WP Minpaku Connector allows you to seamlessly integrate your Minpaku Suite portal with your WordPress website. Display property listings, availability calendars, and enable direct booking integration.

= Key Features =

* **Property Listings**: Display property cards with images, descriptions, and pricing
* **Portal Parity Calendar**: 100% same design and functionality as portal calendar
* **Real-time Pricing**: Live pricing data from portal API with weekend/holiday surcharges
* **Color-coded Calendar**: Weekdays (green), Saturdays (blue), Sundays/holidays (red), booked (gray)
* **One-click Booking**: Direct integration with portal booking system
* **Modal Support**: Option to display calendars in popup modals
* **Responsive Design**: Works perfectly on desktop and mobile devices
* **Secure API Integration**: HMAC-signed requests for data security

= Shortcodes =

* `[minpaku_connector type="properties"]` - Display property listings
* `[minpaku_connector type="availability" property_id="123"]` - Show availability calendar
* `[minpaku_connector type="property" property_id="123"]` - Display property details

= Parameters =

* `property_id` - Property ID (required for availability and property types)
* `months` - Number of months to display (1-12, default: 2)
* `show_prices` - Show pricing on calendar (true/false, default: true)
* `modal` - Display calendar in modal popup (true/false, default: false)
* `limit` - Number of properties to show (default: 12)
* `columns` - Grid columns for property listings (default: 3)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wp-minpaku-connector/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > Minpaku Connector to configure the connection
4. Enter your portal URL, Site ID, API Key, and Secret
5. Test the connection to ensure everything works
6. Use the shortcodes on your pages and posts

== Configuration ==

1. Log in to your Minpaku Suite portal admin area
2. Go to Minpaku › Settings › Connector
3. Enable the connector and add your WordPress site domain to allowed domains
4. Generate new API keys for your site
5. Copy the credentials to the WordPress plugin settings
6. Save and test the connection

== Frequently Asked Questions ==

= How do I get API credentials? =

API credentials are generated from your Minpaku Suite portal admin area under Minpaku › Settings › Connector.

= Can I customize the calendar appearance? =

The calendar uses portal parity design for consistency. Basic styling can be overridden with custom CSS.

= Does it work with caching plugins? =

Yes, but you may need to exclude calendar pages from caching for real-time pricing updates.

= Can I display multiple properties? =

Yes, use the properties shortcode: `[minpaku_connector type="properties" limit="6"]`

== Screenshots ==

1. Property listings grid with calendar buttons
2. Portal parity availability calendar with pricing
3. Property details page with integrated calendar
4. Plugin settings page with connection test
5. Modal calendar popup display

== Changelog ==

= 0.5.0 =
* Unified calendar system using portal parity design
* Improved weekend/holiday pricing calculation
* Simplified shortcode system
* Enhanced booking integration
* Better error handling and validation
* Updated documentation

= 0.4.0 =
* Added modal calendar support
* Improved responsive design
* Enhanced API security
* Better property data handling

= 0.3.0 =
* Initial calendar functionality
* Property listings support
* Basic API integration

== Upgrade Notice ==

= 0.5.0 =
Major update with unified calendar system and improved portal parity. All shortcodes now use the same high-quality calendar display.

== Support ==

For support and documentation, please visit: https://github.com/yato1214/minpaku-suite

== Privacy ==

This plugin connects to your Minpaku Suite portal to fetch property and availability data. No personal data is stored locally or shared with third parties.