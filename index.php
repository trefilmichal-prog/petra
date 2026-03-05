<?php
require_once __DIR__ . '/lib/bootstrap.php';

$pdo = db();

// First user bootstrap: if no users, force install
try {
    $hasUser = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'] > 0;
} catch (Exception $e) {
    app_log('user count error: '.$e->getMessage());
    $hasUser = false;
}

$p = isset($_GET['p']) ? $_GET['p'] : '';
if (!$hasUser) { $p = 'install'; }
if ($p === '') { $p = is_logged_in() ? 'dashboard' : 'login'; }

$routes = array(
    'install' => 'install.php',
    'login' => 'login.php',
    'logout' => 'logout.php',
    'dashboard' => 'dashboard.php',
    'clients' => 'clients.php',
    'rides' => 'rides.php',
    'summaries' => 'summaries.php',
    'settings' => 'settings.php',
    'cms' => 'cms.php',
    'account' => 'account.php',
    'print_schedule' => 'print_schedule.php',
    'print_summary_day' => 'print_summary_day.php',
    'print_summary_range' => 'print_summary_range.php'
);

if (!isset($routes[$p])) { $p = is_logged_in() ? 'dashboard' : 'login'; }

require_once __DIR__ . '/pages/' . $routes[$p];
