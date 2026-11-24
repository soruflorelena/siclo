<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// El técnico y el administrador pueden consultar las reparaciones
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ADMINISTRADOR', 'TECNICO'], true)) {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
require_once '../includes/estado.php';
$pdo = obtener_conexion();
$error = '';
$busqueda = trim($_GET['busqueda'] ?? '');
$reparaciones = [];

try {
    $sql = "SELECT r.id, r.creado_en, r.resultado, r.diagnostico, r.trabajo_realizado,
                   l.id AS lector_id, l.numero_serie, l.marca,
                   COALESCE(NULLIF(u.nombre, ''), u.rpe) AS tecnico,
                   (SELECT string_agg(m.nombre || ' x' || rm.cantidad, ', ' ORDER BY m.nombre)
                      FROM reparacion_materiales rm
                      JOIN materiales m ON m.id = rm.material_id
                     WHERE rm.reparacion_id = r.id) AS materiales
              FROM reparaciones r
              JOIN lectores l ON l.id = r.lector_id
              JOIN usuarios u ON u.id = r.tecnico_id";
    $params = [];
    if ($busqueda !== '') {
        $sql .= " WHERE l.numero_serie ILIKE :busqueda";
        $params['busqueda'] = '%' . $busqueda . '%';
    }
    $sql .= " ORDER BY r.creado_en DESC, r.id DESC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reparaciones = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al cargar las reparaciones: ' . $e->getMessage();
}

function color_resultado(string $resultado): string
{
    switch ($resultado) {
        case 'REPARADO':    return '#1b5e20';
        case 'IRREPARABLE': return '#b71c1c';
        default:            return '#333333';
    }
}

function texto_resultado(string $resultado): string
{
    switch ($resultado) {
        case 'REPARADO':    return 'Reparado';
        case 'IRREPARABLE': return 'Irreparable';
        default:            return $resultado;
    }
}

$titulo_pagina = 'Historial de Reparaciones - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor">
    <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
    <h2>Historial de Reparaciones</h2>

    <?php if ($error): ?>
        <p style="color: red; font-weight: bold; padding: 10px; background-color: #ffebee;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="GET" action="historial.php" style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
        <input type="text" name="busqueda" placeholder="Buscar por número de serie..."
               value="<?php echo htmlspecialchars($busqueda); ?>" style="flex: 1;">
        <button type="submit">Buscar</button>
        <a href="historial.php">Limpiar</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Equipo</th>
                <th>Técnico</th>
                <th>Resultado</th>
                <th>Diagnóstico</th>
                <th>Detalle</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($reparaciones) > 0): ?>
                <?php foreach ($reparaciones as $rep): ?>
                    <tr>
                        <td style="white-space: nowrap; vertical-align: top;"><?php echo date('d/m/Y H:i', strtotime($rep['creado_en'])); ?></td>
                        <td style="vertical-align: top;">
                            <a href="../historial/lector.php?id=<?php echo (int) $rep['lector_id']; ?>"><strong><?php echo htmlspecialchars($rep['numero_serie']); ?></strong></a>
                            <?php if (!empty($rep['marca'])): ?><br><small><?php echo htmlspecialchars($rep['marca']); ?></small><?php endif; ?>
                        </td>
                        <td style="vertical-align: top;"><?php echo htmlspecialchars($rep['tecnico']); ?></td>
                        <td style="vertical-align: top; white-space: nowrap;">
                            <span style="font-weight: bold; color: <?php echo color_resultado($rep['resultado']); ?>;">
                                <?php echo htmlspecialchars(texto_resultado($rep['resultado'])); ?>
                            </span>
                        </td>
                        <td style="vertical-align: top;" title="<?php echo htmlspecialchars($rep['diagnostico']); ?>">
                            <?php echo htmlspecialchars(recortar_texto($rep['diagnostico'], 90)); ?>
                        </td>
                        <td style="vertical-align: top;">
                            <?php if ($rep['resultado'] === 'REPARADO'): ?>
                                <?php if (!empty($rep['materiales'])): ?>
                                    <strong>Materiales:</strong> <?php echo htmlspecialchars($rep['materiales']); ?>
                                <?php else: ?>
                                    Sin materiales
                                <?php endif; ?>
                                <?php if (!empty($rep['trabajo_realizado'])): ?>
                                    <br><small><?php echo htmlspecialchars(recortar_texto($rep['trabajo_realizado'], 90)); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                Se propuso la baja del equipo
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align: center;">No hay reparaciones registradas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../includes/pie.php'; ?>
