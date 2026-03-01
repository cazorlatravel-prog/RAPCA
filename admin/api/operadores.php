<?php
/**
 * RAPCA Admin API - Operadores / Usuarios (CRUD)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireRole(['superadmin', 'admin']);
$pdo  = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET' && !isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Solo superadmin puede modificar usuarios']);
    exit;
}

try {
    switch ($method) {

        case 'GET':
            $action = $_GET['action'] ?? 'list';

            if ($action === 'infras') {
                // Infraestructuras asignadas a un operador
                $uid = (int)($_GET['usuario_id'] ?? 0);
                $stmt = $pdo->prepare("SELECT i.id, i.nombre, i.cod_infoca, i.provincia, i.municipio
                    FROM operario_infraestructura oi
                    JOIN infraestructuras i ON oi.infraestructura_id = i.id
                    WHERE oi.usuario_id = :uid
                    ORDER BY i.nombre");
                $stmt->execute([':uid' => $uid]);
                echo json_encode(['ok' => true, 'infraestructuras' => $stmt->fetchAll()]);
                break;
            }

            $rows = $pdo->query("
                SELECT u.id, u.nombre, u.email, u.rol, u.activo, u.ultimo_login, u.created_at,
                       COUNT(r.id) AS total_fotos,
                       MAX(r.fecha) AS ultima_foto,
                       (SELECT COUNT(*) FROM operario_infraestructura oi WHERE oi.usuario_id = u.id) AS total_infras
                FROM usuarios u
                LEFT JOIN registros r ON u.id = r.usuario_id
                GROUP BY u.id
                ORDER BY FIELD(u.rol, 'superadmin', 'admin', 'operador'), u.nombre
            ")->fetchAll();
            echo json_encode(['ok' => true, 'usuarios' => $rows]);
            break;

        case 'POST':
            if (!validateCsrf()) { http_response_code(403); echo json_encode(['error' => 'CSRF inválido']); exit; }
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            $action = $data['action'] ?? 'create';

            if ($action === 'assign_infras') {
                $uid    = (int)($data['usuario_id'] ?? 0);
                $infraIds = $data['infraestructura_ids'] ?? [];
                if ($uid <= 0) { http_response_code(400); echo json_encode(['error' => 'usuario_id requerido']); exit; }

                $pdo->prepare("DELETE FROM operario_infraestructura WHERE usuario_id = :uid")->execute([':uid' => $uid]);

                $stmt = $pdo->prepare("INSERT INTO operario_infraestructura (usuario_id, infraestructura_id) VALUES (:uid, :iid)");
                foreach ($infraIds as $iid) {
                    $stmt->execute([':uid' => $uid, ':iid' => (int)$iid]);
                }
                echo json_encode(['ok' => true, 'asignadas' => count($infraIds)]);
                break;
            }

            // Create user
            $nombre   = trim($data['nombre'] ?? '');
            $email    = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $rol      = $data['rol'] ?? 'operador';

            if ($nombre === '' || $email === '' || $password === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Nombre, email y contraseña son obligatorios']);
                exit;
            }
            if (!in_array($rol, ['operador','admin','superadmin'])) {
                $rol = 'operador';
            }

            $hash = hashPassword($password);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol, activo) VALUES (:nombre, :email, :password, :rol, 1)");
            $stmt->execute([':nombre' => $nombre, ':email' => $email, ':password' => $hash, ':rol' => $rol]);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !validateCsrf()) { http_response_code(400); echo json_encode(['error' => 'Datos o CSRF inválidos']); exit; }

            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID requerido']); exit; }

            $fields = [];
            $params = [':id' => $id];

            foreach (['nombre','email','rol'] as $f) {
                if (isset($data[$f]) && trim($data[$f]) !== '') {
                    $fields[] = "$f = :$f";
                    $params[":$f"] = trim($data[$f]);
                }
            }
            if (isset($data['activo'])) {
                $fields[] = "activo = :activo";
                $params[':activo'] = (int)$data['activo'];
            }
            if (!empty($data['password'])) {
                $fields[] = "password = :password";
                $params[':password'] = hashPassword($data['password']);
            }

            if (empty($fields)) { echo json_encode(['ok' => true]); break; }

            $sql = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = :id";
            $pdo->prepare($sql)->execute($params);
            echo json_encode(['ok' => true]);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !validateCsrf()) { http_response_code(400); echo json_encode(['error' => 'Datos o CSRF inválidos']); exit; }
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID requerido']); exit; }

            $pdo->prepare("UPDATE usuarios SET activo = 0 WHERE id = :id")->execute([':id' => $id]);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }
} catch (\PDOException $e) {
    http_response_code(500);
    $msg = $e->getMessage();
    if (str_contains($msg, 'Duplicate entry')) {
        echo json_encode(['error' => 'El email ya existe']);
    } else {
        echo json_encode(['error' => 'Error BD: ' . $msg]);
    }
}
