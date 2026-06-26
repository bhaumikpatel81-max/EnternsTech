# Troubleshooting WP Pusher Deployment & Activation

## Quick Diagnostic (Do This First)

### Step 1: Upload diagnostic script
1. Download `diagnose-deployment.php` from the repo root
2. Upload it to your server at `/public_html/diagnose.php` (via FTP)
3. Visit `https://yoursite.com/diagnose.php` in your browser
4. Take a screenshot and check all "YES" items — if any show "NO", the deployment is incomplete

### Step 2: Check logs on server
If the diagnostic script shows issues, check:
- `/public_html/enternstech/wp-content/enterns-deploy-log.txt` — log of file checks on every page load
- `/public_html/enternstech/wp-content/debug.log` — WordPress debug log (if `WP_DEBUG` is enabled)

---

## Common Issues & Fixes

### Issue 1: "The plugin does not have a valid header"

**Root Cause**: WordPress can find the plugin file but can't parse the header.

**Check**:
- Upload the diagnostic script and verify `plugin_file_exists` and `plugin_has_header` both show "YES"
- If "NO" on either, the plugin file didn't deploy correctly

**Solutions**:
1. **Verify WP Pusher is configured for subdirectory WordPress**:
   - In WP Pusher settings, ensure it knows WordPress is at `/enternstech/`, not at root
   - Check WP Pusher logs (in WP Admin → WP Pusher → logs tab)
   
2. **Manual activation** (if WP Pusher fails):
   - Download `plugin-enterns-portal.zip` from GitHub Actions artifacts (Actions tab → latest run)
   - In WP Admin → Plugins → Add New → Upload Plugin
   - Select the ZIP and install
   
3. **Check file permissions**:
   - SSH into server: `chmod 644 /public_html/enternstech/wp-content/plugins/enterns-portal/enterns-portal.php`
   - If SSH unavailable, retry the deployment via WP Pusher

---

### Issue 2: "An error occurred. The requested theme does not exist"

**Root Cause**: WordPress can't find the theme folder at `wp-content/themes/enternstech/`.

**Check**:
- Upload diagnostic script and verify `enternstech_theme_dir_exists` and `theme_has_header` both show "YES"
- If "NO" on either, the theme folder didn't deploy correctly

**Solutions**:
1. **Verify deployment paths** (in WP Pusher settings or GitHub Actions):
   - Local: `wp-content/themes/enternstech/`
   - Server: `/public_html/enternstech/wp-content/themes/enternstech/`
   - Local: `wp-content/plugins/enterns-portal/`
   - Server: `/public_html/enternstech/wp-content/plugins/enterns-portal/`
   
2. **Manual activation** (if WP Pusher fails):
   - Download `theme-enternstech.zip` from GitHub Actions artifacts
   - In WP Admin → Appearance → Themes → Add New → Upload Theme
   - Select the ZIP and install
   
3. **Force refresh**:
   - Go to Settings → Permalinks and click "Save Changes" (flushes WordPress cache)
   - Then try theme/plugin activation again

---

## WordPress 7 Compatibility Checklist

- ✓ Plugin requires PHP 7.4+
- ✓ Theme requires PHP 7.4+, tested up to WordPress 6.5
- ✓ Both are compatible with WordPress 5.8+
- ✓ If you have WordPress 7.x, verify PHP version is 7.4 or higher

---

## Using WP Pusher (Recommended Setup)

1. **Generate GitHub Personal Access Token**:
   - Go to GitHub → Settings → Developer settings → Personal access tokens
   - Generate token with `repo` scope
   - Copy the token

2. **Configure in WP Admin**:
   - Plugins → WP Pusher → Connect Repository
   - Paste your GitHub token
   - Select repository: `bhaumikpatel81-max/EnternsTech`
   - Choose "Plugin" or "Theme" type
   - Set the source path:
     - Plugin: `wp-content/plugins/enterns-portal/`
     - Theme: `wp-content/themes/enternstech/`

3. **Configure Deploy Method**:
   - Set branch: `main`
   - Choose "Enabled" for "Push-to-Deploy" (webhook)

4. **Test the deployment**:
   - Make a small change to the repo (e.g., edit README)
   - Push to `main`
   - Check WP Admin → WP Pusher to see if it auto-deploys
   - If not, manually trigger via "Push" button in WP Pusher

---

## Manual Deployment (Fallback)

If WP Pusher isn't working, download and install manually:

1. **Get the theme/plugin ZIPs**:
   - Go to https://github.com/bhaumikpatel81-max/EnternsTech
   - Click "Actions" tab
   - Open the latest successful run
   - Download artifacts: `enternstech-theme` and `enterns-portal-plugin`

2. **Install via WP Admin**:
   - **Theme**: Appearance → Themes → Add New → Upload Theme → select ZIP → Install
   - **Plugin**: Plugins → Add New → Upload Plugin → select ZIP → Install

3. **Activate**:
   - After installation, click "Activate"
   - Go to Settings → Permalinks and click "Save Changes" once (flushes rewrite rules)

---

## If Issues Persist

1. **Enable debug logging**:
   - Edit `wp-config.php` and add above "That's all, stop editing!":
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```
   - Check `/wp-content/debug.log` for errors

2. **Check file ownership**:
   - SSH: `ls -la /public_html/enternstech/wp-content/plugins/enterns-portal/`
   - Files should be owned by the web server user (usually `nobody` or `www-data`)
   - If wrong, contact hosting support to fix ownership

3. **Contact Support**:
   - Share the output of the diagnostic script (`diagnose.php`)
   - Share relevant lines from `wp-content/debug.log`
   - Share WP Pusher logs from WP Admin

---

## File Cleanup

After diagnosis, **delete these files from the server** (do not commit to git):
- `diagnose.php` (via FTP)
- `.git*` files if they appeared (WP Pusher should exclude these automatically)

The `wp-content/enterns-deploy-log.txt` is safe to leave; it gets regenerated hourly with deployment status.
