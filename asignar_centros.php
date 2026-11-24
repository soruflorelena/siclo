<?php
// Asigna (o cambia) el centro de trabajo de usuarios que YA existen.
// Solo se puede usar desde la terminal o desde esta misma computadora (localhost).
$es_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
if (PHP_SAPI !== 'cli' && !$es_local) {
    http_response_code(403);
    exit('Acceso denegado.');
}
header('Content-Type: text/html; charset=UTF-8');

require_once 'config/base_datos.php';
require_once 'includes/centros.php';

$pdo = obtener_conexion();

// ------------------------------------------------------------------
// Escribe aquí  'RPE' => 'Nombre del centro de trabajo'  (tal como aparece en la lista de abajo).
// Ejemplo:
//     'USER1' => 'Teziutlán CCC',
// ------------------------------------------------------------------
$asignaciones = [
    // 'USER1' => '',
];

echo "<h3>Asignación de centros de trabajo</h3>";

if (count($asignaciones) === 0) {
    echo "<p>No hay asignaciones. Llena el arreglo <code>\$asignaciones</code> en este archivo y vuelve a ejecutarlo.</p>";
}

$stmt = $pdo->prepare("UPDATE usuarios SET centro_trabajo_id = :centro, actualizado_en = CURRENT_TIMESTAMP WHERE rpe = :rpe");

foreach ($asignaciones as $rpe => $centro) {
    $rpe = strtoupper(trim((string) $rpe));
    try {
        $centro_id = buscar_centro_id($pdo, (string) $centro);
        $stmt->execute(['centro' => $centro_id, 'rpe' => $rpe]);

        if ($stmt->rowCount() === 0) {
            echo "<p style='color: #bf360c;'>⚠ El RPE <code>" . htmlspecialchars($rpe) . "</code> no existe, no se asignó nada.</p>";
        } else {
            echo "<p style='color: green;'>✔ <code>" . htmlspecialchars($rpe) . "</code> → " . htmlspecialchars((string) $centro) . "</p>";
        }
    } catch (InvalidArgumentException | RuntimeException $e) {
        echo "<p style='color: red;'>✖ <code>" . htmlspecialchars($rpe) . "</code>: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Centros disponibles
echo "<h4>Centros de trabajo disponibles</h4><ul>";
foreach ($pdo->query("SELECT nombre FROM centros_trabajo WHERE activo = TRUE ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN) as $nombre_centro) {
    echo "<li>" . htmlspecialchars($nombre_centro) . "</li>";
}
echo "</ul>";

// Resumen de usuarios para verificar quiénes siguen sin centro
echo "<h4>Usuarios registrados</h4>";
$usuarios = $pdo->query("SELECT u.rpe, u.nombre, u.rol, c.nombre AS centro
                           FROM usuarios u
                           LEFT JOIN centros_trabajo c ON c.id = u.centro_trabajo_id
                          ORDER BY u.rol, u.rpe")->fetchAll();

echo "<table border='1' cellpadding='6' style='border-collapse: collapse;'>";
echo "<tr><th>RPE</th><th>Nombre</th><th>Rol</th><th>Centro de trabajo</th></tr>";
foreach ($usuarios as $u) {
    if ($u['centro'] !== null) {
        $centro_mostrado = htmlspecialchars($u['centro']);
    } elseif ($u['rol'] === 'USUARIO') {
        $centro_mostrado = "<span style='color: red;'>SIN CENTRO</span>";
    } else {
        $centro_mostrado = "—";
    }
    echo "<tr><td>" . htmlspecialchars($u['rpe']) . "</td><td>" . htmlspecialchars((string) $u['nombre']) . "</td><td>" . htmlspecialchars($u['rol']) . "</td><td>" . $centro_mostrado . "</td></tr>";
}
echo "</table>";
?>
