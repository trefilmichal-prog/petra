<?php
require_once __DIR__ . '/../lib/bootstrap.php';

require_login();
$pdo = db();

$date = isset($_GET['date']) && validate_date($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$company = settings_get('company_name', 'Jízdní řád');
$footer = settings_get('print_footer', '');
$currencyDefault = settings_get('currency_default', 'CZK');

$rows = array();
$kpiCount = 0;
$kpiRevenue = 0;
$kpiDone = 0;
$kpiCancelled = 0;

try {
    $stmt = $pdo->prepare('SELECT r.*, c.name AS client_name FROM rides r JOIN clients c ON c.id=r.client_id
                           WHERE r.ride_date=? AND r.is_active=1 ORDER BY r.start_time ASC, r.sort ASC, r.id ASC');
    $stmt->execute(array($date));
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        $kpiCount++;
        if ($r['status'] === 'done') { $kpiDone++; }
        if ($r['status'] === 'cancelled') { $kpiCancelled++; }
        if ($r['status'] !== 'cancelled') { $kpiRevenue += (int)$r['price_cents']; }
    }
} catch (Exception $e) {
    app_log('print_summary_day error: '.$e->getMessage());
}

$title = $company;
$subtitle = 'Souhrn dne: '.$date;
$navItems = array(
    array('p' => 'rides', 'href' => 'index.php?p=rides&date='.$date, 'label' => 'Zpět'),
);

$content = '';
$content .= '<div class="grid">'.
  '<div class="kpi"><div class="k">Počet jízd</div><div class="v">'.h((string)$kpiCount).'</div></div>'.
  '<div class="kpi"><div class="k">Dokončeno</div><div class="v">'.h((string)$kpiDone).'</div></div>'.
  '<div class="kpi"><div class="k">Zrušeno</div><div class="v">'.h((string)$kpiCancelled).'</div></div>'.
  '<div class="kpi"><div class="k">Tržby (bez zrušených)</div><div class="v">'.h(cents_to_money($kpiRevenue)).' '.h($currencyDefault).'</div></div>'.
'</div>';

$content .= Separator();

$tbl = '<table class="table"><thead><tr>'.
        '<th>Čas</th><th>Klient</th><th>Cena</th><th>Status</th>'.
       '</tr></thead><tbody>';

foreach ($rows as $r) {
    $time = h($r['start_time']).($r['end_time']!=='' ? ('–'.h($r['end_time'])) : '');
    $price = cents_to_money((int)$r['price_cents']).' '.h($r['currency']);
    $status = $r['status'];
    if ($status === 'planned') { $statusLbl = 'Plánováno'; }
    else if ($status === 'done') { $statusLbl = 'Dokončeno'; }
    else { $statusLbl = 'Zrušeno'; }

    $tbl .= '<tr>'.
      '<td><strong>'.$time.'</strong></td>'.
      '<td>'.h($r['client_name']).'</td>'.
      '<td>'.h($price).'</td>'.
      '<td>'.h($statusLbl).'</td>'.
    '</tr>';
}

if (count($rows) === 0) {
    $tbl .= '<tr><td colspan="4">Žádné jízdy.</td></tr>';
}
$tbl .= '</tbody></table>';

$content .= $tbl;
if ($footer !== '') {
    $content .= Separator().'<div class="text-display">'.nl2br(h($footer)).'</div>';
}

$html = Container($content);
LayoutView($title, $subtitle, $navItems, $html, flash_get());
