<?php
require_once __DIR__ . '/../lib/bootstrap.php';

require_login();
$flash = flash_get();
$pdo = db();

$currencyDefault = settings_get('currency_default', 'CZK');

$title = settings_get('company_name', 'Jízdní řád');
$subtitle = 'Souhrny a tisk';
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

$intro = cms_section('summaries', 'intro', '');

$date = isset($_GET['date']) && validate_date($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$from = isset($_GET['from']) && validate_date($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to = isset($_GET['to']) && validate_date($_GET['to']) ? $_GET['to'] : date('Y-m-d');

$content = TextDisplay($intro).Separator();

$content .= '<div class="grid">'.
  '<div class="container">'.
    '<div class="lv-title" style="font-size:16px;">Tisk jízdního řádu (den)</div>'.
    '<div class="text-display">Vytiskne přehled jízd pro vybraný den.</div>'.
    Separator().
    '<form method="get" action="index.php" class="print-hide" target="_blank">'.
      InputHidden('p','summaries').
      '<div class="action-row">'.
        '<div class="field" style="min-width:220px;"><label>Datum</label><input type="date" name="date" value="'.h($date).'"></div>'.
        '<button class="btn primary" type="submit" formaction="index.php?p=print_schedule">Tisk jízdního řádu</button>'.
        '<button class="btn" type="submit" formaction="index.php?p=print_summary_day">Tisk souhrnu dne</button>'.
      '</div>'.
    '</form>'.
  '</div>'.
  '<div class="container">'.
    '<div class="lv-title" style="font-size:16px;">Souhrn období</div>'.
    '<div class="text-display">Souhrn tržeb a jízd podle klienta za vybrané období.</div>'.
    Separator().
    '<form method="get" action="index.php" class="print-hide" onsubmit="if(this.action.indexOf(\'print_summary_range\') !== -1){var from=this.from.value;var to=this.to.value;if(from && to && from > to){alert(\'Datum Od nesmí být později než Do.\');return false;}}return true;">'.
      InputHidden('p','summaries').
      '<div class="action-row">'.
        '<div class="field" style="min-width:200px;"><label>Od</label><input type="date" name="from" value="'.h($from).'"></div>'.
        '<div class="field" style="min-width:200px;"><label>Do</label><input type="date" name="to" value="'.h($to).'"></div>'.
        '<button class="btn primary" type="submit" formaction="index.php?p=print_summary_range" formtarget="_blank">Tisk souhrnu období</button>'.
      '</div>'.
    '</form>'.
  '</div>'.
'</div>';

$html = Container($content);

LayoutView($title, $subtitle, $navItems, $html, $flash);
