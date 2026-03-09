# Email Configuration Setup

## Problem Fixed
The verification emails were not being sent because the system was trying to use `sendmail`, which isn't properly configured on most servers. I've updated the code to use **SMTP** instead, which is much more reliable.

## How to Configure Email

You need to set **environment variables** on your server. Here are your options:

### Option 1: Gmail SMTP (Free)

1. Go to https://myaccount.google.com/apppasswords
2. Generate an "App Password" (you may need to enable 2FA first)
3. Copy the 16-character password
4. Add these environment variables to your server:

```
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-16-char-app-password
FROM_EMAIL=your-email@gmail.com
FROM_NAME=Lokale Tjenester
DOMAIN=your-domain.no
```

### Option 2: Mailtrap (Free Trial, Best for Testing)

1. Sign up at https://mailtrap.io
2. Create a new inbox
3. Click "Integrations" → "PHPMailer"
4. Copy the SMTP credentials
5. Add these environment variables:

```
SMTP_HOST=live.smtp.mailtrap.io
SMTP_PORT=587
SMTP_USER=your-mailtrap-username
SMTP_PASS=your-mailtrap-password
FROM_EMAIL=noreply@your-domain.no
FROM_NAME=Lokale Tjenester
DOMAIN=your-domain.no
```

### Option 3: SendGrid (Paid, Most Reliable)

1. Sign up at https://sendgrid.com
2. Create an API key
3. Add these environment variables:

```
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_USER=apikey
SMTP_PASS=your-sendgrid-api-key
FROM_EMAIL=noreply@your-domain.no
FROM_NAME=Lokale Tjenester
DOMAIN=your-domain.no
```

## How to Set Environment Variables

### On Shared Hosting (cPanel, Plesk, etc.)

Most hosting providers have a control panel where you can set environment variables. Look for:
- Environment Variables section
- Custom PHP settings
- .env file support

You can also create a `.env` file in your project root (but you need to load it in display.php):

```php
// Add this near the top of display.php if using .env file
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}
```

### On Docker/Linux

Export the variables before running your app:
```bash
export SMTP_HOST=smtp.gmail.com
export SMTP_PORT=587
export SMTP_USER=your-email@gmail.com
export SMTP_PASS=your-app-password
export FROM_EMAIL=your-email@gmail.com
export FROM_NAME="Lokale Tjenester"
export DOMAIN=your-domain.no
```

## Testing Email Sending

After setting environment variables:

1. **Restart your web server** (important!)
2. Go to the signup page and create a new account
3. You should see a **green notification popup** saying "Bekreftelsesmail sendt" (Verification email sent)
4. Check your email inbox (and spam folder) for the verification email
5. Check the **error logs** if emails still don't arrive:
   - PHP error log: `/path/to/php_errors.log`
   - File is created in the Finn-Hustle folder

## What Changed

### In `display.php`:
- Updated `send_verification_email()` function to:
  - Try SMTP first if environment variables are configured
  - Fall back to sendmail if SMTP isn't configured
  - Better error logging for debugging
  - Improved email template in Norwegian

### In `script.js`:
- Added `showNotification()` function for toast-style notifications
- Shows green "Email Sent" notification on signup
- Shows "Email Resent" notification on resend button click
- Shows error notifications if email fails

### In `style.css`:
- Added `slideInRight` animation for smooth notification appearance

## Troubleshooting

### "Verification email sent" but it's not arriving?

1. **Check spam folder** - Especially with Gmail
2. **Check environment variables** - Verify they're set correctly:
   ```php
   echo getenv('SMTP_HOST'); // This should print your SMTP host
   ```
3. **Check error logs** - PHP errors are logged to `php_errors.log`
4. **Test SMTP credentials** directly - Verify they work with a mail client first
5. **Check sender email** - Some SMTP servers don't allow arbitrary FROM addresses

### Email sends but user never receives it?

1. Check your email provider's quota/rate limits
2. Check if the domain in the verification link is correct
3. Look at SMTP provider's logs (Gmail, Mailtrap, SendGrid all have dashboards)

## Support

You can always check the verification email manually on the settings page by simply clicking "Resend Verification Email" and watching the notification popup.

If the notification doesn't appear, check browser console (F12 → Console tab) for JavaScript errors.
