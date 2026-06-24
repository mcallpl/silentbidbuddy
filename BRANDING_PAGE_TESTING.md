# Event Branding Page — Testing Guide

## Pre-Testing Setup

### Requirements
- Admin account with valid login
- At least one organization in the database
- Browser with HTML5 color input support
- Database write permissions

### Database Verification
```sql
-- Verify organizations exist
SELECT id, name, brand_primary, brand_accent FROM organizations LIMIT 1;

-- Expected output: Should have at least 1 row
-- Verify required columns exist
SHOW COLUMNS FROM organizations LIKE 'brand_%';
```

## Manual Testing Procedures

### 1. Access & Authentication

**Test Case 1.1: Unauthorized Access**
```
Step 1: Open /admin/event-branding.php WITHOUT logging in
Expected: Redirected to /admin.php (login page)
Result: ✓ / ✗
```

**Test Case 1.2: Authorized Access**
```
Step 1: Login as admin at /admin.php
Step 2: Navigate to /admin/event-branding.php
Expected: Full page loads, organizations visible
Result: ✓ / ✗
```

### 2. Organization Selection

**Test Case 2.1: Single Organization**
```
Step 1: If only 1 org exists, verify it's pre-selected
Step 2: Verify form fields are populated
Expected: Current org details display in form
Result: ✓ / ✗
```

**Test Case 2.2: Multiple Organizations**
```
Step 1: Verify all orgs show as buttons
Step 2: Click different org buttons
Step 3: Verify form updates for each selection
Step 4: Reload page with ?org_id=X parameter
Expected: 
  - Button shows active state
  - Form fields update
  - URL parameter persists selection
Result: ✓ / ✗
```

### 3. Color Picker Functionality

**Test Case 3.1: Color Picker Synchronization**
```
Step 1: Click color picker input for Primary Color
Step 2: Select a different color in native picker
Step 3: Verify hex input field updated
Step 4: Type new hex code in hex input (e.g., #FF0000)
Step 5: Verify color picker updated
Expected:
  - Both inputs show same value
  - Preview updates immediately
Result: ✓ / ✗
```

**Test Case 3.2: All Four Color Pickers**
```
Step 1: Change Primary Color (#172235 → #0066cc)
Step 2: Change Secondary Color (#f4f7f2 → #ffffcc)
Step 3: Change Accent Color (#d99a2b → #ff6600)
Step 4: Change Text Color (#172235 → #333333)
Expected:
  - All colors update in real-time
  - Preview reflects all changes
Result: ✓ / ✗
```

### 4. Form Validation

**Test Case 4.1: Invalid Hex Colors**
```
Step 1: In Primary Color hex input, type "red"
Expected: Form rejects, shows error or ignores invalid input
Step 2: Try typing "##0000ff" (double hash)
Expected: Rejected or corrected
Step 3: Try typing "#00000" (5 chars)
Expected: Rejected or shows error
Result: ✓ / ✗
```

**Test Case 4.2: Valid Hex Variations**
```
Step 1: Type "#ffffff" (lowercase)
Step 2: Verify color updates
Step 3: Try "#FFFFFF" (uppercase)
Step 4: Verify both work
Expected: Both uppercase and lowercase accepted
Result: ✓ / ✗
```

**Test Case 4.3: Organization Name Validation**
```
Step 1: Clear organization name field
Step 2: Try to save
Expected: Error message "Organization name is required"
Result: ✓ / ✗
```

**Test Case 4.4: Logo URL Validation**
```
Step 1: Enter invalid URL "not a url"
Step 2: Try to save
Expected: Error "Invalid logo URL"
Step 3: Enter "https://example.com/logo.png"
Step 4: Save (should work)
Expected: Success message
Result: ✓ / ✗
```

**Test Case 4.5: Email Validation**
```
Step 1: Enter invalid email "notanemail"
Step 2: Try to save
Expected: Error or validation fails
Step 3: Enter valid email "contact@example.com"
Step 4: Save should succeed
Expected: Success message
Result: ✓ / ✗
```

### 5. Live Preview Updates

**Test Case 5.1: Real-Time Color Preview**
```
Step 1: Watch the preview panel
Step 2: Change Primary Color slowly
Step 3: Observe preview updates without lag
Expected: Instant preview update as you type
Result: ✓ / ✗
```

**Test Case 5.2: Sample Item Card**
```
Step 1: Change colors while watching item card
Step 2: Verify border uses primary color
Step 3: Verify background uses secondary color
Step 4: Verify button uses accent color
Step 5: Verify text uses text color
Expected: 
  - Card border: primary color
  - Card background: secondary color
  - Button background: accent color
  - Text color: set text color
Result: ✓ / ✗
```

**Test Case 5.3: Color Palette Swatches**
```
Step 1: Verify 4 color swatches display
Step 2: Change all colors
Step 3: Verify swatch colors update
Step 4: Verify hex codes display for each
Expected: All swatches show current colors with hex codes
Result: ✓ / ✗
```

**Test Case 5.4: Organization Header Preview**
```
Step 1: Enter organization name and logo URL
Step 2: Set event date
Step 3: Verify org header shows:
  - Logo (or fallback initials)
  - Organization name
  - Event date
  - Primary color background
Expected: Header displays all elements with primary color BG
Result: ✓ / ✗
```

### 6. Save Functionality

**Test Case 6.1: Successful Save**
```
Step 1: Change color (e.g., Primary: #0066cc)
Step 2: Click "Save Branding" button
Step 3: Watch for loading state (spinner)
Step 4: Wait for success message
Step 5: Verify "✓ Branding settings saved successfully!"
Expected: Success message appears, button returns to normal
Result: ✓ / ✗
```

**Test Case 6.2: Database Verification**
```
Step 1: After successful save, check database
  SELECT brand_primary FROM organizations WHERE id = 1;
Step 2: Verify the color was saved
Expected: New color appears in database
Result: ✓ / ✗
```

**Test Case 6.3: Error Handling**
```
Step 1: Open network tab in developer tools
Step 2: Try to save with invalid hex (e.g., in network delay scenario)
Step 3: Verify error message appears
Expected: Error message displays to user, save fails gracefully
Result: ✓ / ✗
```

**Test Case 6.4: Save Button States**
```
Step 1: Initial state: button enabled and clickable
Step 2: Click save
Step 3: During save: button disabled, loading spinner shows
Step 4: After save: button re-enabled, spinner hidden
Expected: Button state changes correctly through lifecycle
Result: ✓ / ✗
```

### 7. Reset to Defaults

**Test Case 7.1: Reset Button**
```
Step 1: Change all colors to random values
Step 2: Click "Reset to Defaults" button
Step 3: Confirm the alert dialog
Step 4: Verify colors reset to defaults:
  - Primary: #172235
  - Secondary: #f4f7f2
  - Accent: #d99a2b
  - Text: #172235
Expected: All colors return to defaults, preview updates
Result: ✓ / ✗
```

**Test Case 7.2: Cancel Reset**
```
Step 1: Change colors
Step 2: Click "Reset to Defaults"
Step 3: Click "Cancel" in alert
Expected: Colors remain unchanged
Result: ✓ / ✗
```

### 8. Contrast Warning

**Test Case 8.1: Contrast Detection**
```
Step 1: Set text color to #ffffff (white)
Step 2: Set secondary color to #ffff00 (yellow)
Step 3: The contrast warning should NOT appear (good contrast)
Step 4: Set text color to #cccccc (light gray)
Step 5: Set secondary color to #dddddd (lighter gray)
Step 6: The contrast warning should appear
Expected: Warning shows when contrast is low
Result: ✓ / ✗
```

### 9. Event Management (if applicable)

**Test Case 9.1: Event Selection**
```
Step 1: If events exist, verify dropdown shows them
Step 2: Select an event
Step 3: Verify event details might populate
Expected: Event list displays, selection works
Result: ✓ / ✗
```

**Test Case 9.2: Event Date**
```
Step 1: Click event date picker
Step 2: Select a date
Step 3: Verify preview updates with date
Expected: Date displays in organization header preview
Result: ✓ / ✗
```

### 10. Responsive Design Testing

**Test Case 10.1: Desktop (1024px+)**
```
Browser window: 1400px wide
Step 1: Open page
Step 2: Verify 2-column layout (form + sticky preview)
Step 3: Scroll down, preview stays visible on right
Step 4: All elements properly sized
Expected: Perfect 2-column layout, preview sticky
Result: ✓ / ✗
```

**Test Case 10.2: Tablet (768px-1023px)**
```
Browser window: 800px wide
Step 1: Open page
Step 2: Verify 2-column layout if space allows
Step 3: Or verify single column with form first
Step 4: Preview scrolls with page
Step 5: All elements readable
Expected: Optimized tablet view
Result: ✓ / ✗
```

**Test Case 10.3: Mobile (< 768px)**
```
Browser window: 375px wide (iPhone size)
Step 1: Open page
Step 2: Verify single column layout
Step 3: Verify form fills width
Step 4: Verify all buttons are 44px+ height
Step 5: Verify text is readable
Step 6: Verify color pickers work on touch
Expected: Fully mobile-optimized, single column
Result: ✓ / ✗
```

### 11. Browser Compatibility

**Test Case 11.1: Chrome/Chromium**
```
Browser: Chrome latest
Step 1: Load page
Step 2: Test all features
Expected: All features work
Result: ✓ / ✗
```

**Test Case 11.2: Firefox**
```
Browser: Firefox latest
Step 1: Load page
Step 2: Test color picker
Step 3: Test all form features
Expected: All features work
Result: ✓ / ✗
```

**Test Case 11.3: Safari**
```
Browser: Safari latest
Step 1: Load page
Step 2: Test color picker
Step 3: Verify CSS Grid works
Expected: All features work
Result: ✓ / ✗
```

**Test Case 11.4: Mobile Safari (iOS)**
```
Device: iPhone
Step 1: Navigate to page
Step 2: Test color picker on touch device
Step 3: Test form submission
Expected: All features work on mobile
Result: ✓ / ✗
```

## Automated Testing

### Browser Console Checks

Run these in browser console:

**Check 1: Validate page loaded**
```javascript
console.log(document.getElementById('brandingForm') ? '✓ Form loaded' : '✗ Form missing');
console.log(document.getElementById('previewItemCard') ? '✓ Preview loaded' : '✗ Preview missing');
```

**Check 2: Validate color inputs**
```javascript
const colorInputs = document.querySelectorAll('input[type="color"]');
console.log(`✓ Found ${colorInputs.length} color pickers (expected 4)`);
```

**Check 3: Test color sync**
```javascript
const primaryPicker = document.getElementById('primaryColor');
const primaryHex = document.getElementById('primaryColorHex');
primaryPicker.value = '#FF0000';
primaryPicker.dispatchEvent(new Event('input'));
console.log(primaryHex.value === '#FF0000' ? '✓ Sync works' : '✗ Sync failed');
```

### Network Monitoring

**Check API Response**
```
1. Open DevTools → Network tab
2. Change color and save
3. Look for POST to /api/admin/update-branding.php
4. Verify response:
   - Status: 200
   - Body: {"status":"ok",...}
```

## Database Verification Queries

```sql
-- Verify save successful
SELECT id, name, brand_primary, brand_accent, logo_url, updated_at
FROM organizations 
WHERE id = 1;

-- Check update timestamp is recent
SELECT TIMESTAMPDIFF(SECOND, updated_at, NOW()) as seconds_ago
FROM organizations
WHERE id = 1;
-- Should be < 10 seconds if just saved

-- Verify event creation (if tested)
SELECT id, name, slug, event_date, status
FROM events
WHERE organization_id = 1
ORDER BY created_at DESC
LIMIT 1;
```

## Performance Testing

**Test Case: Load Time**
```
Step 1: Open DevTools → Performance
Step 2: Record page load
Step 3: Navigate to event-branding.php
Step 4: Stop recording
Expected: Load time < 2 seconds
```

**Test Case: Preview Update Performance**
```
Step 1: Open Performance tab
Step 2: Start recording
Step 3: Change primary color multiple times rapidly
Step 4: Stop recording
Expected: Updates happen without jank, smooth 60fps
```

## Accessibility Testing

**Test Case: Keyboard Navigation**
```
Step 1: Unplug mouse, use Tab key
Step 2: Tab through all form elements
Step 3: Use arrow keys to change values
Step 4: Use Space/Enter to click buttons
Expected: All elements accessible via keyboard
```

**Test Case: Screen Reader**
```
Step 1: Use screen reader (NVDA, JAWS, or VoiceOver)
Step 2: Navigate page
Step 3: Verify all labels read correctly
Expected: Form is usable with screen reader
```

## Error Scenarios

**Scenario 1: Database Down**
```
Step 1: Stop MySQL server
Step 2: Try to save
Expected: Error message "Failed to update organization"
Step 3: Restart MySQL
Result: ✓ / ✗
```

**Scenario 2: Network Failure**
```
Step 1: Open DevTools → Network
Step 2: Set throttling to "Offline"
Step 3: Try to save
Expected: Error message about network
Step 4: Go back online
Result: ✓ / ✗
```

**Scenario 3: Session Expired**
```
Step 1: Open page and make changes
Step 2: Delete admin session cookie
Step 3: Try to save
Expected: Session expired error, redirect to login
Result: ✓ / ✗
```

## Performance Benchmarks

| Metric | Target | Result |
|--------|--------|--------|
| Page Load Time | < 2s | |
| Time to Interactive | < 3s | |
| First Paint | < 1s | |
| Preview Update Lag | < 100ms | |
| Save Response Time | < 1s | |
| Color Picker Open | < 500ms | |

## Sign-Off Checklist

- [ ] All 11 test sections passed
- [ ] No console errors
- [ ] Database updates verified
- [ ] Mobile responsive works
- [ ] All browsers tested
- [ ] Error handling verified
- [ ] Performance acceptable
- [ ] Accessibility checked
- [ ] Security validated
- [ ] Documentation accurate

## Known Issues & Workarounds

### Issue: Color picker doesn't open on some browsers
**Workaround:** Use hex input instead of color picker

### Issue: Preview not updating
**Workaround:** Check browser console for JavaScript errors, refresh page

### Issue: Logo URL not displaying
**Workaround:** Ensure URL is HTTPS, verify CORS headers

## Support

For issues, check:
1. Browser console (F12 → Console tab)
2. Network tab for failed requests
3. PHP error logs at `/logs/error.log`
4. Database error log: `SHOW ENGINE INNODB STATUS;`

---

**Last Updated:** June 24, 2026
**Status:** Ready for testing
