<?php
session_start();

// Proteger la página: Solo Administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'ADMINISTRADOR') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();

// Consulta para obtener los lectores unidos con el nombre de su centro de trabajo
$sql = "SELECT l.numero_serie, l.marca, l.tipo_conector, l.numero_etiqueta, l.rpe_asociado, c.nombre AS centro_trabajo 
        FROM lectores l 
        LEFT JOIN centros_trabajo c ON l.centro_trabajo_id = c.id 
        ORDER BY l.creado_en DESC";

$stmt = $pdo->query($sql);
$lectores = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Lectores - SICLO</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f9; margin: 0; padding: 20px; }
        .contenedor { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #00796b; color: white; }
        tr:hover { background-color: #f5f5f5; }
        a.boton-volver { display: inline-block; margin-bottom: 20px; color: #00796b; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

    <div class="contenedor">
        <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
        
        <h2>Inventario de Lectores Ópticos</h2>

        <?php if (count($lectores) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Serie</th>
                        <th>Marca</th>
                        <th>Conector</th>
                        <th>Etiqueta</th>
                        <th>Centro de Trabajo</th>
                        <th>RPE Asignado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lectores as $lector): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($lector['numero_serie']); ?></td>
                            <td><?php echo htmlspecialchars($lector['marca'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($lector['tipo_conector']); ?></td>
                            <td><?php echo htmlspecialchars($lector['numero_etiqueta'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($lector['centro_trabajo']); ?></td>
                            <td><?php echo htmlspecialchars($lector['rpe_asociado'] ?? 'Sin asignar'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay lectores registrados en el inventario todavía.</p>
        <?php endif; ?>
    </div>

</body>
</html>