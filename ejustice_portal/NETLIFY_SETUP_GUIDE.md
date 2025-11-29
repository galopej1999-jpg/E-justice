# Netlify (Static Frontend) + External PHP Backend Deployment Guide

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│ Netlify (Static Frontend + Proxy Layer)                     │
│ - Serves public/ folder (HTML, CSS, JS)                    │
│ - Proxies API calls via _redirects to backend              │
│ - Manages SSL/TLS certificates                              │
└────────────────────┬────────────────────────────────────────┘
                     │ (proxy requests)
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ External PHP Backend (Railway / Render / Cloud Run)         │
│ - PHP-FPM + Nginx (or Railway's managed PHP)               │
│ - Handles all business logic, DB queries, file uploads     │
│ - Stores encrypted documents in storage/                    │
│ - Manages sessions (cookie-based, same-site/secure)        │
└─────────────────────────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ Managed Database (MySQL 8.0)                                │
│ - Railway Managed Database / Render PostgreSQL / Cloud SQL  │
│ - Applied migrations: ejustice_portal.sql + audit + barangay│
└─────────────────────────────────────────────────────────────┘
```

---

## Step 1: Deploy PHP Backend to Railway (Recommended)

Railway is simplest because it auto-detects `Procfile` and applies the correct buildpack.

### Option A: Deploy from Git (Automatic)

1. **Create a Railway account** and log in at https://railway.app
2. **Create a new project**:
   - Click "+ Create Project" → Select "Deploy from Git"
   - Link your GitHub repo (or connect Git provider)
   - Railway auto-detects `Procfile` and Dockerfile
3. **Add a MySQL database**:
   - In Railway project, click "+ Add Plugin" → Select "MySQL"
   - Railway auto-injects `DATABASE_URL` into your app environment
4. **Set environment variables**:
   - In Railway, go to project → "Variables"
   - Add:
     - `DOC_ENC_KEY`: Generate a strong 32+ character random string. Example:
       ```bash
       openssl rand -hex 32
       ```
     - `APP_ENV`: `production`
     - `APP_DEBUG`: `false`
     - `NODE_ENV`: `production` (Railway may expect this)
   - Note: `DATABASE_URL` is auto-set by Railway MySQL plugin
5. **Verify deployment**:
   - Railway shows "Active" status when deployment succeeds
   - Copy your **Railway public URL** (e.g., `https://ejustice-backend-production.railway.app`)
   - Open it in browser → You should see the login page

### Option B: Deploy Using Docker

If Git deployment fails:

1. **Build and push Docker image**:
   ```bash
   docker build -t your-registry/ejustice_portal:latest .
   docker push your-registry/ejustice_portal:latest
   ```
   (Use Docker Hub, GitHub Container Registry, or Railway's registry.)

2. **In Railway**:
   - Create new project → "Deploy from Docker Image"
   - Paste image name: `your-registry/ejustice_portal:latest`
   - Add MySQL plugin
   - Set env vars (DOC_ENC_KEY, etc.)

### Option C: Deploy to Render (Alternative)

If Railway is unavailable:

1. Go to https://render.com, sign up, create a new service
2. **Choose deployment method**: "Web Service" → "Docker" or "Build from source"
3. **For source deploy**: Point to your Git repo, set `Start Command`:
   ```
   php -S 0.0.0.0:${PORT:-8080} -t public
   ```
4. **Add managed PostgreSQL or MySQL** database via Render dashboard
5. Set environment variables and deploy

---

## Step 2: Run Database Migrations in Production

Your deployed PHP backend connects to the managed database via `DATABASE_URL`. Apply migrations in order:

### Via phpMyAdmin (if available on your backend host):

1. Access `https://your-backend-url/phpmyadmin` (if exposed; check your host's instructions)
2. Select the `ejustice_portal` database
3. Go to "Import" tab
4. Upload and run each file in order:
   - `sql/ejustice_portal.sql`
   - `sql/002_add_audit_logs.sql`
   - `sql/003_add_barangay_module.sql`
   - `sql/004_add_barangay_case_routing.sql`

### Via CLI (if you have SSH access):

```bash
# SSH into your backend host
ssh your-backend-host

# Navigate to repo
cd /app

# Run migrations using mysql CLI (if installed) or PHP
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < sql/ejustice_portal.sql
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < sql/002_add_audit_logs.sql
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < sql/003_add_barangay_module.sql
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < sql/004_add_barangay_case_routing.sql
```

### Via PHP script (safest, no CLI needed):

Create `run_migrations.php` in `public/`:

```php
<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

// Only allow system_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'system_admin') {
    die('Unauthorized');
}

$migrations = [
    'sql/ejustice_portal.sql',
    'sql/002_add_audit_logs.sql',
    'sql/003_add_barangay_module.sql',
    'sql/004_add_barangay_case_routing.sql',
];

foreach ($migrations as $file) {
    if (file_exists("../$file")) {
        $sql = file_get_contents("../$file");
        try {
            $pdo->exec($sql);
            echo "✓ Executed $file<br>";
        } catch (Exception $e) {
            echo "✗ Error in $file: " . $e->getMessage() . "<br>";
        }
    }
}
?>
```

Then visit: `https://your-backend-url/run_migrations.php` (logged in as admin)

---

## Step 3: Deploy Static Frontend to Netlify

1. **Create a Netlify account** at https://netlify.com
2. **Connect your Git repo**:
   - In Netlify, click "Connect to Git" → Link GitHub/GitLab/Bitbucket
   - Select your repo
3. **Configure build settings**:
   - **Base directory**: (leave empty, root)
   - **Build command**: (leave empty; this is static, no build needed)
   - **Publish directory**: `public`
4. **Set environment variable for backend URL**:
   - In Netlify Site Settings → "Build & Deploy" → "Environment"
   - Add: `REACT_APP_BACKEND_URL` = `https://your-backend-url` (e.g., your Railway URL)
     - Note: Netlify env vars are build-time only; the _redirects rules below handle runtime proxying.
5. **Deploy**:
   - Netlify auto-deploys when you push to Git
   - Your site gets a domain like `https://your-site-name.netlify.app`

---

## Step 4: Update Netlify Proxy Rules

1. **Find `public/_redirects`** in your repo
2. **Replace `BACKEND_URL`** with your actual backend URL:
   ```
   # Before:
   /api/*                                  BACKEND_URL/:splat  200!

   # After (example Railway):
   /api/*                   https://ejustice-backend-production.railway.app/:splat  200!
   ```
   Do this for **all** rules in the file.
3. **Commit and push** to Git
4. **Netlify redeploys automatically**

Alternatively, use `netlify.toml` instead of `_redirects`:

```toml
[build]
  publish = "public"
  command = ""

[[redirects]]
  from = "/api/*"
  to = "https://your-backend-url/:splat"
  status = 200
  force = true

# (repeat for all other routes)
```

---

## Step 5: Session & Cookie Handling

Your PHP app uses `$_SESSION` (server-side sessions). Netlify's proxy preserves cookies, but you must configure correctly:

### In `config/config.php`, ensure:

```php
// No explicit domain in session config (let browser use current domain)
ini_set('session.cookie_samesite', 'Lax');  // or 'Strict' if no cross-origin needs
ini_set('session.cookie_secure', true);     // HTTPS only (Netlify is HTTPS)
ini_set('session.cookie_httponly', true);   // Prevent JS access
ini_set('session.name', 'PHPSESSID');       // Standard session cookie name
ini_set('session.use_only_cookies', true);

// Store sessions on backend (not in local filesystem if load-balanced)
// If using Railway: sessions stored in /tmp (ephemeral) or use memcached
// For reliability, consider session storage to database:
```

### Test session flow:

1. **Login at** `https://your-netlify-domain.netlify.app/login.php`
2. Browser sends credentials to backend via Netlify proxy
3. Backend sets `Set-Cookie: PHPSESSID=...` (path=/, domain not set)
4. Netlify proxy preserves cookie in response
5. On next request, browser auto-includes cookie (same domain)
6. Backend sees `$_SESSION` and user is logged in

**If sessions don't persist:**
- Check browser console (DevTools) → Network → look for `Set-Cookie` headers
- Ensure backend returns cookies without a fixed domain
- Verify Netlify proxy rule doesn't strip `Set-Cookie` headers (default is to preserve them)

---

## Step 6: Large File Uploads & Timeouts

Netlify proxy has limits:
- Default timeout: ~30 seconds
- Max request size: ~100 MB (increase in netlify.toml if needed)

### Configure upload limits in `netlify.toml`:

```toml
[[redirects]]
  from = "/upload_document.php"
  to = "https://your-backend-url/upload_document.php"
  status = 200
  force = true
  headers = { "X-Request-Timeout" = "120" }  # 2 minutes
```

### Or, for very large uploads (> 100 MB), bypass Netlify proxy:

Upload directly to backend (have frontend POST to `https://your-backend-url/upload_document.php` instead of using proxy).

---

## Step 7: Encrypted Documents & Storage

Documents are encrypted with `openssl_encrypt` (AES-256-CBC) and stored in `storage/documents/`.

### On a managed platform like Railway:

- `storage/` is **ephemeral** (lost if instance restarts)
- **Solution**: Use persistent volumes or cloud storage (S3)

### Option A: Use Railway volumes

In `railway.toml` (or Railway UI):

```toml
[[services.volumes]]
  path = "/app/storage"
  name = "storage"
```

### Option B: Use S3 (AWS / DigitalOcean Spaces / Wasabi)

Modify `public/upload_document.php` to upload to S3 instead of local filesystem:

```php
// Instead of:
// file_put_contents("../storage/documents/$filename.enc", $encrypted);

// Use (with AWS SDK):
use Aws\S3\S3Client;
$s3 = new S3Client([
    'version' => 'latest',
    'region'  => 'us-east-1',
    'credentials' => [
        'key'    => getenv('AWS_ACCESS_KEY_ID'),
        'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
    ]
]);

$s3->putObject([
    'Bucket' => getenv('S3_BUCKET'),
    'Key'    => "documents/$filename.enc",
    'Body'   => $encrypted,
]);
```

---

## Step 8: Environment Variables Checklist

### On your backend host (Railway, Render, etc.), set:

```
DATABASE_URL=mysql://user:password@host:port/ejustice_portal
  (Auto-set by managed DB plugin; verify in host dashboard)

DOC_ENC_KEY=<strong_32+_character_random_string>
  Generate with: openssl rand -hex 32

APP_ENV=production
APP_DEBUG=false
NODE_ENV=production

# Optional (for S3 storage):
AWS_ACCESS_KEY_ID=<your-key>
AWS_SECRET_ACCESS_KEY=<your-secret>
S3_BUCKET=ejustice-documents
AWS_REGION=us-east-1
```

### On Netlify (optional, for frontend build-time):

```
REACT_APP_BACKEND_URL=https://your-backend-url
```

---

## Step 9: Testing End-to-End

1. **Open Netlify domain** in browser: `https://your-site.netlify.app`
   - You should see the eJustice login page
2. **Register a new complainant** (if seed not run)
3. **Login** and verify session persists across page reloads
4. **File a case** (online filing)
5. **Upload a document** (encryption + storage)
6. **View the document** (decryption + audit log entry)
7. **Check audit logs** (as staff)
8. **Test Barangay flow** (if logged in as barangay staff)
9. **Check that escalations** create police blotter cases

If any step fails, check:
- Browser DevTools Network tab for failed requests and status codes
- Backend logs (in Railway/Render dashboard)
- Netlify deploy logs (in Netlify dashboard → "Deployments")
- PHP error logs on backend

---

## Step 10: Continuous Deployment

Once set up, every time you push to Git:

1. **GitHub** → your repo is updated
2. **Railway** auto-pulls and redeploys backend (if Git-connected)
3. **Netlify** auto-redeploys frontend (if Git-connected)

---

## Troubleshooting

### Issue: "Cannot GET /login.php"
- **Cause**: Proxy rule not matching or backend not responding
- **Fix**: 
  - Verify backend URL in `_redirects` is correct and reachable
  - Test backend directly: `curl https://your-backend-url/login.php`

### Issue: "Session lost after redirect"
- **Cause**: Cookies not preserved across proxy
- **Fix**:
  - Check `Set-Cookie` in DevTools Network tab
  - Ensure `session.cookie_domain` is NOT set in `config/config.php`
  - Set `session.cookie_samesite = 'Lax'` or `'None; Secure'` (if cross-origin)

### Issue: "502 Bad Gateway"
- **Cause**: Backend overloaded, timeout, or crashed
- **Fix**:
  - Check backend logs in Railway/Render dashboard
  - Increase request timeout in netlify.toml

### Issue: "Encrypted document download fails"
- **Cause**: Document file not found (ephemeral storage lost)
- **Fix**:
  - Use persistent volumes (Step 7, Option A) or S3 (Option B)

---

## Quick Reference: URLs After Deployment

- **Netlify Frontend**: `https://your-site-name.netlify.app`
- **Backend (Railway)**: `https://your-project-backend.railway.app` (or Render URL)
- **phpMyAdmin (if exposed)**: `https://your-backend-url/phpmyadmin`
- **Netlify Admin**: `https://app.netlify.com`
- **Railway Admin**: `https://railway.app/dashboard`

---

## Next Steps

1. ✅ Deploy backend to Railway or Render
2. ✅ Run database migrations
3. ✅ Deploy frontend to Netlify
4. ✅ Update `_redirects` with real backend URL
5. ✅ Test end-to-end login, file upload, document view
6. ✅ Enable HTTPS (auto-enabled on Netlify and Railway)
7. ✅ Set up CI/CD notifications (Netlify + Railway can post to Slack)
8. ✅ Monitor logs and errors (set up error tracking like Sentry if needed)

**Happy deploying!** 🚀
