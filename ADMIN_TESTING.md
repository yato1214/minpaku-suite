# Manual Testing Procedures for Minpaku Suite Admin Interface

## Prerequisites

1. WordPress installation with the Minpaku Suite plugin activated
2. User account with `manage_options` capability (Administrator)
3. At least one `property` post type with status `publish`

## Test Procedures

### 1. Access Admin Settings Page

**Steps:**
1. Log in to WordPress admin dashboard
2. Navigate to the main menu and look for "Minpaku Suite"
3. Click on "Minpaku Suite" to access the settings page

**Expected Results:**
- "Minpaku Suite" menu item should be visible in the admin menu
- Clicking should load the settings page at `/wp-admin/admin.php?page=mcs-settings`
- Page should display "Minpaku Suite Settings" title

### 2. Test Settings Form

**Steps:**
1. On the settings page, locate the "General Settings" section
2. Test Export Disposition dropdown:
   - Change between "Inline" and "Attachment" options
3. Test Flush on Save checkbox:
   - Check/uncheck the option
4. Test Alert Settings:
   - Toggle "Enable Alerts" checkbox
   - Modify "Alert Threshold" (ensure minimum value is 1)
   - Modify "Cooldown Hours" (ensure minimum value is 1)
   - Update "Alert Recipient" email address
5. Click "Save Settings" button

**Expected Results:**
- All form fields should be functional
- Settings should save successfully with a success notice
- Page should reload showing the saved values
- Validation should prevent invalid values (negative numbers, invalid emails)

### 3. Test Mapping Regeneration

**Steps:**
1. Scroll to "Property Mappings" section
2. Note any existing mappings in the table
3. Click "Regenerate Mappings" button
4. Confirm the action in the popup dialog

**Expected Results:**
- Confirmation dialog should appear asking "Are you sure you want to regenerate all mappings?"
- Button should show "Processing..." during execution
- Success notice should appear showing number of mappings generated
- Page should reload automatically after 1.5 seconds
- Table should display updated mappings with:
  - Post ID column
  - Property Title column
  - ICS URL column (in format: `home_url/ics/property/{post_id}/{key}.ics`)
  - Actions column with "Copy URL" buttons

### 4. Test URL Copy Functionality

**Steps:**
1. In the mappings table, locate a "Copy URL" button
2. Click the button
3. Try to paste the copied content elsewhere (text editor, browser address bar)

**Expected Results:**
- Button should temporarily show "Copying..." then "Copied!"
- Button should change color to green (success state)
- After 2 seconds, button should return to original state
- The ICS URL should be successfully copied to clipboard
- Copied URL should be accessible via Ctrl+V/Cmd+V

### 5. Test Sync Now Functionality

**Steps:**
1. Scroll to "Sync Management" section or click "Sync Now" button near mappings
2. Click "Sync Now" button
3. Confirm the action in the popup dialog
4. Wait for the sync process to complete

**Expected Results:**
- Confirmation dialog should appear asking "Are you sure you want to sync all properties now?"
- Button should show "Processing..." during execution
- Success/warning notice should appear with sync results showing:
  - Number added
  - Number updated
  - Number skipped
  - Number not modified
  - Number of errors
- If `MCS_Sync` class is not available, appropriate error message should display

### 6. Test Ajax Error Handling

**Steps:**
1. Temporarily disable JavaScript in browser
2. Try clicking "Regenerate Mappings" or "Sync Now" buttons
3. Re-enable JavaScript
4. Try the actions again but with network disconnected
5. Reconnect network and retry

**Expected Results:**
- With JavaScript disabled: Fallback forms should work (hidden legacy forms)
- With network issues: Error notices should display appropriately
- All error states should be user-friendly and not expose technical details

### 7. Test Concurrent Operations Protection

**Steps:**
1. Open the settings page in two browser tabs
2. In tab 1, click "Regenerate Mappings"
3. Quickly switch to tab 2 and click "Regenerate Mappings" again
4. Repeat for "Sync Now" operations

**Expected Results:**
- Second operation should be blocked with message "Regeneration is already in progress" or "Sync is already in progress"
- Transient locks should prevent duplicate operations
- Operations should complete successfully in the first tab

### 8. Test Responsive Design

**Steps:**
1. Access the settings page on different screen sizes:
   - Desktop (1200px+ width)
   - Tablet (768px-1199px width)
   - Mobile (320px-767px width)
2. Test all functionality on each screen size

**Expected Results:**
- Table should remain readable on all screen sizes
- Buttons should be appropriately sized and positioned
- Copy buttons should remain functional
- Forms should be usable on mobile devices

### 9. Test Permissions

**Steps:**
1. Create a user with lower privileges (Editor, Author, etc.)
2. Log in as that user
3. Try to access `/wp-admin/admin.php?page=mcs-settings` directly
4. Check if "Minpaku Suite" menu appears for non-admin users

**Expected Results:**
- Non-admin users should not see the "Minpaku Suite" menu
- Direct access should be blocked with "You do not have sufficient permissions" message
- Ajax requests from non-admin users should be rejected

### 10. Test Data Persistence

**Steps:**
1. Configure all settings with specific values
2. Generate mappings
3. Deactivate and reactivate the plugin
4. Check if settings and mappings persist

**Expected Results:**
- All settings should persist in `mcs_settings` option
- Mappings should remain in the option
- Post meta `_ics_key` should persist on property posts
- No data loss should occur during plugin lifecycle

## Troubleshooting Common Issues

### JavaScript Not Loading
- Check browser console for 404 errors on admin.js
- Verify file exists at `/wp-content/plugins/minpaku-suite/assets/admin.js`
- Check for plugin conflicts disabling JavaScript

### CSS Not Loading
- Check browser network tab for 404 errors on admin.css
- Verify file exists at `/wp-content/plugins/minpaku-suite/assets/admin.css`
- Clear browser cache

### Ajax Requests Failing
- Check browser console for JavaScript errors
- Verify nonce values are being generated correctly
- Check WordPress admin-ajax.php is accessible
- Review server error logs for PHP errors

### Copy Function Not Working
- Test in different browsers (modern browsers vs older ones)
- Check if site is served over HTTPS (required for modern Clipboard API)
- Verify fallback copy method works in older browsers

### Settings Not Saving
- Check if user has `manage_options` capability
- Verify nonce validation is passing
- Review sanitization function for any issues
- Check for WordPress option update failures

## Performance Considerations

### Mapping Regeneration
- Monitor performance with large numbers of property posts (100+)
- Verify transient locks prevent concurrent operations
- Check memory usage during bulk operations

### Sync Operations
- Monitor sync duration for multiple mappings
- Verify timeout handling for slow external requests
- Check that locks prevent overlapping sync operations

## Browser Compatibility

Test in the following browsers:
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Internet Explorer 11 (if support required)

## Accessibility Testing

- Test keyboard navigation through all form elements
- Verify screen reader compatibility with ARIA labels
- Check color contrast for all UI elements
- Test with browser zoom up to 200%