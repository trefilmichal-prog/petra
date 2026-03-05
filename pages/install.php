<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$pdo = db();
$flash = flash_get();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash_set('err', 'Neplatný CSRF token.');
        header('Location: index.php?p=install');
        exit;
    }

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $pass1 = isset($_POST['pass1']) ? (string)$_POST['pass1'] : '';
    $pass2 = isset($_POST['pass2']) ? (string)$_POST['pass2'] : '';

    if ($username === '' || strlen($username) < 3) {
        flash_set('err', 'Uživatelské jméno musí mít alespoň 3 znaky.');
        header('Location: index.php?p=install');
        exit;
    }
    if ($pass1 === '' || strlen($pass1) < 8) {
        flash_set('err', 'Heslo musí mít alespoň 8 znaků.');
        header('Location: index.php?p=install');
        exit;
    }
    if ($pass1 !== $pass2) {
        flash_set('err', 'Hesla se neshodují.');
        header('Location: index.php?p=install');
        exit;
    }

    try {
        // Ensure still no user
        $c = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
        if ($c > 0) {
            flash_set('err', 'Admin už existuje.');
            header('Location: index.php?p=login');
            exit;
        }

        $hash = password_hash($pass1, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, is_active, created_at) VALUES (?, ?, 1, ?)');
        $stmt->execute(array($username, $hash, date('c')));

        flash_set('ok', 'Admin účet byl vytvořen. Přihlaste se.');
        header('Location: index.php?p=login');
        exit;
    } catch (Exception $e) {
        app_log('install error: '.$e->getMessage());
        flash_set('err', 'Chyba při vytváření účtu.');
        header('Location: index.php?p=install');
        exit;
    }
}

$title = settings_get('company_name', 'Jízdní řád');
$subtitle = 'Vytvoření prvního admin účtu';
$navItems = array();

$html = '';
$html .= Container(
    '<form method="post" action="index.php?p=install">'.
      InputHidden('csrf_token', csrf_token()).
      '<div class="grid">'.
        '<div class="field"><label>Uživatelské jméno</label><input type="text" name="username" required></div>'.
        '<div class="field"><label>Heslo</label><input type="password" name="pass1" required></div>'.
        '<div class="field"><label>Heslo znovu</label><input type="password" name="pass2" required></div>'.
      '</div>'.
      Separator().
      ActionRow(
        Button('Vytvořit admin účet', 'submit', '', '', 'primary', array())
      ).
    '</form>'.
    Separator().
    TextDisplay("Po vytvoření admin účtu bude instalace uzamčena.\nDoporučení: nastavte přístup pouze přes HTTPS.")
);

LayoutView($title, $subtitle, $navItems, $html, $flash);
