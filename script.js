// script.js - Funcionalidad JavaScript para el capturador de webhooks

let lastUpdateTime = '';
let autoRefreshInterval = null;
let webhooksCount = 0;
let selectedWebhookId = null;
let webhooksData = [];

// Inicializar la aplicación cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    loadWebhooks();
    setupAutoRefresh();
});

// Cargar webhooks desde la API
async function loadWebhooks() {
    try {
        showLoading(true);
        const response = await fetch('api.php?action=get_webhooks&limit=50');
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
        const response = await fetch(`api.php?action=get_new_webhooks&since=${encodeURIComponent(lastUpdateTime)}`);
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

                ${Object.keys(headers).length > 0 ? `
                <div class="detail-section">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <h4>Cabeceras HTTP</h4>
                        <span class="accordion-icon"><img width="32" height="32" src="https://img.icons8.com/windows/32/circled-chevron-right.png" alt="circled-chevron-right"/></span>
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

                ${webhook.body ? `
                <div class="detail-section">
                    <h4>Payload / Body</h4>
                    <div class="payload-container">
                        <button class="copy-button" onclick="copyPayload(this, ${webhook.id})">
                            📋 Copiar
                        </button>
                        <div class="code-block" id="payload-${webhook.id}">${isJSON ? syntaxHighlight(formattedBody) : escapeHtml(formattedBody)}</div>
                    </div>
                </div>
                ` : '<div class="detail-section"><p><em>Sin contenido en el body</em></p></div>'}
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
                button.innerHTML = '<img width="32" height="32" src="https://img.icons8.com/windows/32/copy.png" alt="copy"/> Copiar';
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
        const response = await fetch('api.php?action=clear_webhooks');
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