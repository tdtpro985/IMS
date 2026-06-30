<?php
// ============================================================
// Database Configuration
// ============================================================
date_default_timezone_set('Asia/Manila');

define('DB_HOST', 'db');
define('DB_USER', 'ims_user');
define('DB_PASS', 'ims_pass');
define('DB_NAME', 'tdt_ims');

function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
