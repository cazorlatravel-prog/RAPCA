<?php
/**
 * RAPCA - API: Obtener campos dinámicos
 *
 * GET → devuelve los campos activos en JSON
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        "SELECT id, nombre, slug, tipo, opciones, obligatorio, orden
         FROM campos_formulario
         WHERE activo = 1
         ORDER BY orden ASC, id ASC"
    );
    $stmt->execute();
    $campos = $stmt->fetchAll();

    // Decodificar opciones JSON en cada campo
    foreach ($campos as &$campo) {
        $campo['obligatorio'] = (bool) $campo['obligatorio'];
        if ($campo['opciones']) {
            $campo['opciones'] = json_decode($campo['opciones'], true);
        } else {
            $campo['opciones'] = null;
        }
    }
    unset($campo);

    echo json_encode(['ok' => true, 'campos' => $campos]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de base de datos']);
}
