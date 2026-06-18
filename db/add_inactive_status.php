<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$result = $db->query("ALTER TABLE interns MODIFY COLUMN status ENUM('Active','Inactive','Archived') NOT NULL DEFAULT 'Active'");
echo $result ? "Done: status ENUM updated to Active/Inactive/Archived\n" : "Error: " . $db->error . "\n";
