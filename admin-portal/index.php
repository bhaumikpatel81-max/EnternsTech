<?php
/**
 * Enterns Tech — Admin Portal
 * Accessible only at /admin-portal/ — never linked from the main site.
 */

session_start();
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// ── Config check ─────────────────────────────────────────────────────────────
if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(503);
    die(render_setup_page());
}
require_once __DIR__ . '/config.php';

// ── DB connection (lazy) ──────────────────────────────────────────────────────
$pdo = null;
function get_db(): PDO {
    global $pdo;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        create_tables($pdo);
    } catch (PDOException $e) {
        die('<div style="font:14px monospace;padding:2rem;color:#f87171;background:#0a0e1a">DB Error: ' . htmlspecialchars($e->getMessage()) . '</div>');
    }
    return $pdo;
}

function create_tables(PDO $db): void {
    $p = DB_PREFIX;
    $db->exec("CREATE TABLE IF NOT EXISTS `{$p}et_transactions` (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        order_id   VARCHAR(60)     NOT NULL,
        plan       VARCHAR(120)    DEFAULT '',
        amount     DECIMAL(10,2)   NOT NULL,
        currency   VARCHAR(3)      DEFAULT 'USD',
        status     VARCHAR(20)     DEFAULT 'COMPLETED',
        payer_name VARCHAR(200)    DEFAULT '',
        payer_email VARCHAR(200)   DEFAULT '',
        created_at DATETIME        DEFAULT CURRENT_TIMESTAMP,
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `{$p}et_revenue_manual` (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        entry_date  DATE            NOT NULL,
        amount      DECIMAL(10,2)   NOT NULL,
        description VARCHAR(500)    DEFAULT '',
        category    VARCHAR(100)    DEFAULT 'Other',
        created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
        INDEX (entry_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// ── Actions ───────────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';
$error  = '';

// Login
if ($action === 'login') {
    if (isset($_POST['password']) && $_POST['password'] === ADMIN_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['et_auth']    = true;
        $_SESSION['et_time']    = time();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $error = 'Incorrect password. Please try again.';
    sleep(1);
}

// Logout
if ($action === 'logout') {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Session timeout: 8 hours
if (!empty($_SESSION['et_auth']) && (time() - ($_SESSION['et_time'] ?? 0)) > 28800) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$logged_in = !empty($_SESSION['et_auth']);

// Add manual revenue
if ($logged_in && $action === 'add_revenue') {
    $db   = get_db();
    $stmt = $db->prepare('INSERT INTO `' . DB_PREFIX . 'et_revenue_manual` (entry_date, amount, description, category) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $_POST['entry_date'] ?? date('Y-m-d'),
        abs(floatval($_POST['amount'] ?? 0)),
        substr(strip_tags($_POST['description'] ?? ''), 0, 500),
        substr(strip_tags($_POST['category']    ?? 'Other'), 0, 100),
    ]);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?section=manual&added=1');
    exit;
}

// Delete manual revenue
if ($logged_in && $action === 'delete_revenue') {
    $db   = get_db();
    $stmt = $db->prepare('DELETE FROM `' . DB_PREFIX . 'et_revenue_manual` WHERE id = ?');
    $stmt->execute([intval($_POST['entry_id'] ?? 0)]);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?section=manual');
    exit;
}

// Delete PayPal transaction
if ($logged_in && $action === 'delete_transaction') {
    $db   = get_db();
    $stmt = $db->prepare('DELETE FROM `' . DB_PREFIX . 'et_transactions` WHERE id = ?');
    $stmt->execute([intval($_POST['tx_id'] ?? 0)]);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?section=transactions');
    exit;
}

// ── Dashboard data ────────────────────────────────────────────────────────────
$stats = [];
$monthly_labels  = [];
$monthly_paypal  = [];
$monthly_manual  = [];
$monthly_total   = [];
$transactions    = [];
$manual_entries  = [];
$section         = $_GET['section'] ?? 'overview';

if ($logged_in) {
    $db = get_db();
    $p  = DB_PREFIX;

    // Summary stats
    $stats['paypal_total']  = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}et_transactions` WHERE status='COMPLETED'")->fetchColumn();
    $stats['manual_total']  = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}et_revenue_manual`")->fetchColumn();
    $stats['grand_total']   = $stats['paypal_total'] + $stats['manual_total'];
    $stats['tx_count']      = (int)   $db->query("SELECT COUNT(*) FROM `{$p}et_transactions`")->fetchColumn();

    $this_m = (int) date('n');
    $this_y = (int) date('Y');
    $pp_m   = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}et_transactions` WHERE MONTH(created_at)={$this_m} AND YEAR(created_at)={$this_y} AND status='COMPLETED'")->fetchColumn();
    $mn_m   = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}et_revenue_manual` WHERE MONTH(entry_date)={$this_m} AND YEAR(entry_date)={$this_y}")->fetchColumn();
    $stats['this_month']    = $pp_m + $mn_m;

    // Monthly chart — last 12 months
    for ($i = 11; $i >= 0; $i--) {
        $ts  = strtotime("-{$i} months");
        $lbl = date('M Y', $ts);
        $m   = (int) date('n', $ts);
        $y   = (int) date('Y', $ts);

        $pp = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}et_transactions` WHERE MONTH(created_at)={$m} AND YEAR(created_at)={$y} AND status='COMPLETED'")->fetchColumn();
        $mn = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}et_revenue_manual` WHERE MONTH(entry_date)={$m} AND YEAR(entry_date)={$y}")->fetchColumn();

        $monthly_labels[] = $lbl;
        $monthly_paypal[] = $pp;
        $monthly_manual[] = $mn;
        $monthly_total[]  = $pp + $mn;
    }

    // Tables
    $transactions   = $db->query("SELECT * FROM `{$p}et_transactions` ORDER BY created_at DESC LIMIT 50")->fetchAll();
    $manual_entries = $db->query("SELECT * FROM `{$p}et_revenue_manual` ORDER BY entry_date DESC LIMIT 100")->fetchAll();

    // ── Portal stats (from enterns-portal plugin tables) ──────────────────────
    // Tables are created when the plugin activates; we guard with try/catch so
    // the admin portal still works before the plugin is installed.
    $portal = [
        'mentors_total'   => 0,
        'mentors_pending' => 0,
        'students_total'  => 0,
        'students_active' => 0,
        'sessions_total'  => 0,
        'payments_paid'   => 0.0,
    ];
    try {
        $portal['mentors_total']   = (int) $db->query("SELECT COUNT(*) FROM `{$p}enp_mentors`")->fetchColumn();
        $portal['mentors_pending'] = (int) $db->query("SELECT COUNT(*) FROM `{$p}enp_mentors` WHERE status='pending'")->fetchColumn();
        $portal['students_total']  = (int) $db->query("SELECT COUNT(*) FROM `{$p}enp_students`")->fetchColumn();
        $portal['students_active'] = (int) $db->query("SELECT COUNT(*) FROM `{$p}enp_students` WHERE status='active'")->fetchColumn();
        $portal['sessions_total']  = (int) $db->query("SELECT COUNT(*) FROM `{$p}enp_sessions`")->fetchColumn();
        $portal['payments_paid']   = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}enp_payments` WHERE status='paid'")->fetchColumn();
    } catch (PDOException $e) {
        // Plugin tables not yet created — portal data will show zeros.
    }

    // Mentor applications list for Applications tab.
    $applications = [];
    try {
        $applications = $db->query("SELECT * FROM `{$p}enp_mentors` ORDER BY created_at DESC LIMIT 100")->fetchAll();
    } catch (PDOException $e) {}

    // Student list for Students tab.
    $portal_students = [];
    try {
        $portal_students = $db->query("SELECT s.*, m.full_name AS mentor_name FROM `{$p}enp_students` s LEFT JOIN `{$p}enp_mentors` m ON s.mentor_id = m.id ORDER BY s.created_at DESC LIMIT 100")->fetchAll();
    } catch (PDOException $e) {}
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmt_money(float $v): string { return '$' . number_format($v, 2); }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function active_section(string $s): string {
    global $section;
    return $section === $s ? 'active' : '';
}

function render_setup_page(): string {
    return <<<HTML
<!doctype html><html><head><meta charset="UTF-8"><title>Admin Setup</title>
<style>body{background:#0a0e1a;color:#e5e7eb;font-family:system-ui;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#111827;border:1px solid #1f2937;border-radius:12px;padding:2rem;max-width:520px;width:90%}
h2{color:#22D3EE;margin-top:0}pre{background:#0a0e1a;padding:1rem;border-radius:8px;overflow-x:auto;font-size:13px;color:#a3e635}
</style></head><body><div class="box">
<h2>⚙️ Admin Portal Setup</h2>
<p>Create the file <strong>admin-portal/config.php</strong> on the server (or deploy it) with the following content:</p>
<pre>&lt;?php
define('ADMIN_PASSWORD', 'your-secret-password');
define('DB_HOST',   'localhost');
define('DB_NAME',   'your_wp_database_name');
define('DB_USER',   'your_db_username');
define('DB_PASS',   'your_db_password');
define('DB_PREFIX', 'wp_');</pre>
<p>You can find the database details in your Bluehost cPanel → MySQL Databases, and in your WordPress <code>wp-config.php</code> file.</p>
<p>After uploading <code>config.php</code>, reload this page.</p>
</div></body></html>
HTML;
}

// ── HTML output ───────────────────────────────────────────────────────────────
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Enterns Tech — Admin</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:       #0a0e1a;
      --surface:  #111827;
      --border:   #1f2937;
      --cyan:     #22D3EE;
      --cyan-dim: #0e7490;
      --text:     #f1f5f9;
      --muted:    #94a3b8;
      --green:    #4ade80;
      --red:      #f87171;
      --gold:     #fbbf24;
    }

    body { background: var(--bg); color: var(--text); font-family: system-ui, -apple-system, sans-serif; min-height: 100vh; }

    /* ── LOGIN PAGE ─────────────────────────────────────────────────────────── */
    .login-wrap { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; }
    .login-card {
      background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
      padding: 2.5rem 2rem; width: 100%; max-width: 380px; text-align: center;
      box-shadow: 0 0 40px rgba(34,211,238,.08);
    }
    .login-logo { font-size: 1.6rem; font-weight: 800; color: var(--cyan); margin-bottom: .25rem; letter-spacing: -1px; }
    .login-sub { color: var(--muted); font-size: .85rem; margin-bottom: 2rem; }
    .login-card label { display: block; text-align: left; font-size: .8rem; color: var(--muted); margin-bottom: .4rem; letter-spacing: .05em; text-transform: uppercase; }
    .login-card input[type=password] {
      width: 100%; padding: .75rem 1rem; background: var(--bg); border: 1px solid var(--border);
      border-radius: 8px; color: var(--text); font-size: 1rem; outline: none; transition: border .2s;
    }
    .login-card input[type=password]:focus { border-color: var(--cyan); }
    .btn-login {
      width: 100%; margin-top: 1.25rem; padding: .8rem; background: var(--cyan); color: #0a0e1a;
      font-weight: 700; font-size: 1rem; border: none; border-radius: 8px; cursor: pointer; transition: opacity .2s;
    }
    .btn-login:hover { opacity: .85; }
    .error-msg { background: rgba(248,113,113,.12); border: 1px solid rgba(248,113,113,.3); border-radius: 8px; color: var(--red); padding: .75rem 1rem; margin-bottom: 1.25rem; font-size: .9rem; }
    .lock-icon { font-size: 2.5rem; margin-bottom: 1rem; }

    /* ── DASHBOARD SHELL ────────────────────────────────────────────────────── */
    .dash { display: flex; flex-direction: column; min-height: 100vh; }

    /* Top bar */
    .topbar {
      background: var(--surface); border-bottom: 1px solid var(--border);
      padding: .75rem 1.5rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
    }
    .topbar-brand { font-weight: 800; color: var(--cyan); font-size: 1.1rem; letter-spacing: -0.5px; }
    .topbar-badge { background: rgba(34,211,238,.1); border: 1px solid var(--cyan-dim); color: var(--cyan); font-size: .7rem; padding: .15rem .5rem; border-radius: 99px; text-transform: uppercase; letter-spacing: .08em; }
    .topbar-spacer { flex: 1; }
    .topbar-time { color: var(--muted); font-size: .8rem; }
    .btn-logout { background: rgba(248,113,113,.12); border: 1px solid rgba(248,113,113,.3); color: var(--red); padding: .4rem .9rem; border-radius: 7px; font-size: .83rem; cursor: pointer; transition: background .2s; }
    .btn-logout:hover { background: rgba(248,113,113,.22); }

    /* Nav tabs */
    .nav-tabs { background: var(--surface); border-bottom: 1px solid var(--border); display: flex; gap: .25rem; padding: 0 1.5rem; overflow-x: auto; }
    .nav-tab { padding: .7rem 1.1rem; color: var(--muted); font-size: .88rem; text-decoration: none; border-bottom: 2px solid transparent; white-space: nowrap; transition: color .2s, border-color .2s; display: inline-flex; align-items: center; gap: .35rem; }
    .nav-tab:hover { color: var(--text); }
    .nav-tab.active { color: var(--cyan); border-bottom-color: var(--cyan); font-weight: 600; }
    .nav-badge { background: rgba(251,191,36,.2); color: var(--gold); border: 1px solid rgba(251,191,36,.35); border-radius: 99px; font-size: .68rem; font-weight: 700; padding: .1rem .45rem; }

    /* Main content */
    .main { flex: 1; padding: 1.75rem 1.5rem; max-width: 1200px; width: 100%; margin: 0 auto; }

    /* ── STAT CARDS ─────────────────────────────────────────────────────────── */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }
    .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem 1.5rem; }
    .stat-label { font-size: .75rem; color: var(--muted); text-transform: uppercase; letter-spacing: .07em; margin-bottom: .5rem; }
    .stat-value { font-size: 1.75rem; font-weight: 800; line-height: 1; }
    .stat-value.cyan  { color: var(--cyan); }
    .stat-value.green { color: var(--green); }
    .stat-value.gold  { color: var(--gold); }
    .stat-sub { font-size: .75rem; color: var(--muted); margin-top: .5rem; }

    /* ── CHART ──────────────────────────────────────────────────────────────── */
    .chart-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.75rem; }
    .chart-title { font-size: .95rem; font-weight: 600; margin-bottom: 1rem; color: var(--text); }
    .chart-wrap { position: relative; height: 240px; }

    /* ── TABLES ─────────────────────────────────────────────────────────────── */
    .section-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; color: var(--text); display: flex; align-items: center; gap: .5rem; }
    .section-title span { background: rgba(34,211,238,.1); color: var(--cyan); font-size: .72rem; padding: .2rem .55rem; border-radius: 99px; }
    .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 1.75rem; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: .86rem; }
    thead th { background: rgba(34,211,238,.06); color: var(--muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; padding: .75rem 1rem; text-align: left; white-space: nowrap; border-bottom: 1px solid var(--border); }
    tbody td { padding: .75rem 1rem; border-bottom: 1px solid rgba(31,41,55,.8); vertical-align: middle; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover { background: rgba(255,255,255,.02); }
    .badge { display: inline-block; font-size: .7rem; padding: .2rem .55rem; border-radius: 99px; font-weight: 600; }
    .badge-green { background: rgba(74,222,128,.12); color: var(--green); border: 1px solid rgba(74,222,128,.3); }
    .badge-cyan  { background: rgba(34,211,238,.1);  color: var(--cyan);  border: 1px solid var(--cyan-dim); }
    .badge-gold  { background: rgba(251,191,36,.1);  color: var(--gold);  border: 1px solid rgba(251,191,36,.3); }
    .amount { font-weight: 700; color: var(--green); }
    .empty-row td { text-align: center; color: var(--muted); padding: 2rem; font-size: .9rem; }
    .btn-del { background: none; border: 1px solid rgba(248,113,113,.3); color: var(--red); font-size: .75rem; padding: .25rem .6rem; border-radius: 6px; cursor: pointer; transition: background .2s; }
    .btn-del:hover { background: rgba(248,113,113,.1); }

    /* ── ADD FORM ───────────────────────────────────────────────────────────── */
    .form-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.75rem; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
    .form-group label { display: block; font-size: .75rem; color: var(--muted); text-transform: uppercase; letter-spacing: .07em; margin-bottom: .4rem; }
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%; padding: .65rem .9rem; background: var(--bg); border: 1px solid var(--border);
      border-radius: 8px; color: var(--text); font-size: .9rem; outline: none; transition: border .2s; font-family: inherit;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus { border-color: var(--cyan); }
    .form-group textarea { min-height: 70px; resize: vertical; }
    .form-group select option { background: var(--surface); }
    .btn-add { background: var(--cyan); color: #0a0e1a; font-weight: 700; font-size: .9rem; padding: .65rem 1.4rem; border: none; border-radius: 8px; cursor: pointer; transition: opacity .2s; margin-top: 1.5rem; }
    .btn-add:hover { opacity: .85; }
    .success-banner { background: rgba(74,222,128,.08); border: 1px solid rgba(74,222,128,.25); border-radius: 8px; color: var(--green); padding: .75rem 1rem; margin-bottom: 1.25rem; font-size: .9rem; }

    @media(max-width:600px) {
      .main { padding: 1rem; }
      .stat-value { font-size: 1.4rem; }
      .topbar { padding: .75rem 1rem; }
    }
  </style>
</head>
<body>

<?php if (!$logged_in): ?>
<!-- ═══════════════════ LOGIN PAGE ═══════════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <div class="lock-icon">🔐</div>
    <div class="login-logo">Enterns Tech</div>
    <div class="login-sub">Admin Portal — Private Access Only</div>

    <?php if ($error): ?>
      <div class="error-msg"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="login">
      <div style="margin-bottom:1rem">
        <label for="pwd">Admin Password</label>
        <input type="password" id="pwd" name="password" autofocus placeholder="Enter password">
      </div>
      <button class="btn-login" type="submit">Sign In</button>
    </form>

    <p style="color:var(--muted);font-size:.75rem;margin-top:1.5rem">
      This page is private. Not accessible from the main website.
    </p>
  </div>
</div>

<?php else: ?>
<!-- ═══════════════════ DASHBOARD ═══════════════════ -->
<div class="dash">

  <!-- Top bar -->
  <header class="topbar">
    <div class="topbar-brand">Enterns Tech</div>
    <div class="topbar-badge">Admin Portal</div>
    <div class="topbar-spacer"></div>
    <div class="topbar-time"><?= date('d M Y, H:i') ?></div>
    <form method="POST" style="display:inline">
      <input type="hidden" name="action" value="logout">
      <button class="btn-logout" type="submit">Sign Out</button>
    </form>
  </header>

  <!-- Nav tabs -->
  <nav class="nav-tabs">
    <a href="?section=overview"      class="nav-tab <?= active_section('overview') ?>">Overview</a>
    <a href="?section=applications"  class="nav-tab <?= active_section('applications') ?>">
      Applications<?php if (!empty($portal['mentors_pending'])): ?> <span class="nav-badge"><?= (int)$portal['mentors_pending'] ?></span><?php endif; ?>
    </a>
    <a href="?section=mentors"       class="nav-tab <?= active_section('mentors') ?>">Mentors</a>
    <a href="?section=students"      class="nav-tab <?= active_section('students') ?>">Students</a>
    <a href="?section=sessions"      class="nav-tab <?= active_section('sessions') ?>">Sessions</a>
    <a href="?section=payments"      class="nav-tab <?= active_section('payments') ?>">Payments</a>
    <a href="?section=transactions"  class="nav-tab <?= active_section('transactions') ?>">Legacy Txns</a>
    <a href="?section=manual"        class="nav-tab <?= active_section('manual') ?>">Manual Revenue</a>
  </nav>

  <main class="main">

  <?php if ($section === 'overview'): ?>
  <!-- ── OVERVIEW ────────────────────────────────────────────── -->

    <!-- Portal stat cards -->
    <div class="stats-grid" style="margin-bottom:1rem;">
      <div class="stat-card" style="border-color:rgba(34,211,238,.25)">
        <div class="stat-label">Mentor Applications</div>
        <div class="stat-value cyan"><?= (int)$portal['mentors_total'] ?></div>
        <div class="stat-sub"><?= (int)$portal['mentors_pending'] ?> pending review</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Students</div>
        <div class="stat-value cyan"><?= (int)$portal['students_total'] ?></div>
        <div class="stat-sub"><?= (int)$portal['students_active'] ?> active</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Sessions</div>
        <div class="stat-value cyan"><?= (int)$portal['sessions_total'] ?></div>
        <div class="stat-sub">All time</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Portal Payments</div>
        <div class="stat-value green">&#8377;<?= number_format($portal['payments_paid'], 0) ?></div>
        <div class="stat-sub">Razorpay confirmed</div>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Revenue (All Time)</div>
        <div class="stat-value cyan"><?= fmt_money($stats['grand_total']) ?></div>
        <div class="stat-sub">PayPal + Manual combined</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">This Month</div>
        <div class="stat-value green"><?= fmt_money($stats['this_month']) ?></div>
        <div class="stat-sub"><?= date('F Y') ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">PayPal Revenue</div>
        <div class="stat-value cyan"><?= fmt_money($stats['paypal_total']) ?></div>
        <div class="stat-sub"><?= $stats['tx_count'] ?> payment(s)</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Manual Revenue</div>
        <div class="stat-value gold"><?= fmt_money($stats['manual_total']) ?></div>
        <div class="stat-sub">Manually entered</div>
      </div>
    </div>

    <!-- Revenue chart -->
    <div class="chart-card">
      <div class="chart-title">Monthly Revenue — Last 12 Months</div>
      <div class="chart-wrap">
        <canvas id="revenueChart"></canvas>
      </div>
    </div>

    <!-- Recent activity (last 5 PayPal + last 5 manual) -->
    <div class="section-title">Recent PayPal Transactions <span><?= count($transactions) ?> total</span></div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Order ID</th><th>Plan</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
          <?php if ($transactions): foreach (array_slice($transactions, 0, 5) as $t): ?>
            <tr>
              <td><?= h(date('d M Y', strtotime($t['created_at']))) ?></td>
              <td style="font-size:.78rem;color:var(--muted)"><?= h($t['order_id']) ?></td>
              <td><?= $t['plan'] ? h($t['plan']) : '<span style="color:var(--muted)">—</span>' ?></td>
              <td class="amount"><?= fmt_money((float)$t['amount']) ?></td>
              <td><span class="badge badge-green"><?= h($t['status']) ?></span></td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="5">No PayPal transactions recorded yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="section-title">Recent Manual Entries <span><?= count($manual_entries) ?> total</span></div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Amount</th><th>Category</th><th>Description</th></tr></thead>
          <tbody>
          <?php if ($manual_entries): foreach (array_slice($manual_entries, 0, 5) as $m): ?>
            <tr>
              <td><?= h(date('d M Y', strtotime($m['entry_date']))) ?></td>
              <td class="amount"><?= fmt_money((float)$m['amount']) ?></td>
              <td><span class="badge badge-gold"><?= h($m['category']) ?></span></td>
              <td style="color:var(--muted)"><?= h($m['description']) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="4">No manual entries yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($section === 'transactions'): ?>
  <!-- ── PAYPAL TRANSACTIONS ─────────────────────────────────── -->

    <div class="section-title">PayPal Transactions <span><?= count($transactions) ?> total</span></div>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:1.25rem">
      Transactions are logged automatically when a payment is captured on the website.
    </p>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date &amp; Time</th><th>Order ID</th><th>Plan</th><th>Payer</th><th>Amount</th><th>Currency</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php if ($transactions): foreach ($transactions as $t): ?>
            <tr>
              <td style="white-space:nowrap"><?= h(date('d M Y H:i', strtotime($t['created_at']))) ?></td>
              <td style="font-size:.76rem;color:var(--muted);font-family:monospace"><?= h($t['order_id']) ?></td>
              <td><?= $t['plan'] ? h($t['plan']) : '<span style="color:var(--muted)">—</span>' ?></td>
              <td style="font-size:.82rem"><?= h($t['payer_name'] ?: $t['payer_email'] ?: '—') ?></td>
              <td class="amount"><?= fmt_money((float)$t['amount']) ?></td>
              <td style="color:var(--muted)"><?= h($t['currency']) ?></td>
              <td><span class="badge badge-green"><?= h($t['status']) ?></span></td>
              <td>
                <form method="POST" onsubmit="return confirm('Delete this transaction record?')">
                  <input type="hidden" name="action" value="delete_transaction">
                  <input type="hidden" name="tx_id" value="<?= (int)$t['id'] ?>">
                  <button class="btn-del" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="8">No PayPal transactions recorded yet.<br><small>Transactions appear here once a customer completes payment.</small></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($section === 'manual'): ?>
  <!-- ── MANUAL REVENUE ─────────────────────────────────────── -->

    <?php if (isset($_GET['added'])): ?>
      <div class="success-banner">Entry added successfully.</div>
    <?php endif; ?>

    <div class="section-title">Add Manual Revenue Entry</div>
    <div class="form-card">
      <form method="POST">
        <input type="hidden" name="action" value="add_revenue">
        <div class="form-grid">
          <div class="form-group">
            <label>Date</label>
            <input type="date" name="entry_date" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label>Amount (₹ or $)</label>
            <input type="number" name="amount" step="0.01" min="0.01" placeholder="e.g. 5000" required>
          </div>
          <div class="form-group">
            <label>Category</label>
            <select name="category">
              <option>Training Fee</option>
              <option>Placement Fee</option>
              <option>Internship Fee</option>
              <option>Consultation</option>
              <option>Workshop</option>
              <option>Other</option>
            </select>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Description</label>
            <textarea name="description" placeholder="e.g. John Doe — React Training batch, Jun 2026"></textarea>
          </div>
        </div>
        <button class="btn-add" type="submit">+ Add Entry</button>
      </form>
    </div>

    <div class="section-title">All Manual Entries <span><?= count($manual_entries) ?></span></div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Amount</th><th>Category</th><th>Description</th><th>Logged At</th><th></th></tr></thead>
          <tbody>
          <?php if ($manual_entries): foreach ($manual_entries as $m): ?>
            <tr>
              <td style="white-space:nowrap"><?= h(date('d M Y', strtotime($m['entry_date']))) ?></td>
              <td class="amount"><?= fmt_money((float)$m['amount']) ?></td>
              <td><span class="badge badge-gold"><?= h($m['category']) ?></span></td>
              <td style="color:var(--muted);max-width:300px"><?= h($m['description']) ?></td>
              <td style="font-size:.78rem;color:var(--muted)"><?= h(date('d M Y H:i', strtotime($m['created_at']))) ?></td>
              <td>
                <form method="POST" onsubmit="return confirm('Delete this entry?')">
                  <input type="hidden" name="action" value="delete_revenue">
                  <input type="hidden" name="entry_id" value="<?= (int)$m['id'] ?>">
                  <button class="btn-del" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="6">No manual entries yet. Add one above.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($section === 'applications'): ?>
  <!-- ── MENTOR APPLICATIONS ────────────────────────────────── -->

    <div class="section-title">
      Mentor Applications
      <span><?= count($applications) ?> total</span>
      <?php if ($portal['mentors_pending'] > 0): ?>
        <span style="background:rgba(251,191,36,.15);color:var(--gold);border-color:rgba(251,191,36,.3);"><?= (int)$portal['mentors_pending'] ?> pending</span>
      <?php endif; ?>
    </div>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:1.25rem;">
      Full approve / reject / request-info workflow is available in Phase 3.
    </p>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Name</th><th>Email</th><th>Tech Stack</th><th>LinkedIn</th><th>Status</th><th>Applied</th></tr></thead>
          <tbody>
          <?php if ($applications): foreach ($applications as $a): ?>
            <tr>
              <td style="font-weight:600"><?= h($a['full_name']) ?></td>
              <td style="font-size:.82rem;color:var(--muted)"><?= h($a['email']) ?></td>
              <td style="font-size:.78rem;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($a['tech_stack'] ?? '') ?></td>
              <td><?php if ($a['linkedin']): ?><a href="<?= h($a['linkedin']) ?>" target="_blank" rel="noopener" style="color:var(--cyan);font-size:.8rem;">View ↗</a><?php else: echo '<span style="color:var(--muted)">—</span>'; endif; ?></td>
              <td>
                <?php
                $sc = ['pending'=>'badge-gold','approved'=>'badge-green','rejected'=>''];
                $cls = $sc[$a['status']] ?? '';
                ?>
                <span class="badge <?= $cls ?>"><?= h($a['status']) ?></span>
              </td>
              <td style="font-size:.78rem;color:var(--muted)"><?= h(date('d M Y', strtotime($a['created_at']))) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="6">No mentor applications yet. Share the partner form link to start receiving applications.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($section === 'mentors'): ?>
  <!-- ── MENTORS ────────────────────────────────────────────── -->

    <div class="section-title">Mentors <span><?= (int)$portal['mentors_total'] ?> total</span></div>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:1.25rem;">
      Mentor profile editing, custom fields, rate overrides, and assignment controls come in Phase 3.
    </p>
    <div class="card">
      <div class="table-wrap">
        <?php
        $all_mentors = [];
        try { $all_mentors = $db->query("SELECT * FROM `{$p}enp_mentors` WHERE status='approved' ORDER BY full_name")->fetchAll(); } catch (PDOException $e) {}
        ?>
        <table>
          <thead><tr><th>Name</th><th>Email</th><th>Tech Stack</th><th>Slots</th><th>Rate / Session</th><th>Status</th></tr></thead>
          <tbody>
          <?php if ($all_mentors): foreach ($all_mentors as $m): ?>
            <tr>
              <td style="font-weight:600"><?= h($m['full_name']) ?></td>
              <td style="font-size:.82rem;color:var(--muted)"><?= h($m['email']) ?></td>
              <td style="font-size:.78rem;max-width:200px"><?= h($m['tech_stack'] ?? '') ?></td>
              <td><?= (int)$m['available_slots'] ?></td>
              <td style="color:var(--green)">&#8377;<?= number_format((float)$m['rate_per_session'], 0) ?></td>
              <td><span class="badge badge-green"><?= h($m['status']) ?></span></td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="6">No approved mentors yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($section === 'students'): ?>
  <!-- ── STUDENTS ───────────────────────────────────────────── -->

    <div class="section-title">Students <span><?= (int)$portal['students_total'] ?> total</span></div>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:1.25rem;">
      Full student management (activate, assign mentor, override sessions) comes in Phase 4.
    </p>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Name</th><th>Plan</th><th>Sessions</th><th>Mentor</th><th>CV Redesign</th><th>Status</th><th>Enrolled</th></tr></thead>
          <tbody>
          <?php if ($portal_students): foreach ($portal_students as $s): ?>
            <tr>
              <td style="font-weight:600"><?= h($s['full_name']) ?></td>
              <td><span class="badge badge-cyan"><?= h(strtoupper($s['plan_id'])) ?></span></td>
              <td><?= (int)$s['sessions_used'] ?> / <?= (int)$s['sessions_total'] ?></td>
              <td style="font-size:.82rem"><?= $s['mentor_name'] ? h($s['mentor_name']) : '<span style="color:var(--muted)">Unassigned</span>' ?></td>
              <td><span class="badge <?= $s['cv_redesign_status']==='done' ? 'badge-green' : 'badge-gold' ?>"><?= h($s['cv_redesign_status']) ?></span></td>
              <td><span class="badge <?= $s['status']==='active' ? 'badge-green' : '' ?>"><?= h($s['status']) ?></span></td>
              <td style="font-size:.78rem;color:var(--muted)"><?= h(date('d M Y', strtotime($s['created_at']))) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="7">No students enrolled yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($section === 'sessions'): ?>
  <!-- ── SESSIONS ───────────────────────────────────────────── -->

    <div class="section-title">Sessions <span><?= (int)$portal['sessions_total'] ?> total</span></div>
    <div class="card">
      <div class="table-wrap">
        <?php
        $all_sessions = [];
        try {
            $all_sessions = $db->query("
                SELECT ss.*, st.full_name AS student_name, m.full_name AS mentor_name
                FROM `{$p}enp_sessions` ss
                LEFT JOIN `{$p}enp_students` st ON ss.student_id = st.id
                LEFT JOIN `{$p}enp_mentors`  m  ON ss.mentor_id  = m.id
                ORDER BY ss.scheduled_at DESC LIMIT 100
            ")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <table>
          <thead><tr><th>Date</th><th>Student</th><th>Mentor</th><th>Duration</th><th>Rate</th><th>Status</th><th>Mentor Paid</th></tr></thead>
          <tbody>
          <?php if ($all_sessions): foreach ($all_sessions as $ss): ?>
            <tr>
              <td style="white-space:nowrap"><?= h(date('d M Y H:i', strtotime($ss['scheduled_at']))) ?></td>
              <td><?= h($ss['student_name'] ?? '—') ?></td>
              <td><?= h($ss['mentor_name']  ?? '—') ?></td>
              <td><?= (int)$ss['duration_min'] ?> min</td>
              <td style="color:var(--green)">&#8377;<?= number_format((float)$ss['rate_applied'], 0) ?></td>
              <td><span class="badge <?= $ss['status']==='completed' ? 'badge-green' : 'badge-cyan' ?>"><?= h($ss['status']) ?></span></td>
              <td><?= $ss['mentor_paid'] ? '<span class="badge badge-green">Yes</span>' : '<span style="color:var(--muted);font-size:.8rem;">No</span>' ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="7">No sessions recorded yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($section === 'payments'): ?>
  <!-- ── PORTAL PAYMENTS ────────────────────────────────────── -->

    <div class="section-title">Portal Payments</div>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:1.25rem;">
      Razorpay payment integration and manual "mark paid" are built in Phase 4.
    </p>
    <div class="card">
      <div class="table-wrap">
        <?php
        $portal_payments = [];
        try {
            $portal_payments = $db->query("SELECT * FROM `{$p}enp_payments` ORDER BY created_at DESC LIMIT 100")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <table>
          <thead><tr><th>Date</th><th>Email</th><th>Plan</th><th>Amount</th><th>Gateway</th><th>Gateway ID</th><th>Status</th></tr></thead>
          <tbody>
          <?php if ($portal_payments): foreach ($portal_payments as $pay): ?>
            <tr>
              <td style="white-space:nowrap"><?= h(date('d M Y', strtotime($pay['created_at']))) ?></td>
              <td style="font-size:.82rem"><?= h($pay['email']) ?></td>
              <td><span class="badge badge-cyan"><?= h(strtoupper($pay['plan_id'])) ?></span></td>
              <td class="amount">&#8377;<?= number_format((float)$pay['amount'], 0) ?></td>
              <td style="font-size:.8rem"><?= h($pay['gateway']) ?></td>
              <td style="font-size:.76rem;color:var(--muted);font-family:monospace"><?= h($pay['gateway_payment_id'] ?: $pay['gateway_order_id'] ?: '—') ?></td>
              <td><span class="badge <?= $pay['status']==='paid' ? 'badge-green' : 'badge-gold' ?>"><?= h($pay['status']) ?></span></td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="7">No portal payments yet. Razorpay integration is built in Phase 4.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php endif; ?>

  </main>
</div><!-- .dash -->

<script>
(function(){
  if (document.getElementById('revenueChart') === null) return;

  const labels  = <?= json_encode($monthly_labels) ?>;
  const paypal  = <?= json_encode($monthly_paypal) ?>;
  const manual  = <?= json_encode($monthly_manual) ?>;

  new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label: 'PayPal', data: paypal, backgroundColor: 'rgba(34,211,238,.7)', borderRadius: 4 },
        { label: 'Manual', data: manual, backgroundColor: 'rgba(251,191,36,.6)', borderRadius: 4 },
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#94a3b8', font: { size: 12 } } },
        tooltip: { callbacks: { label: ctx => ' $' + ctx.parsed.y.toFixed(2) } }
      },
      scales: {
        x: { stacked: true, ticks: { color: '#94a3b8', font: { size: 11 } }, grid: { color: '#1f2937' } },
        y: { stacked: true, ticks: { color: '#94a3b8', callback: v => '$' + v }, grid: { color: '#1f2937' } }
      }
    }
  });
})();
</script>

<?php endif; // logged_in ?>
</body>
</html>
