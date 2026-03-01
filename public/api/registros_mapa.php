<?php
/**
 * RAPCA - API: Registros con GPS para mapa
 *
 * GET ?usuario_id=X               → registros del operador
 * GET ?infra_id=Y                 → registros de una infraestructura
 * GET ?tipo_foto=comparativo      → solo comparativas
 * GET ?limit=200                  → limitar resultados
 *
 * POST (JSON body)                → guardar formulario de campo
 *   action=guardar_formulario
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';

// ─── HANDLE POST: Save form data ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || ($input['action'] ?? '') !== 'guardar_formulario') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
        exit;
    }

    $infraId    = (int) ($input['infra_id'] ?? 0);
    $usuarioId  = (int) ($input['usuario_id'] ?? 0);
    $latReal    = (float) ($input['lat_real'] ?? 0);
    $lonReal    = (float) ($input['lon_real'] ?? 0);
    $estado     = in_array($input['estado_incidencia'] ?? '', ['vp', 'ev'], true) ? $input['estado_incidencia'] : 'vp';
    $datos      = $input['datos_tecnicos'] ?? null;
    $obs        = $input['observaciones'] ?? '';
    $nombre     = $input['nombre_archivo'] ?? '';

    if ($infraId <= 0 || $usuarioId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'infra_id y usuario_id requeridos']);
        exit;
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO registros (infra_id, usuario_id, lat_real, lon_real, url_cloudinary,
                                   estado_incidencia, datos_tecnicos, observaciones, tipo_foto, nombre_archivo)
            VALUES (:infra_id, :usuario_id, :lat_real, :lon_real, '',
                    :estado, :datos, :obs, 'aleatorio', :nombre)
        ");
        $stmt->execute([
            ':infra_id'   => $infraId,
            ':usuario_id' => $usuarioId,
            ':lat_real'   => $latReal,
            ':lon_real'   => $lonReal,
            ':estado'     => $estado,
            ':datos'      => $datos !== null ? json_encode($datos) : null,
            ':obs'        => $obs,
            ':nombre'     => $nombre,
        ]);

        echo json_encode([
            'ok' => true,
            'registro_id' => (int) $pdo->lastInsertId(),
        ]);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al guardar formulario']);
    }
    exit;
}

// ─── HANDLE GET: Fetch records ──────────────────────────────
$infraId      = isset($_GET['infra_id']) ? (int) $_GET['infra_id'] : 0;
$usuarioId    = isset($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : 0;
$tipoFoto     = $_GET['tipo_foto'] ?? '';
$limit        = isset($_GET['limit']) ? min((int) $_GET['limit'], 1000) : 500;

try {
    $pdo = getDB();

    $sql = "SELECT r.id, r.infra_id, r.usuario_id, r.fecha, r.lat_real, r.lon_real,
                   r.url_cloudinary, r.estado_incidencia, r.observaciones,
                   r.tipo_foto, r.secuencia_comparativa, r.nombre_archivo,
                   i.nombre AS infra_nombre, i.cod_infoca, i.provincia, i.lat_teorica, i.lon_teorica,
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
