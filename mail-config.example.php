<?php
/**
 * ОБРАЗЕЦ. Скопируйте рядом под именем mail-config.php и впишите доступы.
 *
 * Timeweb: сервер smtp.timeweb.ru, логин — адрес ящика, пароль — от ящика.
 * Порты: 587 (STARTTLS), 465 (SSL), 25/2525 (без шифрования).
 * Шифрование лучше не отключать.
 *
 * mail-config.php закрыт от скачивания в .htaccess и исключён из деплоя.
 */

return [
    'host' => 'smtp.timeweb.ru',
    'port' => 587,
    'secure' => 'starttls', // starttls | ssl
    'user' => 'ЯЩИК@asmbot.ru',
    'password' => 'ПАРОЛЬ',
    'from' => 'ЯЩИК@asmbot.ru',
    'fromName' => 'АльфаСмарт',
];
