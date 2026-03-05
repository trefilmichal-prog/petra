<?php
require_once __DIR__ . '/../lib/bootstrap.php';

require_login();
$flash = flash_get();
$pdo = db();

$pageSlug = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$action = isset($_GET['a']) ? $_GET['a'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash_set('err', 'Neplatný CSRF token.');
        header('Location: index.php?p=cms&page='.urlencode($pageSlug)); exit;
    }

    $postAction = isset($_POST['action']) ? $_POST['action'] : '';
    try {
        if ($postAction === 'save_section') {
            $sid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $label = isset($_POST['label']) ? trim($_POST['label']) : '';
            $content = isset($_POST['content_text']) ? trim($_POST['content_text']) : '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $sort = isset($_POST['sort']) ? (int)$_POST['sort'] : 1000;

            if ($label === '') {
                flash_set('err', 'Label je povinný.');
                header('Location: index.php?p=cms&page='.urlencode($pageSlug)); exit;
            }

            $stmt = $pdo->prepare('UPDATE cms_sections SET label=?, content_text=?, is_active=?, sort=?, updated_at=? WHERE id=?');
            $stmt->execute(array($label, $content, $isActive, $sort, date('c'), $sid));
            flash_set('ok', 'Obsah uložen.');
            header('Location: index.php?p=cms&page='.urlencode($pageSlug)); exit;
        }

        if ($postAction === 'toggle_section') {
            $sid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $stmt = $pdo->prepare('UPDATE cms_sections SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END, updated_at=? WHERE id=?');
            $stmt->execute(array(date('c'), $sid));
            flash_set('ok', 'Stav sekce změněn.');
            header('Location: index.php?p=cms&page='.urlencode($pageSlug)); exit;
        }

    } catch (Exception $e) {
        app_log('cms post error: '.$e->getMessage());
        flash_set('err', 'Chyba při ukládání.');
        header('Location: index.php?p=cms&page='.urlencode($pageSlug)); exit;
    }
}

$title = settings_get('company_name', 'Jízdní řád');
$subtitle = 'Editor obsahu';
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

$intro = cms_section('cms', 'intro', '');

$content = TextDisplay($intro).Separator();

$pages = array();
try {
    $pages = $pdo->query('SELECT slug, title FROM cms_pages WHERE is_active=1 ORDER BY sort ASC, title ASC')->fetchAll();
} catch (Exception $e) {
    app_log('cms pages error: '.$e->getMessage());
}

$options = '';
foreach ($pages as $p) {
    $sel = ($p['slug'] === $pageSlug) ? ' selected' : '';
    $options .= '<option value="'.h($p['slug']).'"'.$sel.'>'.h($p['title']).'</option>';
}

$content .= '<form method="get" action="index.php" class="print-hide">'.
    InputHidden('p','cms').
    '<div class="action-row">'.
      '<div class="field" style="min-width:260px;"><label>Stránka</label><select name="page">'.$options.'</select></div>'.
      Button('Načíst', 'submit', '', '', 'primary', array()).
    '</div>'.
  '</form>'.
  Separator();

$sections = array();
try {
    $stmt = $pdo->prepare('SELECT s.*, p.title AS page_title FROM cms_sections s JOIN cms_pages p ON p.id=s.page_id WHERE p.slug=? ORDER BY s.sort ASC, s.id ASC');
    $stmt->execute(array($pageSlug));
    $sections = $stmt->fetchAll();
} catch (Exception $e) {
    app_log('cms sections error: '.$e->getMessage());
}

if (count($sections) === 0) {
    $content .= TextDisplay('Pro tuto stránku nejsou žádné sekce.');
} else {
    foreach ($sections as $s) {
        $checked = ((int)$s['is_active']===1) ? ' checked' : '';
        $content .= '<div class="container" style="margin-bottom:14px;">'.
            '<div class="lv-title" style="font-size:16px;">'.h($s['label']).'</div>'.
            '<div class="text-display">Key: <strong>'.h($s['section_key']).'</strong> | Sort: <strong>'.h((string)(int)$s['sort']).'</strong> | Stav: <strong>'.(((int)$s['is_active']===1)?'Aktivní':'Neaktivní').'</strong></div>'.
            Separator().
            '<form method="post" action="index.php?p=cms&page='.h($pageSlug).'">'.
              InputHidden('csrf_token', csrf_token()).
              InputHidden('action', 'save_section').
              InputHidden('id', (string)(int)$s['id']).
              '<div class="grid">'.
                '<div class="field"><label>Label</label><input type="text" name="label" value="'.h($s['label']).'"></div>'.
                '<div class="field"><label>Sort</label><input type="number" name="sort" value="'.h((string)(int)$s['sort']).'"></div>'.
                '<div class="field"><label>Aktivní</label><div style="padding:10px 0;"><input type="checkbox" name="is_active" value="1"'.$checked.'> <span class="text-display" style="display:inline;">Zobrazovat</span></div></div>'.
                '<div class="field" style="grid-column:1/-1;"><label>Obsah (text)</label><textarea name="content_text">'.h($s['content_text']).'</textarea></div>'.
              '</div>'.
              Separator().
              ActionRow(
                Button('Uložit', 'submit', '', '', 'primary', array())
              ).
            '</form>'.
            '</div>';
    }
}

$html = Container($content);

LayoutView($title, $subtitle, $navItems, $html, $flash);
