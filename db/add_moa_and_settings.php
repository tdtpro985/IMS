<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// ── MOA table ──────────────────────────────────────────────────────────────
$db->query("CREATE TABLE IF NOT EXISTS moa_agreements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seq             INT UNSIGNED,
    school_name     VARCHAR(200) NOT NULL,
    validity        VARCHAR(50),
    period_start    DATE,
    period_end      DATE,
    status          ENUM('Active','Expired','For Verification','On Process','For Renewal') NOT NULL DEFAULT 'On Process',
    remarks         VARCHAR(255),
    file_path       VARCHAR(255),
    file_name       VARCHAR(255),
    is_archived     TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
echo "MOA table: OK\n";

// ── System settings table ──────────────────────────────────────────────────
$db->query("CREATE TABLE IF NOT EXISTS system_settings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_val VARCHAR(255) NOT NULL,
    label       VARCHAR(150),
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
echo "Settings table: OK\n";

// ── Default settings ───────────────────────────────────────────────────────
$defaults = [
    ['lunch_break_enabled',  '0',   'Deduct Lunch Break from Rendered Hours'],
    ['lunch_break_minutes',  '60',  'Lunch Break Duration (minutes)'],
    ['standard_hours',       '8',   'Standard Daily Hours Threshold'],
];
foreach ($defaults as [$key, $val, $label]) {
    $db->query("INSERT IGNORE INTO system_settings (setting_key, setting_val, label)
                VALUES ('{$key}', '{$val}', '{$label}')");
}
echo "Default settings: OK\n";
echo "Done.\n";
