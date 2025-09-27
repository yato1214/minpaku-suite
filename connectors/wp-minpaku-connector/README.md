# WP Minpaku Connector

A WordPress plugin to connect your WordPress site to a Minpaku Suite portal for displaying property listings and availability calendars.

## Features

- **Secure HMAC Authentication**: All API requests are signed using HMAC-SHA256 for security
- **Property Listings**: Display property cards with images, descriptions, and metadata
- **Availability Calendar**: Show real-time availability for properties
- **Property Details**: Full property information pages
- **Responsive Design**: Mobile-friendly layouts
- **Caching**: Intelligent caching for improved performance
- **Multi-language Support**: Translation-ready with .pot file included

## Installation

### Automatic Installation (Recommended)

1. Download the plugin zip file from your Minpaku Suite portal
2. In your WordPress admin area, go to **Plugins > Add New**
3. Click **Upload Plugin**
4. Choose the downloaded zip file and click **Install Now**
5. Activate the plugin

### Manual Installation

1. Download and extract the plugin files
2. Upload the `wp-minpaku-connector` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the **Plugins** menu in WordPress

## Configuration

### Step 1: Portal Setup

1. Log in to your Minpaku Suite portal admin area
2. Go to **Minpaku › Settings › Connector**
3. Enable the connector feature
4. Add your WordPress site domain to the **Allowed Domains** list (e.g., `yourwordpresssite.com`)
5. Click **Generate New API Keys**
6. Enter a name for your site (optional)
7. Copy the generated credentials:
   - Portal Base URL
   - Site ID
   - API Key
   - Secret

### Step 2: WordPress Setup

1. In your WordPress admin, go to **Settings › Minpaku Connector**
2. Fill in the connection details from Step 1:
   - **Portal Base URL**: Your portal's base URL (e.g., `https://your-portal.com`)
   - **Site ID**: The generated Site ID
   - **API Key**: The generated API Key
   - **Secret**: The generated Secret key
3. Click **Save Changes**
4. Click **Test Connection** to verify the setup

## Usage

### Shortcodes

Once configured, you can use these shortcodes in your posts and pages:

#### Property Listings

Display a grid of properties with base nightly rates:

```
[minpaku_connector type="properties" limit="6" columns="2" modal="true"]
```

**Parameters:**
- `limit`: Number of properties to show (default: 12)
- `columns`: Grid columns (1-6, default: 3)
- `modal`: Enable modal calendar popups (default: "false")
- `class`: Additional CSS class

**Features:**
- Property cards showing base nightly rates (例：料金：15,000円～)
- Amenities display with Japanese labels
- Modal calendar popups for checking availability
- Responsive grid layout

#### Availability Calendar

Show availability for a specific property:

```
[minpaku_connector type="availability" property_id="123" months="2"]
```

**Parameters:**
- `property_id`: Property ID (required)
- `months`: Number of months to display (default: 2)
- `start_date`: Start date (optional, format: YYYY-MM-DD)
- `class`: Additional CSS class

#### Portal-Style Calendar

Display a calendar with the exact same styling and functionality as the Minpaku Suite portal:

```
[minpaku_connector type="availability" property_id="123" months="4" show_prices="true" modal="false"]
```

**Parameters:**
- `property_id`: Property ID (required)
- `months`: Number of months to display (default: 2, max: 12)
- `show_prices`: Show price badges on available days (default: "true")
- `modal`: Display as modal popup button instead of inline calendar (default: "false")

**Features:**
- Real-time availability and pricing data from portal
- Color-coded calendar (weekdays green, Saturdays blue, Sundays/holidays red)
- Price badges showing nightly rates
- "満室" (full) badges for booked dates
- Responsive design matching portal styling
- Click-to-book functionality (redirects to portal booking page)
- Modal popup option for compact display


### CSS Customization

The plugin includes responsive CSS styles. You can customize the appearance by adding CSS to your theme:

```css
/* Customize property cards */
.wmc-property-card {
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Customize availability calendar */
.wmc-calendar {
    border: 2px solid #your-color;
}

/* Customize colors */
.wmc-day-available {
    background: #your-available-color;
}
```

## Security

- All API requests use HMAC-SHA256 authentication
- Nonce-based replay attack prevention
- Timestamp validation (±5 minutes tolerance)
- Domain-based access control via CORS
- Input sanitization and validation

## Caching

The plugin implements intelligent caching:
- **Properties**: 5 minutes cache
- **Availability**: 1 minute cache (frequently changing data)
- Cache automatically clears on configuration changes

## Troubleshooting

### Connection Test Fails

1. **Check Portal Configuration**:
   - Ensure connector is enabled in portal
   - Verify your domain is in the allowed domains list
   - Confirm API keys are active

2. **Check WordPress Configuration**:
   - Verify all connection details are correct
   - Test Portal Base URL in browser
   - Check for typos in API credentials

3. **Check Server Requirements**:
   - PHP 7.4 or higher
   - WordPress 5.0 or higher
   - cURL enabled
   - SSL/HTTPS support

### Shortcodes Not Working

1. **Configuration**: Ensure connection test passes
2. **Property IDs**: Verify property IDs exist in portal
3. **Permissions**: Check that properties are published
4. **Cache**: Try clearing cache in portal if available

### Styling Issues

1. **Theme Conflicts**: Check for CSS conflicts with your theme
2. **Responsive Issues**: Test on different screen sizes
3. **Override Styles**: Use more specific CSS selectors if needed

## Support

For support, please:

1. Check this documentation
2. Verify your configuration settings
3. Test connection in plugin settings
4. Contact your portal administrator

## Changelog

### Version 1.0.0
- Initial release
- Property listings display
- Availability calendar
- Property details view
- HMAC authentication
- Responsive design
- Caching system
- Multi-language support

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Minpaku Suite Portal**: v0.4.1 or higher with Connector feature enabled

## License

This plugin is part of the Minpaku Suite project and follows the same license terms.