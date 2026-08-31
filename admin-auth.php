<?php
/**
 * Единая проверка админского пароля для всех publish-скриптов.
 *
 * Раньше пароль был продублирован строкой в четырёх файлах — сменить его можно
 * было только правкой кода и повторным деплоем. Теперь значение берётся из
 * admin-secret.php, который закрыт от веба в .htaccess.
 *
 * ВАЖНО: это не полноценная авторизация. Пароль по-прежнему отправляет браузер,
 * то есть он виден любому, кто откроет исходники страницы. Настоящее решение —
 * серверные сессии и отдельные учётки сотрудников.
 */

function asmbot_admin_password()
{
    // 1) Секрет рядом со скриптами, но закрытый от веба.
    $secretFile = __DIR__ . '/admin-secret.php';
    if (is_file($secretFile)) {
        $value = include $secretFile;
        if (is_string($value) && $value !== '') {
            return $value;
        }
    }

    // 2) Переменная окружения, если хостинг её умеет.
    $env = getenv('ASMBOT_ADMIN_PASSWORD');
    if (is_string($env) && $env !== '') {
        return $env;
    }

    // 3) Историческое значение — чтобы обновление не заблокировало админку
    //    до того, как будет создан admin-secret.php.
    return 'svarka-admin';
}

/** Прерывает выполнение с 401, если заголовок с паролем не совпал. */
function asmbot_require_admin()
{
    $provided = isset($_SERVER['HTTP_X_ADMIN_PASSWORD'])
        ? (string) $_SERVER['HTTP_X_ADMIN_PASSWORD']
        : '';

    if ($provided === '' || !hash_equals(asmbot_admin_password(), $provided)) {
        http_response_code(401);
        echo json_encode(['error' => 'Неверный пароль админки.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
