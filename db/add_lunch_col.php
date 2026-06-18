<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();
$r = $db->query("SHOW COLUMNS FROM dtr_entries LIKE 'lunch_break_mins'");
if ($r->num_rows === 0) {
    $db->query("ALTER TABLE dtr_entries ADD COLUMN lunch_break_mins INT NOT NULL DEFAULT 0 AFTER remarks");
    echo "Added: lunch_break_mins\n";
} else {
    echo "Already exists: lunch_break_mins\n";
}
echo "Done.\n";
