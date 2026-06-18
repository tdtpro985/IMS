<?php
require_once 'C:/Users/Keith/HRIS/HRIS-KIOSK/backend-php/connect.php';

$db = getImsConnection();
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$sql = "UPDATE interns SET face_embedding = NULL, face_registered_at = NULL, qr_code = NULL WHERE id IN (3, 6)";
if ($db->query($sql) === TRUE) {
    echo "Successfully reset face registration for interns (IDs: 3, 6).\n";
} else {
    echo "Error updating record: " . $db->error . "\n";
}

$db->close();
