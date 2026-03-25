<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';

// Verificar autenticación
requireAuth();

require_once 'includes/header.php';

// enlace al CSS especializado
?>
<link rel="stylesheet" href="css/styleess.css">
<?php

// Obtener la fecha seleccionada o usar la fecha actual
$fecha_seleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Validar formato de fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_seleccionada)) {
    $fecha_seleccionada = date('Y-m-d');
}

// Obtener tareas del día seleccionado
$database = new Database();
$db = $database->getConnection();

$query = "SELECT t.*, c.nombre as cliente_nombre, v.marca, v.modelo 
          FROM turnos t 
          INNER JOIN clientes c ON t.cliente_id = c.id 
          INNER JOIN vehiculos v ON t.vehiculo_id = v.id 
          WHERE t.fecha = :fecha 
          ORDER BY t.hora_inicio";

$stmt = $db->prepare($query);
$stmt->bindParam(':fecha', $fecha_seleccionada);
$stmt->execute();
$turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar turnos por hora
$agenda = [];
foreach ($turnos as $turno) {
    $hora = date('H:i', strtotime($turno['hora_inicio']));
    if (!isset($agenda[$hora])) {
        $agenda[$hora] = [];
    }
    $agenda[$hora][] = $turno;
}

// Calcular fechas para navegación
$fecha_anterior = date('Y-m-d', strtotime($fecha_seleccionada . ' -1 day'));
$fecha_siguiente = date('Y-m-d', strtotime($fecha_seleccionada . ' +1 day'));

$hora_inicio = 8;
$hora_fin = 20;
$total_franjas = ($hora_fin - $hora_inicio) + 1;
$horas_ocupadas = count($agenda);
$horas_libres = max($total_franjas - $horas_ocupadas, 0);

// Timeline vertical (renglones fijos por hora, turnos sin repetirse)
$pixeles_por_minuto = 2.0;
$minutos_totales = ($hora_fin - $hora_inicio + 1) * 60;
$altura_timeline = (int)($minutos_totales * $pixeles_por_minuto);
$inicio_timeline_ts = strtotime($fecha_seleccionada . ' ' . str_pad((string)$hora_inicio, 2, '0', STR_PAD_LEFT) . ':00:00');
$fin_timeline_ts = strtotime('+'.$minutos_totales.' minutes', $inicio_timeline_ts);

$turnos_ordenados = $turnos;
usort($turnos_ordenados, static function(array $a, array $b): int {
    $aInicio = strtotime($a['hora_inicio']);
    $bInicio = strtotime($b['hora_inicio']);
    if ($aInicio === $bInicio) {
        return strtotime($a['hora_fin'] ?? $a['hora_inicio']) <=> strtotime($b['hora_fin'] ?? $b['hora_inicio']);
    }
    return $aInicio <=> $bInicio;
});

$fin_por_carril = [
    $inicio_timeline_ts,
    $inicio_timeline_ts,
    $inicio_timeline_ts,
    $inicio_timeline_ts,
];

$turnos_timeline = [];

foreach ($turnos_ordenados as $turno) {
    $inicio_ts = strtotime($fecha_seleccionada . ' ' . $turno['hora_inicio']);
    $hora_fin_turno = !empty($turno['hora_fin']) ? $turno['hora_fin'] : date('H:i:s', strtotime($turno['hora_inicio'] . ' +60 minutes'));
    $fin_ts = strtotime($fecha_seleccionada . ' ' . $hora_fin_turno);

    if ($fin_ts <= $inicio_ts) {
        $fin_ts = strtotime('+60 minutes', $inicio_ts);
    }

    // Recorte al rango visible del timeline
    $inicio_render_ts = max($inicio_ts, $inicio_timeline_ts);
    $fin_render_ts = min($fin_ts, $fin_timeline_ts);
    if ($fin_render_ts <= $inicio_render_ts) {
        continue;
    }

    $carril = null;
    for ($i = 0; $i < 4; $i++) {
        if ($inicio_ts >= $fin_por_carril[$i]) {
            $carril = $i;
            break;
        }
    }

    if ($carril === null) {
        $carril = array_search(min($fin_por_carril), $fin_por_carril, true);
    }

    $fin_por_carril[$carril] = max($fin_por_carril[$carril], $fin_ts);

    $offset_min = max(0, (int)(($inicio_render_ts - $inicio_timeline_ts) / 60));
    $duracion_min = max(30, (int)(($fin_render_ts - $inicio_render_ts) / 60));

    $turno['__carril'] = $carril;
    $turno['__top'] = (int)($offset_min * $pixeles_por_minuto);
    $turno['__height'] = max(140, (int)($duracion_min * $pixeles_por_minuto) - 2);
    $turno['__hora_inicio_fmt'] = date('H:i', $inicio_ts);
    $turno['__hora_fin_fmt'] = date('H:i', $fin_ts);
    $turnos_timeline[] = $turno;
}

$n8nWebhookUrl = defined('N8N_CHAT_WEBHOOK_URL') ? trim((string)N8N_CHAT_WEBHOOK_URL) : '';
$n8nTimeoutMs = defined('N8N_CHAT_TIMEOUT_MS') ? (int)N8N_CHAT_TIMEOUT_MS : 30000;
$chatUser = isset($_SESSION['username']) ? (string)$_SESSION['username'] : 'usuario';
?>



<div class="container">
    <h2>Agenda de Turnos</h2>
    
<!-- Selector de fecha compacto -->
<div class="date-selector-compact">
    <div class="date-header">
        <!-- Fecha actual -->
        <div class="current-date-compact">
            <?php 
            $fecha_formateada = date('d/m/Y', strtotime($fecha_seleccionada));
            $dia_semana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            $dia_index = date('w', strtotime($fecha_seleccionada));
            echo '<div class="week-day">' . $dia_semana[$dia_index] . '</div>';
            echo '<div class="date">' . $fecha_formateada . '</div>';
            ?>
        </div>

        <!-- Selector de calendario inline -->
        <div class="date-picker-compact">
        <input type="date" 
               id="fecha-calendario" 
               name="fecha" 
               value="<?php echo $fecha_seleccionada; ?>"
               class="compact-date-input"
               onchange="cambiarFecha()"
               title="Seleccionar fecha">
        </div>
        
        <!-- Navegación compacta -->
        <div class="nav-compact">
            <a href="?fecha=<?php echo $fecha_anterior; ?>" class="nav-arrow prev" title="Día anterior">
                <span>◀</span>
            </a>
            <a href="?" class="btn-today-compact" title="Ir a hoy">
                Hoy
            </a>
            <a href="?fecha=<?php echo $fecha_siguiente; ?>" class="nav-arrow next" title="Día siguiente">
                <span>▶</span>
            </a>
        </div>
    </div>    
    
    <!-- Estadísticas minimalistas -->
    <div class="compact-stats">
        <div class="stat-item">
            <span class="stat-label">Turnos:</span>
            <span class="stat-value"><?php echo count($turnos); ?></span>
        </div>
        <div class="stat-divider">•</div>
        <div class="stat-item">
            <span class="stat-label">Ocupadas:</span>
            <span class="stat-value"><?php echo $horas_ocupadas; ?>h</span>
        </div>
        <div class="stat-divider">•</div>
        <div class="stat-item">
            <span class="stat-label">Libres:</span>
            <span class="stat-value"><?php echo $horas_libres; ?>h</span>
        </div>
    </div>
</div>

    <div class="agenda-layout">
        <div class="agenda-title-row">
            <h3>
                <?php
                if ($fecha_seleccionada == date('Y-m-d')) {
                    echo "Turnos de Hoy";
                } else {
                    echo "Turnos del " . $fecha_formateada;
                }
                ?>
            </h3>
            <a href="turnos.php" class="btn btn-primary btn-sm">Gestionar Turnos</a>
        </div>

        <div class="agenda-timeline" style="--timeline-height: <?php echo $altura_timeline; ?>px;">
            <div class="timeline-labels">
                <?php for ($hora_actual = $hora_inicio; $hora_actual <= $hora_fin; $hora_actual++): ?>
                    <?php $top_hora = (int)(($hora_actual - $hora_inicio) * 60 * $pixeles_por_minuto); ?>
                    <div class="timeline-label" style="top: <?php echo $top_hora; ?>px;">
                        <?php echo str_pad((string)$hora_actual, 2, '0', STR_PAD_LEFT); ?>:00
                    </div>
                <?php endfor; ?>
            </div>

            <div class="timeline-board">
                <?php for ($hora_actual = $hora_inicio; $hora_actual <= $hora_fin; $hora_actual++): ?>
                    <?php $top_hora = (int)(($hora_actual - $hora_inicio) * 60 * $pixeles_por_minuto); ?>
                    <div class="timeline-line" style="top: <?php echo $top_hora; ?>px;"></div>
                <?php endfor; ?>

                <?php if (empty($turnos_timeline)): ?>
                    <div class="timeline-empty">
                        <span class="slot-badge">Libre</span>
                        <p>No hay turnos cargados para este día.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($turnos_timeline as $turno): ?>
                        <?php $estado_turno = isset($turno['estado']) && $turno['estado'] !== '' ? $turno['estado'] : 'Programado'; ?>
                        <article class="timeline-card" style="top: <?php echo (int)$turno['__top']; ?>px; height: <?php echo (int)$turno['__height']; ?>px; left: calc(<?php echo (int)$turno['__carril']; ?> * 25% + 6px); width: calc(25% - 12px);">
                            <div class="agenda-card-header">
                                <h4><?php echo htmlspecialchars($turno['cliente_nombre'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <span class="estado-pill"><?php echo htmlspecialchars($estado_turno, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>

                            <p class="timeline-time">
                                <strong><?php echo htmlspecialchars($turno['__hora_inicio_fmt'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($turno['__hora_fin_fmt'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class="mecanico-pill"><?php echo htmlspecialchars($turno['mecanico'] ?: 'Gerente de turno', ENT_QUOTES, 'UTF-8'); ?></span>
                            </p>
                            <p><strong>Vehículo:</strong> <?php echo htmlspecialchars($turno['marca'] . ' ' . $turno['modelo'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Servicio:</strong> <?php echo htmlspecialchars($turno['servicio'], ENT_QUOTES, 'UTF-8'); ?></p>



                            <?php if (!empty($turno['notas'])): ?>
                                <p class="timeline-note"><strong>Notas:</strong> <?php echo nl2br(htmlspecialchars($turno['notas'], ENT_QUOTES, 'UTF-8')); ?></p>
                            <?php endif; ?>

                            <div class="agenda-actions">
                                <a href="ordenes.php?turno_id=<?php echo $turno['id']; ?>" class="btn btn-secondary btn-sm">Crear Orden</a>
                                <a href="editar_turno.php?id=<?php echo $turno['id']; ?>" class="btn btn-primary btn-sm">Editar</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<button type="button" id="chatbot-toggle" class="chatbot-toggle" title="Abrir asistente">&#128172;</button>

<section
    id="chatbot-panel"
    class="chatbot-panel"
    data-webhook="<?php echo htmlspecialchars($n8nWebhookUrl, ENT_QUOTES, 'UTF-8'); ?>"
    data-timeout="<?php echo (int)$n8nTimeoutMs; ?>"
    data-username="<?php echo htmlspecialchars($chatUser, ENT_QUOTES, 'UTF-8'); ?>"
>
    <div class="chatbot-header">
        <span>Asistente STM</span>
        <button type="button" id="chatbot-close" class="chatbot-close" aria-label="Cerrar">x</button>
    </div>
    <div id="chatbot-status" class="chatbot-status">Conectado a n8n</div>
    <div id="chatbot-messages" class="chatbot-messages"></div>
    <div class="chatbot-input">
        <input type="text" id="chatbot-input" placeholder="Escribe una consulta..." maxlength="600">
        <button type="button" id="chatbot-send">Enviar</button>
    </div>
</section>

<script>
function cambiarFecha() {
    const fechaInput = document.getElementById('fecha-calendario');
    const fecha = fechaInput.value;
    
    if (fecha) {
        window.location.href = '?fecha=' + fecha;
    }
}

// Atajos de teclado
document.addEventListener('keydown', function(e) {
    // Flecha izquierda: día anterior
    if (e.key === 'ArrowLeft' && !e.target.matches('input, textarea')) {
        window.location.href = '?fecha=<?php echo $fecha_anterior; ?>';
    }
    
    // Flecha derecha: día siguiente
    if (e.key === 'ArrowRight' && !e.target.matches('input, textarea')) {
        window.location.href = '?fecha=<?php echo $fecha_siguiente; ?>';
    }
    
    // Tecla H: ir a hoy
    if (e.key === 'h' && !e.target.matches('input, textarea')) {
        window.location.href = '?';
    }
});

(function () {
    const panel = document.getElementById('chatbot-panel');
    const toggleBtn = document.getElementById('chatbot-toggle');
    const closeBtn = document.getElementById('chatbot-close');
    const sendBtn = document.getElementById('chatbot-send');
    const input = document.getElementById('chatbot-input');
    const messages = document.getElementById('chatbot-messages');
    const statusEl = document.getElementById('chatbot-status');

    if (!panel || !toggleBtn || !closeBtn || !sendBtn || !input || !messages || !statusEl) {
        return;
    }

    const webhookUrl = panel.dataset.webhook || '';
    const timeoutMs = parseInt(panel.dataset.timeout || '30000', 10);
    const username = panel.dataset.username || 'usuario';

    let firstOpen = true;

    function appendMessage(role, text) {
        const bubble = document.createElement('div');
        bubble.className = 'chat-msg ' + role;
        bubble.textContent = text;
        messages.appendChild(bubble);
        messages.scrollTop = messages.scrollHeight;
        return bubble;
    }

    function openChat() {
        panel.classList.add('open');
        if (firstOpen) {
            if (!webhookUrl) {
                statusEl.textContent = 'Chat sin configurar: define N8N_CHAT_WEBHOOK_URL en config.php';
            }
            appendMessage('bot', 'Hola, soy el asistente STM. Puedo ayudarte con manuales, turnos o dudas del sistema.');
            firstOpen = false;
        }
        input.focus();
    }

    function closeChat() {
        panel.classList.remove('open');
    }

    async function sendMessage() {
        const text = input.value.trim();
        if (!text) {
            return;
        }

        appendMessage('user', text);
        input.value = '';

        if (!webhookUrl) {
            appendMessage('bot', 'No hay endpoint configurado. Carga N8N_CHAT_WEBHOOK_URL para activar el chatbot.');
            return;
        }

        sendBtn.disabled = true;
        input.disabled = true;
        statusEl.textContent = 'Consultando agente n8n...';
        const thinking = appendMessage('bot', 'Procesando...');

        try {
            const controller = new AbortController();
            const timer = setTimeout(function () {
                controller.abort();
            }, timeoutMs);

            const response = await fetch(webhookUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message: text,
                    source: 'dashboard-stm',
                    user: username,
                    page: 'dashboard.php',
                    sent_at: new Date().toISOString()
                }),
                signal: controller.signal
            });
            clearTimeout(timer);

            let botReply = '';
            const contentType = response.headers.get('content-type') || '';
            if (contentType.indexOf('application/json') !== -1) {
                const json = await response.json();
                botReply = json.reply || json.response || json.answer || json.message || '';
                if (!botReply && Array.isArray(json) && json.length > 0) {
                    botReply = json[0].reply || json[0].response || json[0].message || '';
                }
            } else {
                botReply = await response.text();
            }

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            thinking.remove();
            appendMessage('bot', botReply || 'No recibi respuesta util del agente n8n.');
            statusEl.textContent = 'Conectado a n8n';
        } catch (error) {
            thinking.remove();
            appendMessage('bot', 'No pude conectar con el agente n8n. Verifica URL, SSL y firewall del VPS.');
            statusEl.textContent = 'Error de conexion';
        } finally {
            sendBtn.disabled = false;
            input.disabled = false;
            input.focus();
        }
    }

    toggleBtn.addEventListener('click', openChat);
    closeBtn.addEventListener('click', closeChat);
    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>