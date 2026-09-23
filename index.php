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
            body { font-family:'Noto Sans', 'Segoe UI', sans-serif; background:#191919; color:#e9e9e7; display:flex; align-items:center; justify-content:center; height:100vh; margin:0 }
            .login-box { background:#202020; border:1px solid #353535; padding:28px; border-radius:8px; box-shadow:0 20px 60px rgba(0,0,0,0.4); width:360px }
            label { display:block; margin-bottom:8px; color:#b5b5b0 }
            input[type=password] { width:100%; padding:10px; margin-bottom:12px; border:1px solid #353535; background:#252525; color:#e9e9e7; border-radius:5px; box-sizing:border-box }
            button { background:#d6b98c; color:#191919; padding:10px 14px; border:none; border-radius:5px; cursor:pointer; font-weight:600 }
            .error { color:#e48787; margin-bottom:12px }
            .info { font-size:12px; color:#898984; margin-top:8px }
            code { font-family:'JetBrains Mono', monospace; color:#d6b98c }
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
    <link rel="stylesheet" href="vendor/codemirror/lib/codemirror.min.css">
    <link rel="stylesheet" href="vendor/codemirror/codemirror-monaco.css">
    <link rel="shortcut icon" href="kraken.png" type="image/x-icon" />
</head>
<body>
    <header>
            <div id="header-container">
                <div id="header-container-left">
                    <h1><img width="64" height="64" src="kraken.png" alt="kraken"/> Capturador de Webhooks <span id="current-token-title"></span></h1>
                </div>
                <div id="header-container-right">
                    <?php
                    // Permitir seleccionar token vía querystring ?token=abc123 o usar uno vacío
                    $selectedToken = $_GET['token'] ?? '';
                    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                    $endpointExample = $baseUrl . '/webhooks/';
                    $displayEndpoint = $endpointExample . ($selectedToken ? $selectedToken : 'your_token_here');
                    ?>

                    <div class="tokens-toolbar">
                        <code id="token-endpoint-display"><?php echo $displayEndpoint; ?></code>
                        <img class="clickable button-action" onclick="copyToClipboard()" title="Copiar al portapapeles" width="32" height="32" src="https://img.icons8.com/liquid-glass-color/32/link.png" alt="link"/>
                        <button type="button" class="settings-button" onclick="openTokenSettingsModal()">Configuración</button>
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
                    <div style="display: flex; background: aliceblue; height: 100%; align-items: center; padding: 5px 0 5px 10px;">
                        <img class="clickable button-action" onclick="refreshWebhooks()" title="Actualizar registros" width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/connection-sync.png" alt="available-updates"/>
                        <img class="clickable button-critical" onclick="clearWebhooks()" title="Eliminar todos los registros de este token" style="margin-right: 10px;" width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/delete-forever.png" alt="delete-trash"/>
                    </div>
                </div>

                <div id="webhooks-container">
                    <!-- Lista de webhooks se cargará aquí -->
                </div>
                <div id="no-webhooks" style="display: none;">
                    <p><img width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/post-office.png" alt="no-data"/> No hay webhooks aún.</p>
                </div>
            </div>

            <div class="webhook-detail">
                <div id="detail-placeholder">
                    <div class="placeholder-content">
                        <h3><img width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/sell.png" alt="indication"/> Selecciona un registro</h3>
                        <p>Haz clic en cualquier registro de la izquierda para ver sus detalles completos</p>
                    </div>
                </div>
                <div id="detail-content" style="display: none;">
                    <!-- Detalles del webhook seleccionado -->
                </div>
            </div>
        </div>

        <div id="loading" style="display: none;">
            <p><img width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/historical.png" alt="hourglass--v1"/> Cargando webhooks...</p>
        </div>
    </div>

    <script src="vendor/codemirror/lib/codemirror.min.js"></script>
    <script src="vendor/codemirror/mode/javascript/javascript.min.js"></script>
    <script src="vendor/codemirror/addon/edit/closebrackets.min.js"></script>
    <script src="vendor/codemirror/addon/edit/matchbrackets.min.js"></script>
    <script src="script.js"></script>
    <div id="token-settings-modal" class="modal" style="display:none;">
        <div class="modal-backdrop" onclick="closeTokenSettingsModal()"></div>
        <div class="modal-content token-settings-content">
            <button type="button" class="modal-close" title="Cerrar ventana" onclick="closeTokenSettingsModal()">&times;</button>
            <h2>Configuración</h2>
            <div class="token-settings-grid">
                <section class="token-list-panel">
                    <h3>Tokens disponibles</h3>
                    <div id="endpoints-list"><!-- Lista dinámica de endpoints --></div>
                    <form id="create-endpoint-form" class="create-token-form" onsubmit="event.preventDefault(); createEndpoint();">
                        <h3>Crear nuevo token</h3>
                        <div class="endpoint-form-field">
                            <label for="new-endpoint-token">Token (texto único)</label>
                            <input id="new-endpoint-token" type="text" placeholder="abc123" pattern="[A-Za-z0-9]+" maxlength="64" autocomplete="off" oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g, '')" />
                        </div>
                        <div class="endpoint-form-field">
                            <label for="new-endpoint-label">Etiqueta (opcional)</label>
                            <input id="new-endpoint-label" type="text" placeholder="Descripción" />
                        </div>
                        <button type="submit" class="create-button">Crear</button>
                    </form>
                </section>
                <section class="response-config-panel">
                    <h3>Configuración de <strong id="modal-token-name">-</strong></h3>
                    <div class="method-config-header">
                        <label for="resp-methods">Métodos permitidos</label>
                        <select id="resp-methods" multiple size="3" onchange="handleAllowedMethodsChange()">
                            <option value="ALL" selected>Todos</option>
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PATCH">PATCH</option>
                            <option value="PUT">PUT</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                    </div>
                    <h4>Respuesta</h4>
                    <form id="response-config-form" onsubmit="return false;">
                        <div class="properties-endpoint-form">
                            <div class="endpoint-form-field">
                                <label for="resp-status">Código de respuesta</label>
                                <input id="resp-status" type="number" value="200" min="100" max="599">
                            </div>
                            <div class="endpoint-form-field">
                                <label for="resp-ctype">Tipo de respuesta</label>
                                <select id="resp-ctype">
                                    <option value="application/json">JSON</option>
                                    <option value="application/x-www-form-urlencoded">URL Encoded</option>
                                </select>
                            </div>
                        </div>
                        <div class="editor-label-row">
                            <label for="resp-body">Body de la respuesta</label>
                            <button type="button" class="editor-action" onclick="formatResponseJson()" title="Formatear como JSON">{ } Formatear JSON</button>
                        </div>
                        <textarea id="resp-body" rows="10"></textarea>
                        <div class="response-variables-help">
                            <strong>Variables dinámicas</strong>
                            <p>Se sustituyen cada vez que llega una llamada. La fecha usa el formato <code>DD-MM-YYYY</code> y el timestamp <code>YYYY-MM-DD HH:MM:SS</code>.</p>
                            <ul>
                                <li><code>%*CURRENT_DATE*%</code> Fecha actual.</li>
                                <li><code>%*TIMESTAMP*%</code> Timestamp actual.</li>
                                <li><code>%*RANDOM_DATE*%</code> Fecha aleatoria entre 1970 y hoy.</li>
                                <li><code>%*IDENTIFIER_X_YYY*%</code> Identificador aleatorio de X caracteres: <code>NUMERIC</code>, <code>ALPHA</code> o <code>ALL</code>.</li>
                            </ul>
                            <p>Ejemplo: <code>{"dato":"%*IDENTIFIER_8_ALL*%"}</code></p>
                        </div>
                        <div class="response-config-actions">
                            <button type="button" class="btn-danger" onclick="deleteResponseConfig()">Borrar configuración</button>
                            <button type="button" class="btn-action" onclick="saveResponseConfig()">Guardar configuración</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
    <footer>
        <div class="site-footer">
            <div style="display:flex;align-items:center;gap:12px;">
                <img src="kraken.png" width="48" height="48" alt="kraken"/>
                <div>
                    <div style="font-weight:700;">Capturador de Webhooks</div>
                    <div style="font-size:12px;color:#667eea;">Hecho por <a href="https://gadev.com.es/">gaDEV</a> en 2025.</div>
                </div>
            </div>

            <div style="text-align:center; font-size:14px; color:#334155;">
                <div>Software de API-Testing</div>
                <div style="font-size:12px;color:#667;">
                    <a href="https://github.com/JoseGonzalez84/capturadorWebhooks/"><img width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/github.png" alt="github"/></a>
                    <a href="www.linkedin.com/in/jose-gonzalez-silva"><img width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/linkedin.png" alt="linkedin-2"/></a>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:8px;">
                <a href="?logout=1" title="Cerrar sesión" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px; color:#e53e3e;">
                    <img width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/exit.png" alt="logout"/>
                    <span style="font-weight:600;color:#e53e3e;">Cerrar sesión</span>
                </a>
            </div>
        </div>
    </footer>
</body>
</html>