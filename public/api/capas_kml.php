<?php
/**
 * RAPCA - API de Capas KML (Pública / Operador)
 *
 * GET: Devuelve las capas KML activas (solo lectura).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();

$stmt = $pdo->prepare(
    "SELECT id, nombre, contenido_kml, color
     FROM capas_kml
     WHERE activa = 1
     ORDER BY created_at DESC"
);
$stmt->execute();
$capas = $stmt->fetchAll();

echo json_encode(['ok' => true, 'capas' => $capas], JSON_UNESCAPED_UNICODE);
