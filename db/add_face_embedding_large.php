<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$r = $db->query("SHOW COLUMNS FROM interns LIKE 'face_embedding_large'");
if ($r->num_rows === 0) {
    $db->query("ALTER TABLE interns ADD COLUMN face_embedding_large LONGTEXT NULL AFTER face_embedding");
    echo "Added column interns.face_embedding_large\n";
} else {
    echo "Already exists: interns.face_embedding_large\n";
}

echo "Database migration done.\n";
