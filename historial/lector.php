<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'ADMINISTRADOR') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();

// Recibir el ID del lector por la URL 
$lector_id = $_GET['id'] ?? '';

if (empty($lector_id)) {
    die("No se especificó un lector válido.");
}

try {
    // 1. Obtener los datos básicos del lector para el título
    $stmtLector = $pdo->prepare("SELECT numero_serie, estado FROM lectores WHERE id = :id");
    $stmtLector->execute(['id' => $lector_id]);
    $lector = $stmtLector->fetch();

    if (!$lector) {
        die("El lector no existe en la base de datos.");
    }

    // Obtener el historial de este lector
    $sqlHistorial = "SELECT h.accion, h.descripcion, h.creado_en, u.rpe AS usuario 
                     FROM historial h 
                     JOIN usuarios u ON h.usuario_id = u.id 
                     WHERE h.lector_id = :lector_id 
                     ORDER BY h.creado_en DESC";
    
    $stmtHistorial = $pdo->prepare($sqlHistorial);
    $stmtHistorial->execute(['lector_id' => $lector_id]);
    $movimientos = $stmtHistorial->fetchAll();

} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}

$titulo_pagina = 'Historial de Lector - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 800px; margin: 0 auto;">
    <a href="../lectores/listado.php" class="boton-volver">← Volver al Inventario</a>
    
    <div style="background-color: #455a64; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="margin: 0; color: white;">Historial del Equipo: <?php echo htmlspecialchars($lector['numero_serie']); ?></h2>
        <p style="margin: 5px 0 0 0;">Estado Actual: <strong><?php echo htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $lector['estado'])))); ?>

    <table>
        <thead>
            <tr>
                <th>Fecha y Hora</th>
                <th>Acción Realizada</th>
                <th>Descripción / Detalles</th>
                <th>Usuario responsable</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($movimientos) > 0): ?>
                <?php foreach ($movimientos as $mov): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($mov['creado_en']))); ?></td>
                        <td><strong><?php echo htmlspecialchars($mov['accion']); ?></strong></td>
                        <td><?php echo htmlspecialchars($mov['descripcion']); ?></td>
                        <td><?php echo htmlspecialchars($mov['usuario']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px;">Este equipo aún no tiene movimientos registrados.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../includes/pie.php'; ?>