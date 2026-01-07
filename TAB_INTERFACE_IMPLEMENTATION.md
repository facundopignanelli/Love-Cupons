# Multi-User Tab-Based Coupon System - Implementation Guide

## Overview
Complete redesign of the Love Coupons plugin to support a peer-to-peer coupon system with an intuitive tab-based interface. All users can create and redeem coupons, with admin control over posting permissions.

## Major Changes

### 1. **User Capabilities**
- ✅ All users can create and redeem coupons (not just admins)
- ✅ Removed role-based restrictions
- ✅ Permissions managed through admin panel

### 2. **Tab-Based Interface**
Two separate tabs on the frontend:

#### **Tab 1: "My Coupons"**
- Shows coupons the user can redeem (coupons assigned to them)
- Organized by status: Available, Redeemed, Expired
- Users can click to redeem available coupons
- Clean, organized display

#### **Tab 2: "Posted Coupons"**
- Shows coupons the user has created
- Displays recipient information
- Shows redemption status for each coupon
- Includes a form to create new coupons
- Users can only create coupons to allowed recipients

### 3. **Frontend Coupon Creation**
Users can create coupons directly from the "Posted Coupons" tab:
- **Coupon Title** (required)
- **Send To** - Select recipient user (required, dropdown)
- **Terms & Conditions** (optional, textarea)
- **Expiry Date** (optional, date picker)
- **Usage Limit** (default: 1, number input)

Form submissions are handled via AJAX with:
- Real-time validation
- User-friendly error messages
- Success feedback
- Page refresh after creation

### 4. **Admin Permissions Management**
New admin page: **Coupons > Posting Permissions**

Admins can control:
- Which users can post coupons
- Who each user can send coupons to
- Options:
  - **"Can post to all users"** - User can create coupons for any other user
  - **"Select specific users"** - User can only post to selected users

### 5. **One-to-One Coupon System**
- Each coupon is created for ONE specific user
- User selects recipient when creating coupon
- Recipient is stored in `_love_coupon_assigned_to` array (single user)
- Cannot be shared with multiple users per coupon

## Files Modified

### **love-coupons.php** (Main Plugin File)
**New Hooks:**
- `admin_menu` - Register admin settings page

**New AJAX Actions:**
- `wp_ajax_love_coupons_create` - Handle frontend coupon creation

**New Methods:**

#### Frontend Methods:
- `display_coupons_shortcode()` - Updated to show tabs
- `render_my_coupons()` - Tab 1: Display redeemable coupons
- `render_posted_coupons()` - Tab 2: Show user's created coupons
- `render_create_coupon_form()` - Coupon creation form UI
- `get_allowed_recipients_for_user()` - Get users current user can post to
- `ajax_create_coupon()` - AJAX handler for form submission

#### Admin Methods:
- `add_admin_settings_page()` - Register admin menu item
- `render_admin_settings_page()` - Admin permissions UI and form handling

### **assets/css/love-coupons.css**
**New Styles:**
- `.love-coupons-tabs` - Tab navigation bar
- `.love-tab-button` - Individual tab buttons
- `.love-tab-pane` - Tab content containers
- `.love-create-coupon-form` - Form styling
- `.form-group`, `.form-row` - Form layout
- `.love-coupon-posted-item` - Posted coupon display
- `.love-posted-coupons-wrapper` - Container for posted coupons section
- Form focus states and validation styles
- Mobile responsive adjustments

### **love-coupons.js**
**New Methods:**
- `handleCreateCoupon()` - Form submission handler
- `showFormError()` - Display form error messages

**Updated Methods:**
- `bindEvents()` - Added form submission listener

**Features:**
- AJAX form submission with loading states
- Error handling and user feedback
- Form validation
- Auto page refresh after success

### **includes/class-config.php**
No changes required - existing meta fields support new functionality

## Database Structure

### WordPress Options
```
Option Name: love_coupons_posting_restrictions
Structure: array(
    $user_id => array(
        'all',  // Can post to everyone, OR
        $recipient_id_1,
        $recipient_id_2,
        ...
    )
)
```

### Post Meta
Existing meta fields used:
- `_love_coupon_created_by` - Creator user ID
- `_love_coupon_assigned_to` - Array with single recipient user ID
- `_love_coupon_terms` - Terms & conditions
- `_love_coupon_expiry_date` - Expiry date
- `_love_coupon_usage_limit` - Usage count limit
- `_love_coupon_redeemed` - Redemption status
- `_love_coupon_redemption_date` - When redeemed
- `_love_coupon_redemption_count` - Times redeemed

## How It Works

### User Journey - Creating a Coupon
1. User logs in and navigates to coupon page
2. Clicks "Posted Coupons" tab
3. Fills out coupon form:
   - Enters title
   - Selects recipient from allowed list
   - (Optional) Adds terms and expiry
4. Submits form
5. Form submitted via AJAX
6. Backend validates:
   - User has permission to post to recipient
   - All required fields filled
   - Recipient exists
7. Coupon created and published
8. Success message shown
9. Page refreshes to show new coupon

### User Journey - Redeeming a Coupon
1. User sees coupons assigned to them in "My Coupons" tab
2. Under "Available" section
3. Clicks "Redeem" button
4. Confirms redemption
5. Backend validates user can access coupon
6. Coupon marked as redeemed
7. Moves to "Redeemed" section

### Admin Journey - Setting Permissions
1. Admin goes to **Coupons > Posting Permissions**
2. Sees all users listed
3. For each user, can set:
   - Can post to all users (checkbox)
   - OR Select specific users from list
4. Saves permissions
5. Users can now only post to configured recipients

## Security Features

✅ **AJAX Nonce Verification** - Prevents CSRF attacks
✅ **Capability Checks** - Admin-only for settings page
✅ **User Validation** - All user IDs validated
✅ **Permission Validation** - Checks if user can post before creating
✅ **Input Sanitization** - All form inputs sanitized
✅ **Output Escaping** - All displayed data properly escaped
✅ **Access Control** - Users can only see assigned coupons

## Accessibility Features

✅ **Tab Navigation** - Keyboard accessible
✅ **ARIA Labels** - Screen reader support
✅ **Form Labels** - Proper label associations
✅ **Error Messages** - Clear, actionable feedback
✅ **Focus Management** - Clear focus indicators
✅ **Responsive Design** - Mobile-friendly interface

## Default Behavior

**If No Restrictions Set:**
- Users can post to any other user by default
- Can select any recipient in the dropdown

**If Restrictions Set:**
- Only allowed recipients appear in dropdown
- Cannot create coupons outside allowed set

## Frontend Shortcode

```
[love_coupons]
```

Attributes (optional):
- `limit` - Number of coupons per query (default: -1)
- `show_expired` - Show expired coupons (default: yes)

## Styling Customization

Main colors:
- Primary: `#2c6e49` (green)
- Error: `#d63638` (red)
- Success: `#00a32a` (green)

Key CSS classes for customization:
- `.love-coupons-wrapper` - Main container
- `.love-coupons-tabs` - Tab navigation
- `.love-tab-button` - Tab buttons
- `.love-tab-pane` - Tab content
- `.love-create-coupon-form` - Creation form
- `.love-coupons-grid` - Coupon grid layout

## Future Enhancements

Potential features to consider:
- Coupon templates (pre-made coupon types)
- Bulk coupon creation
- Coupon scheduling (send at specific date)
- Email notifications when coupons received/redeemed
- Coupon history/archive
- Search and filter in tabs
- Coupon copy/duplicate function
- Analytics dashboard for admins
- Coupon expiration reminders
- Redemption confirmations/proof

## Notes

- Coupons default to published status immediately
- Users who create coupons are tracked automatically
- The system prevents self-assignment (users can't send to themselves)
- All database operations are properly validated
- Forms use AJAX to avoid page reloads during submission
