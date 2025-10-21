<?php
// Protección simple del front con contraseña (session-based)
session_start();

// Intentar cargar composer autoload y luego .env usando phpdotenv si está disponible
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
if (class_exists('Dotenv\Dotenv')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();
    } catch (Exception $e) {
        // noop
    }
}

// Fallback: si no se cargó ADMIN_PASSWORD desde entorno, intentar parsear .env manualmente
$adminPassword = getenv('ADMIN_PASSWORD') ?: ($_ENV['ADMIN_PASSWORD'] ?? null);
$adminPasswordHash = getenv('ADMIN_PASSWORD_HASH') ?: ($_ENV['ADMIN_PASSWORD_HASH'] ?? null);
if (empty($adminPassword) && empty($adminPasswordHash)) {
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile) && is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            // key=value parsing, allow quotes
            if (preg_match('/^([A-Z0-9_]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^#]*))/i', $line, $m)) {
                $k = $m[1];
                $v = isset($m[2]) && $m[2] !== '' ? $m[2] : (isset($m[3]) && $m[3] !== '' ? $m[3] : (isset($m[4]) ? trim($m[4]) : ''));
                if ($k === 'ADMIN_PASSWORD' && $v !== '') {
                    $adminPassword = $v;
                    putenv('ADMIN_PASSWORD=' . $v);
                    $_ENV['ADMIN_PASSWORD'] = $v;
                }
                if ($k === 'ADMIN_PASSWORD_HASH' && $v !== '') {
                    $adminPasswordHash = $v;
                    putenv('ADMIN_PASSWORD_HASH=' . $v);
                    $_ENV['ADMIN_PASSWORD_HASH'] = $v;
                }
            }
        }
    }
}

// Manejar logout rápido
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ' . ($_SERVER['SCRIPT_NAME'] ?? '/'));
    exit;
}

// Obtener credenciales desde entorno (usar .env o variables de entorno)
$adminPassword = getenv('ADMIN_PASSWORD') ?: ($_ENV['ADMIN_PASSWORD'] ?? null);
$adminPasswordHash = getenv('ADMIN_PASSWORD_HASH') ?: ($_ENV['ADMIN_PASSWORD_HASH'] ?? null);

$loginError = '';
// Procesar intento de login
if (!isset($_SESSION['is_authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
        $pw = $_POST['admin_password'];
        $ok = false;
        if (!empty($adminPasswordHash)) {
            // comprobar hash (password_hash) si se proporcionó
            if (password_verify($pw, $adminPasswordHash)) $ok = true;
        } elseif (!empty($adminPassword)) {
            if (hash_equals($adminPassword, $pw)) $ok = true;
        } else {
            // Si no hay contraseña configurada, bloquear acceso y mostrar mensaje
            $loginError = 'No hay contraseña configurada. Configure ADMIN_PASSWORD o ADMIN_PASSWORD_HASH.';
        }

        if ($ok) {
            $_SESSION['is_authenticated'] = true;
            // Redirigir para limpiar POST
            header('Location: ' . ($_SERVER['REQUEST_URI']));
            exit;
        } else {
            if (empty($loginError)) $loginError = 'Contraseña incorrecta.';
        }
    }

    // Mostrar formulario de login y detener la ejecución del front
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Login - Capturador de Webhooks</title>
        <style>
            body { font-family: Arial, Helvetica, sans-serif; background: #f4f6f8; display:flex; align-items:center; justify-content:center; height:100vh; margin:0 }
            .login-box { background:#fff; padding:24px; border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,0.08); width:360px }
            label { display:block; margin-bottom:8px; color:#333 }
            input[type=password] { width:100%; padding:10px; margin-bottom:12px; border:1px solid #ddd; border-radius:6px }
            button { background:#667eea; color:#fff; padding:10px 14px; border:none; border-radius:6px; cursor:pointer }
            .error { color:#c53030; margin-bottom:12px }
            .info { font-size:12px; color:#666; margin-top:8px }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>Acceso</h2>
            <?php if ($loginError): ?>
                <div class="error"><?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>
            <form method="post">
                <label for="admin_password">Contraseña:</label>
                <input id="admin_password" name="admin_password" type="password" autocomplete="off" />
                <div style="display:flex; gap:8px; align-items:center;">
                    <button type="submit">Entrar</button>
                </div>
            </form>
            <div class="info">Protege el panel con la variable de entorno <code>ADMIN_PASSWORD</code> o <code>ADMIN_PASSWORD_HASH</code>.</div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php
    // Calcular base href dinámicamente según la ubicación del script
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    if ($scriptDir === '/' || $scriptDir === '\\') {
        $scriptDir = '';
    }
    $baseHref = $scriptDir . '/';
    ?>
    <base href="<?php echo htmlspecialchars($baseHref, ENT_QUOTES); ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capturador de Webhooks</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="kraken.png" type="image/x-icon" />   
</head>
<body>
    <header>
            <div id="header-container">
                <div id="header-container-left">
                    <h1><img width="64" height="64" src="kraken.png" alt="kraken"/> Capturador de Webhooks</h1>
                </div>
                <div id="header-container-right">
                    <?php
                    // Permitir seleccionar token vía querystring ?token=abc123 o usar uno vacío
                    $selectedToken = $_GET['token'] ?? '';
                    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                    $endpointExample = $baseUrl . '/webhooks/';
                    $displayEndpoint = $endpointExample . ($selectedToken ? $selectedToken : 'your_token_here');
                    ?>

                    <div class="tokens-accordion" id="tokens-accordion">
                        <div class="tokens-header" id="tokens-accordion-header">
                            <div class="tokens-left">                                
                                <strong><span style="visibility:hidden" id="token-current-value"><?php echo htmlspecialchars($selectedToken); ?></span></strong>
                            </div>
                            <div class="tokens-center">
                                <code id="token-endpoint-display"><?php echo $displayEndpoint; ?></code>
                            </div>
                            <div class="tokens-right">
                                <button id="tokens-toggle-button" onclick="toggleTokensAccordion()">Abrir</button>
                            </div>
                        </div>

                        <div class="tokens-content" id="tokens-accordion-content" style="display:none;">
                            <div style="display:flex; gap:16px; align-items:flex-start;">
                                <div style="flex:2;">
                                    <h4>Crear nuevo token</h4>
                                    <form id="create-endpoint-form" onsubmit="return false;">
                                        <div class="endpoint-form-field">
                                            <label>Token (texto único):</label><br/>
                                            <input id="new-endpoint-token" type="text" placeholder="abc123" 
                                                pattern="[A-Za-z0-9]+" title="Solo letras A-Z (mayúsculas/minúsculas) y números" 
                                                maxlength="64" autocomplete="off"
                                                oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g, '')" />
                                        </div>               
                                        <div class="endpoint-form-field">
                                            <label>Etiqueta (opcional):</label><br/>
                                            <input id="new-endpoint-label" type="text" placeholder="Descripción" />
                                        </div>
                                        <button onclick="createEndpoint()"><img width="24" height="24" src="https://img.icons8.com/windows/32/add-file.png" alt="add-file"/></button>
                                    </form>
                                </div>
                                <div style="flex:3;">
                                    <h4>Tokens existentes</h4>
                                    <div id="endpoints-list">
                                        <!-- Lista dinámica de endpoints -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
    <div class="container">
        <div class="main-layout">
            <div class="webhooks-list">
                <div class="webhooks-list-title">
                    <div style="display: flex;align-items: center;">
                        <h3>Registros Capturados</h3> (&nbsp;<span class="stat-value" id="total-count">0</span>&nbsp;)
                    </div>
                    <div style="display: flex;">
                        <button onclick="refreshWebhooks()"><img width="24" height="24" src="https://img.icons8.com/windows/32/available-updates.png" alt="available-updates" title="Actualizar registros"/></button>
                        <button onclick="clearWebhooks()" style="margin-right: 10px;" class="btn-danger" title="Eliminar todos los registros de este token"><img width="24" height="24" src="https://img.icons8.com/windows/32/delete-trash.png" alt="delete-trash"/></button>
                    </div>
                </div>
                
                <div id="webhooks-container">
                    <!-- Lista de webhooks se cargará aquí -->
                </div>
                <div id="no-webhooks" style="display: none;">
                    <p><img width="24" height="24" src="https://img.icons8.com/windows/32/mailbox-closed-flag-down--v1.png" alt="mailbox-closed-flag-down--v1"/> No hay webhooks aún.</p>
                </div>
            </div>

            <div class="webhook-detail">
                <div id="detail-placeholder">
                    <div class="placeholder-content">
                        <h3><img width="24" height="24" src="https://img.icons8.com/windows/32/hand-left.png" alt="hand-left"/> Selecciona un registro</h3>
                        <p>Haz clic en cualquier registro de la izquierda para ver sus detalles completos</p>
                    </div>
                </div>
                <div id="detail-content" style="display: none;">
                    <!-- Detalles del webhook seleccionado -->
                </div>
            </div>
        </div>

        <div id="loading" style="display: none;">
            <p><img width="24" height="24" src="https://img.icons8.com/windows/32/hourglass--v1.png" alt="hourglass--v1"/> Cargando webhooks...</p>
        </div>
    </div>

    <script src="script.js"></script>
    <!-- Modal para configuración de respuesta por token -->
    <div id="response-modal" class="modal" style="display:none;">
        <div class="modal-backdrop" onclick="closeResponseModal()"></div>
        <div class="modal-content">
            <button onclick="closeResponseModal()" type="button" class="btn-close"><img width="24" height="24" src="https://img.icons8.com/windows/32/close-window.png" alt="close-window" title="Cerrar modal"/></button>
            <h3>Configurar respuesta para token <span id="modal-token-name"></span></h3>
            <form id="response-config-form" onsubmit="return false;">
                <label>Status code:</label><br/>
                <input id="resp-status" type="number" value="200" min="100" max="599" />
                <label>Content-Type:</label><br/>
                <input id="resp-ctype" type="text" value="application/json" />
                <label>Body:</label><br/>
                <textarea id="resp-body" rows="8" style="width:100%;"></textarea>
                <div style="display:flex; gap:8px; margin-top:8px; flex-direction: row-reverse;">
                    <button onclick="saveResponseConfig()"><img width="24" height="24" src="https://img.icons8.com/windows/32/chat-message-sent.png" alt="chat-message-sent" title="Guardar respuesta"/></button>
                    <button onclick="deleteResponseConfig()" class="btn-danger"><img width="24" height="24" src="https://img.icons8.com/windows/32/delete-chat--v1.png" alt="delete-chat--v1"title="Eliminar respuesta"/></button>
                </div>
            </form>
        </div>
    </div>
    <footer>
        <div class="site-footer"></div>
    </footer>
</body>
</html>