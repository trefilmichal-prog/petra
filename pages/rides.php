<?php
require_once __DIR__ . '/../lib/bootstrap.php';

require_login();
$flash = flash_get();
$pdo = db();

$currencyDefault = settings_get('currency_default', 'CZK');

$date = isset($_GET['date']) && validate_date($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$action = isset($_GET['a']) ? $_GET['a'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

function load_clients_options($pdo) {
    $opts = array();
    $stmt = $pdo->query('SELECT id, name, default_price_cents, currency FROM clients WHERE is_active = 1 ORDER BY sort ASC, name ASC');
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $opts[] = array(
            'value' => (string)(int)$r['id'],
            'label' => $r['name'],
            'default_price_cents' => (int)$r['default_price_cents'],
            'currency' => $r['currency']
        );
    }
    return $opts;
}

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash_set('err', 'Neplatný CSRF token.');
        header('Location: index.php?p=rides&date='.urlencode($date));
        exit;
    }

    $postAction = isset($_POST['action']) ? $_POST['action'] : '';

    try {
        if ($postAction === 'save_ride') {
            $rid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $ride_date = isset($_POST['ride_date']) ? $_POST['ride_date'] : $date;
            $start = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
            $end = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
            $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
            $pickup = isset($_POST['pickup']) ? trim($_POST['pickup']) : '';
            $dropoff = isset($_POST['dropoff']) ? trim($_POST['dropoff']) : '';
            $priceStr = isset($_POST['price']) ? trim($_POST['price']) : '';
            $currency = isset($_POST['currency']) ? trim($_POST['currency']) : $currencyDefault;
            $status = isset($_POST['status']) ? trim($_POST['status']) : 'planned';
            $note = isset($_POST['note']) ? trim($_POST['note']) : '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $sort = isset($_POST['sort']) ? (int)$_POST['sort'] : 1000;

            if (!validate_date($ride_date)) {
                flash_set('err', 'Neplatné datum.');
                header('Location: index.php?p=rides&date='.urlencode($date)); exit;
            }
            if (!validate_time($start) || $start === '') {
                flash_set('err', 'Neplatný čas začátku (HH:MM).');
                header('Location: index.php?p=rides&date='.urlencode($ride_date).($rid?('&a=edit&id='.$rid):'')); exit;
            }
            if (!validate_time($end)) {
                flash_set('err', 'Neplatný čas konce (HH:MM).');
                header('Location: index.php?p=rides&date='.urlencode($ride_date).($rid?('&a=edit&id='.$rid):'')); exit;
            }
            if ($client_id <= 0) {
                flash_set('err', 'Vyberte klienta.');
                header('Location: index.php?p=rides&date='.urlencode($ride_date).($rid?('&a=edit&id='.$rid):'')); exit;
            }

            $cents = money_to_cents($priceStr);
            if ($cents === null) {
                flash_set('err', 'Cena musí být číslo (např. 250 nebo 250.50).');
                header('Location: index.php?p=rides&date='.urlencode($ride_date).($rid?('&a=edit&id='.$rid):'')); exit;
            }

            $allowed = array('planned','done','cancelled');
            if (!in_array($status, $allowed, true)) { $status = 'planned'; }

            $now = date('c');

            if ($rid > 0) {
                $stmt = $pdo->prepare('UPDATE rides SET ride_date=?, start_time=?, end_time=?, client_id=?, pickup=?, dropoff=?, price_cents=?, currency=?, status=?, note=?, is_active=?, sort=?, updated_at=? WHERE id=?');
                $stmt->execute(array($ride_date,$start,$end,$client_id,$pickup,$dropoff,$cents,$currency,$status,$note,$isActive,$sort,$now,$rid));
                flash_set('ok', 'Jízda upravena.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO rides (ride_date,start_time,end_time,client_id,pickup,dropoff,price_cents,currency,status,note,is_active,sort,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute(array($ride_date,$start,$end,$client_id,$pickup,$dropoff,$cents,$currency,$status,$note,$isActive,$sort,$now,$now));
                flash_set('ok', 'Jízda vytvořena.');
            }

            header('Location: index.php?p=rides&date='.urlencode($ride_date)); exit;
        }

        if ($postAction === 'toggle_active') {
            $rid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $stmt = $pdo->prepare('UPDATE rides SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END, updated_at=? WHERE id=?');
            $stmt->execute(array(date('c'), $rid));
            flash_set('ok', 'Stav jízdy změněn.');
            header('Location: index.php?p=rides&date='.urlencode($date)); exit;
        }

        if ($postAction === 'delete_ride') {
            $rid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $stmt = $pdo->prepare('DELETE FROM rides WHERE id = ?');
            $stmt->execute(array($rid));
            flash_set('ok', 'Jízda smazána.');
            header('Location: index.php?p=rides&date='.urlencode($date)); exit;
        }

    } catch (Exception $e) {
        app_log('rides post error: '.$e->getMessage());
        flash_set('err', 'Chyba při ukládání.');
        header('Location: index.php?p=rides&date='.urlencode($date)); exit;
    }
}

$title = settings_get('company_name', 'Jízdní řád');
$subtitle = 'Jízdní řád';
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

$intro = cms_section('rides', 'intro', '');

$content = TextDisplay($intro).Separator();

// Date selector
$content .= '<form class="print-hide" method="get" action="index.php">'.
    InputHidden('p', 'rides').
    '<div class="action-row">'.
      '<div class="field" style="min-width:220px;"><label>Datum</label><input type="date" name="date" value="'.h($date).'"></div>'.
      Button('Načíst', 'submit', '', '', 'primary', array()).
      '<a class="btn" href="index.php?p=print_schedule&date='.h($date).'" target="_blank">Tisk jízdního řádu</a>'.
      '<a class="btn" href="index.php?p=print_summary_day&date='.h($date).'" target="_blank">Tisk souhrnu dne</a>'.
      '<a class="btn" href="index.php?p=rides&date='.h($date).'&a=new">+ Nová jízda</a>'.
    '</div>'.
'</form>'.
Separator();

if ($action === 'edit' || $action === 'new') {
    $ride = array('id'=>0,'ride_date'=>$date,'start_time'=>'08:00','end_time'=>'','client_id'=>0,'pickup'=>'','dropoff'=>'','price_cents'=>0,'currency'=>$currencyDefault,'status'=>'planned','note'=>'','is_active'=>1,'sort'=>1000);
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM rides WHERE id = ?');
        $stmt->execute(array($id));
        $r = $stmt->fetch();
        if ($r) { $ride = $r; }
    }

    $clients = load_clients_options($pdo);
    $opts = array();
    foreach ($clients as $c) {
        $opts[] = array('value'=>$c['value'], 'label'=>$c['label']);
    }

    $statusOpts = array(
        array('value'=>'planned','label'=>'Plánováno'),
        array('value'=>'done','label'=>'Dokončeno'),
        array('value'=>'cancelled','label'=>'Zrušeno')
    );

    $checked = ((int)$ride['is_active']===1) ? ' checked' : '';

    $content .= '<form method="post" action="index.php?p=rides&date='.h($date).'">'.
        InputHidden('csrf_token', csrf_token()).
        InputHidden('action', 'save_ride').
        InputHidden('id', (string)(int)$ride['id']).
        '<div class="grid">'.
          '<div class="field"><label>Datum</label><input type="date" name="ride_date" value="'.h($ride['ride_date']).'" required></div>'.
          '<div class="field"><label>Klient</label>'.Select('client_id', $opts, (string)(int)$ride['client_id'], array('required'=>'required')).'</div>'.
          '<div class="field"><label>Čas začátku</label><input type="text" name="start_time" value="'.h($ride['start_time']).'" placeholder="HH:MM" required></div>'.
          '<div class="field"><label>Čas konce</label><input type="text" name="end_time" value="'.h($ride['end_time']).'" placeholder="HH:MM"></div>'.
          '<div class="field"><label>Nástup</label><input type="text" name="pickup" value="'.h($ride['pickup']).'"></div>'.
          '<div class="field"><label>Výstup</label><input type="text" name="dropoff" value="'.h($ride['dropoff']).'"></div>'.
          '<div class="field"><label>Cena</label><input type="text" name="price" value="'.h(cents_to_money((int)$ride['price_cents'])).'"></div>'.
          '<div class="field"><label>Měna</label><input type="text" name="currency" value="'.h($ride['currency']).'"></div>'.
          '<div class="field"><label>Status</label>'.Select('status', $statusOpts, (string)$ride['status'], array()).'</div>'.
          '<div class="field"><label>Řazení (sort)</label><input type="number" name="sort" value="'.h((string)(int)$ride['sort']).'"></div>'.
          '<div class="field"><label>Aktivní</label><div style="padding:10px 0;"><input type="checkbox" name="is_active" value="1"'.$checked.'> <span class="text-display" style="display:inline;">Zobrazovat</span></div></div>'.
          '<div class="field" style="grid-column:1/-1;"><label>Poznámka</label><textarea name="note">'.h($ride['note']).'</textarea></div>'.
        '</div>'.
        Separator().
        ActionRow(
            Button('Uložit', 'submit', '', '', 'primary', array()).
            '<a class="btn" href="index.php?p=rides&date='.h($date).'">Zpět</a>'
        ).
      '</form>';

    $html = Container($content);
    LayoutView($title, $subtitle, $navItems, $html, $flash);
    exit;
}

// List rides for date
$rows = array();
try {
    $stmt = $pdo->prepare('SELECT r.*, c.name AS client_name FROM rides r JOIN clients c ON c.id=r.client_id
                           WHERE r.ride_date = ? ORDER BY r.start_time ASC, r.sort ASC, r.id ASC');
    $stmt->execute(array($date));
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    app_log('rides list error: '.$e->getMessage());
}

$tbl = '<table class="table"><thead><tr>'.
        '<th>Čas</th><th>Klient</th><th>Trasa</th><th>Cena</th><th>Status</th><th>Stav</th><th class="print-hide">Akce</th>'.
       '</tr></thead><tbody>';

$total = 0;

foreach ($rows as $r) {
    $rid = (int)$r['id'];
    $time = h($r['start_time']).($r['end_time']!=='' ? ('–'.h($r['end_time'])) : '');
    $route = h($r['pickup']).($r['dropoff']!=='' ? (' → '.h($r['dropoff'])) : '');
    $price = cents_to_money((int)$r['price_cents']).' '.h($r['currency']);
    $status = $r['status'];
    if ($status === 'planned') { $statusLbl = 'Plánováno'; }
    else if ($status === 'done') { $statusLbl = 'Dokončeno'; }
    else { $statusLbl = 'Zrušeno'; }
    $activeLbl = ((int)$r['is_active']===1) ? 'Aktivní' : 'Neaktivní';

    if ((int)$r['is_active']===1 && $status !== 'cancelled') {
        $total += (int)$r['price_cents'];
    }

    $tbl .= '<tr>'.
        '<td><strong>'.$time.'</strong></td>'.
        '<td>'.h($r['client_name']).'<div class="text-display">'.nl2br(h($r['note'])).'</div></td>'.
        '<td>'.$route.'</td>'.
        '<td>'.h($price).'</td>'.
        '<td>'.h($statusLbl).'</td>'.
        '<td>'.h($activeLbl).'</td>'.
        '<td class="print-hide">'.
          '<a class="btn small" href="index.php?p=rides&date='.h($date).'&a=edit&id='.$rid.'">Upravit</a> '.
          '<form method="post" action="index.php?p=rides&date='.h($date).'" style="display:inline">'.
            InputHidden('csrf_token', csrf_token()).
            InputHidden('action', 'toggle_active').
            InputHidden('id', (string)$rid).
            Button('Aktivní/Neaktivní', 'submit', '', '', 'small', array()).
          '</form> '.
          '<form method="post" action="index.php?p=rides&date='.h($date).'" style="display:inline" onsubmit="return confirm(\'Opravdu smazat jízdu?\');">'.
            InputHidden('csrf_token', csrf_token()).
            InputHidden('action', 'delete_ride').
            InputHidden('id', (string)$rid).
            Button('Smazat', 'submit', '', '', 'danger small', array()).
          '</form>'.
        '</td>'.
    '</tr>';
}
if (count($rows) === 0) {
    $tbl .= '<tr><td colspan="7" class="text-display">Pro vybrané datum nejsou žádné jízdy.</td></tr>';
}
$tbl .= '</tbody></table>';

$content .= $tbl;
$content .= Separator();
$content .= '<div class="text-display">Celkem (bez zrušených): <strong>'.h(cents_to_money($total)).' '.h($currencyDefault).'</strong></div>';

$html = Container($content);

LayoutView($title, $subtitle, $navItems, $html, $flash);
