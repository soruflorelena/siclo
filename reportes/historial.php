<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'USUARIO') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
require_once '../includes/estado.php';
$pdo = obtener_conexion();
$usuario_id = $_SESSION['usuario_id'];

$sql = "SELECT r.id, r.folio, r.numero_ticket, r.falla, r.creado_en, r.fecha_falla, 
               l.numero_serie, l.estado, l.tipo_conector,
               c.nombre AS centro_trabajo 
        FROM reportes_falla r
        INNER JOIN lectores l ON r.lector_id = l.id
        INNER JOIN centros_trabajo c ON r.centro_trabajo_id = c.id
        WHERE r.usuario_id = :usuario_id
        ORDER BY r.fecha_falla DESC, r.creado_en DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['usuario_id' => $usuario_id]);
$reportes = $stmt->fetchAll();

// Marcas de daño de todos los reportes del usuario, agrupadas por reporte
$stmtMarcas = $pdo->prepare("SELECT m.reporte_id, m.coordenada_x, m.coordenada_y
                             FROM reporte_marcas m
                             INNER JOIN reportes_falla r ON r.id = m.reporte_id
                             WHERE r.usuario_id = :usuario_id
                             ORDER BY m.reporte_id, m.orden, m.id");
$stmtMarcas->execute(['usuario_id' => $usuario_id]);
$marcas_por_reporte = [];
foreach ($stmtMarcas->fetchAll() as $m) {
    $marcas_por_reporte[$m['reporte_id']][] = $m;
}

$titulo_pagina = 'Mis Reportes - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 1000px; margin: 0 auto;">
    <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
    <h2>Mis Reportes de Falla</h2>

    <?php if (count($reportes) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha de la Falla</th>
                    <th>Folio</th>
                    <th>Ticket</th>
                    <th>Serie del Lector</th>
                    <th>Centro de Trabajo</th>
                    <th>Estado Físico</th>
                    <th>Falla Reportada</th>
                    <th>Daño Marcado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportes as $reporte): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($reporte['fecha_falla']))); ?></td>
                        <td><?php echo htmlspecialchars($reporte['folio']); ?></td>
                        <td><?php echo htmlspecialchars($reporte['numero_ticket'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($reporte['numero_serie']); ?></td>
                        <td><?php echo htmlspecialchars($reporte['centro_trabajo']); ?></td>
                        <td>
                            <span style="font-weight: bold; color: <?php echo color_estado($reporte['estado']); ?>;">
                                <?php echo htmlspecialchars(formato_estado($reporte['estado'])); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($reporte['falla']); ?></td>
                        <td>
                            <?php
                                $img_src = ($reporte['tipo_conector'] === 'USB') ? '../img/usb.png' : '../img/db17.png';
                                $marcas_reporte = $marcas_por_reporte[$reporte['id']] ?? [];
                            ?>
                            <a href="ver_dano.php?folio=<?php echo urlencode($reporte['folio']); ?>" title="Ver imagen completa">
                                <div style="position: relative; display: inline-block; border: 1px solid #ccc; line-height: 0;">
                                    <img src="<?php echo $img_src; ?>" alt="Lector" style="width: 110px; height: auto; display: block;">
                                    <?php foreach ($marcas_reporte as $marca): ?>
                                        <div style="position: absolute; left: <?php echo (float) $marca['coordenada_x']; ?>%; top: <?php echo (float) $marca['coordenada_y']; ?>%; transform: translate(-50%, -50%); font-size: 14px; line-height: 1;">❌</div>
                                    <?php endforeach; ?>
                                </div>
                            </a>
                            <?php if (count($marcas_reporte) === 0): ?>
                                <br><small>Sin marca</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Aún no has reportado ninguna falla.</p>
    <?php endif; ?>
</div>

<?php require_once '../includes/pie.php'; ?>