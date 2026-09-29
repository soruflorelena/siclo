<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'USUARIO') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();
$mensaje = '';
$error = '';

try {
    $stmtCentros = $pdo->query("SELECT id, nombre FROM centros_trabajo WHERE activo = true ORDER BY nombre");
    $centros = $stmtCentros->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar centros: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folio = trim($_POST['folio']);
    $centro_trabajo_id = $_POST['centro_trabajo_id'];
    $falla = trim($_POST['falla']);
    $numero_ticket = trim($_POST['numero_ticket']);
    $serie = trim($_POST['serie']);
    $numero_etiqueta = trim($_POST['numero_etiqueta']);
    $usuario_id = $_SESSION['usuario_id'];

    if (empty($folio) || empty($centro_trabajo_id) || empty($falla) || empty($serie)) {
        $error = 'Por favor, llena los campos obligatorios.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Buscar si el lector ya existe
            $stmtLector = $pdo->prepare("SELECT id FROM lectores WHERE numero_serie = :serie LIMIT 1");
            $stmtLector->execute(['serie' => $serie]);
            $lector = $stmtLector->fetch();

            // Si el lector NO existe, lo creamos automáticamente
            if (!$lector) {
                // Tomamos el RPE del usuario que inició sesión
                $rpe_sesion = $_SESSION['rpe'];
                $etiqueta_final = empty($numero_etiqueta) ? 'N/A' : $numero_etiqueta;
                
                // Insertamos usando el RPE de la sesión y 'N/A' solo para el conector
                $sqlNuevoLector = "INSERT INTO lectores (numero_serie, centro_trabajo_id, rpe_asociado, tipo_conector) 
                                   VALUES (:serie, :centro_trabajo_id, :rpe_sesion, 'N/A')";
                $stmtNuevo = $pdo->prepare($sqlNuevoLector);
                $stmtNuevo->execute([
                    'serie' => $serie,
                    'centro_trabajo_id' => $centro_trabajo_id,
                    'rpe_sesion' => $rpe_sesion
                ]);
                $lector_id = $pdo->lastInsertId(); 
                
                $sqlHistorialAlta = "INSERT INTO historial (lector_id, accion, descripcion, usuario_id) 
                                     VALUES (:lector_id, 'ALTA_AUTOMATICA', 'Alta automática al reportar falla', :usuario_id)";
                $stmtHistorialAlta = $pdo->prepare($sqlHistorialAlta);
                $stmtHistorialAlta->execute([
                    'lector_id' => $lector_id,
                    'usuario_id' => $usuario_id
                ]);
            } else {
                $lector_id = $lector['id'];
            }

            // 2. Insertar el reporte de falla
            $sqlFalla = "INSERT INTO reportes_falla (folio, numero_ticket, falla, centro_trabajo_id, lector_id, usuario_id) 
                        VALUES (:folio, :numero_ticket, :falla, :centro_trabajo_id, :lector_id, :usuario_id)";
            $stmtFalla = $pdo->prepare($sqlFalla);
            $stmtFalla->execute([
                'folio' => $folio,
                'numero_ticket' => empty($numero_ticket) ? null : $numero_ticket,
                'falla' => $falla,
                'centro_trabajo_id' => $centro_trabajo_id,
                'lector_id' => $lector_id,
                'usuario_id' => $usuario_id
            ]);

            // 3. Registrar la falla en el historial
            $descripcion_historial = "Falla reportada. Folio: $folio. Ticket: " . ($numero_ticket ?: 'N/A');
            $sqlHistorial = "INSERT INTO historial (lector_id, accion, descripcion, usuario_id) 
                             VALUES (:lector_id, 'REPORTE_FALLA', :descripcion, :usuario_id)";
            $stmtHistorial = $pdo->prepare($sqlHistorial);
            $stmtHistorial->execute([
                'lector_id' => $lector_id,
                'descripcion' => $descripcion_historial,
                'usuario_id' => $usuario_id
            ]);

            $pdo->commit();
            $mensaje = 'Falla reportada exitosamente. El equipo entró en inventario.';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Error al registrar la falla: ' . $e->getMessage();
        }
    }
}

$titulo_pagina = 'Registrar Falla - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 600px; margin: 0 auto;">
    <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
    <h2>Registrar Falla de Equipo</h2>

    <?php if ($mensaje): ?>
        <p style="color: green; font-weight: bold; padding: 10px; background-color: #e8f5e9;"><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color: red; font-weight: bold; padding: 10px; background-color: #ffebee;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST" action="nuevo.php">
        <div class="campo">
            <label for="folio">Folio *</label>
            <input type="text" id="folio" name="folio" required>
        </div>
        <div class="campo">
            <label for="numero_ticket">Número de Ticket (Opcional)</label>
            <input type="text" id="numero_ticket" name="numero_ticket">
        </div>
        <div class="campo">
            <label for="serie">Número de Serie del Lector *</label>
            <input type="text" id="serie" name="serie" placeholder="Ingrese la serie exacta" required>
        </div>
        <div class="campo">
            <label for="centro_trabajo_id">Centro de Trabajo *</label>
            <select id="centro_trabajo_id" name="centro_trabajo_id" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($centros as $centro): ?>
                    <option value="<?php echo $centro['id']; ?>"><?php echo htmlspecialchars($centro['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="falla">Descripción de la Falla *</label>
            <textarea id="falla" name="falla" required></textarea>
        </div>
        <button type="submit">Registrar Falla</button>
    </form>
</div>

<?php require_once '../includes/pie.php'; ?>