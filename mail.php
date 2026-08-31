<?php
/**
 * Отправка писем через SMTP без внешних библиотек.
 *
 * Доступы — в mail-config.php (закрыт от веба в .htaccess, исключён из деплоя).
 * Образец: mail-config.example.php
 *
 * Поддерживает STARTTLS (порт 587) и SSL (порт 465).
 */

function asmbot_mail_config()
{
    $file = __DIR__ . '/mail-config.php';
    if (!is_file($file)) {
        return null;
    }
    $cfg = include $file;
    if (!is_array($cfg) || empty($cfg['host']) || empty($cfg['user'])) {
        return null;
    }
    return [
        'host' => (string) $cfg['host'],
        'port' => isset($cfg['port']) ? (int) $cfg['port'] : 587,
        'secure' => isset($cfg['secure']) ? (string) $cfg['secure'] : 'starttls',
        'user' => (string) $cfg['user'],
        'password' => isset($cfg['password']) ? (string) $cfg['password'] : '',
        'from' => !empty($cfg['from']) ? (string) $cfg['from'] : (string) $cfg['user'],
        'fromName' => isset($cfg['fromName']) ? (string) $cfg['fromName'] : '',
    ];
}

/** Читает ответ сервера и проверяет ожидаемый код. */
function asmbot_smtp_expect($socket, $expected, $stage)
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        // Последняя строка многострочного ответа: "250 текст" (пробел вместо дефиса).
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, (array) $expected, true)) {
        throw new RuntimeException(sprintf('SMTP %s: получен код %d', $stage, $code));
    }
    return $response;
}

function asmbot_smtp_send($socket, $command)
{
    fwrite($socket, $command . "\r\n");
}

/** Заголовок с не-ASCII текстом по RFC 2047. */
function asmbot_mime_header($text)
{
    $text = (string) $text;
    if (preg_match('//u', $text) && !preg_match('/[^\x20-\x7E]/', $text)) {
        return $text;
    }
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function asmbot_format_address($email, $name = '')
{
    $email = trim((string) $email);
    $name = trim((string) $name);
    if ($name === '') {
        return $email;
    }
    return asmbot_mime_header($name) . ' <' . $email . '>';
}

/**
 * Отправляет письмо. Бросает RuntimeException с безопасным текстом
 * (без пароля) при любой ошибке.
 *
 * @param string $html HTML-тело письма
 * @param string $text текстовая версия (для почтовиков без HTML)
 */
function asmbot_send_mail($to, $subject, $html, $text = '', $toName = '')
{
    $cfg = asmbot_mail_config();
    if ($cfg === null) {
        throw new RuntimeException('Файл mail-config.php не найден или заполнен неверно.');
    }

    $to = trim((string) $to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Некорректный адрес получателя.');
    }

    $secure = strtolower($cfg['secure']);
    $host = $secure === 'ssl' ? 'ssl://' . $cfg['host'] : $cfg['host'];

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client(
        $host . ':' . $cfg['port'],
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT
    );
    if (!$socket) {
        throw new RuntimeException('Не удалось подключиться к почтовому серверу.');
    }
    stream_set_timeout($socket, 20);

    try {
        asmbot_smtp_expect($socket, [220], 'приветствие');

        $ehloName = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^a-zA-Z0-9.\-]/', '', $_SERVER['HTTP_HOST']) : 'localhost';
        if ($ehloName === '') {
            $ehloName = 'localhost';
        }

        asmbot_smtp_send($socket, 'EHLO ' . $ehloName);
        asmbot_smtp_expect($socket, [250], 'EHLO');

        if ($secure === 'starttls') {
            asmbot_smtp_send($socket, 'STARTTLS');
            asmbot_smtp_expect($socket, [220], 'STARTTLS');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Не удалось установить шифрование TLS.');
            }
            asmbot_smtp_send($socket, 'EHLO ' . $ehloName);
            asmbot_smtp_expect($socket, [250], 'EHLO после TLS');
        }

        asmbot_smtp_send($socket, 'AUTH LOGIN');
        asmbot_smtp_expect($socket, [334], 'AUTH');
        asmbot_smtp_send($socket, base64_encode($cfg['user']));
        asmbot_smtp_expect($socket, [334], 'логин');
        asmbot_smtp_send($socket, base64_encode($cfg['password']));
        asmbot_smtp_expect($socket, [235], 'пароль');

        asmbot_smtp_send($socket, 'MAIL FROM:<' . $cfg['from'] . '>');
        asmbot_smtp_expect($socket, [250], 'MAIL FROM');
        asmbot_smtp_send($socket, 'RCPT TO:<' . $to . '>');
        asmbot_smtp_expect($socket, [250, 251], 'RCPT TO');

        asmbot_smtp_send($socket, 'DATA');
        asmbot_smtp_expect($socket, [354], 'DATA');

        $boundary = 'b' . bin2hex(random_bytes(12));
        if ($text === '') {
            $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
        }

        $headers = [
            'From: ' . asmbot_format_address($cfg['from'], $cfg['fromName']),
            'To: ' . asmbot_format_address($to, $toName),
            'Subject: ' . asmbot_mime_header($subject),
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $cfg['host'] . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = implode("\r\n", $headers) . "\r\n\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "\r\n"
            . '--' . $boundary . "--\r\n";

        // Точка в начале строки экранируется, иначе она обрывает письмо.
        $body = preg_replace('/^\./m', '..', $body);

        fwrite($socket, $body . "\r\n.\r\n");
        asmbot_smtp_expect($socket, [250], 'отправка');

        asmbot_smtp_send($socket, 'QUIT');
    } finally {
        @fclose($socket);
    }

    return true;
}
