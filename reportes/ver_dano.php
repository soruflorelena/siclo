<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
if (!isset($_SESSION['usuario_id'])) { header("Location: ../panel.php"); exit; }

require_once '../config/base_datos.php';
$pdo = obtener_conexion();
$folio = $_GET['folio'] ?? '';

if (empty($folio)) die("Folio no válido.");

$stmt = $pdo->prepare("SELECT r.*, l.tipo_conector FROM reportes_falla r JOIN lectores l ON r.lector_id = l.id WHERE r.folio = :folio");
$stmt->execute(['folio' => $folio]);
$reporte = $stmt->fetch();

if (!$reporte) die("Reporte no encontrado.");
$es_admin = ($_SESSION['rol'] === 'ADMINISTRADOR');
if ($es_admin) {
    $url_volver = '../historial/lector.php?id=' . urlencode((string)$reporte['lector_id']);
    $texto_volver = '← Volver al Historial del Equipo';
} else {
    $url_volver = 'historial.php';
    $texto_volver = '← Volver a Mis Reportes';
}

$img_src = ($reporte['tipo_conector'] === 'USB') ? '../img/usb.png' : '../img/db17.png';
$x = $reporte['coordenada_x'] ?? null;
$y = $reporte['coordenada_y'] ?? null;

$titulo_pagina = 'Daño Físico - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 600px; margin: 0 auto; text-align: center;">
    <div style="text-align: left;">
    <a href="<?php echo htmlspecialchars($url_volver); ?>" class="boton-volver"><?php echo $texto_volver; ?></a>
    </div>
    <h2>Daño Físico Reportado (Folio: <?php echo htmlspecialchars($folio); ?>)</h2>
    
    <?php if ($x !== null && $y !== null): ?>
        <div style="position: relative; display: inline-block; border: 1px solid #ccc; max-width: 100%;">
            <img src="<?php echo $img_src; ?>" alt="Lector" style="max-width: 400px; display: block;">
            <div style="position: absolute; left: <?php echo $x; ?>%; top: <?php echo $y; ?>%; color: red; font-weight: bold; font-size: 28px; transform: translate(-50%, -50%); text-shadow: 1px 1px 2px black;">❌</div>
        </div>
    <?php else: ?>
        <p>No se marcaron áreas de daño en la imagen para este reporte.</p>
    <?php endif; ?>
</div>

<?php require_once '../includes/pie.php'; ?>