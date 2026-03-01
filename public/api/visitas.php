<?php
/**
 * RAPCA - API de Visitas del Operador
 *
 * GET: Lista visitas (registros) del operador agrupadas por infraestructura y fecha
 *   - usuario_id: ID del operador
 *   - action=detalle&registro_id=X: Detalle de un registro específico
 *
 * POST: Editar un registro existente
 *   - action=editar
 *   - registro_id: ID del registro a editar
 *   - usuario_id: para verificar propiedad
 *   - observaciones, estado_incidencia: campos editables
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $usuarioId = isset($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : 0;
    $action = $_GET['action'] ?? 'listar';

    if ($usuarioId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'usuario_id requerido']);
        exit;
    }

    // Detalle de un registro
    if ($action === 'detalle') {
        $registroId = isset($_GET['registro_id']) ? (int) $_GET['registro_id'] : 0;
        if ($registroId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'registro_id requerido']);
            exit;
        }

        $stmt = $pdo->prepare(
            "SELECT r.id, r.infra_id, r.fecha, r.lat_real, r.lon_real,
                    r.url_cloudinary, r.estado_incidencia, r.observaciones,
                    r.tipo_foto, r.secuencia_comparativa, r.nombre_archivo,
                    i.nombre AS infra_nombre, i.cod_infoca
             FROM registros r
             INNER JOIN infraestructuras i ON r.infra_id = i.id
             WHERE r.id = :id AND r.usuario_id = :uid"
        );
        $stmt->execute([':id' => $registroId, ':uid' => $usuarioId]);
        $registro = $stmt->fetch();

        if (!$registro) {
            echo json_encode(['ok' => false, 'error' => 'Registro no encontrado']);
            exit;
        }

        echo json_encode(['ok' => true, 'registro' => $registro]);
        exit;
    }

    // Listar visitas agrupadas por infraestructura y día
    $stmt = $pdo->prepare(
        "SELECT r.id, r.infra_id, r.fecha, r.lat_real, r.lon_real,
                r.url_cloudinary, r.estado_incidencia, r.observaciones,
                r.tipo_foto, r.secuencia_comparativa, r.nombre_archivo,
                i.nombre AS infra_nombre, i.cod_infoca
         FROM registros r
         INNER JOIN infraestructuras i ON r.infra_id = i.id
         WHERE r.usuario_id = :uid
         ORDER BY r.fecha DESC
         LIMIT 500"
    );
    $stmt->execute([':uid' => $usuarioId]);
    $registros = $stmt->fetchAll();

    // Agrupar por infraestructura + día
    $visitas = [];
    foreach ($registros as $r) {
        $dia = date('Y-m-d', strtotime($r['fecha']));
        $key = $r['infra_id'] . '_' . $dia;

        if (!isset($visitas[$key])) {
            $visitas[$key] = [
                'infra_id' => (int) $r['infra_id'],
                'infra_nombre' => $r['infra_nombre'],
                'infra_codigo' => $r['cod_infoca'] ?? '',
                'fecha' => $dia,
                'fotos' => [],
            ];
        }

        $visitas[$key]['fotos'][] = [
            'id' => (int) $r['id'],
            'url' => $r['url_cloudinary'],
            'estado' => $r['estado_incidencia'],
            'tipo' => $r['tipo_foto'],
            'seq' => $r['secuencia_comparativa'] ? (int) $r['secuencia_comparativa'] : null,
            'observaciones' => $r['observaciones'] ?? '',
            'nombre' => $r['nombre_archivo'] ?? '',
            'hora' => date('H:i', strtotime($r['fecha'])),
        ];
    }

    echo json_encode([
        'ok' => true,
        'visitas' => array_values($visitas),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $registroId = isset($_POST['registro_id']) ? (int) $_POST['registro_id'] : 0;
    $usuarioId = isset($_POST['usuario_id']) ? (int) $_POST['usuario_id'] : 0;

    if ($action === 'editar') {
        if ($registroId <= 0 || $usuarioId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'registro_id y usuario_id requeridos']);
            exit;
        }

        // Verificar que el registro pertenece al usuario
        $stmt = $pdo->prepare("SELECT id FROM registros WHERE id = :id AND usuario_id = :uid");
        $stmt->execute([':id' => $registroId, ':uid' => $usuarioId]);
        if (!$stmt->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'No tienes permiso para editar este registro']);
            exit;
        }

        // Construir UPDATE dinámico solo con campos enviados
        $updates = [];
        $params = [':id' => $registroId];

        if (isset($_POST['observaciones'])) {
            $updates[] = 'observaciones = :obs';
            $params[':obs'] = trim($_POST['observaciones']);
        }
        if (isset($_POST['estado_incidencia'])) {
            $estado = $_POST['estado_incidencia'];
            if (in_array($estado, ['vp', 'ev'], true)) {
                $updates[] = 'estado_incidencia = :estado';
                $params[':estado'] = $estado;
            }
        }
        if (empty($updates)) {
            echo json_encode(['ok' => false, 'error' => 'No hay campos para actualizar']);
            exit;
        }

        $sql = "UPDATE registros SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'añadir_foto') {
        echo json_encode(['ok' => true, 'message' => 'Usa el endpoint subir.php para añadir fotos']);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Acción no reconocida']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
