<?php
/**
 * Служебная точка для проверки базы и накатывания схемы.
 * Доступна только с админским паролем; миграции идемпотентны.
 *
 *   GET  ?action=check    — подключение, версия сервера, список таблиц
 *   POST ?action=migrate  — создать/дополнить таблицы
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/db.php';

asmbot_require_admin();

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : 'check';

/**
 * Подбор имени базы/пользователя, когда точные значения из панели неизвестны.
 * Пароль берётся из db-config.php и наружу не передаётся; в ответ уходят
 * только имена и код ошибки.
 */
if ($action === 'probe') {
    $cfg = asmbot_db_config();
    if ($cfg === null) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db-config.php не найден.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $base = $cfg['name'];
    $names = array_values(array_unique([$base, strtolower($base), strtoupper($base)]));
    $users = array_values(array_unique([$cfg['user'], strtolower($cfg['user']), strtoupper($cfg['user'])]));
    $hosts = array_values(array_unique([$cfg['host'], 'localhost', '127.0.0.1']));

    $results = [];
    $winner = null;

    foreach ($hosts as $host) {
        foreach ($names as $name) {
            foreach ($users as $user) {
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $cfg['charset']);
                try {
                    new PDO($dsn, $user, $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $results[] = ['host' => $host, 'name' => $name, 'user' => $user, 'result' => 'OK'];
                    if ($winner === null) {
                        $winner = ['host' => $host, 'name' => $name, 'user' => $user];
                    }
                } catch (PDOException $e) {
                    $results[] = [
                        'host' => $host,
                        'name' => $name,
                        'user' => $user,
                        'result' => 'error ' . $e->getCode(),
                    ];
                }
            }
        }
    }

    echo json_encode(['ok' => $winner !== null, 'working' => $winner, 'tried' => $results], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = asmbot_db();
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'check') {
    $tables = [];
    foreach ($pdo->query('SHOW TABLES') as $row) {
        $tables[] = array_values($row)[0];
    }
    echo json_encode([
        'ok' => true,
        'serverVersion' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
        'tables' => $tables,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Удаление тестовых аккаунтов вида test-...@example.com. */
if ($action === 'cleanup-test' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // example.com зарезервирован под тесты — реальных клиентов там не бывает.
    $stmt = $pdo->prepare("DELETE FROM clients WHERE email LIKE '%@example.com'");
    $stmt->execute();
    $left = $pdo->query('SELECT COUNT(*) AS n FROM clients')->fetch();
    echo json_encode([
        'ok' => true,
        'deleted' => $stmt->rowCount(),
        'clientsLeft' => (int) $left['n'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action !== 'migrate' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Укажите action=check или POST action=migrate.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Схема. Каждая миграция — отдельный SQL, выполняется по порядку и
 * безопасна к повторному запуску.
 */
$migrations = [
    'clients' => "
        CREATE TABLE IF NOT EXISTS clients (
            id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email         VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NULL,
            name          VARCHAR(190) NOT NULL DEFAULT '',
            phone         VARCHAR(40)  NOT NULL DEFAULT '',
            company       VARCHAR(190) NOT NULL DEFAULT '',
            inn           VARCHAR(20)  NOT NULL DEFAULT '',
            discount_pct  DECIMAL(5,2) NOT NULL DEFAULT 0,
            email_verified_at DATETIME NULL,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_clients_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'client_sessions' => "
        CREATE TABLE IF NOT EXISTS client_sessions (
            token      CHAR(64) PRIMARY KEY,
            client_id  BIGINT UNSIGNED NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sessions_client (client_id),
            CONSTRAINT fk_sessions_client FOREIGN KEY (client_id)
                REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'article_comments' => "
        CREATE TABLE IF NOT EXISTS article_comments (
            id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            article    VARCHAR(190) NOT NULL,
            client_id  BIGINT UNSIGNED NULL,
            author     VARCHAR(190) NOT NULL DEFAULT '',
            body       TEXT NOT NULL,
            status     ENUM('published','hidden') NOT NULL DEFAULT 'published',
            ip         VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_comments_article (article, status, created_at),
            CONSTRAINT fk_comments_client FOREIGN KEY (client_id)
                REFERENCES clients(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'community_articles' => "
        CREATE TABLE IF NOT EXISTS community_articles (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug        VARCHAR(190) NOT NULL,
            title       VARCHAR(190) NOT NULL,
            excerpt     VARCHAR(400) NOT NULL DEFAULT '',
            body        MEDIUMTEXT NOT NULL,
            client_id   BIGINT UNSIGNED NULL,
            author      VARCHAR(190) NOT NULL DEFAULT '',
            status      ENUM('pending','published','rejected') NOT NULL DEFAULT 'pending',
            moderator_note VARCHAR(400) NOT NULL DEFAULT '',
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_community_slug (slug),
            KEY idx_community_status (status, created_at),
            CONSTRAINT fk_community_client FOREIGN KEY (client_id)
                REFERENCES clients(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'orders' => "
        CREATE TABLE IF NOT EXISTS orders (
            id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            number        VARCHAR(32) NOT NULL,
            client_id     BIGINT UNSIGNED NULL,
            status        VARCHAR(32) NOT NULL DEFAULT 'new',
            contact_name  VARCHAR(190) NOT NULL DEFAULT '',
            contact_phone VARCHAR(40)  NOT NULL DEFAULT '',
            contact_email VARCHAR(190) NOT NULL DEFAULT '',
            comment       TEXT NULL,
            discount_pct  DECIMAL(5,2) NOT NULL DEFAULT 0,
            total         DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_orders_number (number),
            KEY idx_orders_client (client_id),
            KEY idx_orders_status (status),
            CONSTRAINT fk_orders_client FOREIGN KEY (client_id)
                REFERENCES clients(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'order_items' => "
        CREATE TABLE IF NOT EXISTS order_items (
            id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id   BIGINT UNSIGNED NOT NULL,
            product_id VARCHAR(190) NOT NULL DEFAULT '',
            name       VARCHAR(255) NOT NULL,
            qty        INT UNSIGNED NOT NULL DEFAULT 1,
            price      DECIMAL(12,2) NOT NULL DEFAULT 0,
            KEY idx_items_order (order_id),
            CONSTRAINT fk_items_order FOREIGN KEY (order_id)
                REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'settings' => "
        CREATE TABLE IF NOT EXISTS settings (
            name       VARCHAR(64) PRIMARY KEY,
            value      TEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'messages' => "
        CREATE TABLE IF NOT EXISTS messages (
            id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id  BIGINT UNSIGNED NOT NULL,
            order_id   BIGINT UNSIGNED NULL,
            author     ENUM('client','manager') NOT NULL,
            body       TEXT NOT NULL,
            read_at    DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_messages_client (client_id, created_at),
            CONSTRAINT fk_messages_client FOREIGN KEY (client_id)
                REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
];

/**
 * Дополнения к уже существующим таблицам. MySQL умеет ADD COLUMN IF NOT EXISTS
 * не во всех версиях, поэтому проверяем наличие колонки сами.
 */
$columnPatches = [
    ['table' => 'clients', 'column' => 'verify_token', 'sql' => 'ALTER TABLE clients ADD COLUMN verify_token CHAR(64) NULL'],
    ['table' => 'clients', 'column' => 'verify_sent_at', 'sql' => 'ALTER TABLE clients ADD COLUMN verify_sent_at DATETIME NULL'],
    // Платёжные реквизиты покупателя — попадают в счёт при подтверждении заказа.
    ['table' => 'clients', 'column' => 'kpp', 'sql' => 'ALTER TABLE clients ADD COLUMN kpp VARCHAR(20) NOT NULL DEFAULT ""'],
    ['table' => 'clients', 'column' => 'legal_address', 'sql' => 'ALTER TABLE clients ADD COLUMN legal_address VARCHAR(400) NOT NULL DEFAULT ""'],
    ['table' => 'orders', 'column' => 'buyer_name', 'sql' => 'ALTER TABLE orders ADD COLUMN buyer_name VARCHAR(255) NOT NULL DEFAULT ""'],
    ['table' => 'orders', 'column' => 'buyer_inn', 'sql' => 'ALTER TABLE orders ADD COLUMN buyer_inn VARCHAR(20) NOT NULL DEFAULT ""'],
    ['table' => 'orders', 'column' => 'buyer_kpp', 'sql' => 'ALTER TABLE orders ADD COLUMN buyer_kpp VARCHAR(20) NOT NULL DEFAULT ""'],
    ['table' => 'orders', 'column' => 'buyer_address', 'sql' => 'ALTER TABLE orders ADD COLUMN buyer_address VARCHAR(400) NOT NULL DEFAULT ""'],
    ['table' => 'orders', 'column' => 'exported_at', 'sql' => 'ALTER TABLE orders ADD COLUMN exported_at DATETIME NULL'],
];

$applied = [];
$failed = [];

foreach ($migrations as $name => $sql) {
    try {
        $pdo->exec($sql);
        $applied[] = $name;
    } catch (PDOException $e) {
        $failed[] = ['table' => $name, 'error' => $e->getMessage()];
    }
}

foreach ($columnPatches as $patch) {
    try {
        $check = $pdo->prepare(
            'SELECT COUNT(*) AS n FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $check->execute([$patch['table'], $patch['column']]);
        $exists = (int) $check->fetch()['n'] > 0;

        if (!$exists) {
            $pdo->exec($patch['sql']);
            $applied[] = $patch['table'] . '.' . $patch['column'];
        }
    } catch (PDOException $e) {
        $failed[] = ['table' => $patch['table'] . '.' . $patch['column'], 'error' => $e->getMessage()];
    }
}

$tables = [];
foreach ($pdo->query('SHOW TABLES') as $row) {
    $tables[] = array_values($row)[0];
}

echo json_encode([
    'ok' => count($failed) === 0,
    'applied' => $applied,
    'failed' => $failed,
    'tables' => $tables,
], JSON_UNESCAPED_UNICODE);
