<?php
/**
 * RAPCA - Importar infraestructuras desde Excel/CSV
 *
 * POST: Archivo Excel (.xlsx, .xls) o CSV (.csv)
 *
 * Columnas esperadas (orden o cabeceras):
 *   PROVINCIA, ID ZONA, ID UNIDAD, COD INFOCA, NOMBRE, SUPERFICIE, MUNICIPIO,
 *   MONTE, COD MONTE, PENDIENTE, DISTANCIA APRISCO, VEGETACIÓN,
 *   TIPO CONTRATO, PARQUE, PAGO MAX, DESBROCE, OBSERVACIONES
 *
 * Devuelve JSON con el resultado de la importación.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Archivo no recibido o error de subida']);
    exit;
}

$tmpFile  = $_FILES['archivo']['tmp_name'];
$fileName = $_FILES['archivo']['name'];
$ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Formato no soportado. Usa .xlsx, .xls o .csv']);
    exit;
}

try {
    // Leer el archivo con PhpSpreadsheet
    $spreadsheet = IOFactory::load($tmpFile);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, false);

    if (count($rows) < 2) {
        echo json_encode(['ok' => false, 'error' => 'El archivo debe tener al menos una fila de cabeceras y una de datos']);
        exit;
    }

    // Mapeo de cabeceras → columnas de BD
    $headerMap = [
        'provincia'         => 'provincia',
        'id zona'           => 'id_zona',
        'id_zona'           => 'id_zona',
        'id unidad'         => 'id_unidad',
        'id_unidad'         => 'id_unidad',
        'cod infoca'        => 'cod_infoca',
        'cod_infoca'        => 'cod_infoca',
        'nombre'            => 'nombre',
        'superficie'        => 'superficie',
        'municipio'         => 'municipio',
        'monte'             => 'monte',
        'cod monte'         => 'cod_monte',
        'cod_monte'         => 'cod_monte',
        'pendiente'         => 'pendiente',
        'distancia aprisco' => 'distancia_aprisco',
        'distancia_aprisco' => 'distancia_aprisco',
        'vegetacion'        => 'vegetacion',
        'vegetación'        => 'vegetacion',
        'tipo contrato'     => 'tipo_contrato',
        'tipo_contrato'     => 'tipo_contrato',
        'parque'            => 'parque',
        'pago max'          => 'pago_max',
        'pago_max'          => 'pago_max',
        'desbroce'          => 'desbroce',
        'observaciones'     => 'observaciones',
    ];

    // Orden por defecto si no se reconocen cabeceras
    $defaultOrder = [
        'provincia', 'id_zona', 'id_unidad', 'cod_infoca', 'nombre', 'superficie',
        'municipio', 'monte', 'cod_monte', 'pendiente', 'distancia_aprisco',
        'vegetacion', 'tipo_contrato', 'parque', 'pago_max', 'desbroce', 'observaciones',
    ];

    // Detectar cabeceras en la primera fila
    $headerRow = $rows[0];
    $colMapping = []; // índice columna → nombre campo BD
    $hasHeaders = false;

    foreach ($headerRow as $idx => $cell) {
        $normalized = mb_strtolower(trim((string) $cell));
        // Quitar tildes para la comparación
        $normalized = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n'],
            $normalized
        );
        if (isset($headerMap[$normalized])) {
            $colMapping[$idx] = $headerMap[$normalized];
            $hasHeaders = true;
        }
    }

    // Si no se detectaron cabeceras, usar orden por defecto
    if (!$hasHeaders) {
        foreach ($defaultOrder as $idx => $field) {
            if ($idx < count($headerRow)) {
                $colMapping[$idx] = $field;
            }
        }
        // La primera fila es dato, no cabecera
        $dataStartRow = 0;
    } else {
        $dataStartRow = 1;
    }

    $pdo = getDB();
    $inserted = 0;
    $updated = 0;
    $errors = [];

    $insertSql = "INSERT INTO infraestructuras
        (provincia, id_zona, id_unidad, cod_infoca, nombre, superficie, municipio,
         monte, cod_monte, pendiente, distancia_aprisco, vegetacion,
         tipo_contrato, parque, pago_max, desbroce, observaciones, activa)
        VALUES
        (:provincia, :id_zona, :id_unidad, :cod_infoca, :nombre, :superficie, :municipio,
         :monte, :cod_monte, :pendiente, :distancia_aprisco, :vegetacion,
         :tipo_contrato, :parque, :pago_max, :desbroce, :observaciones, 1)";

    $stmtInsert = $pdo->prepare($insertSql);

    for ($i = $dataStartRow; $i < count($rows); $i++) {
        $row = $rows[$i];
        $fila = $i + 1; // número de fila para mensajes de error (1-based)

        // Extraer datos según mapeo
        $data = [];
        foreach ($colMapping as $colIdx => $field) {
            $data[$field] = isset($row[$colIdx]) ? trim((string) $row[$colIdx]) : '';
        }

        // Validar que al menos tenga nombre
        $nombre = $data['nombre'] ?? '';
        if ($nombre === '') {
            // Saltar filas vacías sin generar error
            $allEmpty = true;
            foreach ($data as $v) {
                if ($v !== '') { $allEmpty = false; break; }
            }
            if ($allEmpty) continue;

            $errors[] = "Fila $fila: campo NOMBRE vacío, se omite";
            continue;
        }

        try {
            $stmtInsert->execute([
                ':provincia'         => ($data['provincia'] ?? '') ?: null,
                ':id_zona'           => ($data['id_zona'] ?? '') ?: null,
                ':id_unidad'         => ($data['id_unidad'] ?? '') ?: null,
                ':cod_infoca'        => ($data['cod_infoca'] ?? '') ?: null,
                ':nombre'            => $nombre,
                ':superficie'        => ($data['superficie'] ?? '') !== '' ? (float) $data['superficie'] : null,
                ':municipio'         => ($data['municipio'] ?? '') ?: null,
                ':monte'             => ($data['monte'] ?? '') ?: null,
                ':cod_monte'         => ($data['cod_monte'] ?? '') ?: null,
                ':pendiente'         => ($data['pendiente'] ?? '') ?: null,
                ':distancia_aprisco' => ($data['distancia_aprisco'] ?? '') ?: null,
                ':vegetacion'        => ($data['vegetacion'] ?? '') ?: null,
                ':tipo_contrato'     => ($data['tipo_contrato'] ?? '') ?: null,
                ':parque'            => ($data['parque'] ?? '') ?: null,
                ':pago_max'          => ($data['pago_max'] ?? '') !== '' ? (float) str_replace(',', '.', $data['pago_max']) : null,
                ':desbroce'          => ($data['desbroce'] ?? '') ?: null,
                ':observaciones'     => ($data['observaciones'] ?? '') ?: null,
            ]);
            $inserted++;
        } catch (\PDOException $e) {
            $errors[] = "Fila $fila ($nombre): " . $e->getMessage();
        }
    }

    echo json_encode([
        'ok' => true,
        'insertadas' => $inserted,
        'errores_count' => count($errors),
        'errores' => array_slice($errors, 0, 20), // máximo 20 errores en respuesta
        'mensaje' => "$inserted infraestructuras importadas correctamente"
            . (count($errors) > 0 ? ". " . count($errors) . " errores." : "."),
    ], JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al procesar archivo: ' . $e->getMessage()]);
}
