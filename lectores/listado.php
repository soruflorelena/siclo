<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'ADMINISTRADOR') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();

$sql = "SELECT l.numero_serie, l.marca, l.tipo_conector, l.numero_etiqueta, l.rpe_asociado, c.nombre AS centro_trabajo 
        FROM lectores l 
        LEFT JOIN centros_trabajo c ON l.centro_trabajo_id = c.id 
        ORDER BY l.creado_en DESC";
$stmt = $pdo->query($sql);
$lectores = $stmt->fetchAll();

$titulo_pagina = 'Inventario - SICLO';
require_once '../includes/encabezado.php';
?>

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

<?php require_once '../includes/pie.php'; ?>