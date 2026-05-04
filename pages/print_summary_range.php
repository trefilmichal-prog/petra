<?php
require_once __DIR__ . '/../lib/bootstrap.php';

require_login();
$pdo = db();

$from = isset($_GET['from']) && validate_date($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to = isset($_GET['to']) && validate_date($_GET['to']) ? $_GET['to'] : date('Y-m-d');

$company = settings_get('company_name', 'Jízdní řád');
$footer = settings_get('print_footer', '');
$currencyDefault = settings_get('currency_default', 'CZK');

$rows = array();
$totalRevenue = 0;
$totalRides = 0;

try {
    $stmt = $pdo->prepare('
        SELECT c.id AS client_id, c.name AS client_name,
               SUM(CASE WHEN r.id IS NULL OR r.status="cancelled" THEN 0 ELSE 1 END) AS rides_count,
               COALESCE(SUM(CASE WHEN r.status="cancelled" THEN 0 ELSE r.price_cents END),0) AS revenue_cents
        FROM clients c
        LEFT JOIN rides r ON r.client_id=c.id AND r.ride_date BETWEEN ? AND ? AND r.is_active=1
        WHERE c.is_active=1
        GROUP BY c.id
        ORDER BY revenue_cents DESC, c.name ASC
    ');
    $stmt->execute(array($from, $to));
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        $totalRides += (int)$r['rides_count'];
        $totalRevenue += (int)$r['revenue_cents'];
    }
} catch (Exception $e) {
    app_log('print_summary_range error: '.$e->getMessage());
}

$title = $company;
$subtitle = 'Souhrn období: '.$from.' – '.$to;
$navItems = array(
    array('p' => 'summaries', 'href' => 'index.php?p=summaries&from='.$from.'&to='.$to, 'label' => 'Zpět'),
);

$content = '';
$content .= '<div class="grid">'.
  '<div class="kpi"><div class="k">Počet jízd</div><div class="v">'.h((string)$totalRides).'</div></div>'.
  '<div class="kpi"><div class="k">Tržby</div><div class="v">'.h(cents_to_money($totalRevenue)).' '.h($currencyDefault).'</div></div>'.
'</div>';
if ($footer !== '') {
    $content .= Separator().'<div class="text-display">'.nl2br(h($footer)).'</div>';
}

$html = Container($content);
LayoutView($title, $subtitle, $navItems, $html, flash_get());
