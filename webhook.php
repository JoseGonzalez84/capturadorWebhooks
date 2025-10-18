<?php
// webhook.php - Endpoint para recibir todas las llamadas REST

require_once 'database.php';

// Función para obtener la IP real del cliente
function getRealIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Función para obtener todas las cabeceras HTTP
function getAllHeadersss() {
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
        }
    }
    return $headers;
}

// Capturar información de la petición
$method = $_SERVER['REQUEST_METHOD'];
$url = $_SERVER['REQUEST_URI'];
$headers = getAllHeadersss();
$body = file_get_contents('php://input');
$ip_address = getRealIP();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

// Soporte para token vía parámetro o querystring (p.ej. /webhooks/abc123 -> token=abc123)
$token = $_GET['token'] ?? null;

// Preparar datos para almacenar (incluyendo token si existe)
$webhook_data = [
    'method' => $method,
    'url' => $url,
    'headers' => json_encode($headers),
    'body' => $body,
    'ip_address' => $ip_address,
    'user_agent' => $user_agent,
    'content_type' => $content_type,
    'endpoint_token' => $token
];

// Guardar en la base de datos
try {
    if ($token !== null) {
        // Usar el método que incluye endpoint_token
        $id = Database::logWebhookWithToken($webhook_data);
    } else {
        $id = Database::logWebhook($webhook_data);
    }

    // Intentar obtener respuesta personalizada para el token
    if ($token) {
        $respCfg = Database::getResponseByToken($token);
        if ($respCfg && isset($respCfg['status_code'])) {
            $code = (int)$respCfg['status_code'];
            $ctype = $respCfg['content_type'] ?? 'application/json';
            $respBody = $respCfg['body'] ?? '';

            http_response_code($code);
            header('Content-Type: ' . $ctype);
            echo $respBody;
            exit;
        }
    }

    // Respuesta por defecto si no hay configuración personalizada
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Webhook recibido correctamente',
        'id' => $id,
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint_token' => $token
    ]);

} catch (Exception $e) {
    // Respuesta de error
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al procesar el webhook: ' . $e->getMessage()
    ]);
}