<?php
/**
 * RAPCA - API: Buscar/crear infraestructuras
 *
 * GET  ?q=texto                             → buscar por nombre/código
 * GET  ?provincia=Y                         → filtrar por provincia
 * GET  ?provincia=Y&municipio=Z             → filtrar por provincia y municipio
 * GET  ?action=provincias                   → lista de provincias únicas
 * GET  ?action=municipios&provincia=Y       → lista de municipios de una provincia
 * GET  ?usuario_id=X                        → filtrar por acceso del operador
 * POST nombre, lat, lon                     → crear nueva infraestructura
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

// Detectar si las columnas provincia/municipio existen
$hasLocationCols = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM infraestructuras LIKE 'provincia'")->fetchAll();
    $hasLocationCols = count($cols) > 0;
} catch (\Exception $e) {
    // tabla puede no existir todavía
}

// ---------------------------------------------------------------
// GET: buscar infraestructuras / listas de provincias/municipios
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
        // Si no tiene infraestructuras asignadas, mostrar todas (permisivo)
    }

    // Listar provincias únicas
    if ($action === 'provincias') {
        if (!$hasLocationCols) {
            echo json_encode(['ok' => true, 'provincias' => []]);
            exit;
        }
        $sql = "SELECT DISTINCT provincia
                FROM infraestructuras
                WHERE activa = 1 AND provincia IS NOT NULL AND provincia != ''" . $infraFilter . "
                ORDER BY provincia ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($infraFilterParams);
        $provincias = array_column($stmt->fetchAll(), 'provincia');
        echo json_encode(['ok' => true, 'provincias' => $provincias]);
        exit;
    }

    // Listar municipios de una provincia
    if ($action === 'municipios') {
        if (!$hasLocationCols) {
            echo json_encode(['ok' => true, 'municipios' => []]);
            exit;
        }
        $provincia = trim($_GET['provincia'] ?? '');
        if ($provincia === '') {
            echo json_encode(['ok' => true, 'municipios' => []]);
            exit;
        }
        $sql = "SELECT DISTINCT municipio
                FROM infraestructuras
                WHERE activa = 1
                  AND provincia = ?  AND municipio IS NOT NULL AND municipio != ''" . $infraFilter . "
                ORDER BY municipio ASC";
        $params = array_merge([$provincia], $infraFilterParams);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $municipios = array_column($stmt->fetchAll(), 'municipio');
        echo json_encode(['ok' => true, 'municipios' => $municipios]);
        exit;
    }

    // Buscar infraestructuras con filtros opcionales
    $q         = trim($_GET['q'] ?? '');
    $provincia = trim($_GET['provincia'] ?? '');
    $municipio = trim($_GET['municipio'] ?? '');

    $where  = "activa = 1";
    $params = [];

    if ($hasLocationCols && $provincia !== '') {
        $where .= " AND provincia = ?";
        $params[] = $provincia;
    }
    if ($hasLocationCols && $municipio !== '') {
        $where .= " AND municipio = ?";
        $params[] = $municipio;
    }

    if ($q !== '') {
        $where .= " AND (nombre LIKE ? OR codigo_unico LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    // Aplicar filtro de operador
    if (!empty($infraFilterParams)) {
        $placeholders = implode(',', array_fill(0, count($infraFilterParams), '?'));
        $where .= " AND id IN ($placeholders)";
        $params = array_merge($params, $infraFilterParams);
    }

    $selectCols = "id, nombre, codigo_unico, lat_teorica, lon_teorica, tipo";
    if ($hasLocationCols) {
        $selectCols .= ", provincia, municipio";
    }

    $stmt = $pdo->prepare(
        "SELECT $selectCols
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
    $nombre    = trim($_POST['nombre'] ?? '');
    $lat       = (float) ($_POST['lat'] ?? 0);
    $lon       = (float) ($_POST['lon'] ?? 0);
    $provincia = trim($_POST['provincia'] ?? '');
    $municipio = trim($_POST['municipio'] ?? '');

    if ($nombre === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'nombre es requerido']);
        exit;
    }

    // Auto-generar código único
    $codigo = 'INF-' . strtoupper(substr(md5($nombre . time()), 0, 8));

    if ($hasLocationCols) {
        $stmt = $pdo->prepare(
            "INSERT INTO infraestructuras (nombre, codigo_unico, lat_teorica, lon_teorica, tipo, provincia, municipio, activa)
             VALUES (:nombre, :codigo, :lat, :lon, NULL, :provincia, :municipio, 1)"
        );
        $stmt->execute([
            ':nombre'    => $nombre,
            ':codigo'    => $codigo,
            ':lat'       => $lat,
            ':lon'       => $lon,
            ':provincia' => $provincia ?: null,
            ':municipio' => $municipio ?: null,
        ]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO infraestructuras (nombre, codigo_unico, lat_teorica, lon_teorica, tipo, activa)
             VALUES (:nombre, :codigo, :lat, :lon, NULL, 1)"
        );
        $stmt->execute([
            ':nombre'  => $nombre,
            ':codigo'  => $codigo,
            ':lat'     => $lat,
            ':lon'     => $lon,
        ]);
    }

    $newId = (int) $pdo->lastInsertId();

    echo json_encode([
        'ok' => true,
        'infraestructura' => [
            'id'            => $newId,
            'nombre'        => $nombre,
            'codigo_unico'  => $codigo,
            'lat_teorica'   => $lat,
            'lon_teorica'   => $lon,
            'provincia'     => $provincia ?: null,
            'municipio'     => $municipio ?: null,
        ]
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
