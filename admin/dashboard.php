<?php
/**
 * RAPCA - Panel de Administración
 * Accesible para superadmin y admin
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = requireRole(['superadmin', 'admin']);
$pdo = getDB();

// ─── Métricas ────────────────────────────────────────────────────────
$totalInfra     = (int) $pdo->query("SELECT COUNT(*) FROM infraestructuras WHERE activa = 1")->fetchColumn();
$totalRegistros = (int) $pdo->query("SELECT COUNT(*) FROM registros")->fetchColumn();
$totalUsuarios  = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn();
$totalOperadores = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND rol = 'operador'")->fetchColumn();

$registrosHoy = (int) $pdo->query("SELECT COUNT(*) FROM registros WHERE DATE(fecha) = CURDATE()")->fetchColumn();
$registrosSemana = (int) $pdo->query("SELECT COUNT(*) FROM registros WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

$fotosAleatorias   = (int) $pdo->query("SELECT COUNT(*) FROM registros WHERE tipo_foto = 'aleatorio'")->fetchColumn();
$fotosComparativas = (int) $pdo->query("SELECT COUNT(*) FROM registros WHERE tipo_foto = 'comparativo'")->fetchColumn();
$fotosVP = (int) $pdo->query("SELECT COUNT(*) FROM registros WHERE estado_incidencia = 'vp'")->fetchColumn();
$fotosEV = (int) $pdo->query("SELECT COUNT(*) FROM registros WHERE estado_incidencia = 'ev'")->fetchColumn();

$subidasFallidas = 0;
try {
    $subidasFallidas = (int) $pdo->query("SELECT COUNT(*) FROM subidas_fallidas WHERE resuelta = 0")->fetchColumn();
} catch (\Exception $e) {}

// Últimos registros
$ultimosRegistros = $pdo->query("
    SELECT r.id, r.fecha, r.tipo_foto, r.estado_incidencia, r.url_cloudinary,
           r.observaciones, r.nombre_archivo,
           i.nombre AS infra_nombre, i.cod_infoca, i.provincia, i.municipio,
           u.nombre AS operador_nombre
    FROM registros r
    JOIN infraestructuras i ON r.infra_id = i.id
    JOIN usuarios u ON r.usuario_id = u.id
    ORDER BY r.fecha DESC
    LIMIT 25
")->fetchAll();

// Operadores con actividad reciente
$operadoresActivos = $pdo->query("
    SELECT u.id, u.nombre, u.email, u.ultimo_login,
           COUNT(r.id) AS total_registros,
           MAX(r.fecha) AS ultima_foto
    FROM usuarios u
    LEFT JOIN registros r ON u.id = r.usuario_id
    WHERE u.activo = 1 AND u.rol = 'operador'
    GROUP BY u.id
    ORDER BY ultima_foto DESC
")->fetchAll();

// Infraestructuras con más registros
$topInfras = $pdo->query("
    SELECT i.id, i.nombre, i.cod_infoca, i.provincia, i.municipio,
           COUNT(r.id) AS total_fotos,
           MAX(r.fecha) AS ultima_visita
    FROM infraestructuras i
    LEFT JOIN registros r ON i.id = r.infra_id
    WHERE i.activa = 1
    GROUP BY i.id
    ORDER BY total_fotos DESC
    LIMIT 15
")->fetchAll();

$isSuperAdmin = isSuperAdmin();
$rolLabel = $isSuperAdmin ? 'Super Administrador' : 'Administrador (solo lectura)';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAPCA - Panel de Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --bg-card: #1e293b;
            --bg-hover: #334155;
            --accent: #3b82f6;
            --accent-light: #60a5fa;
            --green: #22c55e;
            --amber: #f59e0b;
            --red: #ef4444;
            --purple: #a855f7;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --border: rgba(255,255,255,0.06);
            --radius: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* Header */
        .top-bar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--accent), var(--purple));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: #fff;
        }

        .brand-name { font-weight: 800; font-size: 1.05rem; letter-spacing: 1px; }
        .brand-sub { font-size: 0.7rem; color: var(--text-muted); }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 0.82rem;
        }

        .user-info { color: var(--text-muted); }
        .user-info strong { color: var(--text); }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-badge--super { background: rgba(245,158,11,0.15); color: #fbbf24; }
        .role-badge--admin { background: rgba(168,85,247,0.15); color: #c084fc; }

        .btn-sm {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--text-muted);
            transition: all 0.2s;
        }

        .btn-sm:hover { background: var(--bg-hover); color: #fff; }

        /* Layout */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px 20px;
        }

        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .stat-icon--blue { background: rgba(59,130,246,0.12); color: #60a5fa; }
        .stat-icon--green { background: rgba(34,197,94,0.12); color: #4ade80; }
        .stat-icon--purple { background: rgba(168,85,247,0.12); color: #c084fc; }
        .stat-icon--amber { background: rgba(245,158,11,0.12); color: #fbbf24; }
        .stat-icon--red { background: rgba(239,68,68,0.12); color: #f87171; }
        .stat-icon--cyan { background: rgba(6,182,212,0.12); color: #22d3ee; }

        .stat-value { font-size: 1.6rem; font-weight: 800; line-height: 1.1; }
        .stat-label { font-size: 0.72rem; color: var(--text-muted); margin-top: 2px; }

        /* Sections */
        .section-title {
            font-size: 0.92rem;
            font-weight: 700;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i { color: var(--accent-light); font-size: 1rem; }

        /* Tables */
        .panel {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .panel-scroll {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        th {
            background: rgba(255,255,255,0.03);
            padding: 10px 14px;
            text-align: left;
            font-weight: 700;
            color: var(--text-muted);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        tr:last-child td { border-bottom: none; }

        tr:hover td { background: rgba(255,255,255,0.02); }

        .badge {
            display: inline-flex;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.68rem;
            font-weight: 700;
        }

        .badge--vp { background: rgba(59,130,246,0.15); color: #60a5fa; }
        .badge--ev { background: rgba(245,158,11,0.15); color: #fbbf24; }
        .badge--alea { background: rgba(59,130,246,0.1); color: #93c5fd; }
        .badge--comp { background: rgba(168,85,247,0.1); color: #d8b4fe; }

        .thumb {
            width: 36px; height: 36px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid var(--border);
        }

        .text-muted { color: var(--text-muted); }
        .text-green { color: #4ade80; }
        .text-red { color: #f87171; }

        /* Two col layout */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .two-col { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr; }
            .top-bar { flex-direction: column; gap: 10px; text-align: center; }
            .top-bar-right { flex-wrap: wrap; justify-content: center; }
            .container { padding: 16px 12px; }
        }
    </style>
</head>
<body>

    <!-- Top bar -->
    <div class="top-bar">
        <div class="top-bar-left">
            <div class="brand-icon"><i class="bi bi-geo-alt-fill"></i></div>
            <div>
                <div class="brand-name">RAPCA</div>
                <div class="brand-sub">Panel de Administración</div>
            </div>
        </div>
        <div class="top-bar-right">
            <div class="user-info">
                <strong><?= htmlspecialchars($user['nombre']) ?></strong>
                <span class="role-badge <?= $isSuperAdmin ? 'role-badge--super' : 'role-badge--admin' ?>">
                    <i class="bi <?= $isSuperAdmin ? 'bi-shield-lock-fill' : 'bi-eye-fill' ?>"></i>
                    <?= $isSuperAdmin ? 'Superadmin' : 'Admin' ?>
                </span>
            </div>
            <a href="/" class="btn-sm"><i class="bi bi-house"></i> Inicio</a>
            <a href="/public/login.php?logout" class="btn-sm"><i class="bi bi-box-arrow-right"></i> Salir</a>
        </div>
    </div>

    <div class="container">

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-icon--blue"><i class="bi bi-building"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($totalInfra) ?></div>
                    <div class="stat-label">Infraestructuras</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon--green"><i class="bi bi-camera-fill"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($totalRegistros) ?></div>
                    <div class="stat-label">Registros totales</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon--purple"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="stat-value"><?= $totalOperadores ?></div>
                    <div class="stat-label">Operadores</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon--cyan"><i class="bi bi-calendar-check"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($registrosHoy) ?></div>
                    <div class="stat-label">Fotos hoy</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon--amber"><i class="bi bi-calendar-week"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($registrosSemana) ?></div>
                    <div class="stat-label">Fotos esta semana</div>
                </div>
            </div>
            <?php if ($subidasFallidas > 0): ?>
            <div class="stat-card">
                <div class="stat-icon stat-icon--red"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div>
                    <div class="stat-value text-red"><?= $subidasFallidas ?></div>
                    <div class="stat-label">Subidas fallidas</div>
                </div>
            </div>
            <?php else: ?>
            <div class="stat-card">
                <div class="stat-icon stat-icon--green"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <div class="stat-value text-green">OK</div>
                    <div class="stat-label">Sin fallos de subida</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Resumen por tipo -->
        <div class="stats-grid" style="margin-bottom:28px;">
            <div class="stat-card">
                <div class="stat-icon stat-icon--blue"><i class="bi bi-camera"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($fotosAleatorias) ?></div>
                    <div class="stat-label">Fotos aleatorias</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon--purple"><i class="bi bi-layers"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($fotosComparativas) ?></div>
                    <div class="stat-label">Fotos comparativas</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon--blue"><i class="bi bi-eye"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($fotosVP) ?></div>
                    <div class="stat-label">Visita Previa (VP)</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon--amber"><i class="bi bi-clipboard-check"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($fotosEV) ?></div>
                    <div class="stat-label">Evaluación (EV)</div>
                </div>
            </div>
        </div>

        <!-- Últimos registros -->
        <div class="section-title"><i class="bi bi-clock-history"></i> Últimos registros</div>
        <div class="panel">
            <div class="panel-scroll">
                <table>
                    <thead>
                        <tr>
                            <th></th>
                            <th>#</th>
                            <th>Infraestructura</th>
                            <th>Cód. INFOCA</th>
                            <th>Provincia</th>
                            <th>Municipio</th>
                            <th>Operador</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimosRegistros as $r): ?>
                        <tr>
                            <td>
                                <?php if ($r['url_cloudinary']): ?>
                                    <img src="<?= htmlspecialchars($r['url_cloudinary']) ?>" class="thumb" loading="lazy">
                                <?php else: ?>
                                    <span class="text-muted">--</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $r['id'] ?></td>
                            <td><?= htmlspecialchars($r['infra_nombre']) ?></td>
                            <td><code><?= htmlspecialchars($r['cod_infoca'] ?? '') ?></code></td>
                            <td><?= htmlspecialchars($r['provincia'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['municipio'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['operador_nombre']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($r['fecha'])) ?></td>
                            <td>
                                <span class="badge <?= $r['tipo_foto'] === 'comparativo' ? 'badge--comp' : 'badge--alea' ?>">
                                    <?= $r['tipo_foto'] === 'comparativo' ? 'COMP' : 'ALEA' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge--<?= $r['estado_incidencia'] ?>">
                                    <?= strtoupper($r['estado_incidencia']) ?>
                                </span>
                            </td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;">
                                <?= htmlspecialchars($r['observaciones'] ?? '') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($ultimosRegistros)): ?>
                        <tr><td colspan="11" class="text-muted" style="text-align:center;padding:32px;">No hay registros todavía</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="two-col">
            <!-- Operadores -->
            <div>
                <div class="section-title"><i class="bi bi-people-fill"></i> Operadores</div>
                <div class="panel">
                    <div class="panel-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Fotos</th>
                                    <th>Última foto</th>
                                    <th>Último login</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($operadoresActivos as $op): ?>
                                <tr>
                                    <td><?= htmlspecialchars($op['nombre']) ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($op['email']) ?></td>
                                    <td><strong><?= number_format((int)$op['total_registros']) ?></strong></td>
                                    <td>
                                        <?= $op['ultima_foto']
                                            ? date('d/m/Y H:i', strtotime($op['ultima_foto']))
                                            : '<span class="text-muted">--</span>' ?>
                                    </td>
                                    <td>
                                        <?= $op['ultimo_login']
                                            ? date('d/m/Y H:i', strtotime($op['ultimo_login']))
                                            : '<span class="text-muted">Nunca</span>' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($operadoresActivos)): ?>
                                <tr><td colspan="5" class="text-muted" style="text-align:center;padding:24px;">Sin operadores</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Infraestructuras top -->
            <div>
                <div class="section-title"><i class="bi bi-building"></i> Infraestructuras con más registros</div>
                <div class="panel">
                    <div class="panel-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Cód. INFOCA</th>
                                    <th>Provincia</th>
                                    <th>Fotos</th>
                                    <th>Última visita</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topInfras as $inf): ?>
                                <tr>
                                    <td><?= htmlspecialchars($inf['nombre']) ?></td>
                                    <td><code><?= htmlspecialchars($inf['cod_infoca'] ?? '') ?></code></td>
                                    <td><?= htmlspecialchars($inf['provincia'] ?? '') ?></td>
                                    <td><strong><?= number_format((int)$inf['total_fotos']) ?></strong></td>
                                    <td>
                                        <?= $inf['ultima_visita']
                                            ? date('d/m/Y', strtotime($inf['ultima_visita']))
                                            : '<span class="text-muted">Sin visitas</span>' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($topInfras)): ?>
                                <tr><td colspan="5" class="text-muted" style="text-align:center;padding:24px;">Sin infraestructuras</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
