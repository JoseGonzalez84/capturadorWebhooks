<?php
// api.php - API para obtener webhooks via AJAX
require_once 'database.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$token = $_GET['token'] ?? null; // Opcional: token para filtrar endpoints

switch ($action) {
    case 'list_endpoints':
        try {
            $endpoints = Database::listEndpoints();
            echo json_encode(['status' => 'success', 'data' => $endpoints]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'create_endpoint':
        // Puede recibirse como POST form o JSON
        $input = json_decode(file_get_contents('php://input'), true);
        $tokenParam = $_POST['token'] ?? $input['token'] ?? null;
        $label = $_POST['label'] ?? $input['label'] ?? null;

        if (!$tokenParam) {
            echo json_encode(['status' => 'error', 'message' => 'token requerido']);
            break;
        }

        try {
            $id = Database::createEndpoint($tokenParam, $label);
            echo json_encode(['status' => 'success', 'id' => $id, 'token' => $tokenParam]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_endpoint':
        $id = $_GET['id'] ?? null;
        $tokenToDelete = $_GET['token'] ?? null;
        try {
            if ($id) {
                $ok = Database::deleteEndpointById($id);
            } elseif ($tokenToDelete) {
                $ok = Database::deleteEndpointByToken($tokenToDelete);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'id o token requerido']);
                break;
            }
            echo json_encode(['status' => 'success', 'deleted' => (bool)$ok]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
    case 'get_webhooks':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        if ($token) {
            $webhooks = Database::getWebhooksByToken($token, $limit);
        } else {
            $webhooks = Database::getWebhooks($limit);
        }
        echo json_encode(['status' => 'success', 'data' => $webhooks]);
        break;

    case 'get_response':
        // Devuelve la configuración de respuesta para un token
        if (!$token) {
            echo json_encode(['status' => 'error', 'message' => 'token requerido']);
            break;
        }
        try {
            $resp = Database::getResponseByToken($token);
            echo json_encode(['status' => 'success', 'data' => $resp]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'save_response':
        // Guardar/actualizar respuesta para token (POST JSON preferido)
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $tokenParam = $_POST['token'] ?? $input['token'] ?? null;
        $status = $_POST['status'] ?? $input['status'] ?? null;
        $contentType = $_POST['content_type'] ?? $input['content_type'] ?? null;
        $body = $_POST['body'] ?? $input['body'] ?? null;

        if (!$tokenParam) {
            echo json_encode(['status' => 'error', 'message' => 'token requerido']);
            break;
        }

        try {
            $ok = Database::upsertResponse($tokenParam, (int)$status, $contentType ?? 'application/json', $body ?? '');
            echo json_encode(['status' => $ok ? 'success' : 'error']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_response':
        $tokenParam = $_GET['token'] ?? null;
        if (!$tokenParam) {
            echo json_encode(['status' => 'error', 'message' => 'token requerido']);
            break;
        }
        try {
            $ok = Database::deleteResponseByToken($tokenParam);
            echo json_encode(['status' => 'success', 'deleted' => (bool)$ok]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
        
    case 'get_new_webhooks':
        $since = $_GET['since'] ?? '';
        if ($since) {
            if ($token) {
                $webhooks = Database::getWebhooksSinceByToken($token, $since);
            } else {
                $webhooks = Database::getWebhooksSince($since);
            }
            echo json_encode(['status' => 'success', 'data' => $webhooks]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Parámetro since requerido']);
        }
        break;
        
    case 'clear_webhooks':
        // Opcional: función para limpiar la base de datos
        try {
            $db = Database::getConnection();
            if ($token) {
                $stmt = $db->prepare("DELETE FROM webhook_logs WHERE endpoint_token = :token");
                $stmt->execute([':token' => $token]);
            } else {
                $db->exec("DELETE FROM webhook_logs");
            }
            echo json_encode(['status' => 'success', 'message' => 'Webhooks eliminados']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
}
?>