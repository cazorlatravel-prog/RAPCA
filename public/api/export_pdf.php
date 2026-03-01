<?php
/**
 * RAPCA - Exportar PDF de registros
 *
 * Genera un informe PDF para un registro individual o para todos
 * los registros de un usuario.
 *
 * GET ?registro_id=X&usuario_id=Y   → PDF de un registro
 * GET ?usuario_id=Y&all=1           → PDF resumen de todos
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$usuarioId  = isset($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : 0;
$registroId = isset($_GET['registro_id']) ? (int) $_GET['registro_id'] : 0;
$all        = isset($_GET['all']) && $_GET['all'] === '1';

if ($usuarioId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'usuario_id requerido']);
    exit;
}

$pdo = getDB();

try {
    if ($registroId > 0) {
        // Single record PDF
        $stmt = $pdo->prepare("
            SELECT r.*, i.nombre AS infra_nombre, i.codigo_unico, i.provincia, i.municipio,
                   u.nombre AS usuario_nombre, uo.nombre AS uo_nombre
            FROM registros r
            JOIN infraestructuras i ON r.infra_id = i.id
            JOIN usuarios u ON r.usuario_id = u.id
            LEFT JOIN unidades_obra uo ON r.unidad_obra_id = uo.id
            WHERE r.id = :id AND r.usuario_id = :uid
        ");
        $stmt->execute([':id' => $registroId, ':uid' => $usuarioId]);
        $record = $stmt->fetch();

        if (!$record) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Registro no encontrado']);
            exit;
        }

        $html = buildSingleRecordHtml($record);
        $filename = 'RAPCA_registro_' . $registroId . '.pdf';

    } elseif ($all) {
        // All records summary
        $stmt = $pdo->prepare("
            SELECT r.*, i.nombre AS infra_nombre, i.codigo_unico,
                   u.nombre AS usuario_nombre, uo.nombre AS uo_nombre
            FROM registros r
            JOIN infraestructuras i ON r.infra_id = i.id
            JOIN usuarios u ON r.usuario_id = u.id
            LEFT JOIN unidades_obra uo ON r.unidad_obra_id = uo.id
            WHERE r.usuario_id = :uid
            ORDER BY r.fecha DESC
            LIMIT 200
        ");
        $stmt->execute([':uid' => $usuarioId]);
        $records = $stmt->fetchAll();

        if (empty($records)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'No hay registros']);
            exit;
        }

        $html = buildAllRecordsHtml($records, $usuarioId);
        $filename = 'RAPCA_informe_' . date('Y-m-d') . '.pdf';

    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Parámetros insuficientes']);
        exit;
    }

    // Generate PDF
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Stream to browser
    $dompdf->stream($filename, ['Attachment' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

// ─── HTML builders ────────────────────────────────────────────────────

function buildSingleRecordHtml(array $r): string
{
    $fecha = date('d/m/Y H:i', strtotime($r['fecha']));
    $estado = strtoupper($r['estado_incidencia'] ?? 'vp');
    $tipo = $r['tipo_foto'] === 'comparativo' ? 'Comparativa' : 'Aleatoria';
    $seq = $r['secuencia_comparativa'] ? 'W' . $r['secuencia_comparativa'] : '';
    $coords = ($r['lat_real'] && $r['lon_real'])
        ? number_format((float)$r['lat_real'], 7) . ', ' . number_format((float)$r['lon_real'], 7)
        : '--';
    $imgUrl = $r['url_cloudinary'] ?? '';
    $imgTag = $imgUrl ? '<img src="' . htmlspecialchars($imgUrl) . '" style="max-width:100%;max-height:400px;border-radius:8px;margin-top:12px;">' : '';

    return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #333; margin: 30px; }
h1 { font-size: 20px; color: #4f6ef7; margin-bottom: 4px; }
.subtitle { font-size: 11px; color: #888; margin-bottom: 20px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
th { background: #f3f4f6; font-weight: 700; color: #555; width: 30%; }
.badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: 700; }
.badge-vp { background: #dbeafe; color: #2563eb; }
.badge-ev { background: #fef3c7; color: #b45309; }
.badge-alea { background: #dbeafe; color: #1d4ed8; }
.badge-comp { background: #ede9fe; color: #6d28d9; }
.footer { margin-top: 30px; font-size: 9px; color: #aaa; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 10px; }
</style></head><body>
<h1>RAPCA — Informe de Registro</h1>
<div class="subtitle">Generado el {$fecha} · Registro #{$r['id']}</div>
<table>
<tr><th>Infraestructura</th><td>{$r['infra_nombre']} ({$r['codigo_unico']})</td></tr>
<tr><th>Ubicación</th><td>{$r['provincia']} / {$r['municipio']}</td></tr>
<tr><th>Fecha</th><td>{$fecha}</td></tr>
<tr><th>Operador</th><td>{$r['usuario_nombre']}</td></tr>
<tr><th>Estado</th><td><span class="badge badge-{$r['estado_incidencia']}">{$estado}</span></td></tr>
<tr><th>Tipo de foto</th><td><span class="badge badge-{$r['tipo_foto']}">{$tipo}</span> {$seq}</td></tr>
<tr><th>Coordenadas ETRS89</th><td>{$coords}</td></tr>
<tr><th>Unidad de Obra</th><td>{$r['uo_nombre']}</td></tr>
<tr><th>Observaciones</th><td>{$r['observaciones']}</td></tr>
<tr><th>Archivo</th><td>{$r['nombre_archivo']}</td></tr>
</table>
{$imgTag}
<div class="footer">RAPCA — Registro y Análisis de Puntos de Control y Actuaciones · rapca.app</div>
</body></html>
HTML;
}

function buildAllRecordsHtml(array $records, int $userId): string
{
    $total = count($records);
    $aleatorias = count(array_filter($records, fn($r) => $r['tipo_foto'] !== 'comparativo'));
    $comparativas = $total - $aleatorias;
    $today = date('d/m/Y');

    $rows = '';
    foreach ($records as $i => $r) {
        $n = $i + 1;
        $fecha = date('d/m/Y H:i', strtotime($r['fecha']));
        $estado = strtoupper($r['estado_incidencia'] ?? '');
        $tipo = $r['tipo_foto'] === 'comparativo' ? 'COMP' : 'ALEA';
        $estadoClass = $r['estado_incidencia'] ?? 'vp';
        $tipoClass = $r['tipo_foto'] ?? 'aleatorio';
        $rows .= "<tr>
            <td>{$n}</td>
            <td>" . htmlspecialchars($r['infra_nombre']) . "</td>
            <td>{$fecha}</td>
            <td><span class='badge badge-{$tipoClass}'>{$tipo}</span></td>
            <td><span class='badge badge-{$estadoClass}'>{$estado}</span></td>
            <td>" . htmlspecialchars($r['usuario_nombre'] ?? '') . "</td>
        </tr>";
    }

    return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #333; margin: 25px; }
h1 { font-size: 18px; color: #4f6ef7; margin-bottom: 2px; }
.subtitle { font-size: 10px; color: #888; margin-bottom: 16px; }
.stats { display: flex; gap: 20px; margin-bottom: 16px; }
.stat { text-align: center; }
.stat-val { font-size: 22px; font-weight: 800; color: #1a1a2e; }
.stat-label { font-size: 9px; color: #888; text-transform: uppercase; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
th { background: #f3f4f6; font-weight: 700; color: #555; }
.badge { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 9px; font-weight: 700; }
.badge-vp { background: #dbeafe; color: #2563eb; }
.badge-ev { background: #fef3c7; color: #b45309; }
.badge-aleatorio { background: #dbeafe; color: #1d4ed8; }
.badge-comparativo { background: #ede9fe; color: #6d28d9; }
.footer { margin-top: 20px; font-size: 9px; color: #aaa; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px; }
</style></head><body>
<h1>RAPCA — Informe de Registros</h1>
<div class="subtitle">Generado el {$today} · {$total} registros</div>
<table style="width:auto;margin-bottom:16px;">
<tr><td style="padding:4px 16px;text-align:center;"><strong style="font-size:18px;">{$total}</strong><br><span style="font-size:9px;color:#888;">TOTAL</span></td>
<td style="padding:4px 16px;text-align:center;"><strong style="font-size:18px;">{$aleatorias}</strong><br><span style="font-size:9px;color:#888;">ALEATORIAS</span></td>
<td style="padding:4px 16px;text-align:center;"><strong style="font-size:18px;">{$comparativas}</strong><br><span style="font-size:9px;color:#888;">COMPARATIVAS</span></td></tr>
</table>
<table>
<thead><tr><th>#</th><th>Infraestructura</th><th>Fecha</th><th>Tipo</th><th>Estado</th><th>Operador</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
<div class="footer">RAPCA — Registro y Análisis de Puntos de Control y Actuaciones · rapca.app</div>
</body></html>
HTML;
}
