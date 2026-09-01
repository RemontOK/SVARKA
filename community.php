<?php
/**
 * Обсуждения и статьи читателей.
 *
 *   GET  ?action=comments&article=<адрес>   — комментарии к статье
 *   POST ?action=comment                    — оставить комментарий (нужен вход)
 *   GET  ?action=articles                   — опубликованные статьи читателей
 *   GET  ?action=article&slug=<адрес>       — одна статья читателя
 *   POST ?action=submit                     — прислать свою статью (нужен вход)
 *   GET  ?action=mine                       — свои присланные статьи
 *   GET  ?action=likes&subject=<что>        — сколько лайков и стоит ли мой
 *   POST ?action=like {subject}             — поставить или снять лайк
 *   GET  ?action=stats                      — счётчики по всем материалам
 *
 * Комментарий появляется сразу — иначе обсуждения не заводятся; спам держат
 * обязательный вход и ограничение частоты. Статья, наоборот, идёт через
 * модерацию: она попадает в общий каталог материалов и говорит от лица сайта.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/client-auth-lib.php';

const ASMBOT_COMMENT_MIN = 3;
const ASMBOT_COMMENT_MAX = 4000;
const ASMBOT_COMMENTS_PER_HOUR = 20;
const ASMBOT_ARTICLES_PER_DAY = 5;

function asmbot_fail($code, $message)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function asmbot_ok(array $data = [])
{
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

function asmbot_input()
{
    $raw = file_get_contents('php://input');
    if (strlen($raw) > 200000) {
        asmbot_fail(413, 'Слишком длинный текст.');
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Чистая строка без управляющих символов. */
function asmbot_clean($value, $limit)
{
    $text = is_array($value) ? implode(' ', $value) : (string) $value;
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text);
    return mb_substr(trim($text), 0, $limit, 'UTF-8');
}

/** Адрес статьи: латиница, цифры и дефис. */
function asmbot_slugify($value)
{
    static $translit = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];
    $text = mb_strtolower((string) $value, 'UTF-8');
    $text = strtr($text, $translit);
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
    return trim((string) $text, '-');
}

/** Имя для подписи: как представился клиент, иначе почта до собаки. */
function asmbot_author_name(array $client)
{
    $name = trim((string) ($client['name'] ?? ''));
    if ($name !== '') {
        return mb_substr($name, 0, 190, 'UTF-8');
    }
    $email = (string) ($client['email'] ?? '');
    $at = strpos($email, '@');
    return $at > 0 ? substr($email, 0, $at) : 'Гость';
}

/**
 * Кто голосует.
 *
 * Вошедший — по своему номеру, гость — по адресу. Требовать вход ради лайка
 * значит не получить ни одного: это самое дешёвое действие на странице, и
 * порог перед ним должен быть нулевым. Адрес храним не как есть, а отпечатком:
 * для «один голос в одни руки» этого хватает, а сам адрес нам не нужен.
 */
function asmbot_voter(?array $client)
{
    if ($client) {
        return 'c:' . (int) $client['id'];
    }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    return 'ip:' . substr(hash('sha256', $ip . '|asmbot-likes'), 0, 40);
}

/** Что лайкают: статью или отдельную реплику. */
function asmbot_subject($value)
{
    $text = preg_replace('/[^a-zA-Z0-9:_\-]/', '', (string) $value);
    return substr((string) $text, 0, 190);
}

function asmbot_rate_ok(PDO $pdo, $table, $clientId, $limit, $interval)
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE client_id = ? AND created_at > (NOW() - INTERVAL {$interval})"
    );
    $stmt->execute([$clientId]);
    return (int) $stmt->fetchColumn() < $limit;
}

// Любая неожиданность в базе — это всё равно ответ JSON, а не страница
// ошибки: вызывающая сторона разбирает только JSON.
set_exception_handler(function () {
    asmbot_fail(503, 'База временно недоступна.');
});

try {
    $pdo = asmbot_db();
} catch (Throwable $e) {
    // Голого 500 быть не должно: страница ждёт JSON и молча прячет обсуждение.
    asmbot_fail(503, 'База недоступна.');
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';
$client = asmbot_current_client($pdo);

// ---------- Комментарии ------------------------------------------------------

if ($action === 'comments') {
    $article = asmbot_clean($_GET['article'] ?? '', 190);
    if ($article === '') {
        asmbot_fail(400, 'Не указана статья.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, author, body, created_at
         FROM article_comments
         WHERE article = ? AND status = "published"
         ORDER BY created_at ASC
         LIMIT 500'
    );
    $stmt->execute([$article]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Лайки к репликам — тем же запросом, иначе страница уходит в сотню
    // мелких обращений на каждое обсуждение.
    $voter = asmbot_voter($client);
    $likes = $pdo->prepare(
        'SELECT subject, COUNT(*) AS n, MAX(voter = ?) AS mine
         FROM content_likes WHERE subject LIKE "comment:%" GROUP BY subject'
    );
    $likes->execute([$voter]);
    $bySubject = [];
    foreach ($likes as $row) {
        $bySubject[$row['subject']] = ['n' => (int) $row['n'], 'mine' => (bool) (int) $row['mine']];
    }

    foreach ($rows as &$row) {
        $key = 'comment:' . $row['id'];
        $row['likes'] = $bySubject[$key]['n'] ?? 0;
        $row['liked'] = $bySubject[$key]['mine'] ?? false;
    }
    unset($row);

    asmbot_ok(['comments' => $rows]);
}

if ($action === 'comment') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        asmbot_fail(405, 'Только POST.');
    }
    if (!$client) {
        asmbot_fail(401, 'Чтобы писать, войдите в кабинет.');
    }

    $data = asmbot_input();
    $article = asmbot_clean($data['article'] ?? '', 190);
    $body = asmbot_clean($data['body'] ?? '', ASMBOT_COMMENT_MAX);

    if ($article === '') {
        asmbot_fail(400, 'Не указана статья.');
    }
    if (mb_strlen($body, 'UTF-8') < ASMBOT_COMMENT_MIN) {
        asmbot_fail(422, 'Слишком короткий комментарий.');
    }
    if (!asmbot_rate_ok($pdo, 'article_comments', $client['id'], ASMBOT_COMMENTS_PER_HOUR, '1 HOUR')) {
        asmbot_fail(429, 'Слишком много сообщений подряд. Продолжите через час.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO article_comments (article, client_id, author, body, ip) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $article,
        $client['id'],
        asmbot_author_name($client),
        $body,
        isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '',
    ]);

    asmbot_ok([
        'comment' => [
            'id' => (int) $pdo->lastInsertId(),
            'author' => asmbot_author_name($client),
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ]);
}

// ---------- Лайки ------------------------------------------------------------

if ($action === 'likes') {
    $subject = asmbot_subject($_GET['subject'] ?? '');
    if ($subject === '') {
        asmbot_fail(400, 'Не указано, что считать.');
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM content_likes WHERE subject = ?');
    $stmt->execute([$subject]);
    $count = (int) $stmt->fetchColumn();

    $mine = $pdo->prepare('SELECT COUNT(*) FROM content_likes WHERE subject = ? AND voter = ?');
    $mine->execute([$subject, asmbot_voter($client)]);

    asmbot_ok(['count' => $count, 'liked' => (bool) (int) $mine->fetchColumn()]);
}

if ($action === 'like') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        asmbot_fail(405, 'Только POST.');
    }

    $data = asmbot_input();
    $subject = asmbot_subject($data['subject'] ?? '');
    if ($subject === '') {
        asmbot_fail(400, 'Не указано, что лайкать.');
    }

    $voter = asmbot_voter($client);

    // Повторное нажатие снимает лайк: кнопка одна и работает в обе стороны.
    $drop = $pdo->prepare('DELETE FROM content_likes WHERE subject = ? AND voter = ?');
    $drop->execute([$subject, $voter]);
    $liked = false;

    if (!$drop->rowCount()) {
        $add = $pdo->prepare('INSERT IGNORE INTO content_likes (subject, voter, client_id) VALUES (?, ?, ?)');
        $add->execute([$subject, $voter, $client ? $client['id'] : null]);
        $liked = true;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM content_likes WHERE subject = ?');
    $stmt->execute([$subject]);

    asmbot_ok(['count' => (int) $stmt->fetchColumn(), 'liked' => $liked]);
}

if ($action === 'stats') {
    // Список материалов показывает счётчики у каждой строки — одним запросом,
    // а не двадцатью.
    $likes = [];
    foreach ($pdo->query('SELECT subject, COUNT(*) AS n FROM content_likes GROUP BY subject') as $row) {
        $likes[$row['subject']] = (int) $row['n'];
    }

    $comments = [];
    $sql = 'SELECT article, COUNT(*) AS n FROM article_comments WHERE status = "published" GROUP BY article';
    foreach ($pdo->query($sql) as $row) {
        $comments[$row['article']] = (int) $row['n'];
    }

    // Пустой массив PHP кодирует как [], а страница ждёт словарь.
    asmbot_ok(['likes' => (object) $likes, 'comments' => (object) $comments]);
}

// ---------- Статьи читателей -------------------------------------------------

if ($action === 'articles') {
    $stmt = $pdo->query(
        'SELECT slug, title, excerpt, author, created_at
         FROM community_articles
         WHERE status = "published"
         ORDER BY created_at DESC
         LIMIT 200'
    );
    asmbot_ok(['articles' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'article') {
    $slug = asmbot_clean($_GET['slug'] ?? '', 190);
    $stmt = $pdo->prepare(
        'SELECT slug, title, excerpt, body, author, created_at
         FROM community_articles
         WHERE slug = ? AND status = "published"'
    );
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        asmbot_fail(404, 'Статья не найдена.');
    }
    asmbot_ok(['article' => $row]);
}

if ($action === 'mine') {
    if (!$client) {
        asmbot_fail(401, 'Нужен вход.');
    }
    $stmt = $pdo->prepare(
        'SELECT slug, title, status, moderator_note, created_at
         FROM community_articles WHERE client_id = ? ORDER BY created_at DESC LIMIT 100'
    );
    $stmt->execute([$client['id']]);
    asmbot_ok(['articles' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'submit') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        asmbot_fail(405, 'Только POST.');
    }
    if (!$client) {
        asmbot_fail(401, 'Чтобы писать статьи, войдите в кабинет.');
    }

    $data = asmbot_input();
    $title = asmbot_clean($data['title'] ?? '', 190);
    $excerpt = asmbot_clean($data['excerpt'] ?? '', 400);
    $body = asmbot_clean($data['body'] ?? '', 60000);

    if (mb_strlen($title, 'UTF-8') < 8) {
        asmbot_fail(422, 'Заголовок слишком короткий.');
    }
    if (mb_strlen($body, 'UTF-8') < 200) {
        asmbot_fail(422, 'Текст слишком короткий — нужно хотя бы 200 символов.');
    }
    if (!asmbot_rate_ok($pdo, 'community_articles', $client['id'], ASMBOT_ARTICLES_PER_DAY, '1 DAY')) {
        asmbot_fail(429, 'На сегодня хватит. Продолжите завтра.');
    }

    // Адрес должен быть уникальным: к повторяющемуся заголовку добавляем номер.
    $base = asmbot_slugify($title);
    if ($base === '') {
        $base = 'statya';
    }
    $slug = $base;
    $check = $pdo->prepare('SELECT COUNT(*) FROM community_articles WHERE slug = ?');
    for ($n = 2; $n < 200; $n += 1) {
        $check->execute([$slug]);
        if (!(int) $check->fetchColumn()) {
            break;
        }
        $slug = $base . '-' . $n;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO community_articles (slug, title, excerpt, body, client_id, author)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$slug, $title, $excerpt, $body, $client['id'], asmbot_author_name($client)]);

    asmbot_ok(['slug' => $slug, 'status' => 'pending']);
}

asmbot_fail(400, 'Неизвестное действие.');
