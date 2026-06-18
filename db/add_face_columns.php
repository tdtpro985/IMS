<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// Add face columns to interns table
$columns_interns = [
    'registration_token' => "ALTER TABLE interns ADD COLUMN registration_token VARCHAR(64) NULL UNIQUE AFTER email",
    'token_expires_at' => "ALTER TABLE interns ADD COLUMN token_expires_at DATETIME NULL AFTER registration_token",
    'face_embedding' => "ALTER TABLE interns ADD COLUMN face_embedding LONGTEXT NULL AFTER profile_photo",
    'qr_code' => "ALTER TABLE interns ADD COLUMN qr_code VARCHAR(100) NULL UNIQUE AFTER face_embedding",
    'face_registered_at' => "ALTER TABLE interns ADD COLUMN face_registered_at DATETIME NULL AFTER qr_code"
];

foreach ($columns_interns as $col => $sql) {
    $r = $db->query("SHOW COLUMNS FROM interns LIKE '$col'");
    if ($r->num_rows === 0) {
        $db->query($sql);
        echo "Added column interns.$col\n";
    } else {
        echo "Already exists: interns.$col\n";
    }
}

// Add entry_source column to dtr_entries table
$r = $db->query("SHOW COLUMNS FROM dtr_entries LIKE 'entry_source'");
if ($r->num_rows === 0) {
    $db->query("ALTER TABLE dtr_entries ADD COLUMN entry_source ENUM('manual','kiosk') NOT NULL DEFAULT 'manual'");
    echo "Added column dtr_entries.entry_source\n";
} else {
    echo "Already exists: dtr_entries.entry_source\n";
}

echo "Database migration done.\n";
