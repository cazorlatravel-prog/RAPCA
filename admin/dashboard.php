<?php
/**
 * RAPCA - Panel de Administración completo
 * Tabs: Resumen | Campos | Infraestructuras | Operadores | Mapa
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['superadmin', 'admin']);
$pdo = getDB();
$isSA = isSuperAdmin();
$csrf = csrfToken();

// ─── Métricas para Resumen ──────────────────────────────────────────
$totalInfra      = (int) $pdo->query("SELECT COUNT(*) FROM infraestructuras WHERE activa = 1")->fetchColumn();
$totalRegistros  = (int) $pdo->query("SELECT COUNT(*) FROM registros")->fetchColumn();
$totalOperadores = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND rol = 'operador'")->fetchColumn();
$registrosHoy    = (int) $pdo->query("SELECT COUNT(*) FROM registros WHERE DATE(fecha) = CURDATE()")->fetchColumn();
$registrosSemana = (int) $pdo->query("SELECT COUNT(*) FROM registros WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$fotosAlea  = (int) $pdo->query("SELECT COUNT(*) FROM registros WHERE tipo_foto = 'aleatorio'")->fetchColumn();
$fotosComp  = (int) $pdo->query("SELECT COUNT(*) FROM registros WHERE tipo_foto = 'comparativo'")->fetchColumn();
$fotosVP    = (int) $pdo->query("SELECT COUNT(*) FROM registros WHERE estado_incidencia = 'vp'")->fetchColumn();
$fotosEV    = (int) $pdo->query("SELECT COUNT(*) FROM registros WHERE estado_incidencia = 'ev'")->fetchColumn();
$subFallidas = 0;
try { $subFallidas = (int) $pdo->query("SELECT COUNT(*) FROM subidas_fallidas WHERE resuelta = 0")->fetchColumn(); } catch (\Exception $e) {}

$ultimosReg = $pdo->query("
    SELECT r.id, r.fecha, r.tipo_foto, r.estado_incidencia, r.url_cloudinary, r.observaciones,
           i.nombre AS infra_nombre, i.cod_infoca, i.provincia, i.municipio,
           u.nombre AS operador_nombre
    FROM registros r JOIN infraestructuras i ON r.infra_id = i.id JOIN usuarios u ON r.usuario_id = u.id
    ORDER BY r.fecha DESC LIMIT 20
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAPCA - Panel de Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        :root{--bg:#0f172a;--card:#1e293b;--hover:#334155;--accent:#3b82f6;--alight:#60a5fa;--green:#22c55e;--amber:#f59e0b;--red:#ef4444;--purple:#a855f7;--txt:#f1f5f9;--muted:#94a3b8;--border:rgba(255,255,255,.06);--r:12px}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--txt);min-height:100vh}

        /* Top bar */
        .topbar{background:var(--card);border-bottom:1px solid var(--border);padding:10px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:200}
        .topbar-l{display:flex;align-items:center;gap:10px}
        .brand{width:34px;height:34px;background:linear-gradient(135deg,var(--accent),var(--purple));border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.95rem;color:#fff}
        .brand-txt{font-weight:800;font-size:.95rem;letter-spacing:.8px}
        .brand-sub{font-size:.65rem;color:var(--muted)}
        .topbar-r{display:flex;align-items:center;gap:12px;font-size:.78rem}
        .topbar-r strong{color:var(--txt)}
        .rbadge{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:5px;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
        .rbadge-s{background:rgba(245,158,11,.15);color:#fbbf24}
        .rbadge-a{background:rgba(168,85,247,.15);color:#c084fc}
        .btn-s{padding:5px 12px;border-radius:7px;font-size:.72rem;font-weight:600;text-decoration:none;border:1px solid var(--border);color:var(--muted);transition:.2s;cursor:pointer;background:0 0}
        .btn-s:hover{background:var(--hover);color:#fff}

        /* Tabs */
        .tabs{display:flex;gap:2px;background:var(--card);border-bottom:1px solid var(--border);padding:0 20px;overflow-x:auto}
        .tab{padding:12px 18px;font-size:.78rem;font-weight:600;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;transition:.2s;white-space:nowrap;display:flex;align-items:center;gap:6px}
        .tab:hover{color:var(--txt)}
        .tab.active{color:var(--alight);border-bottom-color:var(--accent)}
        .tab-content{display:none}
        .tab-content.active{display:block}

        .container{max-width:1340px;margin:0 auto;padding:20px}

        /* Stats */
        .sg{display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:12px;margin-bottom:22px}
        .sc{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:16px;display:flex;align-items:center;gap:12px}
        .si{width:40px;height:40px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
        .si-b{background:rgba(59,130,246,.12);color:#60a5fa}.si-g{background:rgba(34,197,94,.12);color:#4ade80}
        .si-p{background:rgba(168,85,247,.12);color:#c084fc}.si-a{background:rgba(245,158,11,.12);color:#fbbf24}
        .si-r{background:rgba(239,68,68,.12);color:#f87171}.si-c{background:rgba(6,182,212,.12);color:#22d3ee}
        .sv{font-size:1.4rem;font-weight:800;line-height:1.1}
        .sl{font-size:.68rem;color:var(--muted);margin-top:1px}

        /* Panel / Table */
        .pnl{background:var(--card);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;margin-bottom:20px}
        .pnl-hdr{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
        .pnl-hdr h3{font-size:.85rem;font-weight:700;display:flex;align-items:center;gap:6px}
        .pnl-hdr h3 i{color:var(--alight)}
        .pnl-scroll{overflow-x:auto}
        table{width:100%;border-collapse:collapse;font-size:.78rem}
        th{background:rgba(255,255,255,.03);padding:9px 12px;text-align:left;font-weight:700;color:var(--muted);font-size:.67rem;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);white-space:nowrap}
        td{padding:9px 12px;border-bottom:1px solid var(--border);white-space:nowrap}
        tr:last-child td{border-bottom:0}
        tr:hover td{background:rgba(255,255,255,.02)}
        .badge{display:inline-flex;padding:2px 7px;border-radius:5px;font-size:.65rem;font-weight:700}
        .b-vp{background:rgba(59,130,246,.15);color:#60a5fa}.b-ev{background:rgba(245,158,11,.15);color:#fbbf24}
        .b-alea{background:rgba(59,130,246,.1);color:#93c5fd}.b-comp{background:rgba(168,85,247,.1);color:#d8b4fe}
        .b-act{background:rgba(34,197,94,.15);color:#4ade80}.b-inact{background:rgba(239,68,68,.15);color:#f87171}
        .b-op{background:rgba(59,130,246,.12);color:#60a5fa}.b-admin{background:rgba(168,85,247,.12);color:#c084fc}
        .b-sa{background:rgba(245,158,11,.12);color:#fbbf24}
        .thumb{width:34px;height:34px;border-radius:5px;object-fit:cover;border:1px solid var(--border)}
        .txt-m{color:var(--muted)}.txt-g{color:#4ade80}.txt-r{color:#f87171}
        .two-c{display:grid;grid-template-columns:1fr 1fr;gap:18px}

        /* Buttons */
        .btn{padding:7px 16px;border-radius:8px;font-size:.75rem;font-weight:600;border:none;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:5px}
        .btn-pri{background:var(--accent);color:#fff}.btn-pri:hover{background:#2563eb}
        .btn-ok{background:var(--green);color:#fff}.btn-ok:hover{background:#16a34a}
        .btn-dn{background:var(--red);color:#fff}.btn-dn:hover{background:#dc2626}
        .btn-gh{background:var(--hover);color:var(--txt)}.btn-gh:hover{background:#475569}
        .btn-am{background:var(--amber);color:#000}.btn-am:hover{background:#d97706;color:#fff}
        .btn:disabled{opacity:.4;cursor:not-allowed}

        /* Forms */
        .form-row{display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap}
        .form-group{display:flex;flex-direction:column;gap:3px;flex:1;min-width:160px}
        .form-group label{font-size:.68rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.3px}
        .form-group input,.form-group select,.form-group textarea{background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:8px 10px;color:var(--txt);font-size:.78rem;font-family:inherit}
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(59,130,246,.2)}
        .form-group select{cursor:pointer}
        .form-group textarea{resize:vertical;min-height:60px}

        /* Modal */
        .modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;display:none;align-items:center;justify-content:center;padding:20px}
        .modal-bg.open{display:flex}
        .modal{background:var(--card);border:1px solid var(--border);border-radius:14px;width:100%;max-width:640px;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.5)}
        .modal-hdr{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
        .modal-hdr h3{font-size:.9rem;font-weight:700}
        .modal-close{background:0 0;border:0;color:var(--muted);font-size:1.2rem;cursor:pointer}
        .modal-close:hover{color:var(--txt)}
        .modal-body{padding:20px}
        .modal-foot{padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px}

        /* Search */
        .search-box{display:flex;gap:8px;align-items:center}
        .search-box input{background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:7px 12px;color:var(--txt);font-size:.78rem;width:240px}
        .search-box input:focus{outline:none;border-color:var(--accent)}

        /* Map */
        #admin-map{height:600px;border-radius:var(--r);border:1px solid var(--border)}
        .map-toolbar{display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;align-items:center}

        /* File upload area */
        .upload-area{border:2px dashed var(--border);border-radius:var(--r);padding:30px;text-align:center;color:var(--muted);cursor:pointer;transition:.2s}
        .upload-area:hover{border-color:var(--accent);color:var(--alight)}
        .upload-area i{font-size:2rem;display:block;margin-bottom:8px}

        /* Infra assign chips */
        .chip-list{display:flex;flex-wrap:wrap;gap:4px;max-height:200px;overflow-y:auto;padding:4px 0}
        .chip{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.7rem;background:var(--hover);color:var(--txt);cursor:pointer;transition:.15s;border:1px solid transparent}
        .chip.selected{background:rgba(59,130,246,.2);border-color:var(--accent);color:var(--alight)}

        /* Toast */
        .toast{position:fixed;bottom:24px;right:24px;padding:12px 20px;border-radius:10px;font-size:.8rem;font-weight:600;z-index:1000;opacity:0;transform:translateY(10px);transition:.3s;pointer-events:none}
        .toast.show{opacity:1;transform:translateY(0)}
        .toast-ok{background:var(--green);color:#fff}
        .toast-err{background:var(--red);color:#fff}

        /* Link box */
        .link-box{background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:8px 12px;font-size:.75rem;color:var(--alight);word-break:break-all;display:flex;align-items:center;gap:8px}
        .link-box button{flex-shrink:0}

        @media(max-width:900px){.two-c{grid-template-columns:1fr}.sg{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:600px){.sg{grid-template-columns:1fr}.topbar{flex-direction:column;gap:8px;text-align:center}.topbar-r{flex-wrap:wrap;justify-content:center}.container{padding:14px 10px}.form-row{flex-direction:column}}
    </style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
    <div class="topbar-l">
        <div class="brand"><i class="bi bi-geo-alt-fill"></i></div>
        <div>
            <div class="brand-txt">RAPCA</div>
            <div class="brand-sub">Panel de Administración</div>
        </div>
    </div>
    <div class="topbar-r">
        <strong><?= htmlspecialchars($user['nombre']) ?></strong>
        <span class="rbadge <?= $isSA ? 'rbadge-s' : 'rbadge-a' ?>">
            <i class="bi <?= $isSA ? 'bi-shield-lock-fill' : 'bi-eye-fill' ?>"></i>
            <?= $isSA ? 'Superadmin' : 'Admin' ?>
        </span>
        <a href="/" class="btn-s"><i class="bi bi-house"></i> Inicio</a>
        <a href="/public/login.php?logout" class="btn-s"><i class="bi bi-box-arrow-right"></i> Salir</a>
    </div>
</div>

<!-- TABS -->
<div class="tabs">
    <div class="tab active" data-tab="resumen"><i class="bi bi-speedometer2"></i> Resumen</div>
    <div class="tab" data-tab="campos"><i class="bi bi-ui-checks-grid"></i> Campos</div>
    <div class="tab" data-tab="infras"><i class="bi bi-building"></i> Infraestructuras</div>
    <div class="tab" data-tab="operadores"><i class="bi bi-people-fill"></i> Operadores</div>
    <div class="tab" data-tab="mapa"><i class="bi bi-map"></i> Mapa</div>
</div>

<!-- ═══════════════════ TAB RESUMEN ═══════════════════ -->
<div class="tab-content active" id="tc-resumen">
<div class="container">
    <div class="sg">
        <div class="sc"><div class="si si-b"><i class="bi bi-building"></i></div><div><div class="sv"><?= number_format($totalInfra) ?></div><div class="sl">Infraestructuras</div></div></div>
        <div class="sc"><div class="si si-g"><i class="bi bi-camera-fill"></i></div><div><div class="sv"><?= number_format($totalRegistros) ?></div><div class="sl">Registros totales</div></div></div>
        <div class="sc"><div class="si si-p"><i class="bi bi-people-fill"></i></div><div><div class="sv"><?= $totalOperadores ?></div><div class="sl">Operadores</div></div></div>
        <div class="sc"><div class="si si-c"><i class="bi bi-calendar-check"></i></div><div><div class="sv"><?= number_format($registrosHoy) ?></div><div class="sl">Fotos hoy</div></div></div>
        <div class="sc"><div class="si si-a"><i class="bi bi-calendar-week"></i></div><div><div class="sv"><?= number_format($registrosSemana) ?></div><div class="sl">Fotos semana</div></div></div>
        <?php if ($subFallidas > 0): ?>
        <div class="sc"><div class="si si-r"><i class="bi bi-exclamation-triangle-fill"></i></div><div><div class="sv txt-r"><?= $subFallidas ?></div><div class="sl">Subidas fallidas</div></div></div>
        <?php else: ?>
        <div class="sc"><div class="si si-g"><i class="bi bi-check-circle-fill"></i></div><div><div class="sv txt-g">OK</div><div class="sl">Sin fallos</div></div></div>
        <?php endif; ?>
    </div>
    <div class="sg">
        <div class="sc"><div class="si si-b"><i class="bi bi-camera"></i></div><div><div class="sv"><?= number_format($fotosAlea) ?></div><div class="sl">Aleatorias</div></div></div>
        <div class="sc"><div class="si si-p"><i class="bi bi-layers"></i></div><div><div class="sv"><?= number_format($fotosComp) ?></div><div class="sl">Comparativas</div></div></div>
        <div class="sc"><div class="si si-b"><i class="bi bi-eye"></i></div><div><div class="sv"><?= number_format($fotosVP) ?></div><div class="sl">Visita Previa (VP)</div></div></div>
        <div class="sc"><div class="si si-a"><i class="bi bi-clipboard-check"></i></div><div><div class="sv"><?= number_format($fotosEV) ?></div><div class="sl">Evaluación (EV)</div></div></div>
    </div>

    <div class="pnl">
        <div class="pnl-hdr"><h3><i class="bi bi-clock-history"></i> Últimos registros</h3></div>
        <div class="pnl-scroll">
            <table>
                <thead><tr><th></th><th>#</th><th>Infraestructura</th><th>Cód.</th><th>Provincia</th><th>Municipio</th><th>Operador</th><th>Fecha</th><th>Tipo</th><th>Estado</th><th>Observaciones</th></tr></thead>
                <tbody>
                <?php foreach ($ultimosReg as $r): ?>
                <tr>
                    <td><?php if($r['url_cloudinary']): ?><img src="<?= htmlspecialchars($r['url_cloudinary']) ?>" class="thumb" loading="lazy"><?php else: ?><span class="txt-m">--</span><?php endif; ?></td>
                    <td><?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['infra_nombre']) ?></td>
                    <td><code><?= htmlspecialchars($r['cod_infoca']??'') ?></code></td>
                    <td><?= htmlspecialchars($r['provincia']??'') ?></td>
                    <td><?= htmlspecialchars($r['municipio']??'') ?></td>
                    <td><?= htmlspecialchars($r['operador_nombre']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($r['fecha'])) ?></td>
                    <td><span class="badge <?= $r['tipo_foto']==='comparativo'?'b-comp':'b-alea' ?>"><?= $r['tipo_foto']==='comparativo'?'COMP':'ALEA' ?></span></td>
                    <td><span class="badge b-<?= $r['estado_incidencia'] ?>"><?= strtoupper($r['estado_incidencia']) ?></span></td>
                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($r['observaciones']??'') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($ultimosReg)): ?><tr><td colspan="11" class="txt-m" style="text-align:center;padding:28px">Sin registros</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- ═══════════════════ TAB CAMPOS ═══════════════════ -->
<div class="tab-content" id="tc-campos">
<div class="container">
    <div class="pnl">
        <div class="pnl-hdr">
            <h3><i class="bi bi-ui-checks-grid"></i> Campos de formulario</h3>
            <?php if($isSA): ?><button class="btn btn-pri" onclick="campoModal()"><i class="bi bi-plus-lg"></i> Nuevo campo</button><?php endif; ?>
        </div>
        <div class="pnl-scroll">
            <table>
                <thead><tr><th>Orden</th><th>Nombre</th><th>Slug</th><th>Tipo</th><th>Opciones</th><th>Oblig.</th><th>Estado</th><?php if($isSA): ?><th>Acciones</th><?php endif; ?></tr></thead>
                <tbody id="campos-body"><tr><td colspan="8" class="txt-m" style="text-align:center;padding:28px">Cargando...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- ═══════════════════ TAB INFRAESTRUCTURAS ═══════════════════ -->
<div class="tab-content" id="tc-infras">
<div class="container">
    <div class="pnl">
        <div class="pnl-hdr">
            <h3><i class="bi bi-building"></i> Infraestructuras</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <div class="search-box"><input type="text" id="infra-search" placeholder="Buscar nombre, cod, municipio..."></div>
                <?php if($isSA): ?>
                <button class="btn btn-pri" onclick="infraModal()"><i class="bi bi-plus-lg"></i> Nueva</button>
                <button class="btn btn-am" onclick="document.getElementById('import-file').click()"><i class="bi bi-upload"></i> Importar CSV/Excel</button>
                <input type="file" id="import-file" accept=".csv,.xlsx,.xls" style="display:none" onchange="importInfras(this)">
                <?php endif; ?>
            </div>
        </div>
        <div class="pnl-scroll">
            <table>
                <thead><tr><th>ID</th><th>Nombre</th><th>Cód. INFOCA</th><th>Provincia</th><th>Municipio</th><th>Zona</th><th>Unidad</th><th>Superficie</th><th>Coord.</th><th>Fotos</th><th>Estado</th><?php if($isSA): ?><th>Acciones</th><?php endif; ?></tr></thead>
                <tbody id="infras-body"><tr><td colspan="12" class="txt-m" style="text-align:center;padding:28px">Cargando...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- ═══════════════════ TAB OPERADORES ═══════════════════ -->
<div class="tab-content" id="tc-operadores">
<div class="container">
    <div class="pnl">
        <div class="pnl-hdr">
            <h3><i class="bi bi-people-fill"></i> Usuarios y Operadores</h3>
            <?php if($isSA): ?><button class="btn btn-pri" onclick="userModal()"><i class="bi bi-plus-lg"></i> Nuevo usuario</button><?php endif; ?>
        </div>
        <div class="pnl-scroll">
            <table>
                <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Infras</th><th>Fotos</th><th>Última foto</th><th>Último login</th><th>Estado</th><th>Enlace operador</th><?php if($isSA): ?><th>Acciones</th><?php endif; ?></tr></thead>
                <tbody id="users-body"><tr><td colspan="11" class="txt-m" style="text-align:center;padding:28px">Cargando...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- ═══════════════════ TAB MAPA ═══════════════════ -->
<div class="tab-content" id="tc-mapa">
<div class="container">
    <div class="map-toolbar">
        <h3 style="font-size:.9rem;display:flex;align-items:center;gap:6px"><i class="bi bi-map" style="color:var(--alight)"></i> Mapa de infraestructuras</h3>
        <div style="flex:1"></div>
        <?php if($isSA): ?>
        <button class="btn btn-am" onclick="document.getElementById('kml-file').click()"><i class="bi bi-upload"></i> Cargar KML/KMZ</button>
        <input type="file" id="kml-file" accept=".kml,.kmz" style="display:none" onchange="uploadKml(this)">
        <?php endif; ?>
        <button class="btn btn-gh" onclick="loadKmlLayers()"><i class="bi bi-layers"></i> Recargar capas</button>
    </div>
    <div id="admin-map"></div>

    <!-- KML layers table -->
    <div class="pnl" style="margin-top:18px">
        <div class="pnl-hdr"><h3><i class="bi bi-layers"></i> Capas KML cargadas</h3></div>
        <div class="pnl-scroll">
            <table>
                <thead><tr><th>Nombre</th><th>Color</th><th>Tamaño</th><th>Creada</th><th>Activa</th><?php if($isSA): ?><th>Acciones</th><?php endif; ?></tr></thead>
                <tbody id="kml-body"><tr><td colspan="6" class="txt-m" style="text-align:center;padding:20px">Cargando...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- ═══════════════════ MODALS ═══════════════════ -->

<!-- Modal Campo -->
<div class="modal-bg" id="modal-campo">
<div class="modal">
    <div class="modal-hdr"><h3 id="modal-campo-title">Nuevo campo</h3><button class="modal-close" onclick="closeModal('modal-campo')">&times;</button></div>
    <div class="modal-body">
        <input type="hidden" id="campo-id">
        <div class="form-row">
            <div class="form-group"><label>Nombre</label><input type="text" id="campo-nombre" placeholder="Ej: Temperatura"></div>
            <div class="form-group"><label>Slug</label><input type="text" id="campo-slug" placeholder="Ej: temperatura"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Tipo</label>
                <select id="campo-tipo"><option value="texto">Texto</option><option value="numero">Número</option><option value="select">Select</option><option value="checkbox">Checkbox</option><option value="textarea">Textarea</option><option value="fecha">Fecha</option></select>
            </div>
            <div class="form-group"><label>Orden</label><input type="number" id="campo-orden" value="0" min="0"></div>
            <div class="form-group"><label>Obligatorio</label>
                <select id="campo-obligatorio"><option value="0">No</option><option value="1">Sí</option></select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Opciones (para select, una por línea)</label><textarea id="campo-opciones" rows="3" placeholder="Opción A&#10;Opción B&#10;Opción C"></textarea></div>
        </div>
    </div>
    <div class="modal-foot">
        <button class="btn btn-gh" onclick="closeModal('modal-campo')">Cancelar</button>
        <button class="btn btn-pri" onclick="saveCampo()">Guardar</button>
    </div>
</div>
</div>

<!-- Modal Infraestructura -->
<div class="modal-bg" id="modal-infra">
<div class="modal">
    <div class="modal-hdr"><h3 id="modal-infra-title">Editar infraestructura</h3><button class="modal-close" onclick="closeModal('modal-infra')">&times;</button></div>
    <div class="modal-body">
        <input type="hidden" id="infra-id">
        <div class="form-row">
            <div class="form-group"><label>Nombre *</label><input type="text" id="infra-nombre"></div>
            <div class="form-group"><label>Cód. INFOCA</label><input type="text" id="infra-cod_infoca"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Provincia</label><input type="text" id="infra-provincia"></div>
            <div class="form-group"><label>Municipio</label><input type="text" id="infra-municipio"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>ID Zona</label><input type="text" id="infra-id_zona"></div>
            <div class="form-group"><label>ID Unidad</label><input type="text" id="infra-id_unidad"></div>
            <div class="form-group"><label>Superficie (ha)</label><input type="text" id="infra-superficie"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Monte</label><input type="text" id="infra-monte"></div>
            <div class="form-group"><label>Cód. Monte</label><input type="text" id="infra-cod_monte"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Pendiente</label><input type="text" id="infra-pendiente"></div>
            <div class="form-group"><label>Distancia aprisco</label><input type="text" id="infra-distancia_aprisco"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Vegetación</label><input type="text" id="infra-vegetacion"></div>
            <div class="form-group"><label>Tipo contrato</label><input type="text" id="infra-tipo_contrato"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Parque</label><input type="text" id="infra-parque"></div>
            <div class="form-group"><label>Pago máx.</label><input type="text" id="infra-pago_max"></div>
            <div class="form-group"><label>Desbroce</label><input type="text" id="infra-desbroce"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Latitud</label><input type="text" id="infra-lat_teorica" placeholder="37.0000000"></div>
            <div class="form-group"><label>Longitud</label><input type="text" id="infra-lon_teorica" placeholder="-3.0000000"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Observaciones</label><textarea id="infra-observaciones" rows="2"></textarea></div>
        </div>
    </div>
    <div class="modal-foot">
        <button class="btn btn-gh" onclick="closeModal('modal-infra')">Cancelar</button>
        <button class="btn btn-pri" onclick="saveInfra()">Guardar</button>
    </div>
</div>
</div>

<!-- Modal Usuario -->
<div class="modal-bg" id="modal-user">
<div class="modal">
    <div class="modal-hdr"><h3 id="modal-user-title">Nuevo usuario</h3><button class="modal-close" onclick="closeModal('modal-user')">&times;</button></div>
    <div class="modal-body">
        <input type="hidden" id="user-id">
        <div class="form-row">
            <div class="form-group"><label>Nombre *</label><input type="text" id="user-nombre"></div>
            <div class="form-group"><label>Email *</label><input type="email" id="user-email"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Contraseña <span id="pw-hint">(obligatoria)</span></label><input type="password" id="user-password" placeholder="Mínimo 6 caracteres"></div>
            <div class="form-group"><label>Rol</label>
                <select id="user-rol"><option value="operador">Operador</option><option value="admin">Admin (lectura)</option><option value="superadmin">Superadmin</option></select>
            </div>
        </div>
        <!-- Asignar infraestructuras -->
        <div id="user-infras-section" style="margin-top:12px">
            <label style="font-size:.72rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.3px;margin-bottom:6px;display:block">Infraestructuras asignadas</label>
            <input type="text" id="user-infra-filter" placeholder="Filtrar infraestructuras..." style="background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:6px 10px;color:var(--txt);font-size:.75rem;width:100%;margin-bottom:8px">
            <div class="chip-list" id="user-infra-chips">Cargando...</div>
        </div>
    </div>
    <div class="modal-foot">
        <button class="btn btn-gh" onclick="closeModal('modal-user')">Cancelar</button>
        <button class="btn btn-pri" onclick="saveUser()">Guardar</button>
    </div>
</div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const CSRF = '<?= $csrf ?>';
const IS_SA = <?= $isSA ? 'true' : 'false' ?>;
const APP_URL = '<?= APP_URL ?>';

// ─── Helpers ──────────────────────────────────────────
function $(s){ return document.querySelector(s); }
function $$(s){ return document.querySelectorAll(s); }
function toast(msg, ok=true){ const t=$('#toast'); t.textContent=msg; t.className='toast '+(ok?'toast-ok':'toast-err')+' show'; setTimeout(()=>t.classList.remove('show'),3000); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
function openModal(id){ document.getElementById(id).classList.add('open'); }
function esc(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
function fmtDate(d){ if(!d) return '--'; const p=new Date(d); return isNaN(p)?d:p.toLocaleDateString('es-ES')+' '+p.toLocaleTimeString('es-ES',{hour:'2-digit',minute:'2-digit'}); }
function fmtSize(b){ if(b<1024) return b+' B'; if(b<1048576) return (b/1024).toFixed(1)+' KB'; return (b/1048576).toFixed(1)+' MB'; }

async function api(url, opts={}){
    const h = opts.headers || {};
    if(!(opts.body instanceof FormData)){
        h['Content-Type'] = 'application/json';
        h['X-CSRF-TOKEN'] = CSRF;
    }
    const r = await fetch(url, {...opts, headers:h});
    return r.json();
}

// ─── Tabs ─────────────────────────────────────────────
const tabLoaded = {};
$$('.tab').forEach(t => {
    t.addEventListener('click', () => {
        $$('.tab').forEach(x=>x.classList.remove('active'));
        $$('.tab-content').forEach(x=>x.classList.remove('active'));
        t.classList.add('active');
        const name = t.dataset.tab;
        document.getElementById('tc-'+name).classList.add('active');
        if(!tabLoaded[name]){
            tabLoaded[name]=true;
            if(name==='campos') loadCampos();
            if(name==='infras') loadInfras();
            if(name==='operadores') loadUsers();
            if(name==='mapa') initMap();
        }
    });
});

// ═══════════════════ CAMPOS ═══════════════════
let camposData=[];
async function loadCampos(){
    const r = await api('/admin/api/campos.php');
    camposData = r.campos||[];
    renderCampos();
}
function renderCampos(){
    const tb=$('#campos-body');
    if(!camposData.length){ tb.innerHTML='<tr><td colspan="8" class="txt-m" style="text-align:center;padding:28px">Sin campos definidos</td></tr>'; return; }
    tb.innerHTML = camposData.map(c=>`<tr>
        <td>${c.orden}</td>
        <td><strong>${esc(c.nombre)}</strong></td>
        <td><code>${esc(c.slug)}</code></td>
        <td>${esc(c.tipo)}</td>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis">${c.opciones&&c.opciones.length?esc(c.opciones.join(', ')):'<span class="txt-m">--</span>'}</td>
        <td>${c.obligatorio?'<span class="badge b-ev">Sí</span>':'No'}</td>
        <td>${c.activo?'<span class="badge b-act">Activo</span>':'<span class="badge b-inact">Inactivo</span>'}</td>
        ${IS_SA?`<td style="display:flex;gap:4px">
            <button class="btn btn-gh" style="padding:4px 8px;font-size:.68rem" onclick="campoModal(${c.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn ${c.activo?'btn-dn':'btn-ok'}" style="padding:4px 8px;font-size:.68rem" onclick="toggleCampo(${c.id},${c.activo?0:1})">${c.activo?'<i class="bi bi-x-lg"></i>':'<i class="bi bi-check-lg"></i>'}</button>
        </td>`:''}`).join('');
}
function campoModal(id){
    const c = id ? camposData.find(x=>x.id==id) : null;
    $('#modal-campo-title').textContent = c ? 'Editar campo' : 'Nuevo campo';
    $('#campo-id').value = c ? c.id : '';
    $('#campo-nombre').value = c ? c.nombre : '';
    $('#campo-slug').value = c ? c.slug : '';
    $('#campo-tipo').value = c ? c.tipo : 'texto';
    $('#campo-orden').value = c ? c.orden : 0;
    $('#campo-obligatorio').value = c ? c.obligatorio : 0;
    $('#campo-opciones').value = c&&c.opciones ? c.opciones.join('\n') : '';
    openModal('modal-campo');
}
async function saveCampo(){
    const id = $('#campo-id').value;
    const data = {
        nombre: $('#campo-nombre').value.trim(),
        slug: $('#campo-slug').value.trim(),
        tipo: $('#campo-tipo').value,
        orden: parseInt($('#campo-orden').value)||0,
        obligatorio: parseInt($('#campo-obligatorio').value),
        opciones: $('#campo-opciones').value.trim().split('\n').filter(x=>x.trim()),
        csrf_token: CSRF
    };
    if(!data.nombre||!data.slug){ toast('Nombre y slug obligatorios',false); return; }
    let r;
    if(id){
        data.id = parseInt(id);
        r = await api('/admin/api/campos.php',{method:'PUT',body:JSON.stringify(data)});
    } else {
        r = await api('/admin/api/campos.php',{method:'POST',body:JSON.stringify(data)});
    }
    if(r.ok){ toast('Campo guardado'); closeModal('modal-campo'); loadCampos(); }
    else toast(r.error||'Error',false);
}
async function toggleCampo(id,activo){
    const r = await api('/admin/api/campos.php',{method:'PUT',body:JSON.stringify({id,activo,csrf_token:CSRF})});
    if(r.ok){ toast(activo?'Campo activado':'Campo desactivado'); loadCampos(); }
    else toast(r.error||'Error',false);
}

// Auto-generate slug from nombre
$('#campo-nombre')?.addEventListener('input', function(){
    if(!$('#campo-id').value){
        $('#campo-slug').value = this.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');
    }
});

// ═══════════════════ INFRAESTRUCTURAS ═══════════════════
let infrasData=[];
const infraFieldIds = ['nombre','cod_infoca','provincia','municipio','id_zona','id_unidad','superficie','monte','cod_monte','pendiente','distancia_aprisco','vegetacion','tipo_contrato','parque','pago_max','desbroce','observaciones','lat_teorica','lon_teorica'];

async function loadInfras(q=''){
    const r = await api('/admin/api/infraestructuras.php?q='+encodeURIComponent(q));
    infrasData = r.infraestructuras||[];
    renderInfras();
}
function renderInfras(){
    const tb=$('#infras-body');
    if(!infrasData.length){ tb.innerHTML='<tr><td colspan="12" class="txt-m" style="text-align:center;padding:28px">Sin resultados</td></tr>'; return; }
    tb.innerHTML = infrasData.map(i=>`<tr>
        <td>${i.id}</td>
        <td><strong>${esc(i.nombre)}</strong></td>
        <td><code>${esc(i.cod_infoca||'')}</code></td>
        <td>${esc(i.provincia||'')}</td>
        <td>${esc(i.municipio||'')}</td>
        <td>${esc(i.id_zona||'')}</td>
        <td>${esc(i.id_unidad||'')}</td>
        <td>${i.superficie||'--'}</td>
        <td>${i.lat_teorica&&i.lon_teorica?'<span class="badge b-act" title="'+i.lat_teorica+', '+i.lon_teorica+'"><i class="bi bi-geo-alt"></i></span>':'<span class="txt-m">--</span>'}</td>
        <td><strong>${i.total_fotos||0}</strong></td>
        <td>${i.activa?'<span class="badge b-act">Activa</span>':'<span class="badge b-inact">Inactiva</span>'}</td>
        ${IS_SA?`<td style="display:flex;gap:4px">
            <button class="btn btn-gh" style="padding:4px 8px;font-size:.68rem" onclick="infraModal(${i.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn ${i.activa?'btn-dn':'btn-ok'}" style="padding:4px 8px;font-size:.68rem" onclick="toggleInfra(${i.id},${i.activa?0:1})">${i.activa?'<i class="bi bi-x-lg"></i>':'<i class="bi bi-check-lg"></i>'}</button>
        </td>`:''}`).join('');
}
function infraModal(id){
    const i = id ? infrasData.find(x=>x.id==id) : null;
    $('#modal-infra-title').textContent = i ? 'Editar infraestructura #'+i.id : 'Nueva infraestructura';
    $('#infra-id').value = i ? i.id : '';
    infraFieldIds.forEach(f => {
        const el = document.getElementById('infra-'+f);
        if(el) el.value = i ? (i[f]||'') : '';
    });
    openModal('modal-infra');
}
async function saveInfra(){
    const id = $('#infra-id').value;
    const data = {csrf_token:CSRF};
    infraFieldIds.forEach(f => { data[f] = document.getElementById('infra-'+f)?.value || ''; });
    if(!data.nombre.trim()){ toast('Nombre obligatorio',false); return; }
    let r;
    if(id){
        data.id = parseInt(id);
        r = await api('/admin/api/infraestructuras.php',{method:'PUT',body:JSON.stringify(data)});
    } else {
        r = await api('/admin/api/infraestructuras.php',{method:'POST',body:JSON.stringify(data)});
    }
    if(r.ok){ toast('Infraestructura guardada'); closeModal('modal-infra'); loadInfras($('#infra-search').value); }
    else toast(r.error||'Error',false);
}
async function toggleInfra(id,activa){
    const r = await api('/admin/api/infraestructuras.php',{method:'PUT',body:JSON.stringify({id,activa,csrf_token:CSRF})});
    if(r.ok){ toast(activa?'Activada':'Desactivada'); loadInfras($('#infra-search').value); }
    else toast(r.error||'Error',false);
}
async function importInfras(input){
    if(!input.files.length) return;
    const fd = new FormData();
    fd.append('archivo', input.files[0]);
    fd.append('csrf_token', CSRF);
    toast('Importando...');
    const r = await fetch('/admin/api/infraestructuras.php',{method:'POST',body:fd,headers:{'X-CSRF-TOKEN':CSRF}});
    const j = await r.json();
    input.value='';
    if(j.ok){
        let msg = j.insertadas+' infraestructuras importadas';
        if(j.errores_count) msg += ', '+j.errores_count+' errores';
        toast(msg, j.errores_count===0);
        if(j.errores && j.errores.length) console.warn('Errores de importación:', j.errores);
        loadInfras('');
    } else toast(j.error||'Error importando',false);
}
// Search debounce
let infraTimer;
$('#infra-search')?.addEventListener('input',function(){ clearTimeout(infraTimer); infraTimer=setTimeout(()=>loadInfras(this.value),400); });

// ═══════════════════ OPERADORES ═══════════════════
let usersData=[], allInfrasForAssign=[];
async function loadUsers(){
    const [r, inf] = await Promise.all([
        api('/admin/api/operadores.php'),
        api('/admin/api/infraestructuras.php?q=')
    ]);
    usersData = r.usuarios||[];
    allInfrasForAssign = (inf.infraestructuras||[]).filter(i=>i.activa);
    renderUsers();
}
function renderUsers(){
    const tb=$('#users-body');
    if(!usersData.length){ tb.innerHTML='<tr><td colspan="11" class="txt-m" style="text-align:center;padding:28px">Sin usuarios</td></tr>'; return; }
    tb.innerHTML = usersData.map(u=>{
        const rolBadge = u.rol==='superadmin'?'b-sa':u.rol==='admin'?'b-admin':'b-op';
        const link = u.rol==='operador' ? APP_URL+'/public/operador.php?user='+u.id : '';
        return `<tr>
        <td>${u.id}</td>
        <td><strong>${esc(u.nombre)}</strong></td>
        <td class="txt-m">${esc(u.email)}</td>
        <td><span class="badge ${rolBadge}">${u.rol}</span></td>
        <td>${u.total_infras||0}</td>
        <td><strong>${u.total_fotos||0}</strong></td>
        <td>${fmtDate(u.ultima_foto)}</td>
        <td>${fmtDate(u.ultimo_login)}</td>
        <td>${u.activo?'<span class="badge b-act">Activo</span>':'<span class="badge b-inact">Inactivo</span>'}</td>
        <td>${link?`<div class="link-box" style="padding:4px 8px"><a href="${esc(link)}" target="_blank" style="color:var(--alight);text-decoration:none;font-size:.7rem">${esc(link)}</a><button class="btn-s" style="padding:2px 6px;font-size:.6rem" onclick="navigator.clipboard.writeText('${esc(link)}');toast('Enlace copiado')"><i class="bi bi-clipboard"></i></button></div>`:'<span class="txt-m">N/A</span>'}</td>
        ${IS_SA?`<td style="display:flex;gap:4px;flex-wrap:nowrap">
            <button class="btn btn-gh" style="padding:4px 8px;font-size:.68rem" onclick="userModal(${u.id})" title="Editar"><i class="bi bi-pencil"></i></button>
            <button class="btn ${u.activo?'btn-dn':'btn-ok'}" style="padding:4px 8px;font-size:.68rem" onclick="toggleUser(${u.id},${u.activo?0:1})" title="${u.activo?'Desactivar':'Activar'}">${u.activo?'<i class="bi bi-x-lg"></i>':'<i class="bi bi-check-lg"></i>'}</button>
        </td>`:''}</tr>`;
    }).join('');
}

let userAssignedInfras = new Set();
async function userModal(id){
    const u = id ? usersData.find(x=>x.id==id) : null;
    $('#modal-user-title').textContent = u ? 'Editar usuario #'+u.id : 'Nuevo usuario';
    $('#user-id').value = u ? u.id : '';
    $('#user-nombre').value = u ? u.nombre : '';
    $('#user-email').value = u ? u.email : '';
    $('#user-password').value = '';
    $('#user-rol').value = u ? u.rol : 'operador';
    $('#pw-hint').textContent = u ? '(dejar vacío para no cambiar)' : '(obligatoria)';

    userAssignedInfras = new Set();
    if(u){
        const r = await api('/admin/api/operadores.php?action=infras&usuario_id='+u.id);
        (r.infraestructuras||[]).forEach(i => userAssignedInfras.add(i.id));
    }
    renderInfraChips();
    openModal('modal-user');
}
function renderInfraChips(filter=''){
    const f = filter.toLowerCase();
    const chips = allInfrasForAssign.filter(i => !f || i.nombre.toLowerCase().includes(f) || (i.cod_infoca||'').toLowerCase().includes(f) || (i.municipio||'').toLowerCase().includes(f));
    $('#user-infra-chips').innerHTML = chips.length ? chips.map(i =>
        `<div class="chip ${userAssignedInfras.has(i.id)?'selected':''}" onclick="toggleInfraChip(${i.id},this)">${esc(i.nombre)} ${i.cod_infoca?'('+esc(i.cod_infoca)+')':''}</div>`
    ).join('') : '<span class="txt-m" style="font-size:.75rem;padding:8px">Sin infraestructuras</span>';
}
function toggleInfraChip(id,el){
    if(userAssignedInfras.has(id)){ userAssignedInfras.delete(id); el.classList.remove('selected'); }
    else { userAssignedInfras.add(id); el.classList.add('selected'); }
}
$('#user-infra-filter')?.addEventListener('input',function(){ renderInfraChips(this.value); });

async function saveUser(){
    const id = $('#user-id').value;
    const data = {
        nombre: $('#user-nombre').value.trim(),
        email: $('#user-email').value.trim(),
        password: $('#user-password').value,
        rol: $('#user-rol').value,
        csrf_token: CSRF
    };
    if(!data.nombre||!data.email){ toast('Nombre y email obligatorios',false); return; }
    if(!id && !data.password){ toast('Contraseña obligatoria para nuevo usuario',false); return; }
    if(data.password && data.password.length < 6){ toast('Contraseña mínimo 6 caracteres',false); return; }
    if(!data.password) delete data.password;

    let r;
    if(id){
        data.id = parseInt(id);
        r = await api('/admin/api/operadores.php',{method:'PUT',body:JSON.stringify(data)});
    } else {
        r = await api('/admin/api/operadores.php',{method:'POST',body:JSON.stringify(data)});
    }
    if(!r.ok){ toast(r.error||'Error',false); return; }

    // Save infra assignments
    const uid = id || r.id;
    const r2 = await api('/admin/api/operadores.php',{method:'POST',body:JSON.stringify({
        action:'assign_infras',
        usuario_id: parseInt(uid),
        infraestructura_ids: Array.from(userAssignedInfras),
        csrf_token: CSRF
    })});

    toast('Usuario guardado'+(r2.ok?', '+r2.asignadas+' infras asignadas':''));
    closeModal('modal-user');
    loadUsers();
}
async function toggleUser(id,activo){
    const r = await api('/admin/api/operadores.php',{method:'PUT',body:JSON.stringify({id,activo,csrf_token:CSRF})});
    if(r.ok){ toast(activo?'Usuario activado':'Usuario desactivado'); loadUsers(); }
    else toast(r.error||'Error',false);
}

// ═══════════════════ MAPA ═══════════════════
let map=null, infraMarkers=[], kmlLayersOnMap=[];
function initMap(){
    if(map) return;
    setTimeout(()=>{
        map = L.map('admin-map').setView([37.8,-3.8],8);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
            attribution:'© OpenStreetMap',maxZoom:19
        }).addTo(map);
        loadMapInfras();
        loadKmlLayers();
    },100);
}
async function loadMapInfras(){
    const r = await api('/admin/api/infraestructuras.php?q=');
    const infras = (r.infraestructuras||[]).filter(i=>i.activa && i.lat_teorica && i.lon_teorica);
    infraMarkers.forEach(m=>map.removeLayer(m));
    infraMarkers=[];
    const bounds=[];
    infras.forEach(i=>{
        const lat=parseFloat(i.lat_teorica), lon=parseFloat(i.lon_teorica);
        if(isNaN(lat)||isNaN(lon)) return;
        const m = L.circleMarker([lat,lon],{radius:7,fillColor:'#3b82f6',color:'#1e40af',weight:2,fillOpacity:.8})
            .bindPopup(`<strong>${esc(i.nombre)}</strong><br>
                ${i.cod_infoca?'Cód: '+esc(i.cod_infoca)+'<br>':''}
                ${i.provincia?esc(i.provincia)+', ':''}${i.municipio?esc(i.municipio)+'<br>':''}
                Fotos: ${i.total_fotos||0}<br>
                <small>${lat.toFixed(5)}, ${lon.toFixed(5)}</small>`)
            .addTo(map);
        infraMarkers.push(m);
        bounds.push([lat,lon]);
    });
    if(bounds.length) map.fitBounds(bounds,{padding:[30,30]});
}

async function loadKmlLayers(){
    const r = await api('/admin/api/capas_kml.php');
    const capas = r.capas||[];
    renderKmlTable(capas);

    // Load active KML content onto map
    kmlLayersOnMap.forEach(l=>map.removeLayer(l));
    kmlLayersOnMap=[];

    const rFull = await fetch('/public/api/capas_kml.php');
    const jFull = await rFull.json();
    const fullCapas = jFull.capas || jFull || [];

    fullCapas.forEach(c=>{
        if(!c.contenido_kml) return;
        try {
            const parser = new DOMParser();
            const kmlDoc = parser.parseFromString(c.contenido_kml, 'text/xml');
            const layer = parseKML(kmlDoc, c.color||'#8b5cf6');
            if(layer){ layer.addTo(map); kmlLayersOnMap.push(layer); }
        } catch(e){ console.warn('Error parsing KML:', c.nombre, e); }
    });
}

function parseKML(doc, color){
    const group = L.featureGroup();
    let hasFeatures = false;
    // Placemarks
    const pms = doc.querySelectorAll('Placemark');
    pms.forEach(pm=>{
        const name = pm.querySelector('name')?.textContent || '';
        // Point
        const pt = pm.querySelector('Point coordinates');
        if(pt){
            const [lon,lat] = pt.textContent.trim().split(',').map(Number);
            if(!isNaN(lat)&&!isNaN(lon)){
                L.circleMarker([lat,lon],{radius:5,fillColor:color,color:color,weight:1,fillOpacity:.7}).bindPopup(esc(name)).addTo(group);
                hasFeatures=true;
            }
        }
        // LineString
        const ls = pm.querySelector('LineString coordinates');
        if(ls){
            const coords = ls.textContent.trim().split(/\s+/).map(c=>{const p=c.split(',');return [Number(p[1]),Number(p[0])];}).filter(c=>!isNaN(c[0])&&!isNaN(c[1]));
            if(coords.length){ L.polyline(coords,{color:color,weight:2}).bindPopup(esc(name)).addTo(group); hasFeatures=true; }
        }
        // Polygon
        const pg = pm.querySelector('Polygon outerBoundaryIs LinearRing coordinates');
        if(pg){
            const coords = pg.textContent.trim().split(/\s+/).map(c=>{const p=c.split(',');return [Number(p[1]),Number(p[0])];}).filter(c=>!isNaN(c[0])&&!isNaN(c[1]));
            if(coords.length){ L.polygon(coords,{color:color,fillColor:color,fillOpacity:.15,weight:2}).bindPopup(esc(name)).addTo(group); hasFeatures=true; }
        }
    });
    return hasFeatures ? group : null;
}

function renderKmlTable(capas){
    const tb=$('#kml-body');
    if(!capas.length){ tb.innerHTML='<tr><td colspan="6" class="txt-m" style="text-align:center;padding:20px">Sin capas KML</td></tr>'; return; }
    tb.innerHTML = capas.map(c=>`<tr>
        <td><strong>${esc(c.nombre)}</strong></td>
        <td><span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:${esc(c.color||'#8b5cf6')};vertical-align:middle"></span> ${esc(c.color||'')}</td>
        <td>${fmtSize(c.tamano_bytes||0)}</td>
        <td>${fmtDate(c.created_at)}</td>
        <td>${c.activa?'<span class="badge b-act">Sí</span>':'<span class="badge b-inact">No</span>'}</td>
        ${IS_SA?`<td style="display:flex;gap:4px">
            <button class="btn ${c.activa?'btn-dn':'btn-ok'}" style="padding:4px 8px;font-size:.68rem" onclick="toggleKml(${c.id},${c.activa?0:1})">${c.activa?'<i class="bi bi-x-lg"></i>':'<i class="bi bi-check-lg"></i>'}</button>
            <button class="btn btn-dn" style="padding:4px 8px;font-size:.68rem" onclick="if(confirm('¿Eliminar capa ${esc(c.nombre)}?'))deleteKml(${c.id})"><i class="bi bi-trash"></i></button>
        </td>`:''}`).join('');
}

async function uploadKml(input){
    if(!input.files.length) return;
    const fd = new FormData();
    fd.append('archivo', input.files[0]);
    fd.append('nombre', input.files[0].name.replace(/\.(kml|kmz)$/i,''));
    fd.append('color', '#'+Math.floor(Math.random()*16777215).toString(16).padStart(6,'0'));
    fd.append('csrf_token', CSRF);
    toast('Subiendo capa...');
    const r = await fetch('/admin/api/capas_kml.php',{method:'POST',body:fd,headers:{'X-CSRF-TOKEN':CSRF}});
    const j = await r.json();
    input.value='';
    if(j.ok){ toast('Capa KML subida'); loadKmlLayers(); }
    else toast(j.error||'Error subiendo',false);
}
async function toggleKml(id,activa){
    const r = await api('/admin/api/capas_kml.php',{method:'PUT',body:JSON.stringify({id,activa,csrf_token:CSRF})});
    if(r.ok){ toast(activa?'Capa activada':'Capa desactivada'); loadKmlLayers(); }
    else toast(r.error||'Error',false);
}
async function deleteKml(id){
    const r = await api('/admin/api/capas_kml.php',{method:'DELETE',body:JSON.stringify({id,csrf_token:CSRF})});
    if(r.ok){ toast('Capa eliminada'); loadKmlLayers(); }
    else toast(r.error||'Error',false);
}

// Close modals on bg click
$$('.modal-bg').forEach(bg=>{
    bg.addEventListener('click',e=>{ if(e.target===bg) bg.classList.remove('open'); });
});
</script>
</body>
</html>
