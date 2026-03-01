<?php
/**
 * RAPCA - API: Registros con GPS para mapa
 *
 * GET ?usuario_id=X               → registros del operador
 * GET ?infra_id=Y                 → registros de una infraestructura
 * GET ?tipo_foto=comparativo      → solo comparativas
 * GET ?limit=200                  → limitar resultados
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';

$infraId      = isset($_GET['infra_id']) ? (int) $_GET['infra_id'] : 0;
$usuarioId    = isset($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : 0;
$tipoFoto     = $_GET['tipo_foto'] ?? '';
$limit        = isset($_GET['limit']) ? min((int) $_GET['limit'], 1000) : 500;

try {
    $pdo = getDB();

    $sql = "SELECT r.id, r.infra_id, r.usuario_id, r.fecha, r.lat_real, r.lon_real,
                   r.url_cloudinary, r.estado_incidencia, r.observaciones,
                   r.tipo_foto, r.secuencia_comparativa, r.nombre_archivo,
                   i.nombre AS infra_nombre, i.cod_infoca, i.lat_teorica, i.lon_teorica,
                   u.nombre AS usuario_nombre
            FROM registros r
            INNER JOIN infraestructuras i ON r.infra_id = i.id
            INNER JOIN usuarios u ON r.usuario_id = u.id
            WHERE 1=1";

    $params = [];

    if ($infraId > 0) {
        $sql .= " AND r.infra_id = :infra_id";
        $params[':infra_id'] = $infraId;
    }

    if ($usuarioId > 0) {
        $sql .= " AND r.usuario_id = :usuario_id";
        $params[':usuario_id'] = $usuarioId;
    }

    if ($tipoFoto !== '' && in_array($tipoFoto, ['aleatorio', 'comparativo'], true)) {
        $sql .= " AND r.tipo_foto = :tipo_foto";
        $params[':tipo_foto'] = $tipoFoto;
    }

    $sql .= " ORDER BY r.fecha DESC LIMIT " . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'ok' => true,
        'registros' => $stmt->fetchAll(),
    ]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de base de datos']);
}
