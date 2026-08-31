<?php
/**
 * Уменьшение и кэш товарных снимков.
 *
 * Поставщики отдают студийные фотографии по 250–600 КБ в полном разрешении,
 * а показываем мы их плиткой 400 px и строкой списка 80 px. Страница каталога
 * из-за этого тянула по несколько мегабайт картинок. Здесь снимок скачивается
 * один раз, ужимается до нужной ширины, кладётся на диск и дальше отдаётся с
 * годовым кэшем.
 *
 * Источники ограничены списком сайтов поставщиков: без него это был бы
 * открытый прокси, через который с нашего сервера ходят куда угодно.
 */

const ASMBOT_IMG_CACHE = __DIR__ . '/img-cache';
const ASMBOT_IMG_MAX_BYTES = 12582912; // 12 МБ — больше товарных фото не бывает
const ASMBOT_IMG_TIMEOUT = 12;

/** Разрешённые ширины: свободный параметр плодил бы копии одного снимка. */
$allowedWidths = [80, 200, 400, 800, 1200];

$allowedHosts = [
    'svarog-rf.ru',
    'foxweld.ru',
    'vektor-grupp.ru',
    'oberon-weld.ru',
    'sts-rf.ru',
    'k2tool.ru',
    'dali-compressor.ru',
    'weldteam.pro',
    'profsvar.com',
    'qvazar.com',
    'asmbot.ru',
];

function asmbot_img_fail($code, $message, $fallback = '')
{
    // Показать фотографию важнее, чем сэкономить: если ужать не получилось,
    // отправляем браузер к исходнику.
    if ($fallback !== '') {
        header('Cache-Control: public, max-age=3600');
        header('Location: ' . $fallback, true, 302);
        exit;
    }
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$source = isset($_GET['u']) ? trim((string) $_GET['u']) : '';
$width = isset($_GET['w']) ? (int) $_GET['w'] : 400;

if ($source === '') {
    asmbot_img_fail(400, 'Не указан адрес снимка.');
}
if (!in_array($width, $allowedWidths, true)) {
    $width = 400;
}

$parts = parse_url($source);
if (!$parts || !isset($parts['scheme'], $parts['host'])) {
    asmbot_img_fail(400, 'Неразборчивый адрес.');
}
if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
    asmbot_img_fail(400, 'Только http и https.');
}

$host = strtolower($parts['host']);
$hostAllowed = false;
foreach ($allowedHosts as $allowed) {
    if ($host === $allowed || $host === 'www.' . $allowed || substr($host, -strlen('.' . $allowed)) === '.' . $allowed) {
        $hostAllowed = true;
        break;
    }
}
if (!$hostAllowed) {
    asmbot_img_fail(403, 'Этот сайт не в списке поставщиков.');
}

$key = sha1($source . '|' . $width);
$cachePath = ASMBOT_IMG_CACHE . '/' . substr($key, 0, 2) . '/' . $key . '.webp';

/** Отдать готовый файл. */
function asmbot_img_send($path)
{
    $modified = filemtime($path);
    $etag = '"' . md5($path . '|' . $modified) . '"';

    header('Content-Type: image/webp');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    header('ETag: ' . $etag);
    header_remove('Pragma');

    $sent = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
    if ($sent !== '' && $sent === $etag) {
        http_response_code(304);
        exit;
    }

    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

if (is_file($cachePath) && filesize($cachePath) > 0) {
    asmbot_img_send($cachePath);
}

// --- Качаем исходник -------------------------------------------------------

$raw = false;
if (function_exists('curl_init')) {
    $ch = curl_init($source);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => ASMBOT_IMG_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_USERAGENT => 'AlfaSmart-Image/1.0 (+https://asmbot.ru)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_BUFFERSIZE => 65536,
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => function ($res, $expected, $got) {
            return $got > ASMBOT_IMG_MAX_BYTES ? 1 : 0;
        },
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false || $status !== 200) {
        $raw = false;
    }
}

if ($raw === false || $raw === '' || strlen($raw) > ASMBOT_IMG_MAX_BYTES) {
    asmbot_img_fail(502, 'Снимок недоступен.', $source);
}

$info = @getimagesizefromstring($raw);
if (!$info || empty($info[0]) || empty($info[1])) {
    asmbot_img_fail(415, 'Это не изображение.', $source);
}

$image = @imagecreatefromstring($raw);
if (!$image) {
    asmbot_img_fail(415, 'Не удалось разобрать снимок.', $source);
}

$srcWidth = imagesx($image);
$srcHeight = imagesy($image);

// Вверх не растягиваем: мелкий исходник от этого лучше не станет, а вес вырастет.
$targetWidth = min($width, $srcWidth);
$targetHeight = (int) max(1, round($srcHeight * ($targetWidth / $srcWidth)));

$canvas = imagecreatetruecolor($targetWidth, $targetHeight);
imagealphablending($canvas, false);
imagesavealpha($canvas, true);
imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcWidth, $srcHeight);
imagedestroy($image);

$dir = dirname($cachePath);
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

$temp = $cachePath . '.' . getmypid() . '.tmp';
$ok = @imagewebp($canvas, $temp, 82);
imagedestroy($canvas);

if (!$ok || !is_file($temp)) {
    @unlink($temp);
    asmbot_img_fail(500, 'Не удалось сохранить снимок.', $source);
}

// Переименование атомарно: параллельные запросы не увидят половину файла.
@rename($temp, $cachePath);

asmbot_img_send(is_file($cachePath) ? $cachePath : $temp);
