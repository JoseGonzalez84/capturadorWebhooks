// script.js - Funcionalidad JavaScript para el capturador de webhooks

let lastUpdateTime = '';
let autoRefreshInterval = null;
let webhooksCount = 0;
let selectedWebhookId = null;
let webhooksData = [];
let currentToken = '';

// Inicializar la aplicación cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar token desde querystring o input
    const urlParams = new URLSearchParams(window.location.search);
    currentToken = urlParams.get('token') || '';

    const tokenInput = document.getElementById('token-input');
    if (tokenInput) {
        if (!currentToken) currentToken = tokenInput.value || '';
        tokenInput.value = currentToken;
    }

    loadWebhooks();
    setupAutoRefresh();
    // Cargar endpoints disponibles
    loadEndpoints();
});

// Cargar lista de endpoints desde la API
async function loadEndpoints() {
    try {
        const resp = await fetch('api.php?action=list_endpoints');
        const data = await resp.json();
        if (data.status === 'success') {
            renderEndpointsList(data.data);
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
        <div class="endpoint-item">
            <div>
                #${ep.id} <strong>${escapeHtml(ep.token)}</strong>
                <div style="color:#888;font-size:12px;"> ${ep.label ? escapeHtml(ep.label) + ' · ' : ''}${ep.created_at}</div>
            </div>
            <div style="display:flex; gap:8px;">
                <button onclick="applyTokenFromList('${encodeURIComponent(JSON.stringify({token:ep.token}))}')"><img width="24" height="24" src="https://img.icons8.com/windows/32/clipboard-approve.png" alt="clipboard-approve"></button>
                <button onclick="deleteEndpoint(${ep.id}, '${ep.token}')" class="btn-danger"><img width="24" height="24" src="https://img.icons8.com/windows/32/delete-trash.png" alt="delete-trash"></button>
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

// Crear nuevo endpoint/token
async function createEndpoint() {
    const token = document.getElementById('new-endpoint-token').value.trim();
    const label = document.getElementById('new-endpoint-label').value.trim();
    if (!token) { alert('El token es requerido'); return; }

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
            alert('Token creado: ' + token);
        } else {
            alert('Error al crear token: ' + data.message);
        }
    } catch (err) {
        console.error('Error al crear endpoint:', err);
        alert('Error de red al crear token');
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
            alert('Error al borrar token: ' + data.message);
        }
    } catch (err) {
        console.error('Error al borrar endpoint:', err);
        alert('Error de red al borrar token');
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
                <img width="24" height="24" src="https://img.icons8.com/windows/32/link.png" alt="link"/>    
                <span>${truncateUrl(webhook.url, 40)}</span>
            </div>
            <div class="list-item-info">
                <span><img width="24" height="24" src="https://img.icons8.com/windows/32/globe-earth.png" alt="globe-earth"/> ${webhook.ip_address}</span>
                <span><img width="24" height="24" src="https://img.icons8.com/windows/32/parking-ticket.png" alt="parking-ticket"/> #${webhook.id}</span>
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

    placeholder.style.display = 'none';
    detailContent.style.display = 'block';

    detailContent.innerHTML = createWebhookDetailHTML(webhook);
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
                                    📋 Copiar
                                </button>
                                <button class="view-raw-button" onclick="toggleRaw(${webhook.id}, this)">
                                    View Raw
                                </button>
                            </div>
                            <pre class="code-block" id="payload-${webhook.id}" data-mode="${isJSON ? 'highlight' : 'raw'}">${isJSON ? syntaxHighlight(formattedBody) : escapeHtml(formattedBody)}</pre>
                        </div>
                </div>
                ` : '<div class="detail-section"><p><em>Sin contenido en el body</em></p></div>'}

                ${Object.keys(headers).length > 0 ? `
                <div class="detail-section">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <h4>Cabeceras HTTP</h4>
                        <span class="accordion-icon"><img width="24" height="24" src="https://img.icons8.com/windows/32/circled-chevron-right.png" alt="circled-chevron-right"/></span>
                    </div>
                    <div class="accordion-content">
                        <div class="headers-list">
                            ${Object.entries(headers).map(([name, value]) => `
                                <div class="header-item">
                                    <div class="header-name">${name}:</div>
                                    <div class="header-value">${value}</div>
                                </div>
                            `).join('')}
                        </div>
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
    const payloadElement = document.getElementById('payload-' + webhookId);
    const text = payloadElement.textContent;

    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            // Cambiar texto del botón temporalmente
            const originalText = button.innerHTML;
            button.innerHTML = '✓ Copiado';
            button.classList.add('copied');

            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('copied');
            }, 2000);
        }).catch(err => {
            console.error('Error al copiar:', err);
            alert('No se pudo copiar al portapapeles');
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
            button.innerHTML = '✓ Copiado';
            button.classList.add('copied');

            setTimeout(() => {
                button.innerHTML = '<img width="24" height="24" src="https://img.icons8.com/windows/32/copy.png" alt="copy"/> Copiar';
                button.classList.remove('copied');
            }, 2000);
        } catch (err) {
            alert('No se pudo copiar al portapapeles');
        }

        document.body.removeChild(textArea);
    }
}

// Actualizar estadísticas
function updateStats(count) {
    webhooksCount = count;
    document.getElementById('total-count').textContent = count;
    document.getElementById('last-update').textContent = new Date().toLocaleTimeString('es-ES');
}

// Mostrar/ocultar indicador de carga
function showLoading(show) {
    document.getElementById('loading').style.display = show ? 'block' : 'none';
}

// Configurar actualización automática
function setupAutoRefresh() {
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
    const url = document.getElementById('webhook-url').textContent;

    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
            alert('URL copiada al portapapeles: ' + url);
        });
    } else {
        // Fallback para navegadores más antiguos
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('URL copiada al portapapeles: ' + url);
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
            webhooksData = [];
            selectedWebhookId = null;
            updateStats(0);
            lastUpdateTime = '';
            alert('Todos los webhooks han sido eliminados correctamente.');
        } else {
            alert('Error al eliminar webhooks: ' + data.message);
        }
    } catch (error) {
        console.error('Error al eliminar webhooks:', error);
        alert('Error de red al eliminar webhooks.');
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
    const url = new URL(window.location.href);
    if (currentToken) {
        url.searchParams.set('token', currentToken);
    } else {
        url.searchParams.delete('token');
    }
    window.history.replaceState({}, '', url.toString());

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
    const pre = document.getElementById('payload-' + webhookId);
    if (!pre) return;

    const currentMode = pre.getAttribute('data-mode') || 'highlight';

    if (currentMode === 'highlight') {
        // Cambiar a raw: quitar spans y mostrar texto plano
        const text = pre.textContent;
        pre.textContent = text; // ya es texto plano
        pre.setAttribute('data-mode', 'raw');
        button.textContent = 'View Highlight';
    } else {
        // Cambiar a highlighted: intentar parsear JSON y aplicar syntaxHighlight
        const text = pre.textContent;
        try {
            const parsed = JSON.parse(text);
            const formatted = JSON.stringify(parsed, null, 2);
            pre.innerHTML = syntaxHighlight(formatted);
            pre.setAttribute('data-mode', 'highlight');
            button.textContent = 'View Raw';
        } catch (e) {
            // No es JSON válido, simplemente mantener texto
            alert('No es JSON válido para resaltar');
        }
    }
}