<?php
// invoice.php
// Отримуємо дані з GET-параметрів (bot передав chat_id і amount)
$chat_id = $_GET['chat_id'] ?? '';
$amount = $_GET['amount'] ?? 100;

// Генеруємо унікальний transaction_id
$transaction_id = uniqid('test_', true);

// Зберігаємо в сесію для тесту
session_start();
$_SESSION['chat_id'] = $chat_id;
$_SESSION['amount'] = $amount;
$_SESSION['transaction_id'] = $transaction_id;
?>

<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<title>Тестова оплата</title>
</head>
<body>
<h2>Тестовий платіж</h2>
<p>Сума: $<?= htmlspecialchars($amount) ?></p>
<p>Chat ID: <?= htmlspecialchars($chat_id) ?></p>
<p>Transaction ID: <?= htmlspecialchars($transaction_id) ?></p>

<form action="pay.php" method="post">
    <input type="hidden" name="chat_id" value="<?= htmlspecialchars($chat_id) ?>">
    <input type="hidden" name="amount" value="<?= htmlspecialchars($amount) ?>">
    <input type="hidden" name="transaction_id" value="<?= htmlspecialchars($transaction_id) ?>">
    <button type="submit">Оплатити</button>
</form>
</body>
</html>
