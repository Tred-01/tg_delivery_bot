<?php
require_once __DIR__ . '/db.php';

/* =========================
   USERS
========================= */

function db_getOrCreateUser($telegramId) {
    $db = db();

    $stmt = $db->prepare("
        SELECT id, telegram_id, balance, role, worker_status
        FROM users
        WHERE telegram_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $telegramId);
    $stmt->execute();

    $stmt->bind_result($id, $tg, $balance, $role, $workerStatus);

    if ($stmt->fetch()) {
        $stmt->close();
        return [
            'id' => $id,
            'telegram_id' => $tg,
            'balance' => $balance,
            'role' => $role,
            'worker_status' => $workerStatus
        ];
    }

    $stmt->close();

    // створюємо користувача
    $stmt = $db->prepare("
        INSERT INTO users (telegram_id, balance, role, worker_status)
        VALUES (?, 0, 'buyer', 'offline')
    ");
    $stmt->bind_param("i", $telegramId);
    $stmt->execute();

    return [
        'id' => $stmt->insert_id,
        'telegram_id' => $telegramId,
        'balance' => 0,
        'role' => 'buyer',
        'worker_status' => 'offline'
    ];
}

function db_getUserByTelegramId($telegramId) {
    $db = db();

    $stmt = $db->prepare("
        SELECT id, telegram_id, balance, role, worker_status
        FROM users
        WHERE telegram_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $telegramId);
    $stmt->execute();

    $stmt->bind_result($id, $tg, $balance, $role, $workerStatus);

    if ($stmt->fetch()) {
        return [
            'id' => $id,
            'telegram_id' => $tg,
            'balance' => $balance,
            'role' => $role,
            'worker_status' => $workerStatus
        ];
    }

    return null;
}

/* =========================
   REGIONS
========================= */

function db_getRegions() {
    $db = db();
    $res = $db->query("
        SELECT id, name
        FROM regions
        WHERE is_active = 1
        ORDER BY id ASC
    ");

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    return $rows;
}

/* =========================
   PRODUCTS
========================= */

function db_getItems() {
    $db = db();
    $res = $db->query("
        SELECT id, name, price
        FROM products
        WHERE is_active = 1
        ORDER BY id ASC
    ");

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    return $rows;
}

/* =========================
   ORDERS
========================= */

function db_createOrder($buyerId, $productId, $regionId) {
    $db = db();

    // Беремо ціну прямо з таблиці products
    $stmt = $db->prepare("
        SELECT price
        FROM products
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $stmt->bind_result($price);
    if (!$stmt->fetch()) {
        $stmt->close();
        return false; // продукт не знайдено
    }
    $stmt->close();

    $stmt = $db->prepare("
        INSERT INTO orders (buyer_id, product_id, region_id, price, status, created_at)
        VALUES (?, ?, ?, ?, 'searching_worker', NOW())
    ");
    $stmt->bind_param("iiid", $buyerId, $productId, $regionId, $price);
    $stmt->execute();

    $insertId = $stmt->insert_id;
    $stmt->close();

    return $insertId;
}

function db_getOrdersByUser($userId) {
    $db = db();

    $stmt = $db->prepare("
        SELECT id, status, price, created_at
        FROM orders
        WHERE buyer_id = ?
        ORDER BY id DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $stmt->bind_result($id, $status, $price, $createdAt);

    $rows = [];
    while ($stmt->fetch()) {
        $rows[] = [
            'id' => $id,
            'status' => $status,
            'price' => $price,
            'created_at' => $createdAt
        ];
    }

    return $rows;
}

function db_getFreeOrders() {
    $db = db();
    $res = $db->query("
        SELECT id, status
        FROM orders
        WHERE status = 'pending'
        ORDER BY id ASC
    ");

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    return $rows;
}

function db_acceptOrder($orderId, $workerId) {
    $db = db();

    $stmt = $db->prepare("
        UPDATE orders
        SET status = 'accepted', worker_id = ?
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ii", $workerId, $orderId);
    $stmt->execute();
}

/* =========================
   WORKER
========================= */

function db_setWorkerStatus($userId, $status) {
    $db = db();

    $stmt = $db->prepare("
        UPDATE users
        SET worker_status = ?
        WHERE id = ?
    ");
    $stmt->bind_param("si", $status, $userId);
    $stmt->execute();
}
