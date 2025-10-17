<!DOCTYPE html>
<html lang="es">
<head>
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
                                <strong><span id="token-current-value"><?php echo htmlspecialchars($selectedToken); ?></span></strong>
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
                                        <input id="new-endpoint-token" type="text" placeholder="abc123" />
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
</body>
</html>