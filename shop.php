<?php
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
            die("<div style='font-family:sans-serif;padding:2rem;color:#c2185b;'><strong>Database connection failed:</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
        }
    }
    return $pdo;
}

// ─── FLASH MESSAGE HELPER ─────────────────────────────────────────────────────
session_start();
function setFlash($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ─── HANDLE ORDER SUBMISSION ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $pdo    = getDB();
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $flower_id = intval($_POST['flower_id'] ?? 0);
    $quantity  = max(1, intval($_POST['quantity'] ?? 1));
    $errors = [];

    if (!$name)    $errors[] = "Full name is required.";
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
    if (!$address) $errors[] = "Delivery address is required.";
    if (!$flower_id) $errors[] = "Please select a flower.";

    if (!$errors) {
        // Upsert customer
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $cust = $stmt->fetchColumn();
        if ($cust) {
            $pdo->prepare("UPDATE customers SET name=?, phone=?, address=? WHERE id=?")
                ->execute([$name, $phone, $address, $cust]);
            $customer_id = $cust;
        } else {
            $pdo->prepare("INSERT INTO customers (name,email,phone,address) VALUES (?,?,?,?)")
                ->execute([$name, $email, $phone, $address]);
            $customer_id = $pdo->lastInsertId();
        }
        // Get price
        $fl = $pdo->prepare("SELECT price, stock FROM flowers WHERE id=?");
        $fl->execute([$flower_id]);
        $flower = $fl->fetch(PDO::FETCH_ASSOC);

        if ($flower && $flower['stock'] >= $quantity) {
            $total = round($flower['price'] * $quantity, 2);
            $pdo->prepare("INSERT INTO orders (customer_id,flower_id,quantity,total_price,status) VALUES (?,?,?,?,?)")
                ->execute([$customer_id, $flower_id, $quantity, $total, 'pending']);
            // Decrease stock
            $pdo->prepare("UPDATE flowers SET stock = stock - ? WHERE id=?")
                ->execute([$quantity, $flower_id]);
            setFlash("Your order has been placed! We will contact you shortly.", "success");
        } else {
            setFlash("Sorry, insufficient stock for that quantity.", "error");
        }
    } else {
        setFlash(implode(" ", $errors), "error");
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "#order");
    exit;
}

// ─── HANDLE TRACK ORDER ──────────────────────────────────────────────────────
$trackResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_order'])) {
    $pdo   = getDB();
    $email = trim($_POST['track_email'] ?? '');
    if ($email) {
        $stmt = $pdo->prepare("
            SELECT o.id, o.quantity, o.total_price, o.status, o.order_date,
                   f.name AS flower_name, f.image_url
            FROM orders o
            JOIN customers c ON c.id = o.customer_id
            JOIN flowers f   ON f.id = o.flower_id
            WHERE c.email = ?
            ORDER BY o.order_date DESC
            LIMIT 10
        ");
        $stmt->execute([$email]);
        $trackResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ─── FETCH DATA ───────────────────────────────────────────────────────────────
$pdo     = getDB();
$flowers = $pdo->query("SELECT * FROM flowers ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$categories = array_unique(array_filter(array_column($flowers, 'category')));
sort($categories);

$flash = getFlash();

// Featured = top 3 by stock
$featured = $pdo->query("SELECT * FROM flowers ORDER BY stock DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Petals — Fresh Flowers, Delivered</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,500;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ── RESET ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 15px; scroll-behavior: smooth; }

/* ── LIGHT THEME (default) ── */
:root,
[data-theme="light"] {
    --rose:         #d63a6c;
    --rose-dark:    #a52a52;
    --rose-mid:     #e8608a;
    --rose-glow:    rgba(214,58,108,0.18);
    --blush:        #f9d4e4;
    --blush-soft:   #fdf0f6;
    --blush-pale:   #fff5f9;
    --petal:        #f7c5d8;

    --bg:           #fff5f9;
    --bg-card:      #ffffff;
    --bg-nav:       #ffffff;
    --bg-input:     #fff5f9;
    --bg-tag:       #fdf0f6;

    --text-dark:    #3a1a28;
    --text-mid:     #7a4a5e;
    --text-soft:    #b07a90;
    --text-inv:     #ffffff;

    --border:       #f0d0de;
    --border-focus: var(--rose-mid);
    --shadow:       rgba(214,58,108,0.10);
    --shadow-lg:    rgba(214,58,108,0.20);
    --shadow-card:  0 4px 24px rgba(214,58,108,0.10);

    --radius:       16px;
    --radius-sm:    10px;
    --radius-xs:    6px;
}

/* ── DARK THEME (dark pink) ── */
[data-theme="dark"] {
    --rose:         #f4a0c5;
    --rose-dark:    #f9d4e4;
    --rose-mid:     #e8608a;
    --rose-glow:    rgba(244,160,197,0.22);
    --blush:        #883c5e;
    --blush-soft:   #6b2a47;
    --blush-pale:   #5a203b;
    --petal:        #a54a70;

    --bg:           #45152a;
    --bg-card:      #5c1c38;
    --bg-nav:       #501830;
    --bg-input:     #702446;
    --bg-tag:       #78284c;

    --text-dark:    #fff5f9;
    --text-mid:     #fce8f2;
    --text-soft:    #f4a0c5;
    --text-inv:     #45152a;

    --border:       #883c5e;
    --border-focus: var(--rose);
    --shadow:       rgba(0,0,0,0.20);
    --shadow-lg:    rgba(0,0,0,0.35);
    --shadow-card:  0 4px 24px rgba(0,0,0,0.20);

    --radius:       16px;
    --radius-sm:    10px;
    --radius-xs:    6px;
}

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text-dark);
    min-height: 100vh;
    transition: background .3s, color .3s;
}

/* ── NAVBAR ── */
.navbar {
    position: sticky;
    top: 0;
    z-index: 200;
    background: var(--bg-nav);
    border-bottom: 1px solid var(--border);
    box-shadow: 0 2px 16px var(--shadow);
    transition: background .3s, border-color .3s;
}
.nav-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1.5rem;
    height: 68px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.nav-brand {
    display: flex;
    align-items: center;
    gap: .6rem;
    text-decoration: none;
}
.nav-brand .brand-petal {
    width: 38px;
    height: 38px;
    background: linear-gradient(135deg, var(--rose), var(--rose-mid));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.nav-brand .brand-petal svg {
    width: 22px;
    height: 22px;
    fill: #fff;
}
.nav-brand h1 {
    font-family: 'Playfair Display', serif;
    font-size: 1.65rem;
    color: var(--rose-dark);
    letter-spacing: -.5px;
    line-height: 1;
}
.nav-brand span {
    color: var(--text-soft);
    font-size: .7rem;
    letter-spacing: .1em;
    text-transform: uppercase;
    display: block;
    margin-top: 1px;
}
.nav-links {
    display: flex;
    align-items: center;
    gap: .25rem;
    list-style: none;
}
.nav-links a {
    color: var(--text-mid);
    text-decoration: none;
    font-size: .88rem;
    font-weight: 500;
    padding: .45rem .9rem;
    border-radius: var(--radius-sm);
    transition: color .15s, background .15s;
}
.nav-links a:hover { color: var(--rose); background: var(--blush-soft); }
.nav-links a.active { color: var(--rose); font-weight: 600; }
.nav-actions {
    display: flex;
    align-items: center;
    gap: .75rem;
}
.btn-theme {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 1.5px solid var(--border);
    background: var(--bg-card);
    color: var(--text-mid);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .2s;
    flex-shrink: 0;
}
.btn-theme:hover { border-color: var(--rose); color: var(--rose); background: var(--blush-soft); }
.btn-theme svg { width: 18px; height: 18px; }
.nav-hamburger {
    display: none;
    background: none;
    border: none;
    color: var(--text-mid);
    cursor: pointer;
    padding: .4rem;
}
.nav-hamburger svg { width: 24px; height: 24px; display: block; }
.nav-mobile-menu {
    display: none;
    background: var(--bg-nav);
    border-top: 1px solid var(--border);
    padding: .75rem 1.5rem 1rem;
    flex-direction: column;
    gap: .15rem;
}
.nav-mobile-menu a {
    color: var(--text-mid);
    text-decoration: none;
    font-size: .92rem;
    font-weight: 500;
    padding: .6rem .75rem;
    border-radius: var(--radius-sm);
    display: block;
    transition: background .15s, color .15s;
}
.nav-mobile-menu a:hover { color: var(--rose); background: var(--blush-soft); }
.nav-mobile-menu.open { display: flex; }

/* ── HERO ── */
.hero {
    background: url('hero.jpg') center/cover no-repeat;
    padding: 6rem 1.5rem 5rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.hero-inner { position: relative; z-index: 1; max-width: 680px; margin: 0 auto; }
.hero-badge {
    display: inline-block;
    background: rgba(255,255,255,.35);
    color: var(--rose-dark);
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    padding: .35rem 1rem;
    border-radius: 50px;
    margin-bottom: 1.4rem;
    border: 1px solid rgba(255,255,255,.5);
    backdrop-filter: blur(6px);
}
.hero h2 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(2.4rem, 6vw, 3.8rem);
    color: var(--rose-dark);
    line-height: 1.12;
    margin-bottom: 1.1rem;
    letter-spacing: -.5px;
}
.hero h2 em {
    font-style: italic;
    color: var(--rose);
}
.hero p {
    font-size: 1.08rem;
    color: var(--text-mid);
    line-height: 1.7;
    margin-bottom: 2.2rem;
    max-width: 520px;
    margin-left: auto;
    margin-right: auto;
}
.hero-cta {
    display: flex;
    gap: .85rem;
    justify-content: center;
    flex-wrap: wrap;
}
.hero-scroll {
    display: flex;
    align-items: center;
    gap: 2.5rem;
    justify-content: center;
    margin-top: 3rem;
}
.hero-scroll-item { text-align: center; }
.hero-scroll-item .val {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--rose-dark);
    line-height: 1;
}
.hero-scroll-item .lbl {
    font-size: .72rem;
    color: var(--text-mid);
    text-transform: uppercase;
    letter-spacing: .08em;
    margin-top: .2rem;
}
.hero-divider { width: 1px; height: 44px; background: var(--petal); opacity: .6; }

/* ── SECTION ── */
.section {
    max-width: 1200px;
    margin: 0 auto;
    padding: 4.5rem 1.5rem;
}
.section-sm { padding-top: 3rem; padding-bottom: 3rem; }
.section-head { text-align: center; margin-bottom: 3rem; }
.section-label {
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--rose);
    margin-bottom: .65rem;
    display: block;
}
.section-head h2 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(1.7rem, 4vw, 2.4rem);
    color: var(--text-dark);
    line-height: 1.18;
    margin-bottom: .6rem;
}
.section-head h2 em { font-style: italic; color: var(--rose); }
.section-head p {
    color: var(--text-soft);
    font-size: .95rem;
    max-width: 520px;
    margin: 0 auto;
    line-height: 1.7;
}

/* ── FEATURED STRIP ── */
.featured-strip {
    background: linear-gradient(90deg, var(--blush-soft), var(--blush-pale));
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    padding: 1.25rem 0;
    overflow: hidden;
}
.featured-ticker {
    display: flex;
    align-items: center;
    gap: 2.5rem;
    white-space: nowrap;
    animation: ticker 22s linear infinite;
}
.featured-ticker .item {
    display: flex;
    align-items: center;
    gap: .6rem;
    color: var(--text-mid);
    font-size: .85rem;
    font-weight: 500;
    flex-shrink: 0;
}
.featured-ticker .dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--rose);
    flex-shrink: 0;
}
@keyframes ticker { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

/* ── SHOP FILTER ── */
.filter-bar {
    display: flex;
    align-items: center;
    gap: .6rem;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 2.4rem;
}
.filter-btn {
    padding: .45rem 1.1rem;
    border-radius: 50px;
    font-family: 'DM Sans', sans-serif;
    font-size: .82rem;
    font-weight: 600;
    border: 1.5px solid var(--border);
    background: var(--bg-card);
    color: var(--text-mid);
    cursor: pointer;
    transition: all .18s;
}
.filter-btn:hover { border-color: var(--rose); color: var(--rose); }
.filter-btn.active {
    background: var(--rose);
    color: #fff;
    border-color: var(--rose);
    box-shadow: 0 4px 12px var(--rose-glow);
}

/* ── FLOWER GRID ── */
.flower-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(256px, 1fr));
    gap: 1.5rem;
}
.flower-card {
    background: var(--bg-card);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-card);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform .22s, box-shadow .22s;
    cursor: pointer;
}
.flower-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 36px var(--shadow-lg);
}
.flower-card:hover .fc-image { transform: scale(1.04); }
.fc-img-wrap {
    height: 200px;
    overflow: hidden;
    position: relative;
    background: var(--blush);
}
.fc-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .35s;
}
.fc-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--blush), var(--petal));
}
.fc-placeholder svg {
    width: 52px;
    height: 52px;
    fill: var(--rose-mid);
    opacity: .6;
}
.fc-badge {
    position: absolute;
    top: .85rem;
    left: .85rem;
    background: var(--rose);
    color: #fff;
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    padding: .2rem .6rem;
    border-radius: 50px;
}
.fc-stock-tag {
    position: absolute;
    top: .85rem;
    right: .85rem;
    background: rgba(255,255,255,.85);
    backdrop-filter: blur(4px);
    color: var(--text-mid);
    font-size: .68rem;
    font-weight: 600;
    padding: .2rem .55rem;
    border-radius: 50px;
    border: 1px solid var(--border);
}
[data-theme="dark"] .fc-stock-tag {
    background: rgba(42,16,32,.85);
    color: var(--text-mid);
}
.fc-stock-tag.low { color: var(--rose); }
.fc-body {
    padding: 1.3rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.fc-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .5rem;
    margin-bottom: .45rem;
}
.fc-name {
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem;
    color: var(--text-dark);
    line-height: 1.2;
    font-weight: 600;
}
.fc-cat {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--rose);
    background: var(--bg-tag);
    border: 1px solid var(--border);
    padding: .18rem .55rem;
    border-radius: 50px;
    white-space: nowrap;
    flex-shrink: 0;
}
.fc-desc {
    font-size: .84rem;
    color: var(--text-soft);
    line-height: 1.55;
    margin-bottom: 1rem;
    flex: 1;
}
.fc-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid var(--border);
    padding-top: 1rem;
    margin-top: auto;
    gap: .5rem;
}
.fc-price {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--rose-dark);
    font-family: 'Playfair Display', serif;
}
.fc-price span {
    font-size: .8rem;
    font-family: 'DM Sans', sans-serif;
    font-weight: 500;
    color: var(--text-soft);
}

/* ── BUTTONS ── */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .45rem;
    padding: .65rem 1.4rem;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: .88rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all .18s;
    line-height: 1;
    white-space: nowrap;
}
.btn svg { width: 16px; height: 16px; flex-shrink: 0; }
.btn-primary {
    background: linear-gradient(135deg, var(--rose), var(--rose-mid));
    color: #fff;
    box-shadow: 0 4px 14px var(--rose-glow);
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 22px var(--shadow-lg); }
.btn-outline {
    background: transparent;
    color: var(--rose);
    border: 1.5px solid var(--rose);
}
.btn-outline:hover { background: var(--blush-soft); }
.btn-ghost {
    background: var(--blush-soft);
    color: var(--text-mid);
    border: 1.5px solid var(--border);
}
.btn-ghost:hover { color: var(--rose); border-color: var(--rose); background: var(--blush); }
.btn-sm { padding: .45rem 1rem; font-size: .8rem; }
.btn-xs { padding: .3rem .7rem; font-size: .75rem; }
.btn-block { width: 100%; }

/* ── HOW IT WORKS ── */
.how-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
}
.how-card {
    text-align: center;
    padding: 2rem 1.5rem;
    background: var(--bg-card);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-card);
    transition: transform .2s, box-shadow .2s;
    position: relative;
}
.how-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px var(--shadow-lg); }
.how-card::before {
    content: '';
    position: absolute;
    top: 0; left: 1.5rem; right: 1.5rem;
    height: 3px;
    background: linear-gradient(90deg, var(--rose), var(--rose-mid));
    border-radius: 0 0 4px 4px;
}
.how-num {
    width: 52px; height: 52px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--blush), var(--petal));
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.2rem;
    font-family: 'Playfair Display', serif;
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--rose-dark);
}
.how-card h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    color: var(--text-dark);
    margin-bottom: .55rem;
}
.how-card p {
    font-size: .85rem;
    color: var(--text-soft);
    line-height: 1.65;
}
.how-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--blush), var(--petal));
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.2rem;
}
.how-icon svg { width: 26px; height: 26px; stroke: var(--rose-dark); fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

/* ── ORDER FORM ── */
#order {
    background: var(--blush-soft);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}
.order-wrap {
    max-width: 1200px;
    margin: 0 auto;
    padding: 4.5rem 1.5rem;
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    gap: 3rem;
    align-items: start;
}
.order-info h2 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(1.7rem, 4vw, 2.3rem);
    color: var(--text-dark);
    line-height: 1.18;
    margin-bottom: .9rem;
}
.order-info h2 em { font-style: italic; color: var(--rose); }
.order-info p {
    color: var(--text-soft);
    font-size: .93rem;
    line-height: 1.7;
    margin-bottom: 2rem;
}
.order-perks { display: flex; flex-direction: column; gap: .9rem; }
.perk {
    display: flex;
    align-items: flex-start;
    gap: .85rem;
}
.perk-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--blush), var(--petal));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.perk-icon svg { width: 18px; height: 18px; stroke: var(--rose-dark); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.perk-text h4 { font-size: .88rem; font-weight: 600; color: var(--text-dark); margin-bottom: .15rem; }
.perk-text p { font-size: .8rem; color: var(--text-soft); line-height: 1.5; }

.order-form-card {
    background: var(--bg-card);
    border-radius: 20px;
    border: 1px solid var(--border);
    box-shadow: 0 8px 40px var(--shadow-lg);
    padding: 2.2rem;
}
.order-form-card h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.3rem;
    color: var(--rose-dark);
    font-style: italic;
    margin-bottom: 1.8rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-group { margin-bottom: 1.1rem; }
.form-group label {
    display: block;
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--text-mid);
    margin-bottom: .45rem;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: .72rem 1rem;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: .9rem;
    color: var(--text-dark);
    background: var(--bg-input);
    transition: border-color .2s, box-shadow .2s;
    outline: none;
    -webkit-appearance: none;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--border-focus);
    box-shadow: 0 0 0 3px var(--rose-glow);
}
.form-group textarea { resize: vertical; min-height: 80px; }
.form-group select { cursor: pointer; }
.form-hint { font-size: .76rem; color: var(--text-soft); margin-top: .3rem; }
.quantity-row {
    display: flex;
    align-items: center;
    gap: .5rem;
}
.qty-btn {
    width: 36px; height: 36px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-xs);
    background: var(--bg-card);
    color: var(--text-mid);
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .15s;
    flex-shrink: 0;
}
.qty-btn:hover { border-color: var(--rose); color: var(--rose); }
.qty-input {
    width: 60px !important;
    text-align: center;
    flex-shrink: 0;
}

/* ── FLASH ── */
.flash {
    padding: .9rem 1.2rem;
    border-radius: var(--radius-sm);
    font-size: .9rem;
    margin-bottom: 1.4rem;
    display: flex;
    align-items: flex-start;
    gap: .65rem;
    line-height: 1.5;
}
.flash svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px; }
.flash-success { background: #d1e7dd; color: #0f5132; border-left: 3px solid #198754; }
.flash-error   { background: #fee2ee; color: #a52a52; border-left: 3px solid var(--rose); }

/* ── TRACK ORDER ── */
.track-card {
    background: var(--bg-card);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-card);
    padding: 2rem;
    max-width: 640px;
    margin: 0 auto 2rem;
}
.track-form {
    display: flex;
    gap: .75rem;
    align-items: flex-end;
}
.track-form .form-group { flex: 1; margin: 0; }
.track-results { margin-top: 2rem; }
.track-item {
    display: flex;
    align-items: center;
    gap: 1.1rem;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border);
}
.track-item:last-child { border-bottom: none; }
.track-thumb {
    width: 52px; height: 52px;
    border-radius: 10px;
    object-fit: cover;
    border: 2px solid var(--petal);
    flex-shrink: 0;
}
.track-thumb-ph {
    width: 52px; height: 52px;
    border-radius: 10px;
    background: var(--blush);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 2px solid var(--petal);
}
.track-thumb-ph svg { width: 24px; height: 24px; fill: var(--rose-mid); opacity: .6; }
.track-meta { flex: 1; min-width: 0; }
.track-meta .name { font-weight: 600; color: var(--text-dark); font-size: .9rem; }
.track-meta .sub { font-size: .78rem; color: var(--text-soft); margin-top: .15rem; }
.track-right { text-align: right; flex-shrink: 0; }
.track-right .price { font-weight: 700; color: var(--rose-dark); font-size: .95rem; }

/* ── BADGE ── */
.badge {
    display: inline-block;
    padding: .22rem .65rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: capitalize;
}
.badge-pending    { background: #fff3cd; color: #856404; }
.badge-processing { background: #cfe2ff; color: #084298; }
.badge-delivered  { background: #d1e7dd; color: #0f5132; }
.badge-cancelled  { background: #f8d7da; color: #842029; }

/* ── TESTIMONIALS ── */
.testi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}
.testi-card {
    background: var(--bg-card);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-card);
    padding: 1.8rem;
    position: relative;
    transition: transform .2s, box-shadow .2s;
}
.testi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px var(--shadow-lg); }
.testi-quote {
    font-family: 'Playfair Display', serif;
    font-size: 3.5rem;
    color: var(--petal);
    line-height: .8;
    margin-bottom: .75rem;
    display: block;
}
.testi-text {
    font-size: .9rem;
    color: var(--text-mid);
    line-height: 1.7;
    margin-bottom: 1.4rem;
    font-style: italic;
}
.testi-author { display: flex; align-items: center; gap: .75rem; }
.testi-avatar {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--rose), var(--rose-mid));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: .9rem;
    flex-shrink: 0;
}
.testi-name { font-weight: 600; color: var(--text-dark); font-size: .88rem; }
.testi-loc  { font-size: .75rem; color: var(--text-soft); }
.stars { color: #f59e0b; font-size: .85rem; margin-bottom: .6rem; letter-spacing: .05em; }

/* ── DELIVERY BANNER ── */
.delivery-banner {
    background: linear-gradient(135deg, var(--rose), var(--rose-mid));
    color: #fff;
    text-align: center;
    padding: 3.5rem 1.5rem;
}
.delivery-banner h2 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(1.5rem, 4vw, 2.2rem);
    font-style: italic;
    margin-bottom: .75rem;
}
.delivery-banner p {
    font-size: .95rem;
    opacity: .88;
    margin-bottom: 1.8rem;
    max-width: 480px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.65;
}
.btn-white {
    background: #fff;
    color: var(--rose-dark);
    font-weight: 700;
}
.btn-white:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.2); }
.btn-white-outline {
    background: transparent;
    color: #fff;
    border: 2px solid rgba(255,255,255,.6);
}
.btn-white-outline:hover { background: rgba(255,255,255,.15); }

/* ── FOOTER ── */
footer {
    background: var(--bg-nav);
    border-top: 1px solid var(--border);
    padding: 3.5rem 1.5rem 2rem;
}
.footer-inner {
    max-width: 1200px;
    margin: 0 auto;
}
.footer-grid {
    display: grid;
    grid-template-columns: 1.8fr 1fr 1fr 1fr;
    gap: 2.5rem;
    margin-bottom: 3rem;
}
.footer-brand h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    color: var(--rose-dark);
    margin-bottom: .4rem;
    letter-spacing: -.3px;
}
.footer-brand p {
    font-size: .84rem;
    color: var(--text-soft);
    line-height: 1.7;
    margin-bottom: 1.4rem;
    max-width: 240px;
}
.footer-socials {
    display: flex;
    gap: .6rem;
}
.social-btn {
    width: 36px; height: 36px;
    border-radius: 8px;
    background: var(--blush-soft);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-mid);
    transition: all .15s;
    text-decoration: none;
}
.social-btn:hover { background: var(--blush); color: var(--rose); border-color: var(--rose); }
.social-btn svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.footer-col h4 {
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--text-dark);
    margin-bottom: 1.1rem;
}
.footer-col ul { list-style: none; }
.footer-col ul li + li { margin-top: .5rem; }
.footer-col ul a {
    text-decoration: none;
    color: var(--text-soft);
    font-size: .85rem;
    transition: color .15s;
}
.footer-col ul a:hover { color: var(--rose); }
.footer-col address {
    font-style: normal;
    font-size: .85rem;
    color: var(--text-soft);
    line-height: 1.8;
}
.footer-bottom {
    border-top: 1px solid var(--border);
    padding-top: 1.4rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .75rem;
}
.footer-bottom p { font-size: .78rem; color: var(--text-soft); }
.footer-bottom a { color: var(--rose); text-decoration: none; }

/* ── BACK TO TOP ── */
.back-top {
    position: fixed;
    bottom: 1.8rem;
    right: 1.8rem;
    width: 44px; height: 44px;
    border-radius: 50%;
    background: var(--rose);
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 16px var(--shadow-lg);
    transition: all .2s;
    opacity: 0;
    pointer-events: none;
    z-index: 300;
}
.back-top.visible { opacity: 1; pointer-events: auto; }
.back-top:hover { transform: translateY(-3px); box-shadow: 0 8px 20px var(--shadow-lg); }
.back-top svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

/* ── ORDER MODAL ── */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.52);
    z-index: 500;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(3px);
}
.modal-overlay.open { display: flex; }
.modal {
    background: var(--bg-card);
    border-radius: 20px;
    padding: 2rem;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 24px 64px rgba(0,0,0,.3);
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    animation: modalIn .2s ease;
}
@keyframes modalIn { from { opacity:0; transform:translateY(16px) scale(.97); } to { opacity:1; transform:none; } }
.modal-close {
    position: absolute;
    top: 1rem; right: 1rem;
    width: 32px; height: 32px;
    border: none;
    background: var(--blush-soft);
    border-radius: 50%;
    color: var(--text-soft);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .15s;
}
.modal-close:hover { background: var(--blush); color: var(--rose); }
.modal-close svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; }
.modal h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.25rem;
    color: var(--rose-dark);
    font-style: italic;
    margin-bottom: 1.4rem;
    padding-right: 2rem;
}
.modal-flower-preview {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: var(--blush-soft);
    border-radius: var(--radius-sm);
    padding: .85rem 1rem;
    margin-bottom: 1.4rem;
    border: 1px solid var(--border);
}
.modal-flower-preview img {
    width: 60px; height: 60px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid var(--petal);
    flex-shrink: 0;
}
.mfp-info .name { font-weight: 700; color: var(--text-dark); font-size: .95rem; }
.mfp-info .price { color: var(--rose); font-weight: 700; font-size: 1.1rem; margin-top: .15rem; }
.mfp-info .cat { font-size: .73rem; color: var(--text-soft); text-transform: uppercase; letter-spacing: .06em; }
.modal-footer {
    display: flex;
    gap: .75rem;
    margin-top: 1.4rem;
    justify-content: flex-end;
}

/* ── DIVIDER ── */
.divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--border), transparent);
    margin: 0;
}

/* ── RESPONSIVE ── */
@media (max-width: 1024px) {
    .footer-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 860px) {
    .order-wrap { grid-template-columns: 1fr; gap: 2rem; }
    .order-info { order: 1; }
    .order-form-card { order: 2; }
}
@media (max-width: 768px) {
    .nav-links { display: none; }
    .nav-hamburger { display: flex; align-items: center; }
    .hero { padding: 4rem 1.25rem 3.5rem; }
    .hero-scroll { gap: 1.5rem; }
    .hero-divider { display: none; }
    .section { padding: 3rem 1.25rem; }
    .footer-grid { grid-template-columns: 1fr 1fr; }
    .footer-brand { grid-column: 1 / -1; }
}
@media (max-width: 560px) {
    html { font-size: 14px; }
    .flower-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
    .fc-img-wrap { height: 150px; }
    .fc-body { padding: .9rem; }
    .fc-name { font-size: 1.05rem; }
    .form-row { grid-template-columns: 1fr; }
    .footer-grid { grid-template-columns: 1fr; }
    .footer-brand { grid-column: auto; }
    .track-form { flex-direction: column; align-items: stretch; }
    .track-form .btn { width: 100%; }
    .hero-scroll { flex-wrap: wrap; gap: 1rem; }
    .how-grid { grid-template-columns: 1fr 1fr; gap: 1.2rem; }
}
@media (max-width: 360px) {
    .flower-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="navbar">
  <div class="nav-inner">
    <a class="nav-brand" href="#">
      <div class="brand-petal">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2C9 2 6.5 4.5 6.5 7.5c0 1.5.6 2.9 1.6 3.9C5.8 12.3 4 14.5 4 17c0 2.8 2.2 5 5 5h6c2.8 0 5-2.2 5-5 0-2.5-1.8-4.7-4.1-5.6 1-1 1.6-2.4 1.6-3.9C17.5 4.5 15 2 12 2z"/>
        </svg>
      </div>
      <div>
        <h1>Petals</h1>
        <span>Flower Boutique</span>
      </div>
    </a>
    <ul class="nav-links">
      <li><a href="#shop">Shop</a></li>
      <li><a href="#how">How It Works</a></li>
      <li><a href="#order">Order</a></li>
      <li><a href="#track">Track Order</a></li>
    </ul>
    <div class="nav-actions">
      <button class="btn-theme" id="themeToggle" title="Toggle dark mode" aria-label="Toggle theme">
        <!-- Moon icon (shown in light mode) -->
        <svg id="iconMoon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <!-- Sun icon (shown in dark mode) -->
        <svg id="iconSun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
          <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
        </svg>
      </button>
      <a href="#order" class="btn btn-primary btn-sm" style="display:none" id="navOrderBtn">Order Now</a>
      <button class="nav-hamburger" id="navHamburger" aria-label="Open menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
    </div>
  </div>
  <div class="nav-mobile-menu" id="mobileMenu">
    <a href="#shop"  onclick="closeMobileMenu()">Shop</a>
    <a href="#how"   onclick="closeMobileMenu()">How It Works</a>
    <a href="#order" onclick="closeMobileMenu()">Order</a>
    <a href="#track" onclick="closeMobileMenu()">Track Order</a>
  </div>
</nav>

<!-- ── HERO ── -->
<section class="hero">
  <div class="hero-inner">
    <div class="hero-badge">Fresh Blooms, Same-Day Delivery</div>
    <h2>Every Flower Tells<br>a <em>Story of Love</em></h2>
    <p>Handcrafted arrangements sourced from the finest local gardens. Order fresh, breathtaking flowers delivered right to your door across the Philippines.</p>
    <div class="hero-cta">
      <a href="#shop" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        Browse Flowers
      </a>
      <a href="#how" class="btn btn-outline">How It Works</a>
    </div>
    <div class="hero-scroll">
      <div class="hero-scroll-item">
        <div class="val"><?= count($flowers) ?>+</div>
        <div class="lbl">Flower Varieties</div>
      </div>
      <div class="hero-divider"></div>
      <div class="hero-scroll-item">
        <div class="val"><?= $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() ?>+</div>
        <div class="lbl">Happy Customers</div>
      </div>
      <div class="hero-divider"></div>
      <div class="hero-scroll-item">
        <div class="val"><?= $pdo->query("SELECT COUNT(*) FROM orders WHERE status='delivered'")->fetchColumn() ?>+</div>
        <div class="lbl">Orders Delivered</div>
      </div>
    </div>
  </div>
</section>

<!-- ── TICKER ── -->
<div class="featured-strip">
  <div class="featured-ticker">
    <?php
    $tickerItems = array_merge($flowers, $flowers); // duplicate for seamless loop
    foreach ($tickerItems as $t): ?>
    <div class="item">
      <div class="dot"></div>
      <?= htmlspecialchars($t['name']) ?> &mdash; &#8369;<?= number_format($t['price'],2) ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── SHOP ── -->
<section class="section" id="shop">
  <div class="section-head">
    <span class="section-label">Our Collection</span>
    <h2>Fresh <em>Blooms</em> for Every Occasion</h2>
    <p>From classic roses to exotic arrangements, discover flowers that capture exactly what you want to say.</p>
  </div>
  <div class="filter-bar" id="filterBar">
    <button class="filter-btn active" data-cat="all">All Flowers</button>
    <?php foreach ($categories as $cat): ?>
    <button class="filter-btn" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="flower-grid" id="flowerGrid">
    <?php foreach ($flowers as $f): ?>
    <?php
      $inStock = intval($f['stock']);
      $stockLabel = $inStock <= 0 ? 'Out of Stock' : ($inStock < 20 ? 'Low Stock' : $inStock . ' in stock');
      $stockClass = $inStock < 20 ? 'low' : '';
      $flowerJson = htmlspecialchars(json_encode([
        'id'        => $f['id'],
        'name'      => $f['name'],
        'category'  => $f['category'],
        'price'     => $f['price'],
        'stock'     => $f['stock'],
        'image_url' => $f['image_url'],
        'description' => $f['description']
      ]), ENT_QUOTES, 'UTF-8');
    ?>
    <div class="flower-card" data-cat="<?= htmlspecialchars($f['category'] ?? '') ?>" onclick="openFlowerModal(<?= $flowerJson ?>)">
      <div class="fc-img-wrap">
        <?php if ($f['image_url'] && file_exists($f['image_url'])): ?>
        <img class="fc-image" src="<?= htmlspecialchars($f['image_url']) ?>" alt="<?= htmlspecialchars($f['name']) ?>">
        <?php else: ?>
        <div class="fc-placeholder">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2C9 2 6.5 4.5 6.5 7.5c0 1.5.6 2.9 1.6 3.9C5.8 12.3 4 14.5 4 17c0 2.8 2.2 5 5 5h6c2.8 0 5-2.2 5-5 0-2.5-1.8-4.7-4.1-5.6 1-1 1.6-2.4 1.6-3.9C17.5 4.5 15 2 12 2z"/>
          </svg>
        </div>
        <?php endif; ?>
        <?php if ($inStock > 0 && $inStock < 20): ?>
        <div class="fc-badge">Hot</div>
        <?php endif; ?>
        <div class="fc-stock-tag <?= $stockClass ?>"><?= $stockLabel ?></div>
      </div>
      <div class="fc-body">
        <div class="fc-top">
          <div class="fc-name"><?= htmlspecialchars($f['name']) ?></div>
          <?php if ($f['category']): ?>
          <div class="fc-cat"><?= htmlspecialchars($f['category']) ?></div>
          <?php endif; ?>
        </div>
        <div class="fc-desc"><?= htmlspecialchars($f['description'] ?? '') ?></div>
        <div class="fc-footer">
          <div class="fc-price">&#8369;<?= number_format($f['price'],2) ?> <span>/ stem</span></div>
          <button class="btn btn-primary btn-xs" <?= $inStock <= 0 ? 'disabled style="opacity:.5;cursor:not-allowed"' : '' ?> onclick="event.stopPropagation(); openOrderModal(<?= $flowerJson ?>)">
            <?= $inStock > 0 ? 'Order' : 'Sold Out' ?>
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$flowers): ?>
    <div style="grid-column:1/-1; text-align:center; padding:4rem 1rem; color:var(--text-soft);">
      <p>No flowers available at the moment. Check back soon.</p>
    </div>
    <?php endif; ?>
  </div>
</section>

<div class="divider"></div>

<!-- ── HOW IT WORKS ── -->
<section class="section section-sm" id="how">
  <div class="section-head">
    <span class="section-label">Simple Process</span>
    <h2>How <em>Ordering</em> Works</h2>
    <p>Getting beautiful flowers delivered has never been easier. Four simple steps and your blooms are on the way.</p>
  </div>
  <div class="how-grid">
    <div class="how-card">
      <div class="how-icon">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </div>
      <h3>Browse Our Catalog</h3>
      <p>Explore our curated selection of fresh, seasonal flowers sourced daily from local growers.</p>
    </div>
    <div class="how-card">
      <div class="how-icon">
        <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
      </div>
      <h3>Choose Your Flowers</h3>
      <p>Pick the perfect bloom, select your quantity, and fill in your delivery details.</p>
    </div>
    <div class="how-card">
      <div class="how-icon">
        <svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      </div>
      <h3>We Prepare and Pack</h3>
      <p>Our florists handpick your flowers and wrap them beautifully, ready for delivery.</p>
    </div>
    <div class="how-card">
      <div class="how-icon">
        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <h3>Delivered With Care</h3>
      <p>Your fresh flowers arrive at your door, packaged to stay beautiful longer.</p>
    </div>
  </div>
</section>

<div class="divider"></div>

<!-- ── ORDER FORM SECTION ── -->
<div id="order">
  <div class="order-wrap">
    <div class="order-info">
      <span class="section-label">Place Your Order</span>
      <h2>Send Someone<br><em>Something Beautiful</em></h2>
      <p>Fill in your details and select the flowers you love. We will process your order and reach out to confirm delivery details.</p>
      <div class="order-perks">
        <div class="perk">
          <div class="perk-icon">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <div class="perk-text">
            <h4>Same-Day Delivery</h4>
            <p>Orders placed before 2 PM are delivered the same day within the metro.</p>
          </div>
        </div>
        <div class="perk">
          <div class="perk-icon">
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
          <div class="perk-text">
            <h4>Freshness Guaranteed</h4>
            <p>All flowers are cut fresh daily. Not satisfied? We will replace them free of charge.</p>
          </div>
        </div>
        <div class="perk">
          <div class="perk-icon">
            <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
          </div>
          <div class="perk-text">
            <h4>Secure and Easy</h4>
            <p>Pay on delivery. No online payment required to place your order.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="order-form-card">
      <h3>Your Order Details</h3>
      <?php if ($flash): ?>
      <div class="flash <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <?php if ($flash['type'] === 'success'): ?>
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
          <?php else: ?>
          <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
          <?php endif; ?>
        </svg>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
      <?php endif; ?>
      <form method="POST" action="#order" id="orderForm">
        <div class="form-row">
          <div class="form-group">
            <label for="f_name">Full Name <span style="color:var(--rose)">*</span></label>
            <input type="text" id="f_name" name="name" placeholder="Sofia Reyes" required autocomplete="name">
          </div>
          <div class="form-group">
            <label for="f_email">Email Address <span style="color:var(--rose)">*</span></label>
            <input type="email" id="f_email" name="email" placeholder="you@example.com" required autocomplete="email">
          </div>
        </div>
        <div class="form-group">
          <label for="f_phone">Phone Number</label>
          <input type="tel" id="f_phone" name="phone" placeholder="+63 912 345 6789" autocomplete="tel">
        </div>
        <div class="form-group">
          <label for="f_address">Delivery Address <span style="color:var(--rose)">*</span></label>
          <textarea id="f_address" name="address" placeholder="House number, street, barangay, city..." required></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="f_flower">Select Flower <span style="color:var(--rose)">*</span></label>
            <select id="f_flower" name="flower_id" required onchange="updatePrice()">
              <option value="">-- Choose a flower --</option>
              <?php foreach ($flowers as $f): ?>
              <?php if ($f['stock'] > 0): ?>
              <option value="<?= $f['id'] ?>" data-price="<?= $f['price'] ?>">
                <?= htmlspecialchars($f['name']) ?> &mdash; &#8369;<?= number_format($f['price'],2) ?>
              </option>
              <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Quantity</label>
            <div class="quantity-row">
              <button type="button" class="qty-btn" onclick="changeQty(-1)">&#8722;</button>
              <input type="number" class="qty-input" id="f_qty" name="quantity" value="1" min="1" max="999" onchange="updatePrice()">
              <button type="button" class="qty-btn" onclick="changeQty(1)">&#43;</button>
            </div>
          </div>
        </div>
        <div id="orderTotal" style="display:none; background:var(--blush-soft); border:1px solid var(--border); border-radius:var(--radius-sm); padding:.85rem 1rem; margin-bottom:1.1rem;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:.85rem; color:var(--text-mid); font-weight:500;">Estimated Total</span>
            <span id="totalAmount" style="font-size:1.2rem; font-weight:700; color:var(--rose-dark); font-family:'Playfair Display',serif;"></span>
          </div>
          <p class="form-hint" style="margin-top:.35rem;">Final amount may vary. Payment is cash on delivery.</p>
        </div>
        <button type="submit" name="place_order" class="btn btn-primary btn-block" style="padding:.85rem; font-size:.95rem;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          Place My Order
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ── TRACK ORDER ── -->
<section class="section section-sm" id="track">
  <div class="section-head">
    <span class="section-label">Order Status</span>
    <h2>Track Your <em>Order</em></h2>
    <p>Enter the email address you used to place your order to see all your orders and their current status.</p>
  </div>
  <div class="track-card">
    <form method="POST" action="#track">
      <div class="track-form">
        <div class="form-group">
          <label for="track_email">Email Address</label>
          <input type="email" id="track_email" name="track_email" placeholder="you@example.com" required value="<?= htmlspecialchars($_POST['track_email'] ?? '') ?>">
        </div>
        <button type="submit" name="track_order" class="btn btn-primary">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Track
        </button>
      </div>
    </form>
    <?php if ($trackResult !== null): ?>
    <div class="track-results">
      <?php if ($trackResult): ?>
      <p style="font-size:.82rem; color:var(--text-soft); margin-bottom:.75rem; font-weight:500; text-transform:uppercase; letter-spacing:.07em;">
        <?= count($trackResult) ?> order<?= count($trackResult) !== 1 ? 's' : '' ?> found
      </p>
      <?php foreach ($trackResult as $t): ?>
      <div class="track-item">
        <?php if ($t['image_url'] && file_exists($t['image_url'])): ?>
        <img class="track-thumb" src="<?= htmlspecialchars($t['image_url']) ?>" alt="">
        <?php else: ?>
        <div class="track-thumb-ph">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C9 2 6.5 4.5 6.5 7.5c0 1.5.6 2.9 1.6 3.9C5.8 12.3 4 14.5 4 17c0 2.8 2.2 5 5 5h6c2.8 0 5-2.2 5-5 0-2.5-1.8-4.7-4.1-5.6 1-1 1.6-2.4 1.6-3.9C17.5 4.5 15 2 12 2z"/></svg>
        </div>
        <?php endif; ?>
        <div class="track-meta">
          <div class="name"><?= htmlspecialchars($t['flower_name']) ?> &times; <?= $t['quantity'] ?></div>
          <div class="sub"><?= date('M j, Y', strtotime($t['order_date'])) ?> &middot; Order #<?= $t['id'] ?></div>
          <div style="margin-top:.4rem;"><span class="badge badge-<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></div>
        </div>
        <div class="track-right">
          <div class="price">&#8369;<?= number_format($t['total_price'], 2) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <div style="text-align:center; padding:2rem 1rem; color:var(--text-soft);">
        <p style="font-size:.9rem;">No orders found for that email address.</p>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<div class="divider"></div>

<!-- ── TESTIMONIALS ── -->


<!-- ── FOOTER ── -->
<footer>
  <div class="footer-inner">
    <div class="footer-grid">
      <div class="footer-brand">
        <h3>Petals</h3>
        <p>Your trusted flower boutique in the Philippines. Fresh blooms, heartfelt arrangements, and reliable delivery since 2024.</p>
        <div class="footer-socials">
          <a href="#" class="social-btn" aria-label="Facebook">
            <svg viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
          </a>
          <a href="#" class="social-btn" aria-label="Instagram">
            <svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
          </a>
          <a href="#" class="social-btn" aria-label="TikTok">
            <svg viewBox="0 0 24 24"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg>
          </a>
        </div>
      </div>
      <div class="footer-col">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="#shop">Shop Flowers</a></li>
          <li><a href="#how">How It Works</a></li>
          <li><a href="#order">Place an Order</a></li>
          <li><a href="#track">Track Order</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Flower Types</h4>
        <ul>
          <?php foreach ($categories as $cat): ?>
          <li><a href="#shop" onclick="filterCat('<?= htmlspecialchars($cat) ?>')"><?= htmlspecialchars($cat) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Contact Us</h4>
        <address>
          123 Mango Avenue<br>
          Cebu City, Philippines<br><br>
          +63 912 345 6789<br>
          info@petals.com<br><br>
          Mon&ndash;Sat: 8am &ndash; 7pm<br>
          Sunday: 9am &ndash; 5pm
        </address>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> Petals Flower Boutique. All rights reserved.</p>
      <p>Made with care in Cebu, Philippines &middot; <a href="#">Privacy Policy</a></p>
    </div>
  </div>
</footer>

<!-- ── FLOWER DETAIL MODAL ── -->
<div class="modal-overlay" id="flowerDetailModal">
  <div class="modal" style="max-width: 540px; padding: 1.5rem;">
    <button class="modal-close" onclick="closeFlowerModal()">
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div id="fd_content"></div>
  </div>
</div>

<!-- ── QUICK ORDER MODAL ── -->
<div class="modal-overlay" id="quickOrderModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal()">
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <h3 id="qo_title">Order Flower</h3>
    <div class="modal-flower-preview" id="qo_preview"></div>

    <?php if ($flash && $flash['type'] === 'modal'): ?>
    <div class="flash flash-success">...</div>
    <?php endif; ?>

    <form method="POST" action="#order">
      <input type="hidden" name="flower_id" id="qo_flower_id">
      <div class="form-row">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="name" placeholder="Sofia Reyes" required>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" placeholder="you@email.com" required>
        </div>
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="tel" name="phone" placeholder="+63 912 345 6789">
      </div>
      <div class="form-group">
        <label>Delivery Address *</label>
        <textarea name="address" placeholder="Street, Barangay, City..." required></textarea>
      </div>
      <div class="form-group">
        <label>Quantity</label>
        <div class="quantity-row">
          <button type="button" class="qty-btn" onclick="changeModalQty(-1)">&#8722;</button>
          <input type="number" class="qty-input" id="qo_qty" name="quantity" value="1" min="1" onchange="updateModalTotal()">
          <button type="button" class="qty-btn" onclick="changeModalQty(1)">&#43;</button>
        </div>
      </div>
      <div style="background:var(--blush-soft); border:1px solid var(--border); border-radius:var(--radius-sm); padding:.85rem 1rem; margin-bottom:1.1rem;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <span style="font-size:.85rem; color:var(--text-mid); font-weight:500;">Estimated Total</span>
          <span id="qo_total" style="font-size:1.2rem; font-weight:700; color:var(--rose-dark); font-family:'Playfair Display',serif;">&#8369;0.00</span>
        </div>
        <p class="form-hint" style="margin-top:.3rem;">Cash on delivery. No advance payment needed.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button type="submit" name="place_order" class="btn btn-primary">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px;"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          Place Order
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── BACK TO TOP ── -->
<button class="back-top" id="backTop" aria-label="Back to top">
  <svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
</button>

<script>
// ── THEME ──────────────────────────────────────────────────────────────
const html        = document.documentElement;
const themeToggle = document.getElementById('themeToggle');
const iconMoon    = document.getElementById('iconMoon');
const iconSun     = document.getElementById('iconSun');

function applyTheme(t) {
    html.setAttribute('data-theme', t);
    localStorage.setItem('petal_theme', t);
    if (t === 'dark') {
        iconMoon.style.display = 'none';
        iconSun.style.display  = 'block';
    } else {
        iconMoon.style.display = 'block';
        iconSun.style.display  = 'none';
    }
}
// Init
applyTheme(localStorage.getItem('petal_theme') || 'light');
themeToggle.addEventListener('click', () => {
    applyTheme(html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
});

// ── MOBILE NAV ─────────────────────────────────────────────────────────
const mobileMenu    = document.getElementById('mobileMenu');
const navHamburger  = document.getElementById('navHamburger');
function closeMobileMenu() { mobileMenu.classList.remove('open'); }
navHamburger.addEventListener('click', () => mobileMenu.classList.toggle('open'));

// ── SHOP FILTER ────────────────────────────────────────────────────────
const filterBtns  = document.querySelectorAll('.filter-btn');
const flowerCards = document.querySelectorAll('#flowerGrid .flower-card');

function filterCat(cat) {
    filterBtns.forEach(b => b.classList.toggle('active', b.dataset.cat === cat));
    flowerCards.forEach(c => {
        if (cat === 'all' || c.dataset.cat === cat) {
            c.style.display = '';
        } else {
            c.style.display = 'none';
        }
    });
    document.getElementById('shop').scrollIntoView({ behavior: 'smooth' });
}

filterBtns.forEach(btn => {
    btn.addEventListener('click', () => filterCat(btn.dataset.cat));
});

// ── PRICE CALCULATOR (main form) ───────────────────────────────────────
function updatePrice() {
    const sel = document.getElementById('f_flower');
    const qty = parseInt(document.getElementById('f_qty').value) || 1;
    const opt = sel.options[sel.selectedIndex];
    const price = parseFloat(opt?.dataset?.price || 0);
    const totalEl = document.getElementById('orderTotal');
    const amountEl = document.getElementById('totalAmount');
    if (price && qty) {
        totalEl.style.display = '';
        amountEl.textContent = '\u20B1' + (price * qty).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    } else {
        totalEl.style.display = 'none';
    }
}
function changeQty(delta) {
    const el = document.getElementById('f_qty');
    el.value = Math.max(1, (parseInt(el.value) || 1) + delta);
    updatePrice();
}

// ── QUICK ORDER MODAL ──────────────────────────────────────────────────
let currentPrice = 0;

function openOrderModal(flower) {
    if (flower.stock <= 0) return;
    currentPrice = parseFloat(flower.price);

    document.getElementById('qo_title').textContent = 'Order — ' + flower.name;
    document.getElementById('qo_flower_id').value = flower.id;
    document.getElementById('qo_qty').value = 1;

    // Preview
    let img = '';
    if (flower.image_url) {
        img = '<img src="' + flower.image_url + '" alt="" onerror="this.style.display=\'none\'">';
    }
    document.getElementById('qo_preview').innerHTML =
        img +
        '<div class="mfp-info">' +
            '<div class="cat">' + (flower.category || '') + '</div>' +
            '<div class="name">' + flower.name + '</div>' +
            '<div class="price">\u20B1' + parseFloat(flower.price).toLocaleString('en-PH',{minimumFractionDigits:2}) + ' per stem</div>' +
        '</div>';

    updateModalTotal();
    document.getElementById('quickOrderModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('quickOrderModal').classList.remove('open');
    document.body.style.overflow = '';
}

function updateModalTotal() {
    const qty = parseInt(document.getElementById('qo_qty').value) || 1;
    document.getElementById('qo_total').textContent =
        '\u20B1' + (currentPrice * qty).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function changeModalQty(delta) {
    const el = document.getElementById('qo_qty');
    el.value = Math.max(1, (parseInt(el.value) || 1) + delta);
    updateModalTotal();
}

// Close modal on overlay click
document.getElementById('quickOrderModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
});

// ── FLOWER DETAIL MODAL ────────────────────────────────────────────────
let currentFlower = null;

function openFlowerModal(flower) {
    currentFlower = flower;
    let imgHTML = '';
    if (flower.image_url) {
        imgHTML = '<div style="position:relative; margin-bottom:1.5rem;">' +
                    '<img src="' + flower.image_url + '" alt="' + flower.name + '" style="width:100%; height:300px; object-fit:cover; border-radius:12px; display:block;">' +
                    '<button onclick="viewFullScreenImage(\'' + flower.image_url + '\')" style="position:absolute; bottom:12px; right:12px; background:rgba(255,255,255,0.85); border:none; border-radius:50%; width:36px; height:36px; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(0,0,0,0.2); backdrop-filter:blur(4px); transition:transform 0.2s;" onmouseover="this.style.transform=\'scale(1.1)\'" onmouseout="this.style.transform=\'scale(1)\'" aria-label="View Full Image">' +
                        '<svg viewBox="0 0 24 24" width="18" height="18" stroke="var(--text-dark)" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>' +
                    '</button>' +
                  '</div>';
    } else {
        imgHTML = '<div style="width:100%; height:300px; background:var(--blush); border-radius:12px; margin-bottom:1.5rem; display:flex; align-items:center; justify-content:center;"><svg style="width:64px;height:64px;fill:var(--rose-mid);opacity:0.5;" viewBox="0 0 24 24"><path d="M12 2C9 2 6.5 4.5 6.5 7.5c0 1.5.6 2.9 1.6 3.9C5.8 12.3 4 14.5 4 17c0 2.8 2.2 5 5 5h6c2.8 0 5-2.2 5-5 0-2.5-1.8-4.7-4.1-5.6 1-1 1.6-2.4 1.6-3.9C17.5 4.5 15 2 12 2z"/></svg></div>';
    }

    const priceFormatted = '\u20B1' + parseFloat(flower.price).toLocaleString('en-PH', {minimumFractionDigits:2});
    const stockStatus = parseInt(flower.stock) > 0 ? (parseInt(flower.stock) < 20 ? '<span style="color:var(--rose)">Only ' + flower.stock + ' left in stock</span>' : '<span style="color:var(--text-soft)">' + flower.stock + ' in stock</span>') : '<span style="color:var(--rose); font-weight:700;">Out of Stock</span>';
    
    let html = imgHTML + 
        '<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:.5rem;">' +
            '<div>' +
                '<h2 style="font-family:\'Playfair Display\', serif; font-size:1.8rem; color:var(--rose-dark); margin-bottom:.3rem; line-height: 1.1;">' + flower.name + '</h2>' +
                (flower.category ? '<span class="badge" style="background:var(--bg-tag); color:var(--rose); border:1px solid var(--border); font-size:.75rem;">' + flower.category + '</span>' : '') +
            '</div>' +
            '<div style="text-align:right;">' +
                '<div style="font-family:\'Playfair Display\', serif; font-size:1.6rem; font-weight:700; color:var(--rose-dark);">' + priceFormatted + '</div>' +
                '<div style="font-size:.85rem; margin-top:.2rem;">' + stockStatus + '</div>' +
            '</div>' +
        '</div>' +
        '<p style="font-size:.95rem; color:var(--text-mid); line-height:1.6; margin-top:1.2rem; margin-bottom:2rem;">' + (flower.description || 'No description available.') + '</p>' +
        '<div style="display:flex; gap:1rem; justify-content:flex-end;">' +
            '<button class="btn btn-ghost" onclick="closeFlowerModal()">Close</button>' +
            '<button class="btn btn-primary" ' + (parseInt(flower.stock) <= 0 ? 'disabled style="opacity:.5;cursor:not-allowed"' : '') + ' onclick="proceedToOrder()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg> Order Now</button>' +
        '</div>';

    document.getElementById('fd_content').innerHTML = html;
    document.getElementById('flowerDetailModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function viewFullScreenImage(url) {
    const overlay = document.createElement('div');
    overlay.id = 'fsOverlay';
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.backgroundColor = 'rgba(0,0,0,0.85)';
    overlay.style.zIndex = '9999';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.cursor = 'zoom-out';
    overlay.onclick = () => overlay.remove();
    
    const img = document.createElement('img');
    img.src = url;
    img.style.maxWidth = '90vw';
    img.style.maxHeight = '90vh';
    img.style.objectFit = 'contain';
    img.style.borderRadius = '8px';
    img.style.boxShadow = '0 10px 40px rgba(0,0,0,0.5)';
    
    overlay.appendChild(img);
    document.body.appendChild(overlay);
}

function closeFlowerModal() {
    document.getElementById('flowerDetailModal').classList.remove('open');
    if (!document.getElementById('quickOrderModal').classList.contains('open')) {
        document.body.style.overflow = '';
    }
}

function proceedToOrder() {
    closeFlowerModal();
    if (currentFlower) {
        openOrderModal(currentFlower);
    }
}

document.getElementById('flowerDetailModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeFlowerModal();
});

// Close on ESC
document.addEventListener('keydown', e => { 
    if (e.key === 'Escape') {
        const fsOverlay = document.getElementById('fsOverlay');
        if (fsOverlay) {
            fsOverlay.remove();
            return;
        }
        closeModal();
        closeFlowerModal();
    }
});

// ── BACK TO TOP ────────────────────────────────────────────────────────
const backTop = document.getElementById('backTop');
window.addEventListener('scroll', () => {
    backTop.classList.toggle('visible', window.scrollY > 400);
});
backTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

// ── NAV ORDER BTN (show on scroll past hero) ───────────────────────────
const navOBtn = document.getElementById('navOrderBtn');
const heroEl  = document.querySelector('.hero');
if (navOBtn && heroEl) {
    const obs = new IntersectionObserver(entries => {
        navOBtn.style.display = entries[0].isIntersecting ? 'none' : '';
    }, { threshold: 0 });
    obs.observe(heroEl);
}

// ── ACTIVE NAV LINK ────────────────────────────────────────────────────
const sections = document.querySelectorAll('section[id], div[id]');
const navAs    = document.querySelectorAll('.nav-links a');
const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            navAs.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + e.target.id));
        }
    });
}, { rootMargin: '-40% 0px -55%' });
sections.forEach(s => io.observe(s));
</script>
</body>
</html>