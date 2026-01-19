<?php

function tgRequest($method, $params) {
    global $TOKEN;

    $url = "https://api.telegram.org/bot{$TOKEN}/{$method}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function sendMessage($chatId, $text, $keyboard = null) {

    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }

    tgRequest('sendMessage', $data);
}

function editMessage($chatId, $messageId, $text, $keyboard = null) {

    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }

    tgRequest('editMessageText', $data);
}
