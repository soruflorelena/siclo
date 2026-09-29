<?php
session_start();

// Proteger la página: Solo Administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'ADMINISTRADOR') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();

// Consulta para obtener todo el historial uniendo datos de lectores y usuarios
$sql = "SELECT h.creado_en, h.accion, h.descripcion, 
               l.numero_serie, u.rpe AS usuario_rpe 
        FROM historial h
        LEFT JOIN lectores l ON h.lector_id = l.id
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        ORDER BY h.creado_en DESC";

$stmt = $pdo->query($sql);
$movimientos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial General - SICLO</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f9; margin: 0; padding: 20px; }
        .contenedor { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #00796b; color: white; }
        tr:hover { background-color: #f5f5f5; }
        a.boton-volver { display: inline-block; margin-bottom: 20px; color: #00796b; text-decoration: none; font-weight: bold; }
        .etiqueta-accion { background: #e0f7fa; color: #006064; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
    </style>
</head>
<body>

    <div class="contenedor">
        <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
        
        <h2>Historial General de Movimientos</h2>

        <?php if (count($movimientos) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Serie del Lector</th>
                        <th>Acción</th>
                        <th>Descripción</th>
                        <th>Usuario (RPE)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $movimiento): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($movimiento['creado_en']))); ?></td>
                            <td><?php echo htmlspecialchars($movimiento['numero_serie'] ?? 'N/A'); ?></td>
                            <td><span class="etiqueta-accion"><?php echo htmlspecialchars($movimiento['accion']); ?></span></td>
                            <td><?php echo htmlspecialchars($movimiento['descripcion']); ?></td>
                            <td><?php echo htmlspecialchars($movimiento['usuario_rpe'] ?? 'Sistema'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aún no hay movimientos registrados en el historial.</p>
        <?php endif; ?>
    </div>

</body>
</html>