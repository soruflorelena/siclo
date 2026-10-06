<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'USUARIO') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();
$usuario_id = $_SESSION['usuario_id'];

$sql = "SELECT r.folio, r.numero_ticket, r.falla, r.creado_en, 
               l.numero_serie, l.estado, c.nombre AS centro_trabajo 
        FROM reportes_falla r
        INNER JOIN lectores l ON r.lector_id = l.id
        INNER JOIN centros_trabajo c ON r.centro_trabajo_id = c.id
        WHERE r.usuario_id = :usuario_id
        ORDER BY r.creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['usuario_id' => $usuario_id]);
$reportes = $stmt->fetchAll();

$titulo_pagina = 'Mis Reportes - SICLO';
require_once '../includes/encabezado.php';
?>

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
                    <th>Estado Físico</th>
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
                        <td>
                            <strong><?php echo htmlspecialchars($reporte['estado']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($reporte['falla']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Aún no has reportado ninguna falla.</p>
    <?php endif; ?>
</div>

<?php require_once '../includes/pie.php'; ?>