<?php
// api.php - API para obtener webhooks via AJAX
require_once 'database.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_webhooks':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $webhooks = Database::getWebhooks($limit);
        echo json_encode(['status' => 'success', 'data' => $webhooks]);
        break;
        
    case 'get_new_webhooks':
        $since = $_GET['since'] ?? '';
        if ($since) {
            $webhooks = Database::getWebhooksSince($since);
            echo json_encode(['status' => 'success', 'data' => $webhooks]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Parámetro since requerido']);
        }
        break;
        
    case 'clear_webhooks':
        // Opcional: función para limpiar la base de datos
        try {
            $db = Database::getConnection();
            $db->exec("DELETE FROM webhook_logs");
            echo json_encode(['status' => 'success', 'message' => 'Webhooks eliminados']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
}
?>