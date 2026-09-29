<?php
session_start();

// Proteger la página: Solo el rol USUARIO debe ver su propio historial aquí
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'USUARIO') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();

// Obtener el ID del usuario actual de la sesión
$usuario_id = $_SESSION['usuario_id'];

// Consulta usando JOIN para traer datos legibles (serie del lector y nombre del centro)
// y filtramos estrictamente por el usuario actual.
$sql = "SELECT r.folio, r.numero_ticket, r.falla, r.creado_en, 
               l.numero_serie, c.nombre AS centro_trabajo 
        FROM reportes_falla r
        INNER JOIN lectores l ON r.lector_id = l.id
        INNER JOIN centros_trabajo c ON r.centro_trabajo_id = c.id
        WHERE r.usuario_id = :usuario_id
        ORDER BY r.creado_en DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['usuario_id' => $usuario_id]);
$reportes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Historial de Fallas - SICLO</title>
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
        
        <h2>Mis Reportes de Falla</h2>

        <?php if (count($reportes) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Folio</th>
                        <th>Ticket</th>
                        <th>Serie del Lector</th>
                        <th>Centro de Trabajo</th>
                        <th>Falla Reportada</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportes as $reporte): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($reporte['creado_en']))); ?></td>
                            <td><?php echo htmlspecialchars($reporte['folio']); ?></td>
                            <td><?php echo htmlspecialchars($reporte['numero_ticket'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($reporte['numero_serie']); ?></td>
                            <td><?php echo htmlspecialchars($reporte['centro_trabajo']); ?></td>
                            <td><?php echo htmlspecialchars($reporte['falla']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aún no has reportado ninguna falla.</p>
        <?php endif; ?>
    </div>

</body>
</html>