<?php
require_once __DIR__ . '/../lib/bootstrap.php';

require_login();
$flash = flash_get(); // not used
$pdo = db();

$date = isset($_GET['date']) && validate_date($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$company = settings_get('company_name', 'Jízdní řád');
$footer = settings_get('print_footer', '');

$rows = array();

try {
    $stmt = $pdo->prepare('SELECT r.*, c.name AS client_name, c.phone AS client_phone FROM rides r JOIN clients c ON c.id=r.client_id
                           WHERE r.ride_date = ? AND r.is_active = 1 ORDER BY r.start_time ASC, r.sort ASC, r.id ASC');
    $stmt->execute(array($date));
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    app_log('print_schedule error: '.$e->getMessage());
}

$title = $company;
$subtitle = 'Tisk jízdního řádu: '.$date;
$navItems = array(
    array('p' => 'rides', 'href' => 'index.php?p=rides&date='.$date, 'label' => 'Zpět'),
);

$content = '';
$content .= '<div class="text-display"><strong>Datum:</strong> '.h($date).'</div>';
$content .= Separator();

$tbl = '<table class="table"><thead><tr>'.
        '<th>Čas</th><th>Klient</th><th>Telefon</th><th>Trasa</th><th>Provedená jízda</th>'.
       '</tr></thead><tbody>';

foreach ($rows as $r) {
    $time = h($r['start_time']).($r['end_time']!=='' ? ('–'.h($r['end_time'])) : '');
    $route = h($r['pickup']).($r['dropoff']!=='' ? (' → '.h($r['dropoff'])) : '');
    if ($r['status'] === 'planned') { $rideDonePrint = '☐ ANO  ☐ NE'; }
    else if ($r['status'] === 'done') { $rideDonePrint = 'ANO'; }
    else { $rideDonePrint = 'NE'; }

    $tbl .= '<tr>'.
      '<td><strong>'.$time.'</strong></td>'.
      '<td>'.h($r['client_name']).'</td>'.
      '<td>'.h($r['client_phone']).'</td>'.
      '<td>'.$route.'</td>'.
      '<td>'.h($rideDonePrint).'</td>'.
    '</tr>';
}

if (count($rows) === 0) {
    $tbl .= '<tr><td colspan="5">Žádné jízdy.</td></tr>';
}
$tbl .= '</tbody></table>';

$content .= $tbl;
if ($footer !== '') {
    $content .= Separator().'<div class="text-display">'.nl2br(h($footer)).'</div>';
}

$html = Container($content);
LayoutView($title, $subtitle, $navItems, $html, $flash);
