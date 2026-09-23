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

// Expande las variables dinámicas justo antes de enviar la respuesta configurada.
function expandResponseVariables($body) {
    $today = new DateTimeImmutable('now');
    $currentDate = $today->format('d-m-Y');
    $timestamp = $today->format('Y-m-d H:i:s');

    return preg_replace_callback('/%\*(CURRENT_DATE|TIMESTAMP|RANDOM_DATE|IDENTIFIER_(\d{1,3})_(NUMERIC|ALPHA|ALL))\*%/', function ($matches) use ($currentDate, $timestamp) {
        $variable = $matches[1];

        if ($variable === 'CURRENT_DATE') {
            return $currentDate;
        }

        if ($variable === 'TIMESTAMP') {
            return $timestamp;
        }

        if ($variable === 'RANDOM_DATE') {
            $start = (new DateTimeImmutable('1970-01-01'))->getTimestamp();
            $end = (new DateTimeImmutable('now'))->getTimestamp();
            return (new DateTimeImmutable('@' . random_int($start, $end)))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('d-m-Y');
        }

        $length = (int)$matches[2];
        $type = $matches[3];
        if ($length < 1 || $length > 256) {
            return $matches[0];
        }

        $characters = $type === 'NUMERIC' ? '0123456789' : 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        if ($type === 'ALL') {
            $characters .= '0123456789';
        }

        $identifier = '';
        $maxIndex = strlen($characters) - 1;
        for ($index = 0; $index < $length; $index++) {
            $identifier .= $characters[random_int(0, $maxIndex)];
        }

        return $identifier;
    }, $body);
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
            $allowedMethods = json_decode($respCfg['allowed_methods'] ?? '["ALL"]', true);
            if (!is_array($allowedMethods) || empty($allowedMethods)) $allowedMethods = ['ALL'];
            if (!in_array('ALL', $allowedMethods, true) && !in_array(strtoupper($method), $allowedMethods, true)) {
                http_response_code(405);
                header('Allow: ' . implode(', ', $allowedMethods));
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Método no permitido',
                    'method' => $method,
                    'allowed_methods' => $allowedMethods
                ]);
                exit;
            }
            $code = (int)$respCfg['status_code'];
            $ctype = $respCfg['content_type'] ?? 'application/json';
            $respBody = expandResponseVariables($respCfg['body'] ?? '');

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