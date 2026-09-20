<?php

require '../init.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['count' => 0]);
    exit;
}

$stmt = $db->prepare("SELECT COUNT(*) FROM task
    WHERE user_id = :user_id AND start_date <= date('now')");
$stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);

echo json_encode(['count' => (int) $stmt->execute()->fetchArray(SQLITE3_NUM)[0]]);
