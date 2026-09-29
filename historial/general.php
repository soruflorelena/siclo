<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'ADMINISTRADOR') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();

$sql = "SELECT h.creado_en, h.accion, h.descripcion, 
               l.numero_serie, u.rpe AS usuario_rpe 
        FROM historial h
        LEFT JOIN lectores l ON h.lector_id = l.id
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        ORDER BY h.creado_en DESC";
$stmt = $pdo->query($sql);
$movimientos = $stmt->fetchAll();

$titulo_pagina = 'Historial General - SICLO';
require_once '../includes/encabezado.php';
?>

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

<?php require_once '../includes/pie.php'; ?>