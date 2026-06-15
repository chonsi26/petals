<?php
session_start();

// ─── DATABASE CONFIG ──────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'petals_db');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("<div style='font-family:sans-serif;padding:2rem;color:#c2185b;'><strong>Database connection failed:</strong> " . htmlspecialchars($e->getMessage()) . "<br><br>Please create the database and run the setup SQL first.</div>");
        }
    }
    return $pdo;
}

// ─── AUTH ─────────────────────────────────────────────────────────────────────
function isLoggedIn() { return isset($_SESSION['admin_id']); }

function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
        $stmt->execute([trim($_POST['username'])]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($_POST['password'], $user['password'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_name'] = $user['username'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        return "Invalid username or password.";
    }
    return null;
}

function handleLogout() {
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ─── CRUD HELPERS ─────────────────────────────────────────────────────────────
function handleFlowers() {
    $pdo = getDB();
    $msg = '';

    $handleUpload = function() {
        if (isset($_FILES['fimage']) && $_FILES['fimage']['error'] === UPLOAD_ERR_OK) {
            $dir = 'uploads/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $filename = time() . '_' . basename($_FILES['fimage']['name']);
            $target = $dir . $filename;
            if (move_uploaded_file($_FILES['fimage']['tmp_name'], $target)) {
                return $target;
            }
        }
        return null;
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_flower'])) {
            $imageUrl = $handleUpload() ?? '';
            $pdo->prepare("INSERT INTO flowers (name,category,price,stock,description,image_url) VALUES (?,?,?,?,?,?)")
                ->execute([$_POST['fname'],$_POST['fcategory'],$_POST['fprice'],$_POST['fstock'],$_POST['fdesc'],$imageUrl]);
            $msg = "✿ Flower added successfully!";
        } elseif (isset($_POST['edit_flower'])) {
            $imageUrl = $handleUpload();
            if ($imageUrl !== null) {
                $pdo->prepare("UPDATE flowers SET name=?,category=?,price=?,stock=?,description=?,image_url=? WHERE id=?")
                    ->execute([$_POST['fname'],$_POST['fcategory'],$_POST['fprice'],$_POST['fstock'],$_POST['fdesc'],$imageUrl,$_POST['fid']]);
            } else {
                $pdo->prepare("UPDATE flowers SET name=?,category=?,price=?,stock=?,description=? WHERE id=?")
                    ->execute([$_POST['fname'],$_POST['fcategory'],$_POST['fprice'],$_POST['fstock'],$_POST['fdesc'],$_POST['fid']]);
            }
            $msg = "✿ Flower updated successfully!";
        }
    }
    if (isset($_GET['delete_flower'])) {
        $pdo->prepare("DELETE FROM flowers WHERE id=?")->execute([$_GET['delete_flower']]);
        $msg = "✿ Flower deleted.";
    }
    return $msg;
}

function handleCustomers() {
    $pdo = getDB();
    $msg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_customer'])) {
            $pdo->prepare("INSERT INTO customers (name,email,phone,address) VALUES (?,?,?,?)")
                ->execute([$_POST['cname'],$_POST['cemail'],$_POST['cphone'],$_POST['caddress']]);
            $msg = "✿ Customer added successfully!";
        } elseif (isset($_POST['edit_customer'])) {
            $pdo->prepare("UPDATE customers SET name=?,email=?,phone=?,address=? WHERE id=?")
                ->execute([$_POST['cname'],$_POST['cemail'],$_POST['cphone'],$_POST['caddress'],$_POST['cid']]);
            $msg = "✿ Customer updated.";
        }
    }
    if (isset($_GET['delete_customer'])) {
        $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$_GET['delete_customer']]);
        $msg = "✿ Customer removed.";
    }
    return $msg;
}

function handleOrders() {
    $pdo = getDB();
    $msg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_order'])) {
            $flower = $pdo->prepare("SELECT price FROM flowers WHERE id=?");
            $flower->execute([$_POST['oflower']]);
            $price = $flower->fetchColumn();
            $total = $price * $_POST['oquantity'];
            $pdo->prepare("INSERT INTO orders (customer_id,flower_id,quantity,total_price,status) VALUES (?,?,?,?,?)")
                ->execute([$_POST['ocustomer'],$_POST['oflower'],$_POST['oquantity'],$total,$_POST['ostatus']]);
            $msg = "✿ Order placed successfully!";
        } elseif (isset($_POST['edit_order'])) {
            $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$_POST['ostatus'],$_POST['oid']]);
            $msg = "✿ Order status updated.";
        }
    }
    if (isset($_GET['delete_order'])) {
        $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([$_GET['delete_order']]);
        $msg = "✿ Order removed.";
    }
    return $msg;
}

// ─── BOOT ─────────────────────────────────────────────────────────────────────
handleLogout();
$loginError = handleLogin();
$page = $_GET['page'] ?? 'dashboard';
$flowerMsg = $customerMsg = $orderMsg = '';
if (isLoggedIn()) {
    $flowerMsg   = handleFlowers();
    $customerMsg = handleCustomers();
    $orderMsg    = handleOrders();
}

// ─── DATA FETCHERS ────────────────────────────────────────────────────────────
function getStats() {
    $pdo = getDB();
    return [
        'flowers'   => $pdo->query("SELECT COUNT(*) FROM flowers")->fetchColumn(),
        'customers' => $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
        'orders'    => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'revenue'   => $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM orders WHERE status != 'cancelled'")->fetchColumn(),
        'pending'   => $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
        'low_stock' => $pdo->query("SELECT COUNT(*) FROM flowers WHERE stock < 20")->fetchColumn(),
    ];
}

function getChartData() {
    $pdo = getDB();
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $months[] = date('Y-m', strtotime("-$i months"));
    }
    $revenueData = [];
    $ordersData  = [];
    foreach ($months as $m) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price),0), COUNT(*) FROM orders WHERE DATE_FORMAT(order_date,'%Y-%m')=? AND status!='cancelled'");
        $stmt->execute([$m]);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $revenueData[] = round($row[0], 2);
        $ordersData[]  = (int)$row[1];
    }
    $monthLabels = array_map(fn($m) => date('M Y', strtotime($m . '-01')), $months);

    $catStmt = $pdo->query("SELECT category, COUNT(*) as cnt FROM flowers GROUP BY category");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    $topStmt = $pdo->query("SELECT f.name, COALESCE(SUM(o.quantity),0) as sold
        FROM flowers f LEFT JOIN orders o ON f.id=o.flower_id GROUP BY f.id ORDER BY sold DESC LIMIT 5");
    $topFlowers = $topStmt->fetchAll(PDO::FETCH_ASSOC);

    $statusStmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status");
    $statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    return compact('monthLabels','revenueData','ordersData','categories','topFlowers','statuses');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Petals — Flower Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;1,500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --rose:       #d63a6c;
    --rose-dark:  #a52a52;
    --rose-mid:   #e8608a;
    --blush:      #f9d4e4;
    --blush-soft: #fdf0f6;
    --blush-pale: #fff5f9;
    --petal:      #f7c5d8;
    --text-dark:  #3a1a28;
    --text-mid:   #7a4a5e;
    --text-soft:  #b07a90;
    --white:      #ffffff;
    --border:     #f0d0de;
    --shadow:     rgba(214,58,108,0.12);
    --shadow-lg:  rgba(214,58,108,0.20);
    --radius:     14px;
    --radius-sm:  8px;
    --sidebar-w:  240px;
}
html { font-size: 15px; }
body { font-family: 'DM Sans', sans-serif; background: var(--blush-pale); color: var(--text-dark); min-height: 100vh; }

/* ── LOGIN ── */
.login-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center;
    background: linear-gradient(135deg, #fde8f3 0%, #f8c3dc 40%, #f4a0c5 100%); }
.login-card { background: var(--white); border-radius: 24px; padding: 3rem 2.5rem;
    width: 100%; max-width: 400px; box-shadow: 0 20px 60px var(--shadow-lg); }
.login-logo { text-align:center; margin-bottom:2rem; }
.login-logo .flower-icon { font-size: 3.5rem; display:block; margin-bottom:.5rem; }
.login-logo h1 { font-family: 'Playfair Display', serif; font-size: 2.2rem; color: var(--rose); letter-spacing:-0.5px; }
.login-logo p { color: var(--text-soft); font-size:.85rem; margin-top:.25rem; }
.form-group { margin-bottom: 1.2rem; }
.form-group label { display:block; font-size:.8rem; font-weight:600; color:var(--text-mid);
    text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem; }
.form-group input { width:100%; padding:.75rem 1rem; border: 1.5px solid var(--border);
    border-radius: var(--radius-sm); font-family:'DM Sans',sans-serif; font-size:.95rem;
    color:var(--text-dark); background:var(--blush-pale); transition: border-color .2s, box-shadow .2s; outline:none; }
.form-group input:focus { border-color:var(--rose-mid); box-shadow: 0 0 0 3px rgba(214,58,108,.1); }
.btn-login { width:100%; padding:.85rem; background: linear-gradient(135deg, var(--rose), var(--rose-mid));
    color:var(--white); border:none; border-radius: var(--radius-sm); font-family:'DM Sans',sans-serif;
    font-size:1rem; font-weight:600; cursor:pointer; transition: transform .15s, box-shadow .15s; letter-spacing:.03em; }
.btn-login:hover { transform:translateY(-1px); box-shadow: 0 8px 20px var(--shadow-lg); }
.error-msg { background:#fee2ee; color:#c2185b; padding:.75rem 1rem; border-radius:var(--radius-sm);
    font-size:.88rem; margin-bottom:1rem; border-left:3px solid var(--rose); }
.login-hint { text-align:center; font-size:.8rem; color:var(--text-soft); margin-top:1.2rem; }
.login-hint code { background:var(--blush); padding:.1rem .35rem; border-radius:4px; color:var(--rose-dark); }

/* ── LAYOUT ── */
.app { display:flex; min-height:100vh; }

/* ── SIDEBAR ── */
.sidebar { width:var(--sidebar-w); background: var(--blush-pale);
    flex-shrink:0; display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh;
    z-index:100; box-shadow: 4px 0 24px var(--shadow); border-right: 1px solid var(--border); }
.sidebar-brand { padding:1.6rem 1.4rem 1.2rem; border-bottom:1px solid var(--border); }
.sidebar-brand .brand-icon { font-size:2rem; }
.sidebar-brand h1 { font-family:'Playfair Display',serif; color:var(--rose-dark); font-size:1.7rem;
    letter-spacing:-.5px; line-height:1; }
.sidebar-brand p { color:var(--text-soft); font-size:.73rem; letter-spacing:.08em; text-transform:uppercase; margin-top:.2rem; }
.sidebar-nav { flex:1; padding:1rem 0; overflow-y:auto; }
.nav-section { padding:.4rem 1.2rem .2rem; font-size:.65rem; font-weight:700; letter-spacing:.12em;
    text-transform:uppercase; color:var(--text-soft); margin-top:.5rem; }
.nav-item { display:flex; align-items:center; gap:.75rem; padding:.65rem 1.4rem;
    color:var(--text-mid); text-decoration:none; font-size:.9rem; font-weight:500;
    transition: background .15s, color .15s; border-left:3px solid transparent; cursor:pointer; }
.nav-item:hover { background:var(--blush); color:var(--rose-dark); }
.nav-item.active { background:var(--blush); color:var(--rose-dark); border-left-color:var(--rose); font-weight:600; }
.nav-item .icon { font-size:1.1rem; width:20px; text-align:center; }
.sidebar-footer { padding:1rem 1.4rem; border-top:1px solid var(--border); }
.sidebar-footer .admin-info { display:flex; align-items:center; gap:.75rem; }
.admin-avatar { width:36px; height:36px; border-radius:50%; background:var(--blush);
    display:flex; align-items:center; justify-content:center; font-size:1rem; }
.admin-meta { flex:1; }
.admin-meta .name { color:var(--text-dark); font-weight:600; font-size:.88rem; }
.admin-meta .role { color:var(--text-soft); font-size:.72rem; }
.logout-btn { color:var(--text-soft); text-decoration:none; font-size:.75rem;
    display:block; margin-top:.6rem; transition:color .15s; }
.logout-btn:hover { color:var(--rose-dark); }

/* ── MAIN ── */
.main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; min-height:100vh; }
.topbar { background:var(--white); border-bottom:1px solid var(--border); padding:1rem 2rem;
    display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50;
    box-shadow: 0 2px 12px var(--shadow); }
.topbar-title { font-family:'Playfair Display',serif; font-size:1.5rem; color:var(--rose-dark); font-style:italic; }
.topbar-right { display:flex; align-items:center; gap:1rem; }
.badge-pill { background:var(--blush); color:var(--rose); padding:.25rem .75rem; border-radius:50px;
    font-size:.75rem; font-weight:600; }
.content { padding:2rem; flex:1; }

/* ── CARDS ── */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:1.2rem; margin-bottom:2rem; }
.stat-card { background:var(--white); border-radius:var(--radius); padding:1.4rem; position:relative; overflow:hidden;
    box-shadow: 0 2px 16px var(--shadow); border:1px solid var(--border); transition: transform .2s, box-shadow .2s; }
.stat-card:hover { transform:translateY(-2px); box-shadow:0 8px 28px var(--shadow-lg); }
.stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px;
    background: linear-gradient(90deg, var(--rose), var(--rose-mid)); }
.stat-card .stat-icon { font-size:1.8rem; margin-bottom:.6rem; }
.stat-card .stat-val { font-size:1.9rem; font-weight:700; color:var(--rose-dark); line-height:1; }
.stat-card .stat-label { font-size:.75rem; color:var(--text-soft); font-weight:500;
    text-transform:uppercase; letter-spacing:.06em; margin-top:.3rem; }
.stat-card .stat-sub { font-size:.78rem; color:var(--text-mid); margin-top:.4rem; }

/* ── CHARTS ── */
.charts-grid { display:grid; grid-template-columns:2fr 1fr; gap:1.2rem; margin-bottom:2rem; }
.charts-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1.2rem; margin-bottom:2rem; }
.chart-card { background:var(--white); border-radius:var(--radius); padding:1.5rem;
    box-shadow: 0 2px 16px var(--shadow); border:1px solid var(--border); }
.chart-card h3 { font-family:'Playfair Display',serif; font-size:1.05rem; color:var(--rose-dark);
    margin-bottom:1.2rem; display:flex; align-items:center; gap:.5rem; }
.chart-card h3 span { font-size:1.1rem; }
.chart-wrap { position:relative; }
.chart-wrap canvas { max-width:100%; }

/* ── TABLES ── */
.section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.2rem; }
.section-header h2 { font-family:'Playfair Display',serif; font-size:1.4rem; color:var(--rose-dark); font-style:italic; }
.table-card { background:var(--white); border-radius:var(--radius); box-shadow:0 2px 16px var(--shadow);
    border:1px solid var(--border); overflow:hidden; }
.data-table { width:100%; border-collapse:collapse; }
.data-table thead tr { background: linear-gradient(90deg, var(--blush), #fce8f2); }
.data-table th { padding:.85rem 1rem; text-align:left; font-size:.75rem; font-weight:700;
    color:var(--rose-dark); text-transform:uppercase; letter-spacing:.07em; border-bottom:1px solid var(--border); }
.data-table td { padding:.8rem 1rem; border-bottom:1px solid #fdf0f5; font-size:.88rem; color:var(--text-dark); }
.data-table tbody tr:hover { background:var(--blush-pale); }
.data-table tbody tr:last-child td { border-bottom:none; }
.flower-thumb { width:40px; height:40px; border-radius:8px; object-fit:cover; border:2px solid var(--petal); }
.flower-thumb-placeholder { width:40px; height:40px; border-radius:8px; background:var(--blush);
    display:inline-flex; align-items:center; justify-content:center; font-size:1.3rem; border:2px solid var(--petal); }

/* ── FLOWER CARDS ── */
.flower-cards-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:1.5rem; margin-bottom:2rem; }
.flower-card { background:var(--white); border-radius:var(--radius); overflow:hidden; box-shadow:0 2px 16px var(--shadow); border:1px solid var(--border); display:flex; flex-direction:column; transition:transform .2s, box-shadow .2s; }
.flower-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px var(--shadow-lg); }
.fc-image { width:100%; height:180px; object-fit:cover; border-bottom:1px solid var(--border); }
.fc-image-placeholder { width:100%; height:180px; background:var(--blush); display:flex; align-items:center; justify-content:center; font-size:3.5rem; border-bottom:1px solid var(--border); }
.fc-body { padding:1.2rem; display:flex; flex-direction:column; flex:1; }
.fc-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:.5rem; gap:.5rem; }
.fc-title { font-family:'Playfair Display',serif; font-size:1.2rem; color:var(--rose-dark); margin:0; line-height:1.2; }
.fc-category { background:var(--blush-pale); color:var(--rose); padding:.2rem .6rem; border-radius:50px; font-size:.7rem; font-weight:600; border:1px solid var(--border); white-space:nowrap; }
.fc-meta { display:flex; justify-content:space-between; align-items:center; margin-bottom:.8rem; font-size:.9rem; }
.fc-price { font-weight:700; color:var(--text-dark); font-size:1.1rem; }
.fc-stock { font-weight:600; color:var(--text-mid); font-size:.8rem; }
.fc-desc { font-size:.85rem; color:var(--text-soft); line-height:1.4; margin-bottom:1.2rem; flex:1; }
.fc-actions { display:flex; gap:.5rem; margin-top:auto; border-top:1px solid var(--blush-soft); padding-top:1rem; }
.fc-actions .btn { flex:1; justify-content:center; }

/* ── BADGES ── */
.badge { display:inline-block; padding:.2rem .6rem; border-radius:50px; font-size:.72rem; font-weight:600; }
.badge-pending    { background:#fff3cd; color:#856404; }
.badge-processing { background:#cfe2ff; color:#084298; }
.badge-delivered  { background:#d1e7dd; color:#0f5132; }
.badge-cancelled  { background:#f8d7da; color:#842029; }
.stock-low { color:var(--rose); font-weight:600; }

/* ── BUTTONS ── */
.btn { display:inline-flex; align-items:center; gap:.4rem; padding:.5rem .9rem; border-radius:var(--radius-sm);
    font-family:'DM Sans',sans-serif; font-size:.82rem; font-weight:600; cursor:pointer; border:none;
    text-decoration:none; transition: all .15s; }
.btn-primary { background:linear-gradient(135deg, var(--rose), var(--rose-mid)); color:var(--white); }
.btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 14px var(--shadow-lg); }
.btn-outline { background:transparent; color:var(--rose); border:1.5px solid var(--rose); }
.btn-outline:hover { background:var(--blush); }
.btn-danger { background:transparent; color:#dc3545; border:1.5px solid #dc3545; }
.btn-danger:hover { background:#fee2e2; }
.btn-sm { padding:.3rem .6rem; font-size:.75rem; }
.btn-xs { padding:.2rem .45rem; font-size:.7rem; }

/* ── MODAL ── */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:500;
    align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal { background:var(--white); border-radius:18px; padding:2rem; width:100%; max-width:480px;
    box-shadow:0 20px 60px rgba(0,0,0,.25); max-height:90vh; overflow-y:auto; }
.modal h2 { font-family:'Playfair Display',serif; color:var(--rose-dark); font-size:1.3rem; margin-bottom:1.4rem; font-style:italic; }
.modal .form-group label { font-size:.78rem; }
.modal .form-group select, .modal .form-group input, .modal .form-group textarea {
    width:100%; padding:.65rem .9rem; border:1.5px solid var(--border); border-radius:var(--radius-sm);
    font-family:'DM Sans',sans-serif; font-size:.88rem; color:var(--text-dark); background:var(--blush-pale);
    transition: border-color .2s; outline:none; }
.modal .form-group select:focus, .modal .form-group input:focus, .modal .form-group textarea:focus {
    border-color:var(--rose-mid); box-shadow:0 0 0 3px rgba(214,58,108,.1); }
.modal .form-group textarea { resize:vertical; min-height:70px; }
.modal-footer { display:flex; gap:.75rem; justify-content:flex-end; margin-top:1.4rem; }
.close-modal { float:right; background:none; border:none; font-size:1.4rem; cursor:pointer;
    color:var(--text-soft); margin-top:-.5rem; transition:color .15s; }
.close-modal:hover { color:var(--rose); }

/* ── FLASH ── */
.flash { padding:.75rem 1rem; border-radius:var(--radius-sm); margin-bottom:1.2rem;
    font-size:.88rem; background:#fce8f2; color:var(--rose-dark); border-left:3px solid var(--rose);
    display:flex; align-items:center; gap:.5rem; }

/* ── EMPTY ── */
.empty-state { text-align:center; padding:3rem 1rem; color:var(--text-soft); }
.empty-state .e-icon { font-size:3rem; display:block; margin-bottom:.75rem; }

/* ── RECENT TABLE IN DASHBOARD ── */
.dash-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.2rem; margin-bottom:2rem; }

/* ── MOBILE TOPBAR TOGGLE (hidden on desktop) ── */
.menu-toggle { display:none; background:none; border:none; font-size:1.4rem; color:var(--rose-dark);
    cursor:pointer; padding:.2rem .4rem; line-height:1; }
.sidebar-overlay { display:none; }

/* ── RESPONSIVE / MOBILE ── */
@media (max-width: 900px) {
  html { font-size: 14px; }
  html, body { overflow-x: hidden; max-width: 100%; }

  .app { display:block; }

  .menu-toggle { display:inline-flex; align-items:center; }

  .sidebar {
    transform: translateX(-100%);
    transition: transform .25s ease;
    width: min(80vw, 280px);
  }
  .sidebar.open { transform: translateX(0); }

  .sidebar-overlay {
    display:block; position:fixed; inset:0; background:rgba(0,0,0,.4);
    z-index:99; opacity:0; pointer-events:none; transition:opacity .25s ease;
  }
  .sidebar-overlay.open { opacity:1; pointer-events:auto; }

  .main { margin-left:0; width:100%; max-width:100%; min-width:0; }

  .topbar { padding:.85rem 1rem; gap:.5rem; }
  .topbar-title { font-size:1.15rem; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .topbar-right { gap:.5rem; flex-wrap:nowrap; }
  .topbar-right span { display:none; } /* hide the date on small screens */
  .badge-pill { font-size:.7rem; padding:.2rem .55rem; }

  .content { padding:1rem; max-width:100%; min-width:0; overflow-x:hidden; }

  .stats-grid { grid-template-columns: repeat(2, 1fr); gap:.8rem; margin-bottom:1.2rem; }
  .stat-card { padding:1rem; min-width:0; }
  .stat-card .stat-icon { font-size:1.4rem; margin-bottom:.3rem; }
  .stat-card .stat-val { font-size:1.4rem; }
  .stat-card .stat-label { font-size:.68rem; }
  .stat-card .stat-sub { font-size:.7rem; }

  .charts-grid, .charts-grid-3, .dash-grid { grid-template-columns: 1fr; gap:1rem; margin-bottom:1.2rem; }
  .chart-card { padding:1rem; min-width:0; max-width:100%; }
  .chart-card h3 { font-size:.95rem; margin-bottom:.9rem; }
  .chart-wrap { height:200px !important; max-width:100%; }
  .chart-wrap canvas { max-width:100% !important; }

  .section-header { flex-direction:column; align-items:flex-start; gap:.6rem; }
  .section-header .btn { width:100%; justify-content:center; }

  /* Make tables horizontally scrollable instead of squashing */
  .table-card { overflow-x:auto; -webkit-overflow-scrolling:touch; max-width:100%; }
  .data-table { min-width: 640px; }

  /* Recent Orders table on dashboard sits inside a .chart-card, not .table-card */
  .chart-card { overflow-x:auto; -webkit-overflow-scrolling:touch; }
  .chart-card .data-table { min-width: 560px; }

  /* Modals: full-width sheet on mobile */
  .modal { max-width:100%; width:100%; max-height:100%; height:100%; border-radius:0;
    padding:1.2rem; }
  .modal-overlay { padding:0; }

  .login-card { padding:2.2rem 1.5rem; max-width:92vw; }
}

@media (max-width: 480px) {
  .stats-grid { grid-template-columns: 1fr 1fr; }
  .stat-card .stat-val { font-size:1.25rem; }
  .data-table { min-width: 560px; }
  .data-table th, .data-table td { padding:.6rem .7rem; font-size:.8rem; }
}
</style>
</head>
<body>

<?php if (!isLoggedIn()): ?>
<!-- ════════════════════════ LOGIN PAGE ════════════════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <span class="flower-icon">🌸</span>
      <h1>Petals</h1>
      <p>Flower Management System</p>
    </div>
    <?php if ($loginError): ?>
      <div class="error-msg"><?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" placeholder="admin" required autocomplete="username">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" name="login" class="btn-login">Sign In to Petals</button>
    </form>
    <p class="login-hint">Default credentials: <code>admin</code> / <code>admin123</code></p>
  </div>
</div>

<?php else:
    $stats    = getStats();
    $chartData = getChartData();
    $pdo       = getDB();

    $flowers   = $pdo->query("SELECT * FROM flowers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $customers = $pdo->query("SELECT * FROM customers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $orders    = $pdo->query("SELECT o.*, c.name as cname, c.email as cemail, c.phone as cphone, c.address as caddress, f.name as fname FROM orders o
                    LEFT JOIN customers c ON o.customer_id=c.id
                    LEFT JOIN flowers f ON o.flower_id=f.id ORDER BY o.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $recentOrders = array_slice($orders, 0, 5);
?>

<!-- ════════════════════════ APP SHELL ════════════════════════ -->
<div class="app">

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">🌸</div>
    <h1>Petals</h1>
    <p>Admin Panel</p>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Main</div>
    <a href="?page=dashboard" class="nav-item <?= $page==='dashboard'?'active':'' ?>">
      <span class="icon">🏠</span> Dashboard
    </a>
    <div class="nav-section">Manage</div>
    <a href="?page=flowers" class="nav-item <?= $page==='flowers'?'active':'' ?>">
      <span class="icon">🌹</span> Flowers
    </a>
    <a href="?page=customers" class="nav-item <?= $page==='customers'?'active':'' ?>">
      <span class="icon">👤</span> Customers
    </a>
    <a href="?page=orders" class="nav-item <?= $page==='orders'?'active':'' ?>">
      <span class="icon">📦</span> Orders
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="admin-info">
      <div class="admin-avatar">🌸</div>
      <div class="admin-meta">
        <div class="name"><?= htmlspecialchars($_SESSION['admin_name']) ?></div>
        <div class="role">Administrator</div>
      </div>
    </div>
    <a href="?logout=1" class="logout-btn">⟵ Sign Out</a>
  </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main">
  <div class="topbar">
    <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">☰</button>
    <div class="topbar-title">
      <?php
        $titles = ['dashboard'=>'Good day, welcome back 🌸','flowers'=>'Flower Catalog','customers'=>'Customers','orders'=>'Orders'];
        echo $titles[$page] ?? 'Petals';
      ?>
    </div>
    <div class="topbar-right">
      <?php if ($stats['pending'] > 0): ?>
        <span class="badge-pill">⚡ <?= $stats['pending'] ?> pending</span>
      <?php endif; ?>
      <span style="color:var(--text-soft);font-size:.83rem;"><?= date('D, M j Y') ?></span>
    </div>
  </div>

  <div class="content">

  <?php
  // ─────────────────── DASHBOARD ───────────────────
  if ($page === 'dashboard'):
    $labels   = json_encode($chartData['monthLabels']);
    $revenues = json_encode($chartData['revenueData']);
    $ordersD  = json_encode($chartData['ordersData']);
    $catLabels= json_encode(array_column($chartData['categories'], 'category'));
    $catVals  = json_encode(array_column($chartData['categories'], 'cnt'));
    $topNames = json_encode(array_column($chartData['topFlowers'], 'name'));
    $topSold  = json_encode(array_column($chartData['topFlowers'], 'sold'));
    $statusLabels = json_encode(array_column($chartData['statuses'], 'status'));
    $statusVals   = json_encode(array_column($chartData['statuses'], 'cnt'));
  ?>

  <!-- STAT CARDS -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">🌹</div>
      <div class="stat-val"><?= $stats['flowers'] ?></div>
      <div class="stat-label">Flower Types</div>
      <?php if ($stats['low_stock']): ?>
        <div class="stat-sub" style="color:var(--rose)">⚠ <?= $stats['low_stock'] ?> low stock</div>
      <?php endif; ?>
    </div>
    <div class="stat-card">
      <div class="stat-icon">👤</div>
      <div class="stat-val"><?= $stats['customers'] ?></div>
      <div class="stat-label">Customers</div>
      <div class="stat-sub">registered accounts</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">📦</div>
      <div class="stat-val"><?= $stats['orders'] ?></div>
      <div class="stat-label">Total Orders</div>
      <div class="stat-sub"><?= $stats['pending'] ?> pending</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">💰</div>
      <div class="stat-val">₱<?= number_format($stats['revenue'],2) ?></div>
      <div class="stat-label">Total Revenue</div>
      <div class="stat-sub">excl. cancelled</div>
    </div>
  </div>

  <!-- CHARTS ROW 1 -->
  <div class="charts-grid">
    <div class="chart-card">
      <h3><span>📈</span> Revenue &amp; Orders (Last 6 Months)</h3>
      <div class="chart-wrap" style="height:220px;">
        <canvas id="revenueChart"></canvas>
      </div>
    </div>
    <div class="chart-card">
      <h3><span>🥧</span> Order Status</h3>
      <div class="chart-wrap" style="height:220px;">
        <canvas id="statusChart"></canvas>
      </div>
    </div>
  </div>

  <!-- CHARTS ROW 2 -->
  <div class="charts-grid">
    <div class="chart-card">
      <h3><span>🌸</span> Top 5 Best-Selling Flowers</h3>
      <div class="chart-wrap" style="height:200px;">
        <canvas id="topChart"></canvas>
      </div>
    </div>
    <div class="chart-card">
      <h3><span>🌿</span> Flowers by Category</h3>
      <div class="chart-wrap" style="height:200px;">
        <canvas id="catChart"></canvas>
      </div>
    </div>
  </div>

  <!-- RECENT ORDERS TABLE -->
  <div class="chart-card">
    <h3><span>🕑</span> Recent Orders</h3>
    <table class="data-table">
      <thead><tr><th>#</th><th>Customer</th><th>Flower</th><th>Qty</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td><strong>#<?= $o['id'] ?></strong></td>
          <td><?= htmlspecialchars($o['cname'] ?? 'N/A') ?></td>
          <td><?= htmlspecialchars($o['fname'] ?? 'N/A') ?></td>
          <td><?= $o['quantity'] ?></td>
          <td>₱<?= number_format($o['total_price'],2) ?></td>
          <td><span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
          <td style="color:var(--text-soft);font-size:.8rem;"><?= date('M j, Y', strtotime($o['order_date'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$recentOrders): ?><tr><td colspan="7" class="empty-state">No orders yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <script>
  Chart.defaults.font.family = "'DM Sans', sans-serif";
  Chart.defaults.color = '#7a4a5e';
  const rose = '#d63a6c', blush = '#f9d4e4', roseMid = '#e8608a', petals = ['#d63a6c','#e8608a','#f4aac4','#c2185b','#f9d4e4','#7a2040'];

  // Revenue + Orders combo
  new Chart(document.getElementById('revenueChart'), {
    type:'bar',
    data:{
      labels:<?= $labels ?>,
      datasets:[
        { label:'Revenue (₱)', data:<?= $revenues ?>, backgroundColor:'rgba(214,58,108,.18)', borderColor:rose, borderWidth:2, borderRadius:6, yAxisID:'y' },
        { label:'Orders', data:<?= $ordersD ?>, type:'line', borderColor:'#e8608a', backgroundColor:'rgba(232,96,138,.1)', borderWidth:2.5, tension:.4, pointRadius:4, pointBackgroundColor:roseMid, yAxisID:'y1' }
      ]
    },
    options:{ responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ position:'top', labels:{ boxWidth:12, padding:16 } } },
      scales:{
        y:{ position:'left', grid:{ color:'rgba(214,58,108,.07)' }, ticks:{ callback:v=>'₱'+v } },
        y1:{ position:'right', grid:{ drawOnChartArea:false }, ticks:{ stepSize:1 } }
      }
    }
  });

  // Status doughnut
  new Chart(document.getElementById('statusChart'), {
    type:'doughnut',
    data:{ labels:<?= $statusLabels ?>, datasets:[{ data:<?= $statusVals ?>, backgroundColor:['#f0c040','#5b9bd5','#5cb87a','#d94f5c'], borderWidth:0, hoverOffset:6 }] },
    options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{ padding:14, boxWidth:12 } } }, cutout:'65%' }
  });

  // Top flowers horizontal bar
  new Chart(document.getElementById('topChart'), {
    type:'bar',
    data:{ labels:<?= $topNames ?>, datasets:[{ label:'Units Sold', data:<?= $topSold ?>,
      backgroundColor: petals.slice(0,5), borderRadius:6 }] },
    options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ display:false } },
      scales:{ x:{ grid:{ color:'rgba(214,58,108,.07)' }, ticks:{ stepSize:1 } }, y:{ grid:{ display:false } } }
    }
  });

  // Category polar
  new Chart(document.getElementById('catChart'), {
    type:'polarArea',
    data:{ labels:<?= $catLabels ?>, datasets:[{ data:<?= $catVals ?>, backgroundColor: petals.map(c=>c+'aa'), borderColor: petals, borderWidth:1 }] },
    options:{ responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, padding:12, font:{size:11} } } }
    }
  });
  </script>

  <?php
  // ─────────────────── FLOWERS ───────────────────
  elseif ($page === 'flowers'):
  ?>
  <?php if ($flowerMsg): ?><div class="flash"><?= htmlspecialchars($flowerMsg) ?></div><?php endif; ?>
  <div class="section-header">
    <h2>Flower Catalog</h2>
    <button class="btn btn-primary" onclick="openModal('addFlowerModal')">+ Add Flower</button>
  </div>
  <div class="flower-cards-grid">
    <?php foreach ($flowers as $f): ?>
    <div class="flower-card">
      <?php if ($f['image_url']): ?>
        <img src="<?= htmlspecialchars($f['image_url']) ?>" alt="<?= htmlspecialchars($f['name']) ?>" class="fc-image">
      <?php else: ?>
        <div class="fc-image-placeholder">🌸</div>
      <?php endif; ?>
      <div class="fc-body">
        <div class="fc-header">
          <h3 class="fc-title"><?= htmlspecialchars($f['name']) ?></h3>
          <span class="fc-category"><?= htmlspecialchars($f['category']) ?></span>
        </div>
        <div class="fc-meta">
          <span class="fc-price">₱<?= number_format($f['price'],2) ?></span>
          <span class="fc-stock <?= $f['stock'] < 20 ? 'stock-low' : '' ?>">Stock: <?= $f['stock'] ?><?= $f['stock'] < 20 ? ' ⚠' : '' ?></span>
        </div>
        <p class="fc-desc"><?= htmlspecialchars(strlen($f['description']) > 80 ? substr($f['description'],0,80).'…' : $f['description']) ?></p>
        <div class="fc-actions">
          <button class="btn btn-outline btn-sm" onclick='editFlower(<?= json_encode($f) ?>)'>✏ Edit</button>
          <a class="btn btn-danger btn-sm" href="?page=flowers&delete_flower=<?= $f['id'] ?>" onclick="return confirm('Delete this flower?')">✕ Del</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$flowers): ?>
      <div class="empty-state" style="grid-column: 1 / -1;"><span class="e-icon">🌸</span>No flowers yet. Add some!</div>
    <?php endif; ?>
  </div>

  <!-- ADD FLOWER MODAL -->
  <div class="modal-overlay" id="addFlowerModal">
    <div class="modal">
      <button class="close-modal" onclick="closeModal('addFlowerModal')">×</button>
      <h2>✿ Add New Flower</h2>
      <form method="POST" enctype="multipart/form-data">
        <div class="form-group"><label>Flower Name</label><input type="text" name="fname" required></div>
        <div class="form-group"><label>Category</label>
          <select name="fcategory"><option>Rose</option><option>Tulip</option><option>Lily</option><option>Sunflower</option><option>Lavender</option><option>Peony</option><option>Orchid</option><option>Other</option></select>
        </div>
        <div class="form-group"><label>Price (₱)</label><input type="number" name="fprice" step="0.01" min="0" required></div>
        <div class="form-group"><label>Stock</label><input type="number" name="fstock" min="0" value="0" required></div>
        <div class="form-group"><label>Description</label><textarea name="fdesc"></textarea></div>
        <div class="form-group"><label>Upload Photo</label><input type="file" name="fimage" accept="image/*"></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addFlowerModal')">Cancel</button><button type="submit" name="add_flower" class="btn btn-primary">Add Flower</button></div>
      </form>
    </div>
  </div>

  <!-- EDIT FLOWER MODAL -->
  <div class="modal-overlay" id="editFlowerModal">
    <div class="modal">
      <button class="close-modal" onclick="closeModal('editFlowerModal')">×</button>
      <h2>✿ Edit Flower</h2>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="fid" id="ef_id">
        <div class="form-group"><label>Flower Name</label><input type="text" name="fname" id="ef_name" required></div>
        <div class="form-group"><label>Category</label>
          <select name="fcategory" id="ef_cat"><option>Rose</option><option>Tulip</option><option>Lily</option><option>Sunflower</option><option>Lavender</option><option>Peony</option><option>Orchid</option><option>Other</option></select>
        </div>
        <div class="form-group"><label>Price (₱)</label><input type="number" name="fprice" id="ef_price" step="0.01" min="0" required></div>
        <div class="form-group"><label>Stock</label><input type="number" name="fstock" id="ef_stock" min="0" required></div>
        <div class="form-group"><label>Description</label><textarea name="fdesc" id="ef_desc"></textarea></div>
        <div class="form-group">
          <label>Upload New Photo (leave blank to keep current)</label>
          <input type="file" name="fimage" accept="image/*">
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('editFlowerModal')">Cancel</button><button type="submit" name="edit_flower" class="btn btn-primary">Save Changes</button></div>
      </form>
    </div>
  </div>
  <script>
  function editFlower(f) {
    document.getElementById('ef_id').value=f.id;
    document.getElementById('ef_name').value=f.name;
    document.getElementById('ef_cat').value=f.category;
    document.getElementById('ef_price').value=f.price;
    document.getElementById('ef_stock').value=f.stock;
    document.getElementById('ef_desc').value=f.description;
    openModal('editFlowerModal');
  }
  </script>

  <?php
  // ─────────────────── CUSTOMERS ───────────────────
  elseif ($page === 'customers'):
  ?>
  <?php if ($customerMsg): ?><div class="flash"><?= htmlspecialchars($customerMsg) ?></div><?php endif; ?>
  <div class="section-header">
    <h2>Customer List</h2>
    <button class="btn btn-primary" onclick="openModal('addCustModal')">+ Add Customer</button>
  </div>
  <div class="table-card">
    <table class="data-table">
      <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
        <tr>
          <td style="color:var(--text-soft);">#<?= $c['id'] ?></td>
          <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
          <td><?= htmlspecialchars($c['email']) ?></td>
          <td><?= htmlspecialchars($c['phone']) ?></td>
          <td style="font-size:.82rem;color:var(--text-mid);"><?= htmlspecialchars(substr($c['address'],0,40)) ?>…</td>
          <td style="color:var(--text-soft);font-size:.8rem;"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
          <td>
            <button class="btn btn-outline btn-xs" onclick='editCust(<?= json_encode($c) ?>)'>✏ Edit</button>
            <a class="btn btn-danger btn-xs" href="?page=customers&delete_customer=<?= $c['id'] ?>" onclick="return confirm('Delete this customer?')">✕ Del</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$customers): ?><tr><td colspan="7"><div class="empty-state"><span class="e-icon">👤</span>No customers yet.</div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="modal-overlay" id="addCustModal">
    <div class="modal">
      <button class="close-modal" onclick="closeModal('addCustModal')">×</button>
      <h2>✿ Add Customer</h2>
      <form method="POST">
        <div class="form-group"><label>Full Name</label><input type="text" name="cname" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="cemail"></div>
        <div class="form-group"><label>Phone</label><input type="text" name="cphone"></div>
        <div class="form-group"><label>Address</label><textarea name="caddress"></textarea></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addCustModal')">Cancel</button><button type="submit" name="add_customer" class="btn btn-primary">Add Customer</button></div>
      </form>
    </div>
  </div>

  <div class="modal-overlay" id="editCustModal">
    <div class="modal">
      <button class="close-modal" onclick="closeModal('editCustModal')">×</button>
      <h2>✿ Edit Customer</h2>
      <form method="POST">
        <input type="hidden" name="cid" id="ec_id">
        <div class="form-group"><label>Full Name</label><input type="text" name="cname" id="ec_name" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="cemail" id="ec_email"></div>
        <div class="form-group"><label>Phone</label><input type="text" name="cphone" id="ec_phone"></div>
        <div class="form-group"><label>Address</label><textarea name="caddress" id="ec_address"></textarea></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('editCustModal')">Cancel</button><button type="submit" name="edit_customer" class="btn btn-primary">Save Changes</button></div>
      </form>
    </div>
  </div>
  <script>
  function editCust(c) {
    document.getElementById('ec_id').value=c.id;
    document.getElementById('ec_name').value=c.name;
    document.getElementById('ec_email').value=c.email||'';
    document.getElementById('ec_phone').value=c.phone||'';
    document.getElementById('ec_address').value=c.address||'';
    openModal('editCustModal');
  }
  </script>

  <?php
  // ─────────────────── ORDERS ───────────────────
  elseif ($page === 'orders'):
  ?>
  <?php if ($orderMsg): ?><div class="flash"><?= htmlspecialchars($orderMsg) ?></div><?php endif; ?>
  <div class="section-header">
    <h2>Orders</h2>
    <button class="btn btn-primary" onclick="openModal('addOrderModal')">+ New Order</button>
  </div>
  <div class="table-card">
    <table class="data-table">
      <thead><tr><th>#</th><th>Customer</th><th>Flower</th><th>Qty</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td><strong>#<?= $o['id'] ?></strong></td>
          <td><?= htmlspecialchars($o['cname'] ?? '—') ?></td>
          <td><?= htmlspecialchars($o['fname'] ?? '—') ?></td>
          <td><?= $o['quantity'] ?></td>
          <td>₱<?= number_format($o['total_price'],2) ?></td>
          <td><span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
          <td style="color:var(--text-soft);font-size:.8rem;"><?= date('M j, Y', strtotime($o['order_date'])) ?></td>
          <td>
            <button class="btn btn-outline btn-xs" onclick="viewOrder(<?= htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8') ?>)">👁 View</button>
            <button class="btn btn-outline btn-xs" onclick="editOrder(<?= htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8') ?>)">✏ Status</button>
            <a class="btn btn-danger btn-xs" href="?page=orders&delete_order=<?= $o['id'] ?>" onclick="return confirm('Delete this order?')">✕ Del</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="8"><div class="empty-state"><span class="e-icon">📦</span>No orders yet.</div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ADD ORDER MODAL -->
  <div class="modal-overlay" id="addOrderModal">
    <div class="modal">
      <button class="close-modal" onclick="closeModal('addOrderModal')">×</button>
      <h2>✿ New Order</h2>
      <form method="POST">
        <div class="form-group"><label>Customer</label>
          <select name="ocustomer" required>
            <option value="">— Select Customer —</option>
            <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Flower</label>
          <select name="oflower" required>
            <option value="">— Select Flower —</option>
            <?php foreach ($flowers as $f): ?><option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?> — ₱<?= $f['price'] ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Quantity</label><input type="number" name="oquantity" value="1" min="1" required></div>
        <div class="form-group"><label>Status</label>
          <select name="ostatus"><option>pending</option><option>processing</option><option>delivered</option><option>cancelled</option></select>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addOrderModal')">Cancel</button><button type="submit" name="add_order" class="btn btn-primary">Place Order</button></div>
      </form>
    </div>
  </div>

  <!-- EDIT ORDER STATUS MODAL -->
  <div class="modal-overlay" id="editOrderModal">
    <div class="modal">
      <button class="close-modal" onclick="closeModal('editOrderModal')">×</button>
      <h2>✿ Update Order Status</h2>
      <form method="POST">
        <input type="hidden" name="oid" id="eo_id">
        <div class="form-group"><label>Order #<span id="eo_num"></span></label></div>
        <div class="form-group"><label>Status</label>
          <select name="ostatus" id="eo_status"><option>pending</option><option>processing</option><option>delivered</option><option>cancelled</option></select>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('editOrderModal')">Cancel</button><button type="submit" name="edit_order" class="btn btn-primary">Update Status</button></div>
      </form>
    </div>
  </div>

  <!-- VIEW ORDER MODAL -->
  <div class="modal-overlay" id="viewOrderModal">
    <div class="modal">
      <button class="close-modal" onclick="closeModal('viewOrderModal')">×</button>
      <h2>✿ Order Details</h2>
      <div id="vo_content" style="font-size: .9rem; color: var(--text-dark); line-height: 1.6;"></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('viewOrderModal')">Close</button></div>
    </div>
  </div>

  <script>
  function editOrder(o) {
    document.getElementById('eo_id').value=o.id;
    document.getElementById('eo_num').textContent=o.id;
    document.getElementById('eo_status').value=o.status;
    openModal('editOrderModal');
  }
  function viewOrder(o) {
    let html = '<div style="margin-bottom: 1.2rem;"><h4 style="font-size:.85rem; color:var(--text-mid); text-transform:uppercase; margin-bottom:.5rem;">Customer Info</h4>' +
               '<p style="margin-bottom:.3rem;"><strong>Name:</strong> ' + (o.cname || '—') + '</p>' +
               '<p style="margin-bottom:.3rem;"><strong>Email:</strong> ' + (o.cemail || '—') + '</p>' +
               '<p style="margin-bottom:.3rem;"><strong>Phone:</strong> ' + (o.cphone || '—') + '</p>' +
               '<p style="margin-bottom:.3rem;"><strong>Address:</strong> ' + (o.caddress || '—') + '</p></div>' +
               '<div style="border-top: 1px solid var(--border); padding-top: 1.2rem;">' +
               '<h4 style="font-size:.85rem; color:var(--text-mid); text-transform:uppercase; margin-bottom:.5rem;">Order Info</h4>' +
               '<p style="margin-bottom:.3rem;"><strong>Flower:</strong> ' + (o.fname || '—') + '</p>' +
               '<p style="margin-bottom:.3rem;"><strong>Quantity:</strong> ' + o.quantity + '</p>' +
               '<p style="margin-bottom:.3rem;"><strong>Total Price:</strong> ₱' + parseFloat(o.total_price).toLocaleString('en-PH', {minimumFractionDigits:2}) + '</p>' +
               '<p style="margin-bottom:.3rem;"><strong>Status:</strong> <span class="badge badge-' + o.status + '">' + o.status.charAt(0).toUpperCase() + o.status.slice(1) + '</span></p></div>';
    document.getElementById('vo_content').innerHTML = html;
    openModal('viewOrderModal');
  }
  </script>

  <?php endif; ?>

  </div><!-- /content -->
</main>
</div><!-- /app -->

<?php endif; ?>

<!-- MODAL HELPERS -->
<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});

// MOBILE SIDEBAR TOGGLE
(function () {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const toggle   = document.getElementById('menuToggle');
  if (!sidebar || !overlay || !toggle) return;

  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
  }
  toggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
  });
  overlay.addEventListener('click', closeSidebar);
  document.querySelectorAll('.nav-item').forEach(a => a.addEventListener('click', closeSidebar));
})();
</script>
</body>
</html>