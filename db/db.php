<?php

require_once __DIR__ . '/config.php';

function db(): mysqli
{
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            error_log("DB connection error: " . $conn->connect_error);
            die('Database error');
        }

        $conn->set_charset('utf8mb4');
    }

    return $conn;
}
