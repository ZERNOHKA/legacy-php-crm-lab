<?php
function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function database()
{
    $host = getenv('DB_HOST');
    $name = getenv('DB_NAME');
    $db = new PDO('mysql:host=' . $host . ';dbname=' . $name, getenv('DB_USER'), getenv('DB_PASSWORD'));
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $db->exec('SET NAMES utf8');
    return $db;
}

function required_modules()
{
    $modules = array('bcmath', 'bz2', 'calendar', 'Core', 'ctype', 'curl', 'date', 'dom',
        'ereg', 'fileinfo', 'filter', 'hash', 'iconv', 'intl', 'json', 'libxml',
        'mbstring', 'mcrypt', 'mhash', 'mysql', 'mysqli', 'mysqlnd', 'openssl',
        'pcntl', 'pcre', 'PDO', 'pdo_mysql', 'Phar', 'posix',
        'Reflection', 'session', 'shmop', 'SimpleXML', 'sockets', 'SPL', 'standard',
        'sysvmsg', 'sysvsem', 'sysvshm', 'tokenizer', 'xml', 'xmlreader',
        'xmlwriter', 'xsl', 'zip', 'zlib');
    if (PHP_SAPI === 'cli') {
        $modules[] = 'readline';
    }
    return $modules;
}

function runtime_status()
{
    return array(
        'php' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'ini' => php_ini_loaded_file(),
        'timezone' => date_default_timezone_get(),
        'missing_modules' => array_values(array_diff(required_modules(), get_loaded_extensions()))
    );
}
