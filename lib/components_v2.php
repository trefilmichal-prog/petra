<?php
/**
 * Components V2 panel (LayoutView + Container + TextDisplay + Separators + ActionRow/Select)
 * No external dependencies. PHP 5.6 compatible.
 */

function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function LayoutView($title, $subtitle, $navItems, $contentHtml, $flash) {
    $active = isset($_GET['p']) ? $_GET['p'] : '';
    $navHtml = '<div class="nav">';
    foreach ($navItems as $item) {
        $cls = ($active === $item['p']) ? 'active' : '';
        $navHtml .= '<a class="'.$cls.'" href="'.h($item['href']).'">'.h($item['label']).'</a>';
    }
    $navHtml .= '</div>';

    $flashHtml = '';
    if ($flash && isset($flash['type']) && isset($flash['msg'])) {
        $flashHtml = '<div class="flash '.h($flash['type']).'">'.h($flash['msg']).'</div>';
    }

    echo '<!doctype html><html lang="cs"><head>'.
         '<meta charset="utf-8">'.
         '<meta name="viewport" content="width=device-width, initial-scale=1">'.
         '<title>'.h($title).'</title>'.
         '<link rel="stylesheet" href="assets/style.css">'.
         '</head><body>'.
         '<div class="lv-wrap">'.
         '<div class="lv-top">'.
           '<div>'.
             '<div class="lv-title">'.h($title).'</div>'.
             ($subtitle ? '<div class="lv-sub">'.h($subtitle).'</div>' : '').
           '</div>'.
           $navHtml.
         '</div>'.
         $flashHtml.
         $contentHtml.
         '</div>'.
         '</body></html>';
}

function Container($html) {
    return '<div class="container">'.$html.'</div>';
}

function TextDisplay($text) {
    // Text content comes from DB; keep safe output.
    return '<div class="text-display">'.nl2br(h($text)).'</div>';
}

function Separator() {
    return '<div class="separator"></div>';
}

function ActionRow($html) {
    return '<div class="action-row">'.$html.'</div>';
}

function Select($name, $options, $selected, $attrs) {
    $a = '';
    foreach ($attrs as $k => $v) {
        $a .= ' '.h($k).'="'.h($v).'"';
    }
    $out = '<select name="'.h($name).'"'.$a.'>';
    foreach ($options as $opt) {
        $sel = ($opt['value'] === $selected) ? ' selected' : '';
        $out .= '<option value="'.h($opt['value']).'"'.$sel.'>'.h($opt['label']).'</option>';
    }
    $out .= '</select>';
    return $out;
}

function InputHidden($name, $value) {
    return '<input type="hidden" name="'.h($name).'" value="'.h($value).'">';
}

function Button($label, $type, $name, $value, $class, $attrs) {
    $a = '';
    foreach ($attrs as $k => $v) {
        $a .= ' '.h($k).'="'.h($v).'"';
    }
    $nv = '';
    if ($name !== '') { $nv .= ' name="'.h($name).'"'; }
    if ($value !== '') { $nv .= ' value="'.h($value).'"'; }
    return '<button type="'.h($type).'"'.$nv.' class="btn '.h($class).'"'.$a.'>'.h($label).'</button>';
}
