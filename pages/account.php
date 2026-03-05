<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$u = require_login();
$flash = flash_get();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash_set('err', 'Neplatný CSRF token.');
        header('Location: index.php?p=account'); exit;
    }

    $cur = isset($_POST['current_password']) ? (string)$_POST['current_password'] : '';
    $p1 = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
    $p2 = isset($_POST['new_password2']) ? (string)$_POST['new_password2'] : '';

    if ($p1 === '' || strlen($p1) < 8) {
        flash_set('err', 'Nové heslo musí mít alespoň 8 znaků.');
        header('Location: index.php?p=account'); exit;
    }
    if ($p1 !== $p2) {
        flash_set('err', 'Nová hesla se neshodují.');
        header('Location: index.php?p=account'); exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute(array((int)$u['id']));
        $row = $stmt->fetch();
        if (!$row || !password_verify($cur, $row['password_hash'])) {
            flash_set('err', 'Aktuální heslo není správně.');
            header('Location: index.php?p=account'); exit;
        }

        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute(array($hash, (int)$u['id']));

        // refresh session
        session_regenerate_id(true);

        flash_set('ok', 'Heslo bylo změněno.');
        header('Location: index.php?p=account'); exit;

    } catch (Exception $e) {
        app_log('account password error: '.$e->getMessage());
        flash_set('err', 'Chyba při změně hesla.');
        header('Location: index.php?p=account'); exit;
    }
}

$title = settings_get('company_name', 'Jízdní řád');
$subtitle = 'Můj účet';
$navItems = array(
    array('p' => 'dashboard', 'href' => 'index.php?p=dashboard', 'label' => 'Dashboard'),
    array('p' => 'rides', 'href' => 'index.php?p=rides', 'label' => 'Jízdní řád'),
    array('p' => 'clients', 'href' => 'index.php?p=clients', 'label' => 'Klienti'),
    array('p' => 'summaries', 'href' => 'index.php?p=summaries', 'label' => 'Souhrny'),
    array('p' => 'settings', 'href' => 'index.php?p=settings', 'label' => 'Nastavení'),
    array('p' => 'cms', 'href' => 'index.php?p=cms', 'label' => 'Obsah'),
    array('p' => 'account', 'href' => 'index.php?p=account', 'label' => 'Účet'),
    array('p' => 'logout', 'href' => 'index.php?p=logout', 'label' => 'Odhlásit')
);

$content = '';
$content .= Container(
    TextDisplay(cms_section('account', 'intro', 'Zde můžete změnit své heslo.')).Separator().TextDisplay("Přihlášen jako: ".$u['username']).Separator().
    '<form method="post" action="index.php?p=account">'.
      InputHidden('csrf_token', csrf_token()).
      '<div class="grid">'.
        '<div class="field"><label>Aktuální heslo</label><input type="password" name="current_password" required></div>'.
        '<div class="field"><label>Nové heslo</label><input type="password" name="new_password" required></div>'.
        '<div class="field"><label>Nové heslo znovu</label><input type="password" name="new_password2" required></div>'.
      '</div>'.
      Separator().
      ActionRow(Button('Změnit heslo', 'submit', '', '', 'primary', array())).
    '</form>'
);

LayoutView($title, $subtitle, $navItems, $content, $flash);
