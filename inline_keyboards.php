<?php

/* =========================
   MAIN MENU
========================= */
function mainMenuKeyboard($user) {

    $buttons = [];

    $buttons[] = [
        ['text' => '💰 Поповнення', 'callback_data' => 'topup_menu']
    ];

    $buttons[] = [
        ['text' => '📍 Вибір регіону', 'callback_data' => 'region_select']
    ];

    $buttons[] = [
        ['text' => '📦 Мої замовлення', 'callback_data' => 'my_orders']
    ];

    if ($user['role'] === 'worker') {
        $buttons[] = [
            ['text' => ($user['worker_status'] === 'online' ? '🟢 На роботі' : '🔴 Не на роботі'),
             'callback_data' => 'worker_toggle']
        ];
        $buttons[] = [
            ['text' => '📋 Вільні замовлення', 'callback_data' => 'free_orders']
        ];
        $buttons[] = [
            ['text' => '🛠 Замовлення в роботі', 'callback_data' => 'my_assigned_orders']
        ];
    }

    return ['inline_keyboard' => $buttons];
}

/* =========================
   TOP UP
========================= */
function topupKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '💵 $20', 'callback_data' => 'topup_20']],
            [['text' => '💵 $50', 'callback_data' => 'topup_50']],
            [['text' => '💵 $100', 'callback_data' => 'topup_100']],
            [['text' => '⬅️ Назад', 'callback_data' => 'menu_main']]
        ]
    ];
}

/* =========================
   REGIONS
========================= */
function regionKeyboard($regions) {
    $kb = [];

    foreach ($regions as $r) {
        $kb[] = [
            ['text' => '📍 '.$r['name'], 'callback_data' => 'region_'.$r['id']]
        ];
    }

    $kb[] = [['text' => '⬅️ Назад', 'callback_data' => 'menu_main']];

    return ['inline_keyboard' => $kb];
}

/* =========================
   ITEMS
========================= */
function itemsKeyboard($items, $regionId) {
    $kb = [];

    foreach ($items as $i) {
        $kb[] = [
            ['text' => '📦 '.$i['name'].' ($'.$i['price'].')',
             'callback_data' => 'item_'.$i['id'].'_'.$regionId]
        ];
    }

    $kb[] = [['text' => '⬅️ Назад', 'callback_data' => 'region_select']];

    return ['inline_keyboard' => $kb];
}

/* =========================
   ORDERS
========================= */
function ordersKeyboard($orders, $prefix = 'order') {
    $kb = [];

    foreach ($orders as $o) {
        $kb[] = [
            ['text' => '#'.$o['id'].' | '.$o['status'],
             'callback_data' => $prefix.'_'.$o['id']]
        ];
    }

    $kb[] = [['text' => '⬅️ Назад', 'callback_data' => 'menu_main']];

    return ['inline_keyboard' => $kb];
}

function sendUserOrders($chatId, $userId, $messageId = null) {
    // отримуємо замовлення користувача
    $orders = db_getOrdersByUser($userId);

    if (empty($orders)) {
        $text = "📦 У вас ще немає замовлень.";
    } else {
        $text = "📦 *Мої замовлення:*\n\n";

        foreach ($orders as $o) {
            $text .= "🗓 {$o['created_at']}\n";
            $text .= "№{$o['id']} | Статус: {$o['status']} | 💰 {$o['price']}$\n\n";
        }
    }

    // Клавіатура тільки з кнопкою "Назад"
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '⬅️ Назад', 'callback_data' => 'menu_main']
            ]
        ]
    ];

    if ($messageId) {
        // редагуємо повідомлення, якщо є message_id
        editMessage($chatId, $messageId, $text, $keyboard);
    } else {
        // відправляємо нове повідомлення
        sendMessage($chatId, $text, $keyboard);
    }
}

function workerAssignedOrderKeyboard($orderId) {
    return [
        'inline_keyboard' => [
            [['text' => '✅ Виконано', 'callback_data' => 'complete_'.$orderId]],
            [['text' => '⬅️ Назад', 'callback_data' => 'worker_menu']]
        ]
    ];
}
