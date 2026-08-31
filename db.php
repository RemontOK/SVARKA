<?php
/**
 * Подключение к MySQL.
 *
 * Доступы лежат в db-config.php — он закрыт от веба в .htaccess и не входит
 * в деплой, поэтому обновление сайта его не перезапишет.
 * Образец: db-config.example.php
 */

function asmbot_db_config()
{
    $file = __DIR__ . '/db-config.php';
    if (!is_file($file)) {
        return null;
    }
    $config = include $file;
    if (!is_array($config) || empty($config['name']) || !isset($config['user'])) {
        return null;
    }
    return [
        'host' => isset($config['host']) && $config['host'] !== '' ? $config['host'] : 'localhost',
        'name' => (string) $config['name'],
        'user' => (string) $config['user'],
        'password' => isset($config['password']) ? (string) $config['password'] : '',
        'charset' => isset($config['charset']) && $config['charset'] !== '' ? $config['charset'] : 'utf8mb4',
    ];
}

/**
 * Возвращает PDO или бросает RuntimeException с безопасным текстом
 * (без логина и пароля в сообщении).
 */
function asmbot_db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = asmbot_db_config();
    if ($cfg === null) {
        throw new RuntimeException('Файл db-config.php не найден или заполнен неверно.');
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['name'], $cfg['charset']);

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        // Текст исключения PDO может содержать имя пользователя — наружу не отдаём.
        throw new RuntimeException('Не удалось подключиться к базе: ' . $e->getCode());
    }

    return $pdo;
}
