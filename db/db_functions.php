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
        SET status = 'assigned', assigned_worker_id = ?
        WHERE id = ? AND status = 'searching_worker'
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


function db_payAndCreateOrder($buyerId, $productId, $regionId, $price) {
    $db = db();

    // Отримуємо баланс користувача
    $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $buyerId);
    $stmt->execute();
    $stmt->bind_result($balance);
    $stmt->fetch();
    $stmt->close();

    if ($balance < $price) {
        return false; // недостатньо коштів
    }

    // Починаємо транзакцію
    $db->begin_transaction();

    try {
        // Знімаємо баланс
        $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->bind_param("di", $price, $buyerId);
        $stmt->execute();
        $stmt->close();

        // Створюємо замовлення зі статусом 'searching_worker'
        $stmt = $db->prepare("
            INSERT INTO orders (buyer_id, product_id, region_id, price, status, created_at)
            VALUES (?, ?, ?, ?, 'searching_worker', NOW())
        ");
        $stmt->bind_param("iiid", $buyerId, $productId, $regionId, $price);
        $stmt->execute();

        $orderId = $stmt->insert_id;
        $stmt->close();

        $db->commit();

        return $orderId;

    } catch (Exception $e) {
        $db->rollback();
        return false;
    }
}

/* =========================
   ORDERS - нові замовлення для робітників
========================= */
function db_getNewOrders() {
    $db = db();

    $res = $db->query("
        SELECT o.id, o.status, o.price, o.created_at, p.name AS product_name
        FROM orders o
        JOIN products p ON p.id = o.product_id
        WHERE o.status = 'searching_worker'
        ORDER BY o.created_at ASC
    ");

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    return $rows;
}

function db_getAssignedOrdersByWorker($workerId) {
    $db = db();

    $stmt = $db->prepare("
        SELECT o.id, o.status, o.price, o.created_at, p.name
        FROM orders o
        JOIN products p ON p.id = o.product_id
        WHERE o.assigned_worker_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->bind_param("i", $workerId);
    $stmt->execute();

    $stmt->bind_result($id, $status, $price, $createdAt, $productName);

    $orders = [];
    while ($stmt->fetch()) {
        $orders[] = [
            'id' => $id,
            'status' => $status,
            'price' => $price,
            'created_at' => $createdAt,
            'product_name' => $productName
        ];
    }

    $stmt->close();
    return $orders;
}

/* =========================
   USERS BY ROLE
========================= */
function db_getUsersByRole($role) {
    $db = db();

    $stmt = $db->prepare("
        SELECT id, telegram_id, balance, role, worker_status
        FROM users
        WHERE role = ?
        ORDER BY id ASC
    ");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $stmt->bind_result($id, $telegramId, $balance, $roleDb, $workerStatus);

    $users = [];
    while ($stmt->fetch()) {
        $users[] = [
            'id' => $id,
            'telegram_id' => $telegramId,
            'balance' => $balance,
            'role' => $roleDb,
            'worker_status' => $workerStatus
        ];
    }

    $stmt->close();
    return $users;
}


function db_completeOrder($orderId, $workerId) {
    $db = db();

    // Спочатку отримуємо buyer_id для логу
    $stmt = $db->prepare("SELECT buyer_id FROM orders WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $stmt->bind_result($buyerId);
    if (!$stmt->fetch()) {
        $stmt->close();
        return false; // замовлення не знайдено
    }
    $stmt->close();

    // Оновлюємо статус в orders
    $stmt = $db->prepare("
        UPDATE orders
        SET status = 'completed'
        WHERE id = ? AND assigned_worker_id = ?
    ");
    $stmt->bind_param("ii", $orderId, $workerId);
    $stmt->execute();
    $stmt->close();

    // Записуємо в order_logs
    $stmt = $db->prepare("
        INSERT INTO order_logs (order_id, worker_id, buyer_id, status, created_at)
        VALUES (?, ?, ?, 'completed', NOW())
    ");
    $stmt->bind_param("iii", $orderId, $workerId, $buyerId);
    $stmt->execute();
    $stmt->close();

    return true;
}

