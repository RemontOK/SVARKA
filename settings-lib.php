<?php
/**
 * Настройки в таблице settings (ключ → значение).
 * Здесь живут реквизиты компании для счетов и документов.
 */

/** Поля реквизитов и их подписи — один источник правды для формы и счёта. */
function asmbot_company_fields()
{
    return [
        'company_name' => 'Наименование организации',
        'company_inn' => 'ИНН',
        'company_kpp' => 'КПП',
        'company_ogrn' => 'ОГРН',
        'company_address' => 'Юридический адрес',
        'company_phone' => 'Телефон',
        'company_email' => 'Почта',
        'bank_name' => 'Банк',
        'bank_bik' => 'БИК',
        'bank_account' => 'Расчётный счёт',
        'bank_corr_account' => 'Корреспондентский счёт',
        'director_name' => 'Руководитель (для подписи)',
        'accountant_name' => 'Бухгалтер (для подписи)',
        'vat_note' => 'НДС (например: НДС не облагается / В том числе НДС 20%)',
        // Отдельная учётка для 1С — админский пароль ей не выдаём.
        'exchange_1c_login' => 'Обмен с 1С: логин',
        'exchange_1c_password' => 'Обмен с 1С: пароль',
    ];
}

function asmbot_get_settings(PDO $pdo)
{
    $out = [];
    foreach ($pdo->query('SELECT name, value FROM settings') as $row) {
        $out[$row['name']] = $row['value'];
    }
    return $out;
}

function asmbot_save_settings(PDO $pdo, array $values)
{
    $stmt = $pdo->prepare(
        'INSERT INTO settings (name, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    foreach ($values as $name => $value) {
        $stmt->execute([mb_substr((string) $name, 0, 64), mb_substr((string) $value, 0, 2000)]);
    }
}

/** Реквизиты компании с пустыми значениями по умолчанию. */
function asmbot_company_info(PDO $pdo)
{
    $settings = asmbot_get_settings($pdo);
    $info = [];
    foreach (array_keys(asmbot_company_fields()) as $key) {
        $info[$key] = isset($settings[$key]) ? (string) $settings[$key] : '';
    }
    return $info;
}

/** Заполнены ли поля, без которых счёт бессмысленен. */
function asmbot_company_is_ready(array $info)
{
    foreach (['company_name', 'company_inn', 'bank_account', 'bank_bik'] as $required) {
        if (trim((string) ($info[$required] ?? '')) === '') {
            return false;
        }
    }
    return true;
}
