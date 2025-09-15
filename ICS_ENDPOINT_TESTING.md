# ICS Endpoint Testing Guide

## Overview

This guide covers testing the ICS endpoint functionality that serves property-specific iCalendar files at `/ics/property/{post_id}/{key}.ics`.

## URL Structure

```
https://your-site.com/ics/property/{post_id}/{key}.ics
```

Where:
- `{post_id}` = WordPress post ID of the property
- `{key}` = 24-character alphanumeric key stored in `_ics_key` meta

## Prerequisites

1. WordPress site with Minpaku Suite plugin activated
2. At least one published post with `post_type = 'property'`
3. Plugin activation should have flushed rewrite rules

## Test Procedures

### 1. Plugin Activation Test

**Steps:**
1. Deactivate Minpaku Suite plugin
2. Reactivate the plugin
3. Check that rewrite rules are flushed

**Expected Results:**
- Plugin activates without errors
- Rewrite rules are properly registered
- No 404 errors when accessing ICS URLs

### 2. Generate ICS Keys for Properties

**Method A: Via Admin Interface**
1. Go to `Minpaku Suite > Settings`
2. Click "Regenerate Mappings"
3. Confirm the action
4. Check that mappings table shows properties with ICS URLs

**Method B: Via WP-CLI**
```bash
wp mcs mappings regen
```

**Method C: Programmatically**
```php
$post_id = 123; // Your property post ID
$key = MCS_Ics::get_or_create_key($post_id);
$url = MCS_Ics::property_url($post_id);
echo "ICS URL: " . $url;
```

**Expected Results:**
- Each property gets a 24-character `_ics_key` meta value
- URLs follow the format: `home_url/ics/property/{post_id}/{key}.ics`

### 3. Basic ICS Access Test

**Steps:**
1. Get a property post ID and its ICS key
2. Construct the URL: `https://your-site.com/ics/property/{post_id}/{key}.ics`
3. Visit the URL in a browser or use curl:

```bash
curl -i "https://your-site.com/ics/property/123/abcd1234567890abcd123456.ics"
```

**Expected Results:**
- HTTP 200 status
- `Content-Type: text/calendar; charset=utf-8`
- `Content-Disposition: inline; filename="property-123.ics"`
- Valid iCalendar content starting with `BEGIN:VCALENDAR`

### 4. Authentication Test

**Steps:**
1. Try accessing with wrong key:
   ```bash
   curl -i "https://your-site.com/ics/property/123/wrongkey123456789012.ics"
   ```
2. Try accessing non-existent property:
   ```bash
   curl -i "https://your-site.com/ics/property/99999/abcd1234567890abcd123456.ics"
   ```
3. Try accessing draft/private property

**Expected Results:**
- Wrong key: HTTP 404
- Non-existent property: HTTP 404
- Non-published property: HTTP 404

### 5. Cache Headers Test

**Steps:**
1. Make initial request and note ETag and Last-Modified headers:
   ```bash
   curl -i "https://your-site.com/ics/property/123/abcd1234567890abcd123456.ics"
   ```
2. Make conditional request with If-None-Match:
   ```bash
   curl -i -H "If-None-Match: \"etag-value-here\"" "https://your-site.com/ics/property/123/abcd1234567890abcd123456.ics"
   ```
3. Make conditional request with If-Modified-Since:
   ```bash
   curl -i -H "If-Modified-Since: Wed, 15 Sep 2025 10:00:00 GMT" "https://your-site.com/ics/property/123/abcd1234567890abcd123456.ics"
   ```

**Expected Results:**
- Initial request: HTTP 200 with ETag and Last-Modified headers
- Conditional requests (if unchanged): HTTP 304 Not Modified
- Conditional requests (if changed): HTTP 200 with new content

### 6. Content Generation Test

**Default Content Test:**
1. Add `_unavailable_dates` meta to a property:
   ```php
   update_post_meta(123, '_unavailable_dates', ['2025-12-25', '2025-12-26', '2025-01-01']);
   ```
2. Access the ICS URL
3. Verify content includes VEVENT entries for each date

**Custom Events Filter Test:**
1. Add filter to modify events:
   ```php
   add_filter('mcs/property_ics_events', function($events, $post_id) {
       $events[] = [
           'uid' => 'custom-event@example.com',
           'dtstart' => '2025-12-31',
           'dtend' => '2025-12-31',
           'summary' => 'New Year Block',
           'description' => 'Property blocked for New Year',
           'all_day' => true,
       ];
       return $events;
   }, 10, 2);
   ```
2. Access the ICS URL
3. Verify custom event appears in output

**Expected Results:**
- Default: Unavailable dates appear as all-day VEVENT entries
- Custom: Additional events from filter are included
- All events have proper iCalendar formatting

### 7. ICS Content Validation

**Validation Points:**
1. **Structure**: Starts with `BEGIN:VCALENDAR`, ends with `END:VCALENDAR`
2. **Required Properties**:
   - `VERSION:2.0`
   - `PRODID:-//minpaku-suite//JP`
   - `CALSCALE:GREGORIAN`
   - `METHOD:PUBLISH`
3. **Events**: Each event has `BEGIN:VEVENT` and `END:VEVENT`
4. **Line Folding**: Lines longer than 75 octets are properly folded
5. **Escaping**: Special characters are escaped (`,`, `;`, `\n`, etc.)

**Validation Tools:**
- Online iCalendar validators
- Import into calendar applications (Google Calendar, Outlook, etc.)
- Use icalendar parsing libraries

### 8. Performance Test

**Steps:**
1. Create multiple properties with many unavailable dates
2. Access ICS URLs simultaneously
3. Monitor server resources
4. Test with caching enabled/disabled

**Metrics to Monitor:**
- Response time
- Memory usage
- Database queries
- Cache hit rate

### 9. Integration Test with Calendar Applications

**Steps:**
1. Copy ICS URL from admin interface
2. Subscribe to calendar in:
   - Google Calendar
   - Outlook
   - Apple Calendar
   - Thunderbird
3. Verify events display correctly
4. Update property data and check if calendar apps refresh

**Expected Results:**
- Calendar applications can successfully subscribe
- Events display with correct dates and descriptions
- Updates are reflected when applications refresh

### 10. WP-CLI Testing

**Commands to Test:**
```bash
# List current mappings
wp mcs mappings list

# Regenerate all mappings
wp mcs mappings regen

# Sync all (if external sources configured)
wp mcs sync all --dry-run
wp mcs sync all

# Sync single property
wp mcs sync single --post-id=123 --dry-run
```

**Expected Results:**
- All commands execute without errors
- Output is properly formatted and informative
- Dry-run mode shows what would be done without making changes

## Common Issues and Troubleshooting

### 404 Errors on ICS URLs

**Possible Causes:**
- Rewrite rules not flushed after plugin activation
- Permalink structure conflicts
- Web server configuration issues

**Solutions:**
1. Go to `Settings > Permalinks` and click "Save Changes"
2. Deactivate and reactivate the plugin
3. Check `.htaccess` file for conflicts

### Invalid ICS Content

**Possible Causes:**
- Malformed date data in `_unavailable_dates`
- Special characters not properly escaped
- Line folding issues

**Solutions:**
1. Validate date format in meta values (must be Y-m-d)
2. Check custom filter implementations
3. Test with simple data first

### ETag/Caching Issues

**Possible Causes:**
- Server configuration overriding headers
- Proxy/CDN caching issues
- Clock synchronization problems

**Solutions:**
1. Check server response headers directly
2. Bypass any caching layers for testing
3. Ensure server time is accurate

### Performance Issues

**Possible Causes:**
- Large number of events
- Inefficient database queries
- Missing caching

**Solutions:**
1. Optimize event data structure
2. Implement query caching
3. Use object caching if available

## Sample ICS Output

```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//minpaku-suite//JP
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:Property Name
X-WR-CALDESC:Property availability calendar
BEGIN:VEVENT
UID:unavailable-2025-12-25@example.com
DTSTART;VALUE=DATE:20251225
DTEND;VALUE=DATE:20251226
SUMMARY:Unavailable
DESCRIPTION:Property is not available on this date
DTSTAMP:20250915T120000Z
END:VEVENT
END:VCALENDAR
```

## Curl Test Script

```bash
#!/bin/bash

# Configuration
SITE_URL="https://your-site.com"
POST_ID="123"
ICS_KEY="abcd1234567890abcd123456"
ICS_URL="${SITE_URL}/ics/property/${POST_ID}/${ICS_KEY}.ics"

echo "Testing ICS endpoint: $ICS_URL"
echo

# Test 1: Basic access
echo "=== Test 1: Basic Access ==="
curl -i "$ICS_URL"
echo

# Test 2: Wrong key (should be 404)
echo "=== Test 2: Wrong Key ==="
curl -i "${SITE_URL}/ics/property/${POST_ID}/wrongkey123456789012.ics"
echo

# Test 3: Get ETag for caching test
echo "=== Test 3: Get ETag ==="
ETAG=$(curl -s -I "$ICS_URL" | grep -i etag | cut -d' ' -f2 | tr -d '\r')
echo "ETag: $ETAG"
echo

# Test 4: Conditional request with ETag
if [ ! -z "$ETAG" ]; then
    echo "=== Test 4: Conditional Request ==="
    curl -i -H "If-None-Match: $ETAG" "$ICS_URL"
    echo
fi

echo "Testing complete!"
```

## Security Considerations

1. **Key Security**: 24-character keys provide sufficient entropy
2. **Access Control**: Only published properties are accessible
3. **Rate Limiting**: Consider implementing rate limiting for public endpoints
4. **Log Monitoring**: Monitor for suspicious access patterns

## Development Notes

- ICS content is generated dynamically on each request
- ETag generation includes post modification time and settings hash
- Cache headers encourage client-side caching
- Line folding follows RFC 5545 specifications
- UTC timezone is used for all dates