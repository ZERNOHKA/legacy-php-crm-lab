<?php
require dirname(__FILE__) . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$status = runtime_status();
try {
    $db = database();
    $status['database'] = $db->query('SELECT VERSION()')->fetchColumn();
    $status['clients'] = (int) $db->query('SELECT COUNT(*) FROM clients')->fetchColumn();
    $status['ok'] = PHP_VERSION === '5.3.3'
        && PHP_SAPI === 'cgi-fcgi'
        && $status['ini'] === '/etc/php533/php.ini'
        && $status['timezone'] === 'UTC'
        && count($status['missing_modules']) === 0
        && strpos($status['database'], '5.5.') === 0
        && $status['clients'] > 0;
} catch (Exception $error) {
    error_log($error->getMessage());
    $status['ok'] = false;
    $status['error'] = 'Demo database is unavailable.';
}
if (!$status['ok']) {
    header('HTTP/1.1 503 Service Unavailable');
}
echo json_encode($status);
