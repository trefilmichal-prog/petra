<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if (is_logged_in()) {
    header('Location: index.php?p=dashboard');
    exit;
}

$flash = flash_get();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash_set('err', 'Neplatný CSRF token.');
        header('Location: index.php?p=login');
        exit;
    }

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($username === '' || $password === '') {
        flash_set('err', 'Vyplňte jméno i heslo.');
        header('Location: index.php?p=login');
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, password_hash, is_active FROM users WHERE username = ?');
        $stmt->execute(array($username));
        $u = $stmt->fetch();

        if (!$u || (int)$u['is_active'] !== 1 || !password_verify($password, $u['password_hash'])) {
            flash_set('err', 'Neplatné přihlašovací údaje.');
            header('Location: index.php?p=login');
            exit;
        }

        // Session regen
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];

        flash_set('ok', 'Přihlášení proběhlo úspěšně.');
        header('Location: index.php?p=dashboard');
        exit;
    } catch (Exception $e) {
        app_log('login error: '.$e->getMessage());
        flash_set('err', 'Chyba při přihlášení.');
        header('Location: index.php?p=login');
        exit;
    }
}

$title = settings_get('company_name', 'Jízdní řád');
$subtitle = 'Přihlášení';
$navItems = array();

$html = '';
$html .= Container(
    TextDisplay(cms_section('dashboard', 'intro', '')).
    Separator().
    '<form method="post" action="index.php?p=login">'.
      InputHidden('csrf_token', csrf_token()).
      '<div class="grid">'.
        '<div class="field"><label>Uživatel</label><input type="text" name="username" required></div>'.
        '<div class="field"><label>Heslo</label><input type="password" name="password" required></div>'.
      '</div>'.
      Separator().
      ActionRow(
        Button('Přihlásit', 'submit', '', '', 'primary', array())
      ).
    '</form>'
);

LayoutView($title, $subtitle, $navItems, $html, $flash);
