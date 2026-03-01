<?php
/**
 * RAPCA - Interfaz del Operador de Campo
 *
 * Pantalla principal del operador:
 * 1. Selección de infraestructura (solo las asignadas al operador)
 * 2. Selección de unidad de obra
 * 3. Fotos Aleatorias y Comparativas (con ghosting)
 * 4. Watermark con coordenadas ETRS89
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$usuarioId = isset($_GET['user']) ? (int) $_GET['user'] : (int) ($_SESSION['user_id'] ?? 0);

$pdo = getDB();

$userName = 'Operador';
if ($usuarioId > 0) {
    $stmt = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $usuarioId]);
    $row = $stmt->fetch();
    if ($row) $userName = $row['nombre'];
}

$initials = '';
$nameParts = explode(' ', trim($userName));
foreach (array_slice($nameParts, 0, 2) as $part) {
    if (mb_strlen($part) > 0) $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
if ($initials === '') $initials = 'OP';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#4f6ef7">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="RAPCA">
    <title>RAPCA - Operador de Campo</title>
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="css/operador.css">
</head>
<body>
    <!-- PANTALLA 1: FICHA DE VISITA -->
    <div id="screen-ficha" class="screen active">
        <div class="ficha-header">
            <div class="ficha-brand">
                <div class="brand-logo"><i class="bi bi-geo-alt-fill"></i></div>
                <div class="brand-text">
                    <strong>RAPCA</strong>
                    <span>Operador de Campo</span>
                </div>
            </div>
            <div class="ficha-header-right">
                <div id="offline-indicator" class="offline-indicator online">
                    <div id="offline-dot" class="offline-dot online"></div>
                    <span id="offline-text">En linea</span>
                </div>
                <div class="user-avatar" title="<?= htmlspecialchars($userName) ?>"><?= $initials ?></div>
            </div>
        </div>

        <div class="ficha-body">
            <div id="install-banner" class="install-banner hidden">
                <div class="install-banner-content">
                    <div class="install-banner-icon"><i class="bi bi-download"></i></div>
                    <div class="install-banner-text">
                        <strong>Instalar RAPCA</strong>
                        <small>Acceso directo desde tu pantalla de inicio</small>
                    </div>
                </div>
                <div class="install-banner-actions">
                    <button type="button" id="btn-install-app" class="install-btn"><i class="bi bi-phone-fill"></i> Instalar App</button>
                    <button type="button" id="btn-install-dismiss" class="install-dismiss"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>

            <div class="card">
                <div class="card-label"><i class="bi bi-pin-map-fill"></i> Ubicacion</div>
                <div class="filter-row">
                    <select id="filter-provincia" class="input-field"><option value="">Todas las provincias</option></select>
                    <select id="filter-municipio" class="input-field" disabled><option value="">Todos los municipios</option></select>
                </div>
            </div>

            <div class="card">
                <div class="card-label"><i class="bi bi-building"></i> Infraestructura</div>
                <div class="search-container">
                    <div class="search-input-wrap">
                        <i class="bi bi-search search-icon-left"></i>
                        <input type="text" id="infra-search" placeholder="Buscar infraestructura..."
                               autocomplete="off" spellcheck="false" class="input-field input-with-icon">
                    </div>
                    <div id="infra-results" class="search-results hidden"></div>
                </div>
                <input type="hidden" id="infra-id" value="">
                <div id="infra-selected" class="selected-badge hidden">
                    <div class="selected-badge-left">
                        <i class="bi bi-check-circle-fill"></i>
                        <span id="infra-selected-name"></span>
                    </div>
                    <button type="button" id="infra-clear" class="clear-btn"><i class="bi bi-x-lg"></i></button>
                </div>
                <div id="precache-indicator" class="precache-indicator hidden"><i class="bi bi-cloud-check"></i> Fotos precargadas</div>
                <button type="button" id="btn-precache" class="precache-btn"><i class="bi bi-cloud-download"></i> Precargar fotos offline</button>
            </div>

            <div class="card">
                <div class="card-label"><i class="bi bi-tools"></i> Unidad de Obra</div>
                <select id="unidad-obra" class="input-field"><option value="">Seleccionar unidad de obra</option></select>
            </div>

            <div class="card">
                <div class="card-row">
                    <div class="card-row-item">
                        <div class="card-label"><i class="bi bi-calendar3"></i> Fecha</div>
                        <div class="fecha-display" id="fecha-display"></div>
                    </div>
                </div>
                <div class="card-separator"></div>
                <div class="card-label"><i class="bi bi-chat-text"></i> Observaciones</div>
                <textarea id="observaciones-general" placeholder="Notas generales de la visita..." rows="2" class="input-field input-textarea"></textarea>
            </div>

            <div class="card">
                <div class="card-label"><i class="bi bi-flag-fill"></i> Situación de la obra</div>
                <div class="situacion-selector" id="situacion-selector">
                    <button type="button" class="situacion-option active" data-sit="0"><i class="bi bi-eye"></i> VP (Visita Previa)</button>
                    <button type="button" class="situacion-option" data-sit="1"><i class="bi bi-clipboard-check"></i> EV (Evaluación)</button>
                </div>
            </div>

            <div id="hint-select-infra" class="hint-box">
                <i class="bi bi-info-circle"></i>
                <span>Selecciona una infraestructura para habilitar las fotos</span>
            </div>

            <div class="foto-buttons">
                <button type="button" id="btn-fotos-aleatorias" class="foto-btn foto-btn--aleatorio" disabled>
                    <div class="foto-btn-icon"><i class="bi bi-camera-fill"></i></div>
                    <div class="foto-btn-text"><strong>Fotos Aleatorias</strong><small>Fotos libres con GPS ETRS89</small></div>
                    <span class="foto-count" id="count-aleatorias">0</span>
                </button>
                <button type="button" id="btn-fotos-comparativas" class="foto-btn foto-btn--comparativo" disabled>
                    <div class="foto-btn-icon"><i class="bi bi-layers-fill"></i></div>
                    <div class="foto-btn-text"><strong>Fotos Comparativas</strong><small>Ghosting con foto anterior</small></div>
                    <span class="foto-count" id="count-comparativas">0</span>
                </button>
                <button type="button" id="btn-ver-mapa" class="foto-btn foto-btn--mapa">
                    <div class="foto-btn-icon"><i class="bi bi-map-fill"></i></div>
                    <div class="foto-btn-text"><strong>Mapa de Visitas</strong><small>Ver ubicaciones anteriores</small></div>
                    <span class="foto-count"><i class="bi bi-chevron-right"></i></span>
                </button>
                <button type="button" id="btn-mis-visitas" class="foto-btn foto-btn--visitas">
                    <div class="foto-btn-icon"><i class="bi bi-journal-text"></i></div>
                    <div class="foto-btn-text"><strong>Mis Visitas</strong><small>Ver y editar registros anteriores</small></div>
                    <span class="foto-count"><i class="bi bi-chevron-right"></i></span>
                </button>
                <button type="button" id="btn-panel-registros" class="foto-btn foto-btn--panel">
                    <div class="foto-btn-icon"><i class="bi bi-clipboard-data"></i></div>
                    <div class="foto-btn-text"><strong>Panel</strong><small>Registros, exportar Excel/PDF, borrar datos</small></div>
                    <span class="foto-count" id="count-panel">0</span>
                </button>
            </div>

            <div id="sync-bar" class="sync-bar hidden">
                <div class="sync-bar-info"><i class="bi bi-cloud-arrow-up"></i><span><span id="sync-count">0</span> foto(s) pendiente(s)</span></div>
                <div class="sync-bar-actions">
                    <div class="sync-progress"><div id="sync-progress-bar" class="sync-progress-fill"></div></div>
                    <span id="sync-progress-text" class="sync-progress-text"></span>
                    <button type="button" id="btn-manual-sync" class="sync-btn"><i class="bi bi-arrow-repeat"></i> Sincronizar</button>
                </div>
            </div>

            <div id="gallery-section" class="hidden">
                <h3 class="gallery-title"><i class="bi bi-images"></i> Fotos de esta visita</h3>
                <div id="gallery-grid" class="gallery-grid"></div>
            </div>
        </div>

        <div id="guardar-visita-section" class="guardar-visita-fixed hidden">
            <button type="button" id="btn-guardar-visita" class="btn-finalizar-visita"><i class="bi bi-check-circle-fill"></i> Finalizar visita</button>
        </div>
    </div>

    <!-- PANTALLA 2: CAMARA -->
    <div id="screen-camera" class="screen">
        <div class="cam-topbar">
            <button type="button" id="btn-cam-back" class="cam-btn-back"><i class="bi bi-arrow-left"></i></button>
            <div class="cam-info">
                <span id="cam-infra-name">--</span>
                <span id="cam-mode-badge" class="cam-mode-badge">ALEATORIO</span>
            </div>
            <span id="cam-time">--:--</span>
        </div>
        <div class="cam-gps">
            <div class="cam-gps-dot" id="cam-gps-dot"></div>
            <span id="cam-gps-text">ETRS89: --</span>
        </div>
        <div id="cam-compass" class="cam-compass hidden">
            <i class="bi bi-compass"></i>
            <span id="cam-compass-bearing">--°</span>
        </div>
        <div id="cam-minimap" class="cam-minimap hidden"></div>
        <video id="cam-video" autoplay playsinline></video>
        <img id="cam-ghost" src="" alt="" class="cam-ghost">
        <canvas id="cam-capture" class="hidden-canvas"></canvas>
        <div id="cam-seq-counter" class="cam-seq hidden"><span id="cam-seq-label">W1</span></div>
        <div class="cam-controls">
            <div class="cam-controls-left">
                <button type="button" id="btn-ghost-toggle" class="cam-ctrl hidden" title="Toggle Ghost"><i class="bi bi-layers-half"></i></button>
            </div>
            <button type="button" id="btn-shutter" class="cam-shutter"><div class="shutter-ring"><div class="shutter-inner"></div></div></button>
            <div class="cam-controls-right">
                <button type="button" id="btn-load-prev" class="cam-ctrl hidden" title="Cargar fotos anteriores"><i class="bi bi-clock-history"></i></button>
            </div>
        </div>
    </div>

    <!-- PANTALLA 3: PREVIEW + ANOTACIÓN -->
    <div id="screen-preview" class="screen">
        <canvas id="preview-canvas"></canvas>
        <div id="annotation-toolbar" class="annotation-toolbar hidden">
            <div id="annotation-hint" class="annotation-hint"><i class="bi bi-hand-index"></i> Toca la foto para señalar un punto</div>
            <div id="annotation-input-wrap" class="annotation-input-wrap hidden">
                <i class="bi bi-exclamation-triangle-fill annotation-warning-icon"></i>
                <input type="text" id="annotation-text" placeholder="¿Qué quieres señalar?" maxlength="80" class="annotation-input">
                <button type="button" id="btn-annotation-clear" class="annotation-clear-btn" title="Borrar anotación"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="annotation-size-wrap" class="annotation-size-wrap hidden">
                <i class="bi bi-circle annotation-size-icon"></i>
                <input type="range" id="annotation-size" min="1" max="10" value="4" step="1" class="annotation-size-slider">
                <i class="bi bi-circle annotation-size-icon annotation-size-icon--lg"></i>
            </div>
        </div>
        <div class="preview-bar">
            <button type="button" id="btn-retake" class="preview-btn preview-btn--secondary"><i class="bi bi-arrow-repeat"></i> Repetir</button>
            <button type="button" id="btn-annotate" class="preview-btn preview-btn--annotate"><i class="bi bi-circle"></i> Anotar</button>
            <button type="button" id="btn-accept" class="preview-btn preview-btn--primary"><i class="bi bi-check-lg"></i> Aceptar</button>
        </div>
    </div>

    <!-- PANTALLA 4: MAPA -->
    <div id="screen-mapa" class="screen">
        <div class="mapa-topbar">
            <button type="button" id="btn-mapa-back" class="cam-btn-back"><i class="bi bi-arrow-left"></i></button>
            <div class="mapa-title"><strong>Mapa de Visitas</strong><span id="mapa-subtitle">Todas las infraestructuras</span></div>
            <div style="width:40px;"></div>
        </div>
        <div id="op-map" class="op-map"></div>
        <div id="nav-overlay" class="nav-overlay hidden">
            <div class="nav-info">
                <div class="nav-icon"><i class="bi bi-cursor-fill"></i></div>
                <div class="nav-details"><span class="nav-label">Navegando a</span><strong id="nav-target-name">--</strong></div>
                <div class="nav-dist-box"><span id="nav-distance">--</span></div>
            </div>
            <button type="button" id="btn-stop-nav" class="nav-stop-btn"><i class="bi bi-x-circle-fill"></i> Detener</button>
        </div>
        <button type="button" id="btn-mapa-volver" class="mapa-volver-btn"><i class="bi bi-arrow-left-circle-fill"></i> Volver a Toma de Datos</button>
        <div id="mapa-detail-panel" class="mapa-detail-panel hidden">
            <div class="mapa-detail-header">
                <button type="button" id="btn-close-detail" class="modal-close"><i class="bi bi-x-lg"></i></button>
                <h4 id="detail-infra-name">--</h4>
                <code id="detail-infra-code">--</code>
                <span id="detail-infra-distance" class="detail-distance hidden"></span>
            </div>
            <div class="mapa-detail-body" id="mapa-detail-body"></div>
            <div class="mapa-detail-actions">
                <button type="button" id="btn-detail-navegar" class="mapa-action-btn mapa-action--navegar"><i class="bi bi-cursor-fill"></i> Ir a esta ubicación</button>
                <button type="button" id="btn-detail-aleatorio" class="mapa-action-btn mapa-action--aleatorio"><i class="bi bi-camera"></i> Foto</button>
                <button type="button" id="btn-detail-comparativo" class="mapa-action-btn mapa-action--comparativo"><i class="bi bi-layers"></i> Comparativa</button>
            </div>
        </div>
    </div>

    <!-- PANTALLA 5: MIS VISITAS -->
    <div id="screen-visitas" class="screen">
        <div class="visitas-topbar">
            <button type="button" id="btn-visitas-back" class="cam-btn-back"><i class="bi bi-arrow-left"></i></button>
            <div class="visitas-title"><strong>Mis Visitas</strong><span>Registros anteriores</span></div>
            <div style="width:40px;"></div>
        </div>
        <div class="visitas-body" id="visitas-body">
            <div class="visitas-loading"><div class="spinner"></div><span>Cargando visitas...</span></div>
        </div>
        <div class="visitas-footer">
            <button type="button" id="btn-visitas-volver" class="btn-volver-rojo"><i class="bi bi-arrow-left-circle-fill"></i> Volver</button>
        </div>
    </div>

    <!-- PANTALLA 6: EDITAR VISITA -->
    <div id="screen-editar-visita" class="screen">
        <div class="visitas-topbar">
            <button type="button" id="btn-editar-back" class="cam-btn-back"><i class="bi bi-arrow-left"></i></button>
            <div class="visitas-title"><strong>Editar Registro</strong><span id="editar-infra-name">--</span></div>
            <div style="width:40px;"></div>
        </div>
        <div class="editar-body" id="editar-body">
            <div class="editar-foto-preview"><img id="editar-foto-img" src="" alt="Foto"></div>
            <div class="card" style="margin:12px 16px;">
                <div class="card-label"><i class="bi bi-info-circle"></i> Información</div>
                <div class="editar-info" id="editar-info"></div>
            </div>
            <div class="card" style="margin:12px 16px;">
                <div class="card-label"><i class="bi bi-flag"></i> Situación</div>
                <select id="editar-estado" class="input-field">
                    <option value="vp">VP (Visita Previa)</option><option value="ev">EV (Evaluación)</option>
                </select>
            </div>
            <div class="card" style="margin:12px 16px;">
                <div class="card-label"><i class="bi bi-tools"></i> Unidad de Obra</div>
                <select id="editar-uo" class="input-field"><option value="">Sin asignar</option></select>
            </div>
            <div class="card" style="margin:12px 16px;">
                <div class="card-label"><i class="bi bi-chat-text"></i> Observaciones</div>
                <textarea id="editar-observaciones" class="input-field input-textarea" rows="3" placeholder="Observaciones..."></textarea>
            </div>
            <div style="padding:12px 16px 24px;">
                <button type="button" id="btn-guardar-edicion" class="btn-guardar-visita"><i class="bi bi-check-circle-fill"></i> Guardar cambios</button>
            </div>
            <div style="padding:0 16px 24px;">
                <button type="button" id="btn-añadir-foto-visita" class="foto-btn foto-btn--aleatorio" style="width:100%;">
                    <div class="foto-btn-icon"><i class="bi bi-camera-fill"></i></div>
                    <div class="foto-btn-text"><strong>Añadir foto</strong><small>Tomar foto para esta infraestructura</small></div>
                </button>
            </div>
        </div>
    </div>

    <!-- PANTALLA 7: PANEL DE REGISTROS -->
    <div id="screen-panel" class="screen">
        <div class="visitas-topbar">
            <button type="button" id="btn-panel-back" class="cam-btn-back"><i class="bi bi-arrow-left"></i></button>
            <div class="visitas-title"><strong>Panel de Registros</strong><span id="panel-subtitle">Todos los registros</span></div>
            <div style="width:40px;"></div>
        </div>
        <div class="panel-filters">
            <select id="panel-filter-tipo" class="input-field input-field--sm">
                <option value="">Tipo: Todos</option>
                <option value="aleatorio">Aleatorio</option>
                <option value="comparativo">Comparativo</option>
            </select>
            <select id="panel-filter-estado" class="input-field input-field--sm">
                <option value="">Estado: Todos</option>
                <option value="vp">VP (Visita Previa)</option>
                <option value="ev">EV (Evaluación)</option>
            </select>
            <div class="panel-actions-row">
                <button type="button" id="btn-export-excel" class="panel-action-btn panel-action--excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</button>
                <button type="button" id="btn-export-pdf-all" class="panel-action-btn panel-action--pdf"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                <button type="button" id="btn-delete-local" class="panel-action-btn panel-action--delete"><i class="bi bi-trash3"></i> Borrar local</button>
            </div>
        </div>
        <div class="panel-body" id="panel-body">
            <div class="visitas-loading"><div class="spinner"></div><span>Cargando registros...</span></div>
        </div>
        <div class="visitas-footer">
            <button type="button" id="btn-panel-volver" class="btn-volver-rojo"><i class="bi bi-arrow-left-circle-fill"></i> Volver</button>
        </div>
    </div>

    <!-- Overlays -->
    <div id="upload-overlay" class="overlay hidden"><div class="spinner"></div><span>Subiendo foto...</span></div>

    <div id="modal-prev-photos" class="modal-overlay hidden">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="bi bi-clock-history"></i> Fotos anteriores</h3><button type="button" id="btn-close-prev" class="modal-close"><i class="bi bi-x-lg"></i></button></div>
            <div id="prev-photos-grid" class="prev-photos-grid"><p class="text-muted">Cargando...</p></div>
            <div class="modal-footer"><button type="button" id="btn-skip-prev" class="preview-btn preview-btn--secondary">Empezar sin ghost</button></div>
        </div>
    </div>

    <div id="precache-modal" class="modal-overlay hidden">
        <div class="modal-content precache-modal-content">
            <div class="modal-header"><h3><i class="bi bi-cloud-download"></i> Precargando</h3><button type="button" id="btn-close-precache" class="modal-close"><i class="bi bi-x-lg"></i></button></div>
            <div class="precache-body">
                <div class="precache-progress"><div id="precache-progress-bar" class="precache-progress-fill"></div></div>
                <p id="precache-progress-text" class="precache-text">Preparando...</p>
            </div>
        </div>
    </div>

    <!-- Toast container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Export filter modal -->
    <div id="modal-export" class="modal-overlay hidden">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="bi bi-funnel"></i> Exportar con filtros</h3><button type="button" id="btn-close-export" class="modal-close"><i class="bi bi-x-lg"></i></button></div>
            <div class="modal-body-form">
                <label class="modal-label">Tipo de foto</label>
                <select id="export-filter-tipo" class="input-field">
                    <option value="">Todos</option>
                    <option value="aleatorio">Aleatorio</option>
                    <option value="comparativo">Comparativo</option>
                </select>
                <label class="modal-label">Estado</label>
                <select id="export-filter-estado" class="input-field">
                    <option value="">Todos</option>
                    <option value="vp">VP (Visita Previa)</option>
                    <option value="ev">EV (Evaluación)</option>
                </select>
                <label class="modal-label">Año</label>
                <select id="export-filter-year" class="input-field">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" id="btn-do-export-csv" class="preview-btn preview-btn--primary"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV</button>
            </div>
        </div>
    </div>

    <!-- Delete confirmation modal -->
    <div id="modal-delete-local" class="modal-overlay hidden">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="bi bi-exclamation-triangle"></i> Borrar datos locales</h3><button type="button" id="btn-close-delete" class="modal-close"><i class="bi bi-x-lg"></i></button></div>
            <div class="modal-body-form" style="text-align:center;padding:16px;">
                <p style="margin-bottom:8px;"><strong>Se eliminará:</strong></p>
                <ul style="text-align:left;font-size:0.85rem;color:#6b7280;list-style:disc inside;margin-bottom:12px;">
                    <li>Cola de fotos pendientes (IndexedDB)</li>
                    <li>Fotos precargadas para ghosting</li>
                    <li>Caché del service worker</li>
                </ul>
                <p style="font-size:0.8rem;color:#dc3545;"><i class="bi bi-exclamation-circle"></i> Las fotos ya subidas al servidor NO se borran.</p>
            </div>
            <div class="modal-footer" style="gap:8px;">
                <button type="button" id="btn-cancel-delete" class="preview-btn preview-btn--secondary">Cancelar</button>
                <button type="button" id="btn-confirm-delete" class="preview-btn preview-btn--danger"><i class="bi bi-trash3"></i> Borrar todo</button>
            </div>
        </div>
    </div>

    <div id="sync-notification" class="sync-notification hidden">
        <span class="sync-notif-text"></span>
        <button type="button" id="sync-notif-close" class="sync-notif-close"><i class="bi bi-x"></i></button>
    </div>

    <div id="offline-queue-banner" class="offline-queue-banner hidden">
        <div class="oq-banner-left">
            <div class="oq-banner-icon"><i class="bi bi-cloud-arrow-up"></i></div>
            <div class="oq-banner-info">
                <strong id="oq-banner-count">0</strong> <span id="oq-banner-label">fotos pendientes</span>
                <div id="oq-banner-status" class="oq-banner-status">Esperando conexión...</div>
            </div>
        </div>
        <div class="oq-banner-right">
            <div id="oq-banner-progress" class="oq-banner-progress" style="display:none;">
                <div id="oq-banner-progress-fill" class="oq-banner-progress-fill"></div>
            </div>
            <button type="button" id="oq-banner-sync" class="oq-banner-btn" onclick="RapcaOffline.syncQueue()" title="Sincronizar ahora">
                <i class="bi bi-arrow-repeat"></i>
            </button>
        </div>
    </div>

    <script>
        window.RAPCA = {
            usuarioId: <?= $usuarioId ?>,
            userName: <?= json_encode($userName) ?>,
            empresaName: 'RAPCA',
            endpoints: {
                upload: 'subir.php',
                infraestructuras: 'api/infraestructuras.php',
                unidadesObra: 'api/unidades_obra.php',
                fotosComparativas: 'api/fotos_comparativas.php',
                registrosMapa: 'api/registros_mapa.php',
                capasKml: 'api/capas_kml.php',
                visitas: 'api/visitas.php',
                exportPdf: 'api/export_pdf.php',
            }
        };
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/offline.js"></script>
    <script src="js/operador.js"></script>
</body>
</html>
