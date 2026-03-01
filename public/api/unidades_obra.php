<?php
/**
 * RAPCA - API: Obtener unidades de obra
 *
 * GET → devuelve las unidades activas en JSON
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        "SELECT id, nombre, codigo
         FROM unidades_obra
         WHERE activa = 1
         ORDER BY nombre ASC"
    );
    $stmt->execute();

    echo json_encode(['ok' => true, 'unidades' => $stmt->fetchAll()]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de base de datos']);
}
