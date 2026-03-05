<?php
/**
 * Bootstrap: session, DB connection, migrations, auth helpers, CSRF, flash.
 * PHP 5.6 compatible.
 */
require_once __DIR__ . '/components_v2.php';

date_default_timezone_set('Europe/Amsterdam');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Basic hardening
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
// If you have HTTPS, you may set this to 1:
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_samesite', 'Lax');

define('APP_DB_PATH', __DIR__ . '/../db/app.sqlite');
define('APP_LOG_PATH', __DIR__ . '/../logs/app.log');

function app_log($msg) {
    $line = '['.date('Y-m-d H:i:s').'] '.$msg."\n";
    @file_put_contents(APP_LOG_PATH, $line, FILE_APPEND);
}

function db() {
    static $pdo = null;
    if ($pdo !== null) { return $pdo; }

    try {
        if (!extension_loaded('pdo_sqlite')) {
            throw new Exception('PHP rozšíření pdo_sqlite není dostupné.');
        }

        $dir = dirname(APP_DB_PATH);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

        $pdo = new PDO('sqlite:' . APP_DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // SQLite pragmas
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA synchronous = NORMAL;');

        return $pdo;
    } catch (Exception $e) {
        app_log('DB init error: '.$e->getMessage());
        throw $e;
    }
}

function migrate_if_needed() {
    $pdo = db();

    // schema_version table
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_version (id INTEGER PRIMARY KEY, version INTEGER NOT NULL);');
    $row = $pdo->query('SELECT version FROM schema_version WHERE id=1')->fetch();
    $ver = $row ? (int)$row['version'] : 0;

    if ($ver < 1) {
        $pdo->beginTransaction();
        try {
            // Users
            $pdo->exec('CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL
            );');

            // Settings
            $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
                k TEXT PRIMARY KEY,
                v TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );');

            // Clients
            $pdo->exec('CREATE TABLE IF NOT EXISTS clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                phone TEXT NOT NULL DEFAULT "",
                email TEXT NOT NULL DEFAULT "",
                address TEXT NOT NULL DEFAULT "",
                note TEXT NOT NULL DEFAULT "",
                default_price_cents INTEGER NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT "CZK",
                is_active INTEGER NOT NULL DEFAULT 1,
                sort INTEGER NOT NULL DEFAULT 1000,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );');

            // Rides
            $pdo->exec('CREATE TABLE IF NOT EXISTS rides (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ride_date TEXT NOT NULL,          -- YYYY-MM-DD
                start_time TEXT NOT NULL,         -- HH:MM
                end_time TEXT NOT NULL DEFAULT "",-- HH:MM
                client_id INTEGER NOT NULL,
                pickup TEXT NOT NULL DEFAULT "",
                dropoff TEXT NOT NULL DEFAULT "",
                price_cents INTEGER NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT "CZK",
                status TEXT NOT NULL DEFAULT "planned", -- planned|done|cancelled
                note TEXT NOT NULL DEFAULT "",
                is_active INTEGER NOT NULL DEFAULT 1,
                sort INTEGER NOT NULL DEFAULT 1000,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT
            );');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rides_date ON rides(ride_date);');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rides_client ON rides(client_id);');

            // CMS: pages and sections (editable text content for each UI page)
            $pdo->exec('CREATE TABLE IF NOT EXISTS cms_pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                sort INTEGER NOT NULL DEFAULT 1000,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );');

            $pdo->exec('CREATE TABLE IF NOT EXISTS cms_sections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                page_id INTEGER NOT NULL,
                section_key TEXT NOT NULL,
                label TEXT NOT NULL,
                content_text TEXT NOT NULL DEFAULT "",
                is_active INTEGER NOT NULL DEFAULT 1,
                sort INTEGER NOT NULL DEFAULT 1000,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE(page_id, section_key),
                FOREIGN KEY (page_id) REFERENCES cms_pages(id) ON DELETE CASCADE
            );');

            // Seed settings (stored in DB, not hardcoded page content)
            $now = date('c');
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO settings (k, v, updated_at) VALUES (?, ?, ?)');
            $stmt->execute(array('company_name', 'Jízdní řád', $now));
            $stmt->execute(array('currency_default', 'CZK', $now));
            $stmt->execute(array('print_footer', 'Vytvořeno v systému Jízdní řád.', $now));

            // Seed CMS pages/sections
            $pages = array(
                array('dashboard', 'Dashboard'),
                array('rides', 'Jízdní řád'),
                array('clients', 'Klienti'),
                array('summaries', 'Souhrny a tisk'),
                array('settings', 'Nastavení'),
                array('cms', 'Editor obsahu')
            );

            $pIns = $pdo->prepare('INSERT OR IGNORE INTO cms_pages (slug, title, is_active, sort, created_at, updated_at) VALUES (?, ?, 1, 1000, ?, ?)');
            foreach ($pages as $p) {
                $pIns->execute(array($p[0], $p[1], $now, $now));
            }

            $pIdStmt = $pdo->prepare('SELECT id FROM cms_pages WHERE slug = ?');
            $sIns = $pdo->prepare('INSERT OR IGNORE INTO cms_sections (page_id, section_key, label, content_text, is_active, sort, created_at, updated_at) VALUES (?, ?, ?, ?, 1, 1000, ?, ?)');
            $seedSections = array(
                'dashboard' => array(
                    array('intro', 'Intro text', "Zde můžete spravovat jízdní řád, klienty a tisknout souhrny.\n\nDoporučení: nastavte výchozí měnu v Nastavení.")
                ),
                'rides' => array(
                    array('intro', 'Intro text', "Vyberte datum a přidejte jízdy. Každá jízda má klienta, čas a cenu.")
                ),
                'clients' => array(
                    array('intro', 'Intro text', "Spravujte klienty: vytvořit, upravit, deaktivovat nebo smazat. Volitelně nastavte výchozí cenu pro klienta.")
                ),
                'summaries' => array(
                    array('intro', 'Intro text', "Tiskněte jízdní řád pro konkrétní den a různé souhrny podle dne nebo období.")
                ),
                'settings' => array(
                    array('intro', 'Intro text', "Zde nastavíte název systému, výchozí měnu a text do patičky tisku.")
                ),
                'cms' => array(
                    array('intro', 'Intro text', "Veškerý textový obsah panelu je editovatelný zde. UI prvky (tlačítka, pole) jsou součástí aplikace.")
                )
            );

            foreach ($seedSections as $slug => $sections) {
                $pIdStmt->execute(array($slug));
                $pidRow = $pIdStmt->fetch();
                if ($pidRow) {
                    $pid = (int)$pidRow['id'];
                    foreach ($sections as $s) {
                        $sIns->execute(array($pid, $s[0], $s[1], $s[2], $now, $now));
                    }
                }
            }

            $pdo->exec('INSERT OR REPLACE INTO schema_version (id, version) VALUES (1, 1);');
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            app_log('Migration error: '.$e->getMessage());
            throw $e;
        }
    }

    if ($ver < 2) {
        $pdo->beginTransaction();
        try {
            $now = date('c');

            // Add CMS page for account
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO cms_pages (slug, title, is_active, sort, created_at, updated_at) VALUES (?, ?, 1, 1000, ?, ?)');
            $stmt->execute(array('account', 'Můj účet', $now, $now));

            $stmt = $pdo->prepare('SELECT id FROM cms_pages WHERE slug = ?');
            $stmt->execute(array('account'));
            $pidRow = $stmt->fetch();
            if ($pidRow) {
                $pid = (int)$pidRow['id'];
                $sIns = $pdo->prepare('INSERT OR IGNORE INTO cms_sections (page_id, section_key, label, content_text, is_active, sort, created_at, updated_at) VALUES (?, ?, ?, ?, 1, 1000, ?, ?)');
                $sIns->execute(array($pid, 'intro', 'Intro text', "Zde můžete změnit své heslo.", $now, $now));
            }

            $pdo->exec('INSERT OR REPLACE INTO schema_version (id, version) VALUES (1, 2);');
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            app_log('Migration v2 error: '.$e->getMessage());
            throw $e;
        }
    }
}


function flash_set($type, $msg) {
    $_SESSION['flash'] = array('type' => $type, 'msg' => $msg);
}

function flash_get() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function csrf_token() {
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 20) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { return true; }
    $t = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    return (is_string($t) && hash_equals(csrf_token(), $t));
}

function is_logged_in() {
    return isset($_SESSION['uid']) && is_numeric($_SESSION['uid']);
}

function current_user() {
    if (!is_logged_in()) { return null; }
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, username, is_active FROM users WHERE id = ?');
        $stmt->execute(array((int)$_SESSION['uid']));
        $u = $stmt->fetch();
        if ($u && (int)$u['is_active'] === 1) { return $u; }
    } catch (Exception $e) {
        app_log('current_user error: '.$e->getMessage());
    }
    return null;
}

function require_login() {
    $u = current_user();
    if (!$u) {
        header('Location: index.php?p=login');
        exit;
    }
    return $u;
}

function settings_get($k, $default) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT v FROM settings WHERE k = ?');
        $stmt->execute(array($k));
        $r = $stmt->fetch();
        if ($r) { return $r['v']; }
    } catch (Exception $e) {
        app_log('settings_get error: '.$e->getMessage());
    }
    return $default;
}

function settings_set($k, $v) {
    $pdo = db();
    $stmt = $pdo->prepare('INSERT OR REPLACE INTO settings (k, v, updated_at) VALUES (?, ?, ?)');
    $stmt->execute(array($k, $v, date('c')));
}

function cms_page_id($slug) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM cms_pages WHERE slug = ? AND is_active = 1');
    $stmt->execute(array($slug));
    $r = $stmt->fetch();
    return $r ? (int)$r['id'] : 0;
}

function cms_section($slug, $section_key, $fallback) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT s.content_text FROM cms_sections s
                               JOIN cms_pages p ON p.id = s.page_id
                               WHERE p.slug = ? AND s.section_key = ? AND p.is_active = 1 AND s.is_active = 1');
        $stmt->execute(array($slug, $section_key));
        $r = $stmt->fetch();
        if ($r) { return (string)$r['content_text']; }
    } catch (Exception $e) {
        app_log('cms_section error: '.$e->getMessage());
    }
    return $fallback;
}

function validate_date($s) {
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}

function validate_time($s) {
    if ($s === '') { return true; }
    if (!preg_match('/^\d{2}:\d{2}$/', $s)) { return false; }
    $hh = (int)substr($s, 0, 2);
    $mm = (int)substr($s, 3, 2);
    return ($hh >= 0 && $hh <= 23 && $mm >= 0 && $mm <= 59);
}

function money_to_cents($s) {
    // Accept 123, 123.45, 123,45
    $s = trim(str_replace(',', '.', $s));
    if ($s === '') { return 0; }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $s)) { return null; }
    $parts = explode('.', $s, 2);
    $whole = (int)$parts[0];
    $frac = 0;
    if (count($parts) === 2) {
        $f = $parts[1];
        if (strlen($f) === 1) { $f .= '0'; }
        $frac = (int)$f;
    }
    return $whole * 100 + $frac;
}

function cents_to_money($cents) {
    $c = (int)$cents;
    $whole = floor($c / 100);
    $frac = $c % 100;
    return $whole . '.' . str_pad((string)$frac, 2, '0', STR_PAD_LEFT);
}

migrate_if_needed();
