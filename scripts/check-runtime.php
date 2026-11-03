<?php
require '/var/www/html/includes/bootstrap.php';
$status = runtime_status();
$status['ok'] = PHP_VERSION === '5.3.3'
    && $status['ini'] === '/etc/php533/php.ini'
    && $status['timezone'] === 'UTC'
    && count($status['missing_modules']) === 0;
echo json_encode($status) . "\n";
exit($status['ok'] ? 0 : 1);
