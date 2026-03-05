<?php
require_once __DIR__ . '/../lib/bootstrap.php';

require_login();
$flash = flash_get();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash_set('err', 'Neplatný CSRF token.');
        header('Location: index.php?p=settings'); exit;
    }

    $company = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
    $currency = isset($_POST['currency_default']) ? trim($_POST['currency_default']) : '';
    $footer = isset($_POST['print_footer']) ? trim($_POST['print_footer']) : '';

    if ($company === '') { $company = 'Jízdní řád'; }
    if ($currency === '') { $currency = 'CZK'; }

    try {
        settings_set('company_name', $company);
        settings_set('currency_default', $currency);
        settings_set('print_footer', $footer);
        flash_set('ok', 'Nastavení uloženo.');
    } catch (Exception $e) {
        app_log('settings save error: '.$e->getMessage());
        flash_set('err', 'Chyba při ukládání.');
    }

    header('Location: index.php?p=settings'); exit;
}

$title = settings_get('company_name', 'Jízdní řád');
$subtitle = 'Nastavení';
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

$intro = cms_section('settings', 'intro', '');

$company = settings_get('company_name', 'Jízdní řád');
$currency = settings_get('currency_default', 'CZK');
$footer = settings_get('print_footer', '');

$content = TextDisplay($intro).Separator();

$content .= '<form method="post" action="index.php?p=settings">'.
    InputHidden('csrf_token', csrf_token()).
    '<div class="grid">'.
      '<div class="field"><label>Název systému / firmy</label><input type="text" name="company_name" value="'.h($company).'"></div>'.
      '<div class="field"><label>Výchozí měna</label><input type="text" name="currency_default" value="'.h($currency).'"></div>'.
      '<div class="field" style="grid-column:1/-1;"><label>Patička tisku</label><textarea name="print_footer">'.h($footer).'</textarea></div>'.
    '</div>'.
    Separator().
    ActionRow(
      Button('Uložit', 'submit', '', '', 'primary', array())
    ).
  '</form>';

$html = Container($content);

LayoutView($title, $subtitle, $navItems, $html, $flash);
