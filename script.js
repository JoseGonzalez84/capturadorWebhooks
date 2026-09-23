// script.js - Funcionalidad JavaScript para el capturador de webhooks

let lastUpdateTime = '';
let autoRefreshInterval = null;
let webhooksCount = 0;
let selectedWebhookId = null;
let webhooksData = [];
let currentToken = '';
let selectedConfigToken = '';
let responseEditor = null;
const payloadEditors = new Map();

// Inicializar la aplicación cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    initializeResponseEditor();
    // Inicializar token desde querystring, ruta /webhooks/view/<token> o input
    const urlParams = new URLSearchParams(window.location.search);
    currentToken = urlParams.get('token') || '';

    // Si no viene por querystring, intentar extraerlo de la ruta /webhooks/view/<token>
    if (!currentToken) {
        const m = window.location.pathname.match(/^\/webhooks\/view\/([^\/]+)\/?$/);
        if (m) {
            currentToken = decodeURIComponent(m[1]);
            // Mantener la URL limpia: usar la ruta /webhooks/view/<token> sin añadir ?token=
            const cleanPath = window.location.pathname.replace(/\/+$/, '');
            window.history.replaceState({}, '', cleanPath + (window.location.hash || ''));
        }
    }
    const tokenInput = document.getElementById('token-input');
    if (tokenInput) {
        if (!currentToken) currentToken = tokenInput.value || '';
        tokenInput.value = currentToken;
    }

    loadWebhooks();
    setupAutoRefresh();
    // Cargar endpoints disponibles
    loadEndpoints();
    // Actualizar visual del token actual en el encabezado
    const tokenCurrent = document.getElementById('token-current-value');
    if (tokenCurrent) tokenCurrent.textContent = currentToken || '-';
    updateCurrentTokenTitle();
    selectedConfigToken = currentToken;
});

function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'status');

    const messageElement = document.createElement('span');
    messageElement.className = 'toast-message';
    messageElement.textContent = message;

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'toast-close';
    closeButton.setAttribute('aria-label', 'Cerrar aviso');
    closeButton.innerHTML = '&times;';

    let dismissTimer;
    const dismiss = () => {
        window.clearTimeout(dismissTimer);
        toast.classList.remove('visible');
        window.setTimeout(() => toast.remove(), 220);
    };

    closeButton.addEventListener('click', dismiss);
    toast.append(messageElement, closeButton);
    container.appendChild(toast);
    window.setTimeout(() => toast.classList.add('visible'), 10);
    dismissTimer = window.setTimeout(dismiss, 5000);
}

function updateCurrentTokenTitle() {
    const title = document.getElementById('current-token-title');
    if (title) title.textContent = currentToken ? ` · Token: ${currentToken}` : '';
}

function openTokenSettingsModal() {
    const modal = document.getElementById('token-settings-modal');
    if (!modal) return;
    modal.style.display = 'flex';
    loadEndpoints();
    if (currentToken) loadResponseConfig(currentToken);
    else clearResponseConfigPanel();
}

function closeTokenSettingsModal() {
    const modal = document.getElementById('token-settings-modal');
    if (modal) modal.style.display = 'none';
}

// Cargar lista de endpoints desde la API
async function loadEndpoints() {
    try {
        const resp = await fetch('api.php?action=list_endpoints');
        const data = await resp.json();
        if (data.status === 'success') {
            renderEndpointsList(data.data);
            const tokenCurrent = document.getElementById('token-current-value');
            if (tokenCurrent) tokenCurrent.textContent = currentToken || '-';
        } else {
            console.error('Error al cargar endpoints:', data.message);
        }
    } catch (err) {
        console.error('Error de red al cargar endpoints:', err);
    }
}

function renderEndpointsList(endpoints) {
    const container = document.getElementById('endpoints-list');
    if (!container) return;

    if (!endpoints || endpoints.length === 0) {
        container.innerHTML = '<p>No hay tokens creados.</p>';
        return;
    }

    container.innerHTML = endpoints.map(ep => `
        <div class="endpoint-item${selectedConfigToken === ep.token ? ' active' : ''}" onclick="loadResponseConfig('${escapeHtml(ep.token)}')">
            <div class="endpoint-item-title" title="${escapeHtml(ep.token)}">
                <strong>${escapeHtml(ep.token)}</strong>
                <div style="color:#888;font-size:12px;"> ${ep.label ? escapeHtml(ep.label) + ' · ' : ''}${ep.created_at}</div>
            </div>
            <div class="endpoint-actions">
                <button type="button" class="settings-button" onclick="event.stopPropagation(); setToken('${escapeHtml(ep.token)}')">Establecer token</button>
                <button type="button" class="btn-danger" onclick="event.stopPropagation(); deleteEndpoint(${ep.id}, '${ep.token}')">Borrar</button>
            </div>
        </div>
    `).join('');
}

// Helper para aplicar token desde la lista (evita problemas con comillas)
function applyTokenFromList(encoded) {
    try {
        const obj = JSON.parse(decodeURIComponent(encoded));
        const tokenInput = document.getElementById('token-input');
        if (tokenInput) tokenInput.value = obj.token;
        applyToken();
    } catch (e) {
        console.error('Error al aplicar token desde lista', e);
    }
}

function setToken(token) {
    window.location.href = '/webhooks/view/' + encodeURIComponent(token);
}

function clearResponseConfigPanel() {
    document.getElementById('modal-token-name').textContent = '-';
    document.getElementById('resp-status').value = 200;
    document.getElementById('resp-ctype').value = 'application/json';
    setResponseEditorValue('');
}

function initializeResponseEditor() {
    const textarea = document.getElementById('resp-body');
    if (!textarea || typeof CodeMirror === 'undefined') return;

    responseEditor = CodeMirror.fromTextArea(textarea, {
        mode: { name: 'javascript', json: true },
        lineNumbers: true,
        lineWrapping: true,
        matchBrackets: true,
        autoCloseBrackets: true,
        tabSize: 2,
        indentUnit: 2,
        theme: 'monaco-local'
    });
}

function setResponseEditorValue(value) {
    if (responseEditor) responseEditor.setValue(value || '');
    else document.getElementById('resp-body').value = value || '';
}

function getResponseEditorValue() {
    return responseEditor ? responseEditor.getValue() : document.getElementById('resp-body').value;
}

function formatResponseJson() {
    const value = getResponseEditorValue().trim();
    if (!value) return;

    try {
        setResponseEditorValue(JSON.stringify(JSON.parse(value), null, 2));
        showToast('JSON formateado', 'success');
    } catch (error) {
        showToast('El body no contiene un JSON válido', 'error');
    }
}

async function loadResponseConfig(token) {
    selectedConfigToken = token;
    document.querySelectorAll('.endpoint-item').forEach(item => item.classList.remove('active'));
    document.querySelectorAll('.endpoint-item').forEach(item => {
        if (item.querySelector('strong')?.textContent === token) item.classList.add('active');
    });
    try {
        const resp = await fetch('api.php?action=get_response&token=' + encodeURIComponent(token));
        const data = await resp.json();
        if (data.status === 'success') {
            const cfg = data.data || {};
            document.getElementById('modal-token-name').textContent = token;
            document.getElementById('resp-status').value = cfg.status_code || 200;
            document.getElementById('resp-ctype').value = cfg.content_type || 'application/json';
            setResponseEditorValue(cfg.body || '');
            if (responseEditor) responseEditor.refresh();
        } else {
            console.error('Error al cargar config:', data.message);
        }
    } catch (err) {
        console.error('Error de red al cargar config:', err);
    }
}

async function saveResponseConfig() {
    const token = document.getElementById('modal-token-name').textContent;
    const status = parseInt(document.getElementById('resp-status').value) || 200;
    const ctype = document.getElementById('resp-ctype').value || 'application/json';
    const body = getResponseEditorValue() || '';

    try {
        const resp = await fetch('api.php?action=save_response', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: token, status: status, content_type: ctype, body: body })
        });
        const data = await resp.json();
        if (data.status === 'success') {
            showToast('Configuración guardada', 'success');
            closeTokenSettingsModal();
        } else {
            showToast('Error al guardar: ' + data.message, 'error');
        }
    } catch (err) {
        console.error('Error al guardar config:', err);
        showToast('Error de red al guardar', 'error');
    }
}

async function deleteResponseConfig() {
    if (!confirm('¿Eliminar la respuesta personalizada para este token?')) return;
    const token = document.getElementById('modal-token-name').textContent;
    try {
        const resp = await fetch('api.php?action=delete_response&token=' + encodeURIComponent(token));
        const data = await resp.json();
        if (data.status === 'success') {
            showToast('Configuración eliminada', 'success');
            setResponseEditorValue('');
            clearResponseConfigPanel();
        } else {
            showToast('Error al eliminar: ' + data.message, 'error');
        }
    } catch (err) {
        console.error('Error al eliminar config:', err);
        showToast('Error de red al eliminar', 'error');
    }
}

// Crear nuevo endpoint/token
async function createEndpoint() {
    const token = document.getElementById('new-endpoint-token').value.trim();
    const label = document.getElementById('new-endpoint-label').value.trim();
    if (!token) { showToast('El token es requerido', 'error'); return; }

    // Validación: solo permitir A-Z a-z 0-9
    if (!/^[A-Za-z0-9]+$/.test(token)) {
        showToast('El token solo puede contener letras y números (A-Z, a-z, 0-9)', 'error');
        return;
    }

    try {
        const resp = await fetch('api.php?action=create_endpoint', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: token, label: label })
        });
        const data = await resp.json();
        if (data.status === 'success') {
            document.getElementById('new-endpoint-token').value = '';
            document.getElementById('new-endpoint-label').value = '';
            loadEndpoints();
            showToast('Token creado: ' + token, 'success');
        } else {
            showToast('Error al crear token: ' + data.message, 'error');
        }
    } catch (err) {
        console.error('Error al crear endpoint:', err);
        showToast('Error de red al crear token', 'error');
    }
}

// Borrar endpoint por id (y opcionalmente limpiar registros)
async function deleteEndpoint(id, token) {
    if (!confirm('¿Borrar token ' + token + ' y opcionalmente sus registros?')) return;

    try {
        const resp = await fetch('api.php?action=delete_endpoint&id=' + encodeURIComponent(id));
        const data = await resp.json();
        if (data.status === 'success') {
            // Preguntar si también borrar registros asociados
            if (confirm('¿Eliminar también los webhooks asociados a este token?')) {
                await fetch('api.php?action=clear_webhooks&token=' + encodeURIComponent(token));
            }
            loadEndpoints();
            // Si el token borrado estaba aplicado, limpiarlo
            if (currentToken === token) {
                const tokenInput = document.getElementById('token-input');
                if (tokenInput) tokenInput.value = '';
                applyToken();
            }
        } else {
            showToast('Error al borrar token: ' + data.message, 'error');
        }
    } catch (err) {
        console.error('Error al borrar endpoint:', err);
        showToast('Error de red al borrar token', 'error');
    }
}

// Cargar webhooks desde la API
async function loadWebhooks() {
    try {
    showLoading(true);
    let apiUrl = 'api.php?action=get_webhooks&limit=50';
    if (currentToken) apiUrl += '&token=' + encodeURIComponent(currentToken);
    const response = await fetch(apiUrl);
        const data = await response.json();

        if (data.status === 'success') {
            displayWebhooks(data.data);
            updateStats(data.data.length);

            if (data.data.length > 0) {
                lastUpdateTime = data.data[0].timestamp;
            }
        } else {
            console.error('Error al cargar webhooks:', data.message);
        }
    } catch (error) {
        console.error('Error de red:', error);
    } finally {
        showLoading(false);
    }
}

// Cargar solo webhooks nuevos
async function loadNewWebhooks() {
    if (!lastUpdateTime) return;

    try {
    let apiUrl = `api.php?action=get_new_webhooks&since=${encodeURIComponent(lastUpdateTime)}`;
    if (currentToken) apiUrl += '&token=' + encodeURIComponent(currentToken);
    const response = await fetch(apiUrl);
        const data = await response.json();

        if (data.status === 'success' && data.data.length > 0) {
            prependWebhooks(data.data);
            webhooksCount += data.data.length;
            updateStats(webhooksCount);
            lastUpdateTime = data.data[0].timestamp;
        }
    } catch (error) {
        console.error('Error al cargar webhooks nuevos:', error);
    }
}

// Mostrar webhooks en la interfaz
function displayWebhooks(webhooks) {
    const container = document.getElementById('webhooks-container');
    const noWebhooks = document.getElementById('no-webhooks');

    webhooksData = webhooks;

    if (webhooks.length === 0) {
        container.innerHTML = '';
        noWebhooks.style.display = 'block';
        return;
    }

    noWebhooks.style.display = 'none';
    container.innerHTML = webhooks.map(webhook => createWebhookListItemHTML(webhook)).join('');

    // Seleccionar el primero por defecto
    if (!selectedWebhookId && webhooks.length > 0) {
        selectWebhook(webhooks[0].id);
    }
}

// Agregar webhooks nuevos al principio
function prependWebhooks(webhooks) {
    const container = document.getElementById('webhooks-container');
    const noWebhooks = document.getElementById('no-webhooks');

    noWebhooks.style.display = 'none';

    webhooksData = [...webhooks, ...webhooksData];

    const newHTML = webhooks.map(webhook => createWebhookListItemHTML(webhook)).join('');
    container.innerHTML = newHTML + container.innerHTML;
}

// Crear HTML para un item de la lista (simplificado)
function createWebhookListItemHTML(webhook) {
    const timestamp = new Date(webhook.timestamp).toLocaleString('es-ES', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });

    const isSelected = selectedWebhookId === webhook.id ? 'selected' : '';

    return `
        <div class="webhook-list-item ${isSelected}" onclick="selectWebhook(${webhook.id})" data-webhook-id="${webhook.id}">
            <div class="list-item-header">
                <div>
                    <span class="list-item-method method-${webhook.method}">${webhook.method}</span>
                </div>
                <span class="list-item-timestamp">${timestamp}</span>
            </div>
            <div class="list-item-url">
                <img width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/link.png" alt="link"/>
                <span>${truncateUrl(webhook.url, 40)}</span>
            </div>
            <div class="list-item-info">
                <span><img width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/globe.png" alt="globe"/> ${webhook.ip_address}</span>
                <span><img width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/train-ticket.png" alt="train-ticket"/> #${webhook.id}</span>
            </div>
        </div>
    `;
}

// Truncar URL para la lista
function truncateUrl(url, maxLength) {
    if (url.length <= maxLength) return url;
    return url.substring(0, maxLength - 3) + '...';
}

// Seleccionar un webhook y mostrar sus detalles
function selectWebhook(webhookId) {
    selectedWebhookId = webhookId;

    // Actualizar selección visual en la lista
    document.querySelectorAll('.webhook-list-item').forEach(item => {
        if (parseInt(item.dataset.webhookId) === webhookId) {
            item.classList.add('selected');
        } else {
            item.classList.remove('selected');
        }
    });

    // Buscar el webhook en los datos
    const webhook = webhooksData.find(w => w.id === webhookId);

    if (webhook) {
        displayWebhookDetail(webhook);
    }
}

// Mostrar detalles del webhook seleccionado
function displayWebhookDetail(webhook) {
    const detailContent = document.getElementById('detail-content');
    const placeholder = document.getElementById('detail-placeholder');

    payloadEditors.forEach(editor => editor.toTextArea());
    payloadEditors.clear();
    placeholder.style.display = 'none';
    detailContent.style.display = 'block';

    detailContent.innerHTML = createWebhookDetailHTML(webhook);
    initializePayloadEditor(webhook, detailContent);
}

function initializePayloadEditor(webhook, container) {
    const textarea = container.querySelector('#payload-' + webhook.id);
    if (!textarea || typeof CodeMirror === 'undefined') return;

    const editor = CodeMirror.fromTextArea(textarea, {
        mode: textarea.dataset.mode === 'highlight' ? { name: 'javascript', json: true } : null,
        readOnly: true,
        lineNumbers: true,
        lineWrapping: false,
        matchBrackets: true,
        theme: 'monaco-local'
    });
    payloadEditors.set(webhook.id, editor);
}

// Crear HTML para un webhook individual
function createWebhookDetailHTML(webhook) {
    const headers = JSON.parse(webhook.headers || '{}');
    const timestamp = new Date(webhook.timestamp).toLocaleString('es-ES');

    // Formatear el body
    let formattedBody = webhook.body;
    let isJSON = false;

    if (webhook.body) {
        // Intentar parsear como JSON
        try {
            const parsed = JSON.parse(webhook.body);
            formattedBody = JSON.stringify(parsed, null, 2);
            isJSON = true;
        } catch (e) {
            // Si no es JSON válido, intentar detectar si parece JSON
            if (webhook.body.trim().startsWith('{') || webhook.body.trim().startsWith('[')) {
                isJSON = true;
            }
        }
    }

    return `
        <div class="webhook-item">
            <div class="webhook-header">
                <div>
                    <span class="webhook-method method-${webhook.method}">${webhook.method}</span>
                    <span class="webhook-url">${webhook.url}</span>
                </div>
                <div class="webhook-timestamp">${timestamp}</div>
            </div>
            <div class="webhook-details">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">IP de Origen</div>
                        <div class="info-value">${webhook.ip_address}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Content-Type</div>
                        <div class="info-value">${webhook.content_type || 'No especificado'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">User-Agent</div>
                        <div class="info-value">${webhook.user_agent || 'No especificado'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">ID</div>
                        <div class="info-value">#${webhook.id}</div>
                    </div>
                </div>

                ${webhook.body ? `
                <div class="detail-section">
                    <h4>Payload / Body</h4>
                    <div class="payload-container">
                            <div class="payload-toolbar">
                                <button class="copy-button" onclick="copyPayload(this, ${webhook.id})">
                                    <img width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/copy.png" alt="copy"/>
                                </button>
                                <button class="view-raw-button" onclick="toggleRaw(${webhook.id}, this)">
                                    <img width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/view.png" alt="raw"/>
                                </button>
                            </div>
                            <textarea class="code-block" id="payload-${webhook.id}" data-mode="${isJSON ? 'highlight' : 'raw'}">${escapeHtml(formattedBody)}</textarea>
                        </div>
                </div>
                ` : '<div class="detail-section"><p><em>Sin contenido en el body</em></p></div>'}

                ${Object.keys(headers).length > 0 ? `
                <div class="detail-section">
                    <h4>Cabeceras HTTP</h4>
                    <div class="headers-list">
                        ${Object.entries(headers).map(([name, value]) => `
                            <div class="header-item">
                                <div class="header-name">${name}:</div>
                                <div class="header-value">${value}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}

            </div>
        </div>
    `;
}

// Escapar HTML para mostrar contenido de forma segura
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Resaltar sintaxis JSON
function syntaxHighlight(json) {
    if (typeof json !== 'string') {
        json = JSON.stringify(json, null, 2);
    }

    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
        let cls = 'json-number';
        if (/^"/.test(match)) {
            if (/:$/.test(match)) {
                cls = 'json-key';
            } else {
                cls = 'json-string';
            }
        } else if (/true|false/.test(match)) {
            cls = 'json-boolean';
        } else if (/null/.test(match)) {
            cls = 'json-null';
        }
        return '<span class="' + cls + '">' + match + '</span>';
    });
}

// Copiar payload al portapapeles
function copyPayload(button, webhookId) {
    const editor = payloadEditors.get(webhookId);
    const payloadElement = document.getElementById('payload-' + webhookId);
    const text = editor ? editor.getValue() : payloadElement.value;

    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            // Cambiar icono del botón temporalmente a check, manteniendo el estilo
            const originalHTML = button.innerHTML;
            button.innerHTML = '<img width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/order-completed.png" alt="copied"/>';
            button.classList.add('copied');

            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('copied');
            }, 1500);
        }).catch(err => {
            console.error('Error al copiar:', err);
            showToast('No se pudo copiar al portapapeles', 'error');
        });
    } else {
        // Fallback para navegadores antiguos
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.select();

        try {
            document.execCommand('copy');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<img width="24" height="24" src="https://img.icons8.com/liquid-glass-color/32/order-completed.png" alt="copied"/>';
            button.classList.add('copied');

            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('copied');
            }, 1500);
        } catch (err) {
            showToast('No se pudo copiar al portapapeles', 'error');
        }

        document.body.removeChild(textArea);
    }
}

// Actualizar estadísticas
function updateStats(count) {
    webhooksCount = count;
    document.getElementById('total-count').textContent = count;
    //document.getElementById('last-update').textContent = new Date().toLocaleTimeString('es-ES');
}

// Mostrar/ocultar indicador de carga
function showLoading(show) {
    document.getElementById('loading').style.display = show ? 'block' : 'none';
}

// Configurar actualización automática. Establecido a TRUE por defecto;
function setupAutoRefresh() {
    startAutoRefresh();
    /*
    const checkbox = document.getElementById('auto-refresh');

    checkbox.addEventListener('change', function() {
        if (this.checked) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });

    // Iniciar automáticamente si está marcado
    if (checkbox.checked) {
        startAutoRefresh();
    }
    */
}

// Iniciar actualización automática
function startAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }

    autoRefreshInterval = setInterval(() => {
        loadNewWebhooks();
    }, 3000); // Cada 3 segundos
}

// Detener actualización automática
function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

// Actualizar manualmente
function refreshWebhooks() {
    lastUpdateTime = '';
    loadWebhooks();
}

// Copiar URL del webhook al portapapeles
function copyToClipboard() {
    const url = document.getElementById('token-endpoint-display').textContent;

    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
            showToast('URL copiada al portapapeles', 'success');
        });
    } else {
        // Fallback para navegadores más antiguos
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('URL copiada al portapapeles', 'success');
    }
}

// Limpiar todos los webhooks
async function clearWebhooks() {
    if (!confirm('¿Estás seguro de que quieres eliminar todos los webhooks? Esta acción no se puede deshacer.')) {
        return;
    }

    try {
    let apiUrl = 'api.php?action=clear_webhooks';
    if (currentToken) apiUrl += '&token=' + encodeURIComponent(currentToken);
    const response = await fetch(apiUrl);
        const data = await response.json();

        if (data.status === 'success') {
            document.getElementById('webhooks-container').innerHTML = '';
            document.getElementById('no-webhooks').style.display = 'block';
            document.getElementById('detail-content').style.display = 'none';
            document.getElementById('detail-placeholder').style.display = 'flex';
            payloadEditors.forEach(editor => editor.toTextArea());
            payloadEditors.clear();
            webhooksData = [];
            selectedWebhookId = null;
            updateStats(0);
            lastUpdateTime = '';
            showToast('Todos los webhooks han sido eliminados correctamente.', 'success');
        } else {
            showToast('Error al eliminar webhooks: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error al eliminar webhooks:', error);
        showToast('Error de red al eliminar webhooks.', 'error');
    }
}

// Función para toggle del acordeón
function toggleAccordion(headerElement) {
    const content = headerElement.nextElementSibling;
    const icon = headerElement.querySelector('.accordion-icon');

    // Toggle de las clases
    content.classList.toggle('open');
    icon.classList.toggle('open');
}

// Aplicar el token introducido por el usuario y actualizar la URL/endpoint
function applyToken() {
    const tokenInput = document.getElementById('token-input');
    currentToken = tokenInput ? tokenInput.value.trim() : '';

    // Actualizar la URL en el navegador (sin recargar)
    // Actualizar la URL en el navegador (sin recargar): usar /webhooks/view/<token> para mantener limpio el URL
    const origin = window.location.origin || (window.location.protocol + '//' + window.location.host);
    if (currentToken) {
        const newPath = '/webhooks/view/' + encodeURIComponent(currentToken);
        window.history.replaceState({}, '', origin + newPath + (window.location.hash || ''));
    } else {
        // volver a la vista principal sin token
        const newPath = '/webhooks/';
        window.history.replaceState({}, '', origin + newPath + (window.location.hash || ''));
    }

    // Actualizar visual del endpoint
    const webhookUrlEl = document.getElementById('webhook-url');
    if (webhookUrlEl) {
        const base = webhookUrlEl.textContent.split('/webhooks/')[0];
        webhookUrlEl.textContent = base + '/webhooks/' + (currentToken || 'your_token_here');
    }

    // Recargar registros para el token seleccionado
    lastUpdateTime = '';
    loadWebhooks();
}

// Alternar vista raw / highlighted para el payload
function toggleRaw(webhookId, button) {
    const editor = payloadEditors.get(webhookId);
    if (!editor) return;

    const currentMode = editor.getOption('mode') ? 'highlight' : 'raw';

    if (currentMode === 'highlight') {
        // Cambiar a raw: quitar spans y mostrar texto plano
        editor.setOption('mode', null);
        // Cambiar icono del botón a indicate highlight is available (use an eye icon for raw and a code icon for highlight)
        button.innerHTML = '<img width="24" height="24" src="https://img.icons8.com/windows/32/show-property.png" alt="view-raw"/>';
    } else {
        // Cambiar a highlighted: intentar parsear JSON y aplicar syntaxHighlight
        const text = editor.getValue();
        try {
            const parsed = JSON.parse(text);
            const formatted = JSON.stringify(parsed, null, 2);
            editor.setValue(formatted);
            editor.setOption('mode', { name: 'javascript', json: true });
            button.innerHTML = '<img width="24" height="24" src="https://img.icons8.com/windows/32/raw.png" alt="view-raw"/>';
        } catch (e) {
            // No es JSON válido, simplemente mantener texto
            showToast('No es JSON válido para resaltar', 'error');
        }
    }
}