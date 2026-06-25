# Enterns Tech WordPress Launch Package

This repository contains the WordPress code needed to run the Enterns Tech website,
student portal, mentor portal, partner application flow, standalone admin portal,
Razorpay payment flow, and FTP-based deployment to Bluehost.

The Bluehost layout shown in cPanel is:

```text
public_html/
  admin-portal/
  enternstech/
    wp-admin/
    wp-content/
    wp-includes/
    wp-config.php
```

The GitHub Actions workflow is already configured for that layout.

## What Is Included

```text
.
  .github/workflows/deploy.yml
  admin-portal/
    index.php
    config.example.php
  wp-content/
    themes/enternstech/
      front-page.php
      functions.php
      assets/
      style.css
    plugins/enterns-portal/
      enterns-portal.php
      includes/
      templates/
      assets/
```

The public website is served by the `enternstech` theme. The portal features are in
the `enterns-portal` plugin. The standalone management dashboard is deployed to
`/admin-portal/`.

## Portal URLs

These pages are created automatically when the `Enterns Portal` plugin is activated:

| Feature | URL | Notes |
|---|---|---|
| Public website | `/` | Theme front page |
| Admin portal page | `/et-admin/` | WP admin users only; links to standalone portal |
| Standalone admin portal | `/admin-portal/` | Password from `admin-portal/config.php` |
| Mentor portal | `/mentor/` | Requires `et_mentor` role |
| Student portal | `/student/` | Requires `et_student` role |
| Partner / mentor application | `/partner-with-us/` | Public mentor application form |
| Psychometric assessment | `/psy-assessment/` | Token-based candidate assessment |

## Payment Gateway

The active payment integration is Razorpay.

Add these constants to the live WordPress `wp-config.php` file, above the
`/* That's all, stop editing! */` line:

```php
define( 'ENP_RAZORPAY_KEY_ID',     'rzp_test_xxxxxxxxxxxxx' );
define( 'ENP_RAZORPAY_KEY_SECRET', 'xxxxxxxxxxxxxxxxxxxxxxx' );
```

When Razorpay is configured, the homepage enrol buttons create Razorpay orders
through WordPress AJAX. After successful signature verification, the plugin:

- marks the payment as paid
- creates or updates the WordPress student user
- assigns the `et_student` role
- creates or updates the student profile row
- emails a set-password link for new users

Use Razorpay test keys first, then switch to live keys when you are ready.

## Email / SMTP

Student activation, mentor approval, assessment links, and admin notifications use
WordPress mail. Configure SMTP in live `wp-config.php`:

```php
define( 'ENP_SMTP_HOST',      'mail.enternstech.com' );
define( 'ENP_SMTP_PORT',      465 );
define( 'ENP_SMTP_ENCRYPT',   'ssl' );
define( 'ENP_SMTP_USER',      'admin@enternstech.com' );
define( 'ENP_SMTP_PASS',      'REPLACE_WITH_MAILBOX_PASSWORD' );
define( 'ENP_SMTP_FROM',      'admin@enternstech.com' );
define( 'ENP_SMTP_FROM_NAME', 'Enterns Tech' );
```

After activation, open WordPress Admin -> Enterns Portal to see SMTP status and send
a test email.

## Public Contact Form

The homepage consultation form still posts to FormSubmit at `info@enternstech.com`.
After the site is live, submit the contact form once and click the confirmation email
from FormSubmit. Until that confirmation is completed, FormSubmit may not deliver
website enquiries.

## Standalone Admin Portal Setup

The workflow deploys `admin-portal/` to:

```text
public_html/admin-portal/
```

On the server, copy `admin-portal/config.example.php` to `admin-portal/config.php`
and fill in the real values:

```php
define('ADMIN_PASSWORD', 'choose-a-strong-password');
define('DB_HOST',   'localhost');
define('DB_NAME',   'your_wordpress_database_name');
define('DB_USER',   'your_database_username');
define('DB_PASS',   'your_database_password');
define('DB_PREFIX', 'wp_');
```

The portal now auto-detects WordPress at `public_html/enternstech/wp-load.php`.
If your Bluehost path differs, add:

```php
define('ENP_WP_LOAD_PATH', '/home/ACCOUNT/public_html/enternstech/wp-load.php');
```

Do not commit `admin-portal/config.php`; it is ignored by Git.

## Deploy Through GitHub Actions

The workflow uploads only the custom code:

| Local folder | Live folder |
|---|---|
| `wp-content/themes/enternstech/` | `/public_html/enternstech/wp-content/themes/enternstech/` |
| `wp-content/plugins/enterns-portal/` | `/public_html/enternstech/wp-content/plugins/enterns-portal/` |
| `admin-portal/` | `/public_html/admin-portal/` |

Add these GitHub repo secrets under Settings -> Secrets and variables -> Actions:

```text
FTP_SERVER
FTP_USERNAME
FTP_PASSWORD
```

Then push to `main`. The workflow first lints PHP and runs the psychometric scorer
test; if validation passes, it deploys automatically.

If this folder is not yet connected to GitHub:

```bash
git init
git add .
git commit -m "Prepare Enterns Tech WordPress launch"
git branch -M main
git remote add origin https://github.com/bhaumikpatel81-max/EnternsTech.git
git push -u origin main
```

## First Live Activation Checklist

1. Push the repo to GitHub and confirm the FTP deploy workflow succeeds.
2. In WordPress Admin, activate the `EnternsTech` theme.
3. In WordPress Admin -> Plugins, activate `Enterns Portal`.
4. Go to Settings -> Permalinks and click Save Changes once.
5. Add Razorpay and SMTP constants to live `wp-config.php`.
6. Create `public_html/admin-portal/config.php` from the example file.
7. Visit `/partner-with-us/`, submit a test mentor application, and approve it in `/admin-portal/`.
8. Make a Razorpay test payment from the homepage and confirm the student can log in at `/student/`.
9. Send a test SMTP email from WordPress Admin -> Enterns Portal.
10. Submit the homepage contact form once and confirm FormSubmit delivery by email.

## Local Checks

Run syntax checks if PHP is available:

```bash
php -l wp-content/themes/enternstech/functions.php
php -l wp-content/themes/enternstech/front-page.php
php -l wp-content/plugins/enterns-portal/enterns-portal.php
php -l admin-portal/index.php
php wp-content/plugins/enterns-portal/tests/psy-scorer-test.php
```

For a full local test, run this inside a local WordPress install rather than opening
the PHP files directly in a browser.
