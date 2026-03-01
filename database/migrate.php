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
