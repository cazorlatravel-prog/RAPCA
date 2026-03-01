<?php
/**
 * RAPCA Admin API - Campos de formulario (CRUD)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireRole(['superadmin', 'admin']);
$pdo  = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Solo superadmin puede escribir
if ($method !== 'GET' && !isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Solo superadmin puede modificar campos']);
    exit;
}

try {
    switch ($method) {

        case 'GET':
            $rows = $pdo->query("SELECT * FROM campos_formulario ORDER BY orden ASC, id ASC")->fetchAll();
            foreach ($rows as &$r) {
                $r['opciones'] = $r['opciones'] ? json_decode($r['opciones'], true) : [];
            }
            echo json_encode(['ok' => true, 'campos' => $rows]);
            break;

        case 'POST':
            if (!validateCsrf()) { http_response_code(403); echo json_encode(['error' => 'CSRF inválido']); exit; }
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            $nombre = trim($data['nombre'] ?? '');
            $slug   = trim($data['slug'] ?? '');
            $tipo   = $data['tipo'] ?? 'texto';
            $opciones    = $data['opciones'] ?? [];
            $obligatorio = (int)($data['obligatorio'] ?? 0);
            $orden       = (int)($data['orden'] ?? 0);

            if ($nombre === '' || $slug === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Nombre y slug son obligatorios']);
                exit;
            }

            $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower($slug));

            $stmt = $pdo->prepare("INSERT INTO campos_formulario (nombre, slug, tipo, opciones, obligatorio, orden, activo)
                                   VALUES (:nombre, :slug, :tipo, :opciones, :obligatorio, :orden, 1)");
            $stmt->execute([
                ':nombre'      => $nombre,
                ':slug'        => $slug,
                ':tipo'        => $tipo,
                ':opciones'    => is_array($opciones) && count($opciones) ? json_encode($opciones) : null,
                ':obligatorio' => $obligatorio,
                ':orden'       => $orden,
            ]);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !validateCsrf()) { http_response_code(400); echo json_encode(['error' => 'Datos o CSRF inválidos']); exit; }

            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID requerido']); exit; }

            $fields = [];
            $params = [':id' => $id];
            foreach (['nombre','slug','tipo','obligatorio','orden','activo'] as $f) {
                if (isset($data[$f])) {
                    $val = $data[$f];
                    if ($f === 'slug') $val = preg_replace('/[^a-z0-9_]/', '_', strtolower($val));
                    if (in_array($f, ['obligatorio','orden','activo'])) $val = (int)$val;
                    $fields[] = "$f = :$f";
                    $params[":$f"] = $val;
                }
            }
            if (isset($data['opciones'])) {
                $fields[] = "opciones = :opciones";
                $params[':opciones'] = is_array($data['opciones']) && count($data['opciones']) ? json_encode($data['opciones']) : null;
            }

            if (empty($fields)) { echo json_encode(['ok' => true]); break; }

            $sql = "UPDATE campos_formulario SET " . implode(', ', $fields) . " WHERE id = :id";
            $pdo->prepare($sql)->execute($params);
            echo json_encode(['ok' => true]);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !validateCsrf()) { http_response_code(400); echo json_encode(['error' => 'Datos o CSRF inválidos']); exit; }
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID requerido']); exit; }

            $pdo->prepare("UPDATE campos_formulario SET activo = 0 WHERE id = :id")->execute([':id' => $id]);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
}
