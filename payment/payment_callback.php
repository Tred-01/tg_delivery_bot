<?php
require_once __DIR__ . '/db/db_functions.php';

// Отримуємо POST-запит від тестового сервісу
$data = json_decode(file_get_contents('php://input'), true);

$chat_id = $data['chat_id'] ?? null;
$amount  = $data['amount'] ?? 0;
$status  = $data['status'] ?? null;

if (!$chat_id || !$status) {
    http_response_code(400);
    exit('Invalid data');
}

// Якщо статус = paid → додаємо баланс користувачу
if ($status === 'paid') {
    addBalance($chat_id, $amount);
}

// Повертаємо успішний код
http_response_code(200);
echo 'OK';
