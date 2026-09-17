# Email Verification System Documentation

## Overview
CampMart now has a comprehensive email verification system that ensures users verify their email addresses upon signup. This helps build trust in the marketplace and ensures valid contact information.

## Features Implemented

### 1. **Welcome Email with Verification Link**
- Upon signup, users receive a beautifully designed welcome email
- Email includes:
  - Personalized greeting
  - Clear call-to-action button for verification
  - Overview of CampMart features
  - Alternative text link (in case button doesn't work)
  - 24-hour expiration notice
  - Professional branded template

### 2. **Email Verification Handler**
- **File:** `verify-email.php`
- Processes verification tokens from email links
- Handles various scenarios:
  - ✅ **Success:** Email verified successfully
  - ❌ **Invalid Token:** Token doesn't exist or already used
  - ⌛ **Expired Token:** Link expired (>24 hours old)
  - ✓ **Already Verified:** Email was previously verified
- User-friendly success and error messages
- Automatic redirects after verification

### 3. **Resend Verification Functionality**
- Users can request new verification emails
- Available in two locations:
  - **My Profile page:** "Verify Now" button
  - **Verification page:** When link expires
- Generates new token with fresh 24-hour expiry
- Prevents spam by checking if already verified

### 4. **Verification Status Banners**
- **Dashboard Banner** (user-nav.php):
  - Shows yellow alert banner for unverified users
  - Quick "Verify Now" button
  - Dismissible on desktop
  
- **Public Pages Banner** (header.php):
  - Appears for logged-in unverified users
  - Gradient design with icons
  - Mobile-responsive

### 5. **Profile Verification Status**
- **My Profile page** shows verification status:
  - ✅ Green badge when verified
  - ⚠️ Yellow warning when not verified
  - Direct verification action button

## Database Schema

### New Columns Added to `users` Table:
```sql
- email_verified (TINYINT, default: 0)
- email_verification_token (VARCHAR(255), nullable)
- email_verification_expiry (DATETIME, nullable)
- phone_verified (TINYINT, default: 0) [for future use]
```

### Migration File:
- **Location:** `database/add_email_verification.sql`
- Run this SQL script to add the required columns
- Includes index for faster token lookups
- Optional: Auto-verify existing users (commented out)

## Files Modified/Created

### New Files:
1. **verify-email.php** - Email verification handler page
2. **database/add_email_verification.sql** - Database migration
3. **EMAIL_VERIFICATION.md** - This documentation

### Modified Files:
1. **includes/function.php**
   - Added `sendWelcomeEmail()` function
   - Added `resendVerificationEmail()` function

2. **includes/controller.php**
   - Modified `SignupUser()` to generate token and send email
   - Added `ResendVerification()` method
   - Added handler in constructor

3. **my-profile.php**
   - Made "Verify Now" button functional with form submission

4. **includes/user-nav.php**
   - Added verification banner for unverified users

5. **includes/header.php**
   - Added verification banner for public pages

## How to Use

### For Developers:

1. **Run the Database Migration:**
   ```sql
   -- Execute the SQL file
   mysql -u your_user -p your_database < database/add_email_verification.sql
   ```

2. **Configure Mail Settings:**
   - Ensure PHP `mail()` function is configured on your server
   - For production, consider using SMTP (PHPMailer recommended)
   - Update email addresses in `sendWelcomeEmail()` if needed

3. **Test the Flow:**
   - Create a new account on signup.php
   - Check email for verification link
   - Click link to verify
   - Test resend functionality
   - Check banners appear/disappear correctly

### For Users:

1. **Sign up** on the platform
2. **Check your email** for the welcome message
3. **Click "Verify Email Address"** button in the email
4. **Or manually visit** the verification link if button doesn't work
5. If email expired, **click "Resend Verification"** on any page

## Email Configuration

### Current Settings:
- **From:** CampMart <noreply@campmart.ng>
- **Reply-To:** support@campmart.ng
- **Token Expiry:** 24 hours
- **Token Length:** 64 characters (hex)

### Customization:
To change email branding or content, edit the `sendWelcomeEmail()` function in `includes/function.php`.

## Security Features

1. **Token-based verification:**
   - Cryptographically secure random tokens (32 bytes)
   - One-time use tokens (deleted after verification)
   
2. **Expiry mechanism:**
   - Tokens expire after 24 hours
   - Prevents stale links from being used

3. **CSRF Protection:**
   - All resend forms include CSRF tokens
   - Validates on submission

4. **SQL Injection Prevention:**
   - All database queries use prepared statements
   - Input sanitization on all user data

## User Flow Diagram

```
┌─────────────┐
│   Sign Up   │
└──────┬──────┘
       │
       ▼
┌─────────────────────────┐
│ Account Created         │
│ Email Sent              │
│ (with verification link)│
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│ User Clicks Email Link  │
└──────┬──────────────────┘
       │
       ▼
   ┌───┴────┐
   │ Valid? │
   └───┬────┘
       │
    Yes│         No (Invalid/Expired)
       │                  │
       ▼                  ▼
┌──────────────┐   ┌─────────────────┐
│ Email        │   │ Show Error      │
│ Verified ✓   │   │ Offer Resend    │
└──────────────┘   └─────────────────┘
```

## Future Enhancements

### Potential Improvements:
1. **SMS Verification** - Use the `phone_verified` column
2. **Email Templates** - Create more email templates for different actions
3. **Verification Badges** - Show verified badge on user profiles
4. **Reminder Emails** - Send reminders after X days if not verified
5. **SMTP Integration** - Use PHPMailer for better email delivery
6. **Email Logging** - Track sent emails in database
7. **Verification Required** - Restrict certain features until verified

## Troubleshooting

### Common Issues:

1. **Emails Not Sending:**
   - Check PHP mail() configuration
   - Verify server can send emails
   - Check spam folder
   - Consider using SMTP

2. **Tokens Not Working:**
   - Verify database columns exist
   - Check token length (should be 64 chars)
   - Ensure no URL encoding issues

3. **Banner Not Showing:**
   - Clear browser cache
   - Check if user session is active
   - Verify email_verified field in database

4. **Already Verified Error:**
   - User may have verified earlier
   - Check database `email_verified` value
   - This is expected behavior

## Support

For issues or questions:
- Check the code comments in modified files
- Review error messages in browser console
- Check PHP error logs
- Contact: support@campmart.ng

## Version History

- **v1.0** (2026-02-16): Initial implementation
  - Welcome email with verification
  - Verification handler page
  - Resend functionality
  - Status banners
  - Profile integration

---

**Last Updated:** February 16, 2026
**Author:** CampMart Development Team
