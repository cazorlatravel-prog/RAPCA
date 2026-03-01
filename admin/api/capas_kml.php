<?php
/**
 * RAPCA Admin API - Capas KML/KMZ (CRUD)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireRole(['superadmin', 'admin']);
$pdo  = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET' && !isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Solo superadmin puede modificar capas KML']);
    exit;
}

try {
    switch ($method) {

        case 'GET':
            $rows = $pdo->query("SELECT id, nombre, color, activa, created_at, updated_at,
                                        LENGTH(contenido_kml) AS tamano_bytes
                                 FROM capas_kml ORDER BY created_at DESC")->fetchAll();
            echo json_encode(['ok' => true, 'capas' => $rows]);
            break;

        case 'POST':
            if (!validateCsrf()) { http_response_code(403); echo json_encode(['error' => 'CSRF inválido']); exit; }

            $nombre = trim($_POST['nombre'] ?? 'Capa sin nombre');
            $color  = trim($_POST['color'] ?? '#8b5cf6');
            $kmlContent = '';

            if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['archivo'];
                $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if ($ext === 'kml') {
                    $kmlContent = file_get_contents($file['tmp_name']);
                } elseif ($ext === 'kmz') {
                    // KMZ = ZIP con KML dentro
                    $zip = new \ZipArchive();
                    if ($zip->open($file['tmp_name']) === true) {
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $fname = $zip->getNameIndex($i);
                            if (strtolower(pathinfo($fname, PATHINFO_EXTENSION)) === 'kml') {
                                $kmlContent = $zip->getFromIndex($i);
                                break;
                            }
                        }
                        $zip->close();
                    }
                    if ($kmlContent === '') {
                        http_response_code(400);
                        echo json_encode(['error' => 'No se encontró archivo KML dentro del KMZ']);
                        return;
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Solo se admiten archivos KML o KMZ']);
                    return;
                }

                if ($nombre === 'Capa sin nombre') {
                    $nombre = pathinfo($file['name'], PATHINFO_FILENAME);
                }
            } elseif (!empty($_POST['contenido_kml'])) {
                $kmlContent = $_POST['contenido_kml'];
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Archivo KML/KMZ requerido']);
                return;
            }

            $stmt = $pdo->prepare("INSERT INTO capas_kml (nombre, contenido_kml, color, activa) VALUES (:nombre, :kml, :color, 1)");
            $stmt->execute([':nombre' => $nombre, ':kml' => $kmlContent, ':color' => $color]);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !validateCsrf()) { http_response_code(400); echo json_encode(['error' => 'Datos o CSRF inválidos']); exit; }

            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID requerido']); exit; }

            $fields = [];
            $params = [':id' => $id];

            foreach (['nombre','color'] as $f) {
                if (isset($data[$f])) {
                    $fields[] = "$f = :$f";
                    $params[":$f"] = trim($data[$f]);
                }
            }
            if (isset($data['activa'])) {
                $fields[] = "activa = :activa";
                $params[':activa'] = (int)$data['activa'];
            }

            if (empty($fields)) { echo json_encode(['ok' => true]); break; }

            $sql = "UPDATE capas_kml SET " . implode(', ', $fields) . " WHERE id = :id";
            $pdo->prepare($sql)->execute($params);
            echo json_encode(['ok' => true]);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !validateCsrf()) { http_response_code(400); echo json_encode(['error' => 'Datos o CSRF inválidos']); exit; }
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID requerido']); exit; }

            $pdo->prepare("DELETE FROM capas_kml WHERE id = :id")->execute([':id' => $id]);
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
