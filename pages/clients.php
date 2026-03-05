<?php
require_once __DIR__ . '/../lib/bootstrap.php';

require_login();
$flash = flash_get();
$pdo = db();

$currencyDefault = settings_get('currency_default', 'CZK');

$action = isset($_GET['a']) ? $_GET['a'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash_set('err', 'Neplatný CSRF token.');
        header('Location: index.php?p=clients');
        exit;
    }

    $postAction = isset($_POST['action']) ? $_POST['action'] : '';

    try {
        if ($postAction === 'save_client') {
            $cid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $address = isset($_POST['address']) ? trim($_POST['address']) : '';
            $note = isset($_POST['note']) ? trim($_POST['note']) : '';
            $priceStr = isset($_POST['default_price']) ? trim($_POST['default_price']) : '';
            $currency = isset($_POST['currency']) ? trim($_POST['currency']) : $currencyDefault;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $sort = isset($_POST['sort']) ? (int)$_POST['sort'] : 1000;

            if ($name === '') {
                flash_set('err', 'Jméno klienta je povinné.');
                header('Location: index.php?p=clients'.($cid?('&a=edit&id='.$cid):'')); exit;
            }

            $cents = money_to_cents($priceStr);
            if ($cents === null) {
                flash_set('err', 'Výchozí cena musí být číslo (např. 250 nebo 250.50).');
                header('Location: index.php?p=clients'.($cid?('&a=edit&id='.$cid):'')); exit;
            }

            $now = date('c');

            if ($cid > 0) {
                $stmt = $pdo->prepare('UPDATE clients SET name=?, phone=?, email=?, address=?, note=?, default_price_cents=?, currency=?, is_active=?, sort=?, updated_at=? WHERE id=?');
                $stmt->execute(array($name,$phone,$email,$address,$note,$cents,$currency,$isActive,$sort,$now,$cid));
                flash_set('ok', 'Klient upraven.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO clients (name, phone, email, address, note, default_price_cents, currency, is_active, sort, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute(array($name,$phone,$email,$address,$note,$cents,$currency,$isActive,$sort,$now,$now));
                flash_set('ok', 'Klient vytvořen.');
            }

            header('Location: index.php?p=clients'); exit;
        }

        if ($postAction === 'toggle_active') {
            $cid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $stmt = $pdo->prepare('UPDATE clients SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END, updated_at=? WHERE id=?');
            $stmt->execute(array(date('c'), $cid));
            flash_set('ok', 'Stav klienta změněn.');
            header('Location: index.php?p=clients'); exit;
        }

        if ($postAction === 'delete_client') {
            $cid = isset($_POST['id']) ? (int)$_POST['id'] : 0;

            // prevent deletion if rides exist
            $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM rides WHERE client_id = ?');
            $stmt->execute(array($cid));
            $cnt = (int)$stmt->fetch()['c'];

            if ($cnt > 0) {
                flash_set('err', 'Klienta nelze smazat: existují navázané jízdy. Použijte deaktivaci.');
                header('Location: index.php?p=clients'); exit;
            }

            $stmt = $pdo->prepare('DELETE FROM clients WHERE id = ?');
            $stmt->execute(array($cid));
            flash_set('ok', 'Klient smazán.');
            header('Location: index.php?p=clients'); exit;
        }

        if ($postAction === 'move_sort') {
            $cid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $dir = isset($_POST['dir']) ? $_POST['dir'] : 'up';

            $stmt = $pdo->prepare('SELECT id, sort FROM clients WHERE id = ?');
            $stmt->execute(array($cid));
            $cur = $stmt->fetch();
            if (!$cur) { throw new Exception('Not found'); }

            $curSort = (int)$cur['sort'];
            if ($dir === 'up') {
                $stmt = $pdo->prepare('SELECT id, sort FROM clients WHERE sort < ? ORDER BY sort DESC LIMIT 1');
                $stmt->execute(array($curSort));
            } else {
                $stmt = $pdo->prepare('SELECT id, sort FROM clients WHERE sort > ? ORDER BY sort ASC LIMIT 1');
                $stmt->execute(array($curSort));
            }
            $other = $stmt->fetch();

            if ($other) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('UPDATE clients SET sort = ? WHERE id = ?');
                $stmt->execute(array((int)$other['sort'], $cid));
                $stmt->execute(array($curSort, (int)$other['id']));
                $pdo->commit();
            }

            header('Location: index.php?p=clients'); exit;
        }

    } catch (Exception $e) {
        app_log('clients post error: '.$e->getMessage());
        flash_set('err', 'Chyba při ukládání.');
        header('Location: index.php?p=clients'); exit;
    }
}

$title = settings_get('company_name', 'Jízdní řád');
$subtitle = 'Klienti';
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

$intro = cms_section('clients', 'intro', '');

$content = TextDisplay($intro).Separator();

// Edit form
if ($action === 'edit' || $action === 'new') {
    $client = array('id'=>0,'name'=>'','phone'=>'','email'=>'','address'=>'','note'=>'','default_price_cents'=>0,'currency'=>$currencyDefault,'is_active'=>1,'sort'=>1000);
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
        $stmt->execute(array($id));
        $r = $stmt->fetch();
        if ($r) { $client = $r; }
    }

    $checked = ((int)$client['is_active']===1) ? ' checked' : '';
    $content .= '<form method="post" action="index.php?p=clients">'.
        InputHidden('csrf_token', csrf_token()).
        InputHidden('action', 'save_client').
        InputHidden('id', (string)(int)$client['id']).
        '<div class="grid">'.
          '<div class="field"><label>Jméno</label><input type="text" name="name" value="'.h($client['name']).'" required></div>'.
          '<div class="field"><label>Telefon</label><input type="text" name="phone" value="'.h($client['phone']).'"></div>'.
          '<div class="field"><label>E-mail</label><input type="text" name="email" value="'.h($client['email']).'"></div>'.
          '<div class="field"><label>Adresa</label><input type="text" name="address" value="'.h($client['address']).'"></div>'.
          '<div class="field"><label>Výchozí cena</label><input type="text" name="default_price" value="'.h(cents_to_money((int)$client['default_price_cents'])).'"></div>'.
          '<div class="field"><label>Měna</label><input type="text" name="currency" value="'.h($client['currency']).'"></div>'.
          '<div class="field"><label>Řazení (sort)</label><input type="number" name="sort" value="'.h((string)(int)$client['sort']).'"></div>'.
          '<div class="field"><label>Aktivní</label><div style="padding:10px 0;"><input type="checkbox" name="is_active" value="1"'.$checked.'> <span class="text-display" style="display:inline;">Zobrazovat</span></div></div>'.
          '<div class="field" style="grid-column:1/-1;"><label>Poznámka</label><textarea name="note">'.h($client['note']).'</textarea></div>'.
        '</div>'.
        Separator().
        ActionRow(
            Button('Uložit', 'submit', '', '', 'primary', array()).
            '<a class="btn" href="index.php?p=clients">Zpět</a>'
        ).
      '</form>';

    $html = Container($content);
    LayoutView($title, $subtitle, $navItems, $html, $flash);
    exit;
}

// List clients
$rows = array();
try {
    $rows = $pdo->query('SELECT * FROM clients ORDER BY sort ASC, name ASC')->fetchAll();
} catch (Exception $e) {
    app_log('clients list error: '.$e->getMessage());
}

$tbl = '<div class="print-hide">'.ActionRow('<a class="btn primary" href="index.php?p=clients&a=new">+ Nový klient</a>').'</div>'.Separator();

$tbl .= '<table class="table"><thead><tr>'.
        '<th>Řazení</th><th>Klient</th><th>Kontakt</th><th>Adresa</th><th>Stav</th><th>Akce</th>'.
        '</tr></thead><tbody>';

foreach ($rows as $r) {
    $cid = (int)$r['id'];
    $status = ((int)$r['is_active']===1) ? 'Aktivní' : 'Neaktivní';
    $tbl .= '<tr>'.
        '<td>'.h((string)(int)$r['sort']).'<div style="margin-top:6px;">'.
            '<form method="post" action="index.php?p=clients" style="display:inline">'.
              InputHidden('csrf_token', csrf_token()).
              InputHidden('action', 'move_sort').
              InputHidden('id', (string)$cid).
              InputHidden('dir', 'up').
              Button('↑', 'submit', '', '', 'small', array('title'=>'Posunout nahoru')).
            '</form> '.
            '<form method="post" action="index.php?p=clients" style="display:inline">'.
              InputHidden('csrf_token', csrf_token()).
              InputHidden('action', 'move_sort').
              InputHidden('id', (string)$cid).
              InputHidden('dir', 'down').
              Button('↓', 'submit', '', '', 'small', array('title'=>'Posunout dolů')).
            '</form>'.
        '</div></td>'.
        '<td><strong>'.h($r['name']).'</strong><div class="text-display">'.nl2br(h($r['note'])).'</div></td>'.
        '<td>'.h($r['phone']).'<br>'.h($r['email']).'</td>'.
        '<td>'.h($r['address']).'</td>'.
        '<td>'.h($status).'</td>'.
        '<td class="print-hide">'.
            '<a class="btn small" href="index.php?p=clients&a=edit&id='.$cid.'">Upravit</a> '.
            '<form method="post" action="index.php?p=clients" style="display:inline">'.
              InputHidden('csrf_token', csrf_token()).
              InputHidden('action', 'toggle_active').
              InputHidden('id', (string)$cid).
              Button('Aktivní/Neaktivní', 'submit', '', '', 'small', array()).
            '</form> '.
            '<form method="post" action="index.php?p=clients" style="display:inline" onsubmit="return confirm(\'Opravdu smazat klienta?\');">'.
              InputHidden('csrf_token', csrf_token()).
              InputHidden('action', 'delete_client').
              InputHidden('id', (string)$cid).
              Button('Smazat', 'submit', '', '', 'danger small', array()).
            '</form>'.
        '</td>'.
    '</tr>';
}
$tbl .= '</tbody></table>';

$html = Container($content.$tbl);

LayoutView($title, $subtitle, $navItems, $html, $flash);
