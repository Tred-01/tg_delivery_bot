<?php
require_once __DIR__ . '/token.php';
require_once __DIR__ . '/telegram_api.php'; 
require_once __DIR__ . '/db/db_functions.php';
require_once __DIR__ . '/inline_keyboards.php';
require_once __DIR__ . '/callbacks.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

$update = json_decode(file_get_contents('php://input'), true);
file_put_contents(__DIR__.'/logs/webhook.log', json_encode($update).PHP_EOL, FILE_APPEND);

if (!$update) exit;

// MESSAGE
if (isset($update['message'])) {

    $chatId = $update['message']['chat']['id'];
    $telegramId = $update['message']['from']['id'];

    // створюємо або отримуємо користувача
    $user = db_getOrCreateUser($telegramId);

    // завжди надсилаємо меню в одному повідомленні
    sendOrUpdateMenu($chatId, $user);
}

// CALLBACK
if (isset($update['callback_query'])) {
    handleCallback($update['callback_query']);
}

/* =========================
   FUNCTIONS
========================= */

function sendOrUpdateMenu($chatId, $user, $messageId = null) {
    $text = "👋 <b>Delivery Bot</b>\n\nОберіть дію:";
    $keyboard = mainMenuKeyboard($user);

    if ($messageId) {
        editMessage($chatId, $messageId, $text, $keyboard);
    } else {
        sendMessage($chatId, $text, $keyboard);
    }
}

