<?php
require dirname(__FILE__) . '/includes/bootstrap.php';
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
if (!session_start()) {
    header('HTTP/1.1 503 Service Unavailable');
    exit('Не удалось открыть демонстрационную сессию.');
}
$_SESSION['visits'] = isset($_SESSION['visits']) ? $_SESSION['visits'] + 1 : 1;
$visits = $_SESSION['visits'];
session_write_close();

$labels = array('all' => 'Все клиенты', 'active' => 'Активные', 'prospect' => 'Потенциальные', 'archived' => 'Архив');
$filter = isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : 'all';
if (!isset($labels[$filter])) {
    $filter = 'all';
}
$clients = array();
$databaseError = false;
try {
    $db = database();
    if ($filter === 'all') {
        $clients = $db->query('SELECT company, email, status FROM clients ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $query = $db->prepare('SELECT company, email, status FROM clients WHERE status = ? ORDER BY id');
        $query->execute(array($filter));
        $clients = $query->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $error) {
    error_log($error->getMessage());
    $databaseError = true;
    header('HTTP/1.1 503 Service Unavailable');
}
$runtime = runtime_status();
$runtimeOk = PHP_VERSION === '5.3.3' && $runtime['ini'] === '/etc/php533/php.ini'
    && $runtime['timezone'] === 'UTC' && count($runtime['missing_modules']) === 0;
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Публичный демостенд окружения PHP 5.3.3, Apache и MariaDB.">
    <title>Legacy CRM Lab — демонстрационный стенд</title>
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="icon" href="data:,">
</head>
<body>
<div class="layout">
    <header class="topbar">
        <a class="brand" href="/">L<span>Legacy CRM Lab</span></a>
        <span class="demo-label">Локальная демонстрация</span>
    </header>
    <main>
        <div class="intro">
            <p class="eyebrow">LEGACY INFRASTRUCTURE / PHP 5.3.3</p>
            <h1>Старый стек.<br>Воспроизводимый запуск.</h1>
            <p class="lead">Небольшой пример CRM для проверки Apache, PHP через CGI и базы данных. Все компании и адреса вымышлены.</p>
        </div>
        <section class="runtime" aria-label="Состояние окружения">
            <div><span class="caption">PHP</span><strong><?php echo escape(PHP_VERSION); ?></strong></div>
            <div><span class="caption">Обработчик</span><strong><?php echo escape(PHP_SAPI); ?></strong></div>
            <div><span class="caption">Конфигурация</span><strong class="<?php echo $runtimeOk ? 'good' : 'bad'; ?>"><?php echo $runtimeOk ? 'php.ini загружен' : 'Требует проверки'; ?></strong></div>
            <div><span class="caption">База данных</span><strong class="<?php echo $databaseError ? 'bad' : 'good'; ?>"><?php echo $databaseError ? 'Недоступна' : 'Подключена'; ?></strong></div>
        </section>
        <section class="panel" aria-labelledby="clients-title">
            <div class="panel-heading"><div><p class="eyebrow">ДЕМОНСТРАЦИОННЫЕ ДАННЫЕ</p><h2 id="clients-title">Клиенты</h2></div><span class="count"><?php echo count($clients); ?> записей</span></div>
            <nav class="filters" aria-label="Фильтр клиентов">
                <?php foreach ($labels as $key => $label): ?>
                <a href="/?status=<?php echo escape($key); ?>"<?php echo $filter === $key ? ' class="selected" aria-current="page"' : ''; ?>><?php echo escape($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if ($databaseError): ?>
            <p class="empty" role="alert">База данных недоступна. Проверьте состояние контейнера db.</p>
            <?php elseif (count($clients) === 0): ?>
            <p class="empty">В этой группе пока нет клиентов.</p>
            <?php else: ?>
            <div class="table-scroll"><table>
                <thead><tr><th scope="col">Компания</th><th scope="col">Электронная почта</th><th scope="col">Статус</th></tr></thead>
                <tbody>
                <?php foreach ($clients as $client): ?>
                <tr><td class="company"><?php echo escape($client['company']); ?></td><td><?php echo escape($client['email']); ?></td><td><span class="status <?php echo escape($client['status']); ?>"><?php echo escape($labels[$client['status']]); ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </section>
        <aside class="note"><span class="note-icon">i</span><p>Стенд использует устаревшее ПО для воспроизведения legacy-окружения. Запускайте его только локально с демонстрационными данными.</p></aside>
    </main>
    <footer><span>Apache + PHP CGI + MariaDB</span><span>Открытий в этой сессии: <strong id="session-visits"><?php echo (int) $visits; ?></strong></span></footer>
</div>
</body>
</html>
