<?php
require_once __DIR__ . '/db/db_functions.php';
require_once __DIR__ . '/inline_keyboards.php';
require_once __DIR__ . '/telegram_api.php';

function handleCallback(array $cb) {

    $chatId     = $cb['message']['chat']['id'];
    $messageId  = $cb['message']['message_id'];
    $telegramId = $cb['from']['id'];
    $data       = $cb['data'];

    $user = db_getUserByTelegramId($telegramId);

    switch (true) {

        /* ========= MAIN ========= */
        case $data === 'menu_main':
            editMessage($chatId, $messageId,
                "👋 <b>Delivery Bot</b>\n\nОберіть дію:",
                mainMenuKeyboard($user)
            );
            break;

        /* ========= TOPUP ========= */
        case $data === 'topup_menu':
            editMessage($chatId, $messageId,
                "💰 <b>Поповнення</b>\nОберіть суму:",
                topupKeyboard()
            );
            break;

        case str_starts_with($data, 'topup_'):
            editMessage($chatId, $messageId,
                "🔄 Перехід до оплати...\n\n(буде підключено пізніше)",
                mainMenuKeyboard($user)
            );
            break;

        /* ========= REGIONS ========= */
        case $data === 'region_select':
            $regions = db_getRegions();
            editMessage($chatId, $messageId,
                "📍 <b>Оберіть регіон</b>",
                regionKeyboard($regions)
            );
            break;

        case str_starts_with($data, 'region_'):
            $regionId = (int)str_replace('region_', '', $data);
            $items = db_getItems();
            editMessage($chatId, $messageId,
                "📦 <b>Оберіть товар</b>",
                itemsKeyboard($items, $regionId)
            );
            break;

        /* ========= ITEM / ORDER ========= */
        case str_starts_with($data, 'item_'):
            [, $itemId, $regionId] = explode('_', $data);
            $itemId = (int)$itemId;
            $regionId = (int)$regionId;

            // отримуємо продукт, щоб взяти ціну
            $items = db_getItems();
            $price = 0;
            foreach ($items as $i) {
                if ((int)$i['id'] === $itemId) {
                    $price = (float)$i['price']; // float price
                    break;
                }
            }

            // Списання балансу та створення замовлення
            $orderId = db_payAndCreateOrder($user['id'], $itemId, $regionId, $price);

            if (!$orderId) {
                // недостатньо коштів
                sendMessage($chatId,
                    "❌ Недостатньо коштів на балансі. Будь ласка, поповніть баланс.",
                    topupKeyboard()
                );
                break;
            }

            // Надсилаємо push-повідомлення всім робітникам про новий ордер
            $workers = db_getUsersByRole('worker');
            foreach ($workers as $w) {
                sendMessage($w['telegram_id'],
                    "📢 <b>Нове замовлення!</b>\n"
                    ."🛒 Товар: {$i['name']}\n"
                    ."💰 Ціна: {$price}$\n"
                    ."⏳ Статус: searching_worker"
                );
            }

            editMessage($chatId, $messageId,
                "⏳ <b>Замовлення створено</b>\n\nОчікує прийняття робітником.\nСтатус дивіться в «Мої замовлення»",
                mainMenuKeyboard($user)
            );
            break;

        /* ========= MY ORDERS ========= */
        case $data === 'my_orders':
            sendUserOrders($chatId, $user['id'], $messageId);
            break;

        /* ========= WORKER ========= */
        case $data === 'worker_toggle':
            $newStatus = $user['worker_status'] === 'online' ? 'offline' : 'online';
            db_setWorkerStatus($user['id'], $newStatus);

            editMessage($chatId, $messageId,
                $newStatus === 'online' ? "🟢 Ви на роботі" : "🔴 Ви не на роботі",
                mainMenuKeyboard($user)
            );
            break;

        case $data === 'free_orders':
            $orders = db_getNewOrders(); // змінив на нові замовлення

            editMessage($chatId, $messageId,
                "📋 <b>Нові замовлення</b>",
                ordersKeyboard($orders, 'accept')
            );
            break;

        case str_starts_with($data, 'accept_'):
            $orderId = (int)str_replace('accept_', '', $data);
            db_acceptOrder($orderId, $user['id']);

            editMessage($chatId, $messageId,
                "✅ <b>Замовлення прийнято</b>",
                mainMenuKeyboard($user)
            );
            break;
    }
}
