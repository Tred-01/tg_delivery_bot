<?php
// pay.php — імітація платіжного сервісу
session_start();

$chat_id = $_POST['chat_id'] ?? '';
$amount = $_POST['amount'] ?? 0;
$transaction_id = $_POST['transaction_id'] ?? '';

if (!$chat_id || !$transaction_id) {
    die("Невірні дані.");
}

// Тут можна зробити симуляцію затримки оплати
sleep(1);

// Викликаємо твій webhook на сервері бота
$webhook_url = 'https://tredmark.space/payment/payment_callback.php'; // твій callback

$data = [
    'chat_id' => $chat_id,
    'amount' => $amount,
    'transaction_id' => $transaction_id,
    'status' => 'paid' // або 'rejected' для тесту відмови
];

// Викликаємо webhook через cURL
$ch = curl_init($webhook_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json']
]);
$res = curl_exec($ch);
curl_close($ch);

echo "<p>Оплата успешна.</p>";
// echo "<p><a href='invoice.php?chat_id=$chat_id&amount=$amount'>Повернутися</a></p>";
