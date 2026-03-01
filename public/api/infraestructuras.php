<?php
/**
 * RAPCA - API: Buscar/crear infraestructuras
 *
 * GET  ?q=texto                             → buscar por nombre/cod_infoca
 * GET  ?zona=Z                              → filtrar por id_zona
 * GET  ?municipio=M                         → filtrar por municipio
 * GET  ?action=zonas                        → lista de zonas únicas
 * GET  ?action=municipios[&zona=Z]          → lista de municipios únicos
 * GET  ?usuario_id=X                        → filtrar por acceso del operador
 * POST nombre, ...                          → crear nueva infraestructura
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

try {
    $pdo = getDB();
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de conexión a base de datos']);
    exit;
}

// ---------------------------------------------------------------
// GET: buscar infraestructuras / listas de municipios
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action    = trim($_GET['action'] ?? '');
    $usuarioId = isset($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : 0;

    // Obtener IDs de infraestructuras accesibles para el operador
    $infraFilter = '';
    $infraFilterParams = [];
    if ($usuarioId > 0) {
        $infraIds = getOperadorInfraIds($usuarioId);
        if (!empty($infraIds)) {
            $placeholders = implode(',', array_fill(0, count($infraIds), '?'));
            $infraFilter = " AND id IN ($placeholders)";
            $infraFilterParams = $infraIds;
        }
    }

    // Listar zonas únicas
    if ($action === 'zonas') {
        $sql = "SELECT DISTINCT id_zona
                FROM infraestructuras
                WHERE activa = 1 AND id_zona IS NOT NULL AND id_zona != ''" . $infraFilter . "
                ORDER BY id_zona ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($infraFilterParams);
        $zonas = array_column($stmt->fetchAll(), 'id_zona');
        echo json_encode(['ok' => true, 'zonas' => $zonas]);
        exit;
    }

    // Listar municipios únicos (opcionalmente filtrados por zona)
    if ($action === 'municipios') {
        $zona = trim($_GET['zona'] ?? '');
        $sql = "SELECT DISTINCT municipio
                FROM infraestructuras
                WHERE activa = 1 AND municipio IS NOT NULL AND municipio != ''";
        $mParams = [];
        if ($zona !== '') {
            $sql .= " AND id_zona = ?";
            $mParams[] = $zona;
        }
        $sql .= $infraFilter;
        $mParams = array_merge($mParams, $infraFilterParams);
        $sql .= " ORDER BY municipio ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($mParams);
        $municipios = array_column($stmt->fetchAll(), 'municipio');
        echo json_encode(['ok' => true, 'municipios' => $municipios]);
        exit;
    }

    // Buscar infraestructuras con filtros opcionales
    $q         = trim($_GET['q'] ?? '');
    $zona      = trim($_GET['zona'] ?? '');
    $municipio = trim($_GET['municipio'] ?? '');

    $where  = "activa = 1";
    $params = [];

    if ($zona !== '') {
        $where .= " AND id_zona = ?";
        $params[] = $zona;
    }

    if ($municipio !== '') {
        $where .= " AND municipio = ?";
        $params[] = $municipio;
    }

    if ($q !== '') {
        $where .= " AND (nombre LIKE ? OR cod_infoca LIKE ? OR id_zona LIKE ? OR id_unidad LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    // Aplicar filtro de operador
    if (!empty($infraFilterParams)) {
        $placeholders = implode(',', array_fill(0, count($infraFilterParams), '?'));
        $where .= " AND id IN ($placeholders)";
        $params = array_merge($params, $infraFilterParams);
    }

    $stmt = $pdo->prepare(
        "SELECT id, id_zona, id_unidad, cod_infoca, nombre, superficie,
                municipio, monte, cod_monte, pendiente, distancia_aprisco,
                vegetacion, tipo_contrato, parque, pago_max, desbroce,
                observaciones, lat_teorica, lon_teorica
         FROM infraestructuras
         WHERE $where
         ORDER BY nombre ASC
         LIMIT 50"
    );
    $stmt->execute($params);

    echo json_encode(['ok' => true, 'infraestructuras' => $stmt->fetchAll()]);
    exit;
}

// ---------------------------------------------------------------
// POST: crear nueva infraestructura
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'nombre es requerido']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO infraestructuras
            (id_zona, id_unidad, cod_infoca, nombre, superficie, municipio,
             monte, cod_monte, pendiente, distancia_aprisco, vegetacion,
             tipo_contrato, parque, pago_max, desbroce, observaciones,
             lat_teorica, lon_teorica, activa)
         VALUES
            (:id_zona, :id_unidad, :cod_infoca, :nombre, :superficie, :municipio,
             :monte, :cod_monte, :pendiente, :distancia_aprisco, :vegetacion,
             :tipo_contrato, :parque, :pago_max, :desbroce, :observaciones,
             :lat, :lon, 1)"
    );

    $stmt->execute([
        ':id_zona'           => trim($_POST['id_zona'] ?? '') ?: null,
        ':id_unidad'         => trim($_POST['id_unidad'] ?? '') ?: null,
        ':cod_infoca'        => trim($_POST['cod_infoca'] ?? '') ?: null,
        ':nombre'            => $nombre,
        ':superficie'        => ($_POST['superficie'] ?? '') !== '' ? (float) $_POST['superficie'] : null,
        ':municipio'         => trim($_POST['municipio'] ?? '') ?: null,
        ':monte'             => trim($_POST['monte'] ?? '') ?: null,
        ':cod_monte'         => trim($_POST['cod_monte'] ?? '') ?: null,
        ':pendiente'         => trim($_POST['pendiente'] ?? '') ?: null,
        ':distancia_aprisco' => trim($_POST['distancia_aprisco'] ?? '') ?: null,
        ':vegetacion'        => trim($_POST['vegetacion'] ?? '') ?: null,
        ':tipo_contrato'     => trim($_POST['tipo_contrato'] ?? '') ?: null,
        ':parque'            => trim($_POST['parque'] ?? '') ?: null,
        ':pago_max'          => ($_POST['pago_max'] ?? '') !== '' ? (float) $_POST['pago_max'] : null,
        ':desbroce'          => trim($_POST['desbroce'] ?? '') ?: null,
        ':observaciones'     => trim($_POST['observaciones'] ?? '') ?: null,
        ':lat'               => ($_POST['lat'] ?? '') !== '' ? (float) $_POST['lat'] : null,
        ':lon'               => ($_POST['lon'] ?? '') !== '' ? (float) $_POST['lon'] : null,
    ]);

    $newId = (int) $pdo->lastInsertId();

    echo json_encode([
        'ok' => true,
        'infraestructura' => [
            'id'         => $newId,
            'nombre'     => $nombre,
            'cod_infoca' => trim($_POST['cod_infoca'] ?? '') ?: null,
        ]
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
