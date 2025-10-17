<?php
require_once __DIR__ . '/../database.php';

try {
    $list = Database::listEndpoints();
    echo json_encode(['status' => 'success', 'data' => $list], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
}
