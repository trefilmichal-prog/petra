<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$u = require_login();
$flash = flash_get();

$title = settings_get('company_name', 'Jízdní řád');
$subtitle = 'Dashboard';
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

$today = date('Y-m-d');
$selectedDate = isset($_GET['date']) && validate_date($_GET['date']) ? $_GET['date'] : $today;

$pdo = db();
$kpi1 = 0; $kpi2 = 0; $kpi3 = 0;
$currency = settings_get('currency_default', 'CZK');

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c, COALESCE(SUM(price_cents),0) AS s FROM rides WHERE ride_date = ? AND is_active = 1');
    $stmt->execute(array($selectedDate));
    $row = $stmt->fetch();
    $kpi1 = $row ? (int)$row['c'] : 0;
    $kpi2 = $row ? (int)$row['s'] : 0;

    $stmt = $pdo->query('SELECT COUNT(*) AS c FROM clients WHERE is_active = 1');
    $row2 = $stmt->fetch();
    $kpi3 = $row2 ? (int)$row2['c'] : 0;
} catch (Exception $e) {
    app_log('dashboard kpi error: '.$e->getMessage());
}

$intro = cms_section('dashboard', 'intro', '');

$html = '';
$html .= Container(
    TextDisplay($intro).
    Separator().
    '<form class="print-hide" method="get" action="index.php">'.
      InputHidden('p', 'dashboard').
      '<div class="action-row">'.
        '<div class="field" style="min-width:220px;"><label>Datum</label><input type="date" name="date" value="'.h($selectedDate).'"></div>'.
        Button('Načíst', 'submit', '', '', 'primary', array()).
        '<a class="btn" href="index.php?p=rides&date='.h($selectedDate).'">Otevřít jízdní řád</a>'.
        '<a class="btn" href="index.php?p=print_schedule&date='.h($selectedDate).'" target="_blank">Tisk jízdního řádu</a>'.
      '</div>'.
    '</form>'.
    Separator().
    '<div class="grid">'.
      '<div class="kpi"><div class="k">Jízdy dne</div><div class="v">'.h((string)$kpi1).'</div></div>'.
      '<div class="kpi"><div class="k">Tržby dne</div><div class="v">'.h(cents_to_money($kpi2)).' '.h($currency).'</div></div>'.
      '<div class="kpi"><div class="k">Aktivní klienti</div><div class="v">'.h((string)$kpi3).'</div></div>'.
      '<div class="kpi"><div class="k">Uživatel</div><div class="v">'.h($u['username']).'</div></div>'.
    '</div>'
);

LayoutView($title, $subtitle, $navItems, $html, $flash);
