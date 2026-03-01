<?php
/**
 * RAPCA - Migración de base de datos
 *
 * USO: Acceder desde el navegador una sola vez
 * SEGURIDAD: Eliminar este archivo después de ejecutar la migración
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>RAPCA - Migración</title>
    <style>
        body { font-family: monospace; background: #1a1a2e; color: #eee; padding: 2rem; }
        .ok { color: #0f0; }
        .error { color: #f44; }
        .warn { color: #ff0; }
        .info { color: #4fc3f7; }
        pre { background: #16213e; padding: 1rem; border-radius: 8px; overflow-x: auto; }
    </style>
</head>
<body>
<h1>RAPCA - Migración de Base de Datos</h1>
<pre>
<?php
$configPath = __DIR__ . '/../includes/config.php';
require_once $configPath;

echo "<span class='info'>[DEBUG]</span> DB_HOST: " . DB_HOST . "\n";
echo "<span class='info'>[DEBUG]</span> DB_NAME: " . DB_NAME . "\n\n";

try {
    echo "<span class='ok'>[OK]</span> Conectando a la base de datos...\n";
    $pdo = getDB();
    echo "<span class='ok'>[OK]</span> Conexión establecida\n\n";

    // --- Pre-migration: add superadmin role if table already exists ---
    try {
        $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('superadmin','admin','operador') NOT NULL DEFAULT 'operador'");
        echo "<span class='ok'>[OK]</span> Rol 'superadmin' añadido al ENUM de usuarios\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), "doesn't exist")) {
            echo "<span class='info'>[INFO]</span> Tabla usuarios no existe aún, se creará con schema.sql\n";
        } else {
            echo "<span class='warn'>[WARN]</span> ALTER usuarios: " . $e->getMessage() . "\n";
        }
    }

    // --- Pre-migration: change estado_incidencia ENUM from antes/durante/despues to vp/ev ---
    try {
        $pdo->exec("ALTER TABLE registros MODIFY COLUMN estado_incidencia ENUM('vp','ev') NOT NULL DEFAULT 'vp'");
        echo "<span class='ok'>[OK]</span> ENUM estado_incidencia actualizado en registros (vp/ev)\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), "doesn't exist")) {
            echo "<span class='info'>[INFO]</span> Tabla registros no existe aún, se creará con schema.sql\n";
        } else {
            echo "<span class='warn'>[WARN]</span> ALTER registros.estado_incidencia: " . $e->getMessage() . "\n";
        }
    }

    try {
        $pdo->exec("ALTER TABLE subidas_fallidas MODIFY COLUMN estado_incidencia ENUM('vp','ev') DEFAULT 'vp'");
        echo "<span class='ok'>[OK]</span> ENUM estado_incidencia actualizado en subidas_fallidas (vp/ev)\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), "doesn't exist")) {
            echo "<span class='info'>[INFO]</span> Tabla subidas_fallidas no existe aún, se creará con schema.sql\n";
        } else {
            echo "<span class='warn'>[WARN]</span> ALTER subidas_fallidas.estado_incidencia: " . $e->getMessage() . "\n";
        }
    }

    // --- Pre-migration: añadir nuevos campos de infraestructuras (gestión forestal INFOCA) ---
    $newInfraCols = [
        'provincia'         => "VARCHAR(150) DEFAULT NULL COMMENT 'Provincia' AFTER id",
        'id_zona'           => "VARCHAR(50) DEFAULT NULL COMMENT 'Identificador de zona' AFTER provincia",
        'id_unidad'         => "VARCHAR(50) DEFAULT NULL COMMENT 'Identificador de unidad' AFTER id_zona",
        'cod_infoca'        => "VARCHAR(50) DEFAULT NULL COMMENT 'Codigo INFOCA' AFTER id_unidad",
        'superficie'        => "DECIMAL(12,2) DEFAULT NULL COMMENT 'Superficie en hectareas' AFTER nombre",
        'monte'             => "VARCHAR(200) DEFAULT NULL COMMENT 'Nombre del monte' AFTER municipio",
        'cod_monte'         => "VARCHAR(50) DEFAULT NULL COMMENT 'Codigo del monte' AFTER monte",
        'pendiente'         => "VARCHAR(100) DEFAULT NULL COMMENT 'Pendiente del terreno' AFTER cod_monte",
        'distancia_aprisco' => "VARCHAR(100) DEFAULT NULL COMMENT 'Distancia al aprisco' AFTER pendiente",
        'vegetacion'        => "VARCHAR(200) DEFAULT NULL COMMENT 'Tipo de vegetacion' AFTER distancia_aprisco",
        'tipo_contrato'     => "VARCHAR(100) DEFAULT NULL COMMENT 'Tipo de contrato' AFTER vegetacion",
        'parque'            => "VARCHAR(200) DEFAULT NULL COMMENT 'Parque natural' AFTER tipo_contrato",
        'pago_max'          => "DECIMAL(10,2) DEFAULT NULL COMMENT 'Pago maximo' AFTER parque",
        'desbroce'          => "VARCHAR(200) DEFAULT NULL COMMENT 'Tipo de desbroce' AFTER pago_max",
        'observaciones'     => "TEXT DEFAULT NULL AFTER desbroce",
    ];

    foreach ($newInfraCols as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE infraestructuras ADD COLUMN $col $def");
            echo "<span class='ok'>[OK]</span> Columna '$col' añadida a infraestructuras\n";
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate column')) {
                echo "<span class='info'>[INFO]</span> Columna '$col' ya existe en infraestructuras\n";
            } else {
                echo "<span class='warn'>[WARN]</span> ALTER infraestructuras ADD $col: " . $e->getMessage() . "\n";
            }
        }
    }

    // Hacer lat_teorica y lon_teorica opcionales y eliminar codigo_unico obligatorio
    try {
        $pdo->exec("ALTER TABLE infraestructuras MODIFY COLUMN lat_teorica DECIMAL(10,7) DEFAULT NULL");
        $pdo->exec("ALTER TABLE infraestructuras MODIFY COLUMN lon_teorica DECIMAL(10,7) DEFAULT NULL");
        echo "<span class='ok'>[OK]</span> lat/lon_teorica ahora son opcionales\n";
    } catch (PDOException $e) {
        echo "<span class='warn'>[WARN]</span> ALTER lat/lon: " . $e->getMessage() . "\n";
    }

    // --- Pre-migration: eliminar unidad_obra_id de registros ---
    try {
        $pdo->exec("ALTER TABLE registros DROP COLUMN unidad_obra_id");
        echo "<span class='ok'>[OK]</span> Columna unidad_obra_id eliminada de registros\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), "check that column/key exists")) {
            echo "<span class='info'>[INFO]</span> Columna unidad_obra_id ya no existe en registros\n";
        } else {
            echo "<span class='warn'>[WARN]</span> DROP unidad_obra_id: " . $e->getMessage() . "\n";
        }
    }

    // --- Pre-migration: eliminar columnas antiguas de infraestructuras ---
    $dropCols = ['codigo_unico', 'tipo', 'descripcion'];
    foreach ($dropCols as $col) {
        try {
            $pdo->exec("ALTER TABLE infraestructuras DROP COLUMN $col");
            echo "<span class='ok'>[OK]</span> Columna '$col' eliminada de infraestructuras\n";
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), "check that column/key exists")) {
                echo "<span class='info'>[INFO]</span> Columna '$col' ya no existe en infraestructuras\n";
            } else {
                echo "<span class='warn'>[WARN]</span> DROP $col: " . $e->getMessage() . "\n";
            }
        }
    }

    $sqlFile = __DIR__ . '/schema.sql';
    if (!file_exists($sqlFile)) {
        echo "<span class='error'>[ERROR]</span> schema.sql no encontrado\n";
        exit;
    }

    $sql = file_get_contents($sqlFile);
    echo "<span class='ok'>[OK]</span> schema.sql leído (" . strlen($sql) . " bytes)\n\n";

    $statements = [];
    $current = '';
    $lines = explode("\n", $sql);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '--') || $trimmed === '') continue;
        $current .= $line . "\n";
        if (str_ends_with($trimmed, ';')) {
            $statements[] = trim($current);
            $current = '';
        }
    }

    $success = 0;
    $errors = 0;

    foreach ($statements as $i => $stmt) {
        try {
            $pdo->exec($stmt);
            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(\w+)/i', $stmt, $m)) {
                echo "<span class='ok'>[OK]</span> Tabla creada: {$m[1]}\n";
            } elseif (preg_match('/CREATE\s+OR\s+REPLACE\s+VIEW\s+(\w+)/i', $stmt, $m)) {
                echo "<span class='ok'>[OK]</span> Vista creada: {$m[1]}\n";
            } elseif (preg_match('/INSERT/i', $stmt)) {
                echo "<span class='ok'>[OK]</span> Datos insertados\n";
            } elseif (preg_match('/SET\s+/i', $stmt)) {
                echo "<span class='ok'>[OK]</span> SET ejecutado\n";
            } else {
                echo "<span class='ok'>[OK]</span> Sentencia #" . ($i + 1) . " ejecutada\n";
            }
            $success++;
        } catch (PDOException $e) {
            echo "<span class='error'>[ERROR]</span> " . $e->getMessage() . "\n";
            $errors++;
        }
    }

    echo "\n========================================\n";
    echo "Resultado: <span class='ok'>$success ejecutadas</span>";
    if ($errors > 0) echo ", <span class='error'>$errors errores</span>";
    echo "\n========================================\n\n";

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tablas en la base de datos:\n";
    foreach ($tables as $table) {
        echo "  <span class='ok'>✓</span> $table\n";
    }

    echo "\n<span class='warn'>⚠ IMPORTANTE: Elimina este archivo después de la migración.</span>\n";

} catch (Exception $e) {
    echo "<span class='error'>[ERROR FATAL]</span> " . $e->getMessage() . "\n";
}
?>
</pre>
</body>
</html>
