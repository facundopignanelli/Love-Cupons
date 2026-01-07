# Multi-User Coupon Assignment Implementation

## Overview
This document outlines the changes made to support multiple users creating and assigning coupons to specific users.

## Features Added

### 1. **Coupon Assignment System**
- Each coupon can now be assigned to specific users
- If no users are assigned, the coupon is available to all logged-in users
- Admins can always see all coupons

### 2. **User Tracking**
- `_pup_coupon_created_by` - Tracks which user created the coupon (set on first save)
- `_pup_coupon_assigned_to` - Stores an array of user IDs who can access/redeem the coupon

### 3. **Admin Interface Enhancements**
- New meta box: "Assign To Users" - allows creators to select specific users
- New admin column: "Assigned To" - displays which users the coupon is assigned to
- New meta box field: "Created By" - shows who created the coupon
- Interactive checkbox list with user count display

### 4. **Access Control**
- Frontend: Users only see coupons assigned to them
- AJAX: Redemption blocked if user doesn't have access to the coupon
- Admin override: Administrators can see all coupons regardless of assignment

## Files Modified

### 1. **includes/class-config.php**
**New Meta Fields:**
```php
'_pup_coupon_created_by' => array(
    'type' => 'number',
    'sanitize' => 'absint',
    'label' => 'Created By (User ID)',
),
'_pup_coupon_assigned_to' => array(
    'type' => 'array',
    'sanitize' => array( 'Pup_Coupons_Config', 'sanitize_user_ids' ),
    'label' => 'Assigned To (User IDs)',
),
```

**New Helper Method:**
```php
public static function sanitize_user_ids( $user_ids )
```
- Validates and sanitizes arrays of user IDs
- Checks that users exist in the database
- Removes duplicates

### 2. **pup-coupons.php**

**Modified Methods:**

#### `add_meta_boxes()`
- Added new meta box for assigning users

#### `render_recipients_meta_box()` (NEW)
- Displays all site users in a scrollable checkbox list
- Shows real-time count of selected users
- Users see their display name and email address

#### `render_settings_meta_box()`
- Now displays "Created By" information
- Shows the creator's display name

#### `add_admin_columns()`
- Added "Assigned To" column between Title and Status

#### `render_admin_columns()`
- New case for 'assigned_to' column
- Shows "All Users" if no specific assignment
- Lists assigned user names comma-separated

#### `save_meta()`
- Now saves `_pup_coupon_assigned_to` meta field
- Validates user IDs when saving
- Automatically tracks creator on first save via `_pup_coupon_created_by`

#### `display_coupons_shortcode()`
- Added user access check before displaying coupons
- Only shows coupons the user has access to
- Maintains existing sorting (available, redeemed, expired)

#### `ajax_redeem_coupon()`
- Added user access check before redemption allowed
- Returns error if user tries to redeem unauthorized coupon

#### `user_can_access_coupon()` (NEW)
```php
private function user_can_access_coupon( $coupon_id, $user_id )
```
- Checks if a user has access to a specific coupon
- Admins always have access
- Returns true if no assignment restrictions exist
- Otherwise checks if user is in the assigned list

## How It Works

### Scenario 1: Creating a Coupon for Specific Users
1. User with edit capabilities creates a coupon
2. Goes to "Assign To Users" meta box
3. Selects specific users from the checkbox list
4. Saves the coupon
5. Selected users see the coupon on their coupon page
6. Other users don't see it

### Scenario 2: Coupon Available to Everyone
1. User creates a coupon
2. Leaves "Assign To Users" empty (no checkboxes selected)
3. All logged-in users can see and redeem the coupon

### Scenario 3: Admin Management
1. Admin can see all coupons regardless of assignment
2. Can see who each coupon is assigned to in the admin columns
3. Can see who created each coupon in the settings sidebar

## Database Schema

### Meta Fields
```
Post Meta Key               | Type    | Description
---------------------------|---------|----------------------------------
_pup_coupon_created_by      | int     | User ID of coupon creator
_pup_coupon_assigned_to     | array   | Array of user IDs with access
```

## Security Considerations

✅ **User Validation** - User IDs are validated against existing users
✅ **Nonce Verification** - Meta box uses nonces for CSRF protection
✅ **Capability Checks** - Admin operations require proper capabilities
✅ **Sanitization** - All input is properly sanitized
✅ **Access Control** - Frontend filters display based on assignments
✅ **AJAX Protection** - Redemption checks user access before processing

## Backwards Compatibility

- Existing coupons without assignment restrictions work as before
- New fields are optional (empty array = available to all)
- No breaking changes to existing functionality

## Future Enhancements

Potential features to consider:
- Role-based assignment (assign by user role instead of individual users)
- Group assignment (create groups of users)
- Time-based assignment (available to specific users during certain periods)
- Bulk assignment operations in admin
- Assignment history/audit log
- Email notifications when assigned to users
