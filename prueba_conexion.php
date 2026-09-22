<?php
declare(strict_types=1);

require_once __DIR__ . '/config/base_datos.php';

try {
    $conexion = obtener_conexion();
    $consulta = $conexion->prepare('SELECT COUNT(*) AS total FROM centros_trabajo');
    $consulta->execute();
    $resultado = $consulta->fetch();
    $totalCentros = (int) $resultado['total'];
    $mensaje = "Conexión correcta. Centros de trabajo cargados: {$totalCentros}.";
    $correcto = true;
} catch (RuntimeException $error) {
    $mensaje = $error->getMessage();
    $correcto = false;
}
?><!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Prueba de conexión | SICLO</title></head>
<body>
    <main>
        <h1>SICLO: prueba de conexión</h1>
        <p><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if (!$correcto): ?>
            <p>Verifique el archivo <code>.env</code>, que PostgreSQL esté iniciado y que se hayan ejecutado los scripts SQL.</p>
        <?php endif; ?>
    </main>
</body>
</html>
