<?php
/**
 * RAPCA Admin API - Infraestructuras (CRUD + import)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireRole(['superadmin', 'admin']);
$pdo  = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET' && !isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Solo superadmin puede modificar infraestructuras']);
    exit;
}

$infraFields = ['provincia','id_zona','id_unidad','cod_infoca','nombre','superficie',
                'municipio','monte','cod_monte','pendiente','distancia_aprisco',
                'vegetacion','tipo_contrato','parque','pago_max','desbroce',
                'observaciones','lat_teorica','lon_teorica'];

try {
    switch ($method) {

        case 'GET':
            $q = trim($_GET['q'] ?? '');
            $sql = "SELECT i.*, COUNT(r.id) AS total_fotos
                    FROM infraestructuras i
                    LEFT JOIN registros r ON i.id = r.infra_id";
            $params = [];

            if ($q !== '') {
                $sql .= " WHERE (i.nombre LIKE :q OR i.cod_infoca LIKE :q OR i.municipio LIKE :q OR i.provincia LIKE :q)";
                $params[':q'] = "%$q%";
            }

            $sql .= " GROUP BY i.id ORDER BY i.activa DESC, i.nombre ASC LIMIT 200";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['ok' => true, 'infraestructuras' => $stmt->fetchAll()]);
            break;

        case 'POST':
            if (!validateCsrf()) { http_response_code(403); echo json_encode(['error' => 'CSRF inválido']); exit; }

            // Check if it's an import (multipart file upload)
            if (isset($_FILES['archivo'])) {
                handleImport($pdo, $infraFields);
                break;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $nombre = trim($data['nombre'] ?? '');
            if ($nombre === '') { http_response_code(400); echo json_encode(['error' => 'Nombre obligatorio']); exit; }

            $cols = ['nombre'];
            $vals = [':nombre'];
            $params = [':nombre' => $nombre];

            foreach ($infraFields as $f) {
                if ($f === 'nombre') continue;
                if (isset($data[$f]) && trim((string)$data[$f]) !== '') {
                    $cols[] = $f;
                    $vals[] = ":$f";
                    $params[":$f"] = trim((string)$data[$f]);
                }
            }

            $sql = "INSERT INTO infraestructuras (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
            $pdo->prepare($sql)->execute($params);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !validateCsrf()) { http_response_code(400); echo json_encode(['error' => 'Datos o CSRF inválidos']); exit; }

            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID requerido']); exit; }

            $fields = [];
            $params = [':id' => $id];
            foreach ($infraFields as $f) {
                if (isset($data[$f])) {
                    $fields[] = "$f = :$f";
                    $params[":$f"] = $data[$f] === '' ? null : $data[$f];
                }
            }
            if (isset($data['activa'])) {
                $fields[] = "activa = :activa";
                $params[':activa'] = (int)$data['activa'];
            }

            if (empty($fields)) { echo json_encode(['ok' => true]); break; }

            $sql = "UPDATE infraestructuras SET " . implode(', ', $fields) . " WHERE id = :id";
            $pdo->prepare($sql)->execute($params);
            echo json_encode(['ok' => true]);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !validateCsrf()) { http_response_code(400); echo json_encode(['error' => 'Datos o CSRF inválidos']); exit; }
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID requerido']); exit; }

            $pdo->prepare("UPDATE infraestructuras SET activa = 0 WHERE id = :id")->execute([':id' => $id]);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error BD: ' . $e->getMessage()]);
}

function handleImport(PDO $pdo, array $infraFields): void
{
    $file = $_FILES['archivo'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['csv','xlsx','xls'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Solo se admiten archivos CSV, XLS o XLSX']);
        return;
    }

    $rows = [];
    if ($ext === 'csv') {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle, 0, ';');
        if (!$header) $header = fgetcsv($handle, 0, ',');
        if (!$header) { echo json_encode(['error' => 'CSV vacío']); return; }
        $header = array_map(fn($h) => strtoupper(trim($h)), $header);
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < 2) continue;
            $rows[] = array_combine($header, array_pad($row, count($header), ''));
        }
        fclose($handle);
    } else {
        // Try PhpSpreadsheet
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            $autoload = __DIR__ . '/../../vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;
        }
        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $data  = $sheet->toArray(null, true, true, true);
            $header = array_map(fn($h) => strtoupper(trim((string)$h)), array_values(array_shift($data)));
            foreach ($data as $r) {
                $vals = array_values($r);
                if (count($vals) >= 2 && trim((string)($vals[0] ?? '')) !== '') {
                    $rows[] = array_combine($header, array_pad($vals, count($header), ''));
                }
            }
        } else {
            echo json_encode(['error' => 'PhpSpreadsheet no disponible. Use CSV.']);
            return;
        }
    }

    $colMap = [
        'PROVINCIA' => 'provincia', 'ID ZONA' => 'id_zona', 'ID UNIDAD' => 'id_unidad',
        'COD INFOCA' => 'cod_infoca', 'NOMBRE' => 'nombre', 'SUPERFICIE' => 'superficie',
        'MUNICIPIO' => 'municipio', 'MONTE' => 'monte', 'COD MONTE' => 'cod_monte',
        'PENDIENTE' => 'pendiente', 'DISTANCIA APRISCO' => 'distancia_aprisco',
        'VEGETACIÓN' => 'vegetacion', 'VEGETACION' => 'vegetacion',
        'TIPO CONTRATO' => 'tipo_contrato', 'PARQUE' => 'parque',
        'PAGO MAX' => 'pago_max', 'DESBROCE' => 'desbroce', 'OBSERVACIONES' => 'observaciones',
    ];

    $insertadas = 0;
    $errores = [];

    foreach ($rows as $i => $row) {
        $mapped = [];
        foreach ($row as $colName => $val) {
            $dbField = $colMap[$colName] ?? null;
            if ($dbField && trim((string)$val) !== '') {
                $mapped[$dbField] = trim((string)$val);
            }
        }
        if (empty($mapped['nombre'])) {
            $errores[] = "Fila " . ($i + 2) . ": Sin nombre";
            continue;
        }
        try {
            $cols = array_keys($mapped);
            $placeholders = array_map(fn($c) => ":$c", $cols);
            $sql = "INSERT INTO infraestructuras (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $params = [];
            foreach ($mapped as $k => $v) $params[":$k"] = $v;
            $stmt->execute($params);
            $insertadas++;
        } catch (\PDOException $e) {
            $errores[] = "Fila " . ($i + 2) . ": " . $e->getMessage();
        }
    }

    echo json_encode([
        'ok' => true,
        'insertadas' => $insertadas,
        'errores_count' => count($errores),
        'errores' => array_slice($errores, 0, 20),
    ]);
}
