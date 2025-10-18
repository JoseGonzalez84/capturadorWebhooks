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
    <div class="container">
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
                                <div style="flex:1;">
                                    <h4>Crear nuevo token</h4>
                                    <form id="create-endpoint-form" onsubmit="return false;">
                                        <label>Token (texto único):</label><br/>
                         <input id="new-endpoint-token" type="text" placeholder="abc123" 
                             pattern="[A-Za-z0-9]+" title="Solo letras A-Z (mayúsculas/minúsculas) y números" 
                             maxlength="64" autocomplete="off"
                             oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g, '')" />
                                        <label>Etiqueta (opcional):</label><br/>
                                        <input id="new-endpoint-label" type="text" placeholder="Descripción" />
                                        <button onclick="createEndpoint()">Crear</button>
                                    </form>
                                </div>
                                <div style="flex:2;">
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

        <div class="stats">
            <div class="controls">
                <label>
                    <input type="checkbox" id="auto-refresh" checked>
                    Autoupdate (3 secs.)
                </label>
            </div>
            <div class="stat">
                <div>
                    <span class="stat-label">Última actualización:</span>
                    <span class="stat-value" id="last-update">-</span>
                </div>
                <div>
                    <button onclick="refreshWebhooks()"><img width="24" height="24" src="https://img.icons8.com/windows/32/available-updates.png" alt="available-updates"/></button>
                </div>
            </div>
            <div class="stat">
                <div>
                    <span class="stat-label">Total de Webhooks:</span>
                    <span class="stat-value" id="total-count">0</span>
                </div>
                <div>
                    <button onclick="clearWebhooks()" class="btn-danger"><img width="24" height="24" src="https://img.icons8.com/windows/32/delete-trash.png" alt="delete-trash"/></button>
                </div>
                
            </div>
        </div>

        <div class="main-layout">
            <div class="webhooks-list">
                <h3>Registros Capturados</h3>
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
            <h3>Configurar respuesta para token: <span id="modal-token-name"></span></h3>
            <form id="response-config-form" onsubmit="return false;">
                <label>Status code:</label><br/>
                <input id="resp-status" type="number" value="200" min="100" max="599" />
                <label>Content-Type:</label><br/>
                <input id="resp-ctype" type="text" value="application/json" />
                <label>Body:</label><br/>
                <textarea id="resp-body" rows="8" style="width:100%;"></textarea>
                <div style="display:flex; gap:8px; margin-top:8px;">
                    <button onclick="saveResponseConfig()">Guardar</button>
                    <button onclick="deleteResponseConfig()" class="btn-danger">Eliminar</button>
                    <button onclick="closeResponseModal()" type="button">Cerrar</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>