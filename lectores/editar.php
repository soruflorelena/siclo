<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'ADMINISTRADOR') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();
$mensaje = '';
$error = '';

// Recibir el ID del lector
$lector_id = $_GET['id'] ?? $_POST['id'] ?? '';

if (empty($lector_id)) {
    die("No se especificó un lector válido.");
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_rpe = trim($_POST['rpe_asociado'] ?? '');
    $nuevo_centro_id = $_POST['centro_trabajo_id'] ?? '';
    $nueva_etiqueta = trim($_POST['numero_etiqueta'] ?? 'N/A');
    $usuario_id = $_SESSION['usuario_id'];

    if (empty($nuevo_rpe) || empty($nuevo_centro_id)) {
        $error = "El RPE y el Centro de Trabajo son obligatorios.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Obtener los datos anteriores para saber qué cambió
            $stmtLectorAnt = $pdo->prepare("SELECT rpe_asociado, centro_trabajo_id, numero_serie FROM lectores WHERE id = :id");
            $stmtLectorAnt->execute(['id' => $lector_id]);
            $lectorAnterior = $stmtLectorAnt->fetch();

            // 2. Actualizar el lector
            $sqlUpd = "UPDATE lectores 
                       SET rpe_asociado = :rpe, centro_trabajo_id = :centro, numero_etiqueta = :etiqueta 
                       WHERE id = :id";
            $stmtUpd = $pdo->prepare($sqlUpd);
            $stmtUpd->execute([
                'rpe' => $nuevo_rpe,
                'centro' => $nuevo_centro_id,
                'etiqueta' => $nueva_etiqueta,
                'id' => $lector_id
            ]);

            // 3. Registrar en el historial qué fue lo que se modificó
            $descripcion = "Datos actualizados. Nuevo responsable (RPE): $nuevo_rpe. (RPE anterior: " . $lectorAnterior['rpe_asociado'] . ").";
            $sqlHist = "INSERT INTO historial (lector_id, accion, descripcion, usuario_id) 
                        VALUES (:lector_id, 'EDICION_EQUIPO', :descripcion, :usuario_id)";
            $stmtHist = $pdo->prepare($sqlHist);
            $stmtHist->execute([
                'lector_id' => $lector_id,
                'descripcion' => $descripcion,
                'usuario_id' => $usuario_id
            ]);

            $pdo->commit();
            $mensaje = "El equipo " . $lectorAnterior['numero_serie'] . " ha sido actualizado correctamente.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// Cargar los datos actuales para mostrarlos en el formulario
try {
    $stmtLector = $pdo->prepare("SELECT * FROM lectores WHERE id = :id");
    $stmtLector->execute(['id' => $lector_id]);
    $lector = $stmtLector->fetch();

    if (!$lector) {
        die("Lector no encontrado.");
    }

    $stmtCentros = $pdo->query("SELECT id, nombre FROM centros_trabajo WHERE activo = true ORDER BY nombre");
    $centros = $stmtCentros->fetchAll();
} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

$titulo_pagina = 'Editar Lector - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 600px; margin: 0 auto;">
    <a href="listado.php" class="boton-volver">← Volver al Inventario</a>
    <h2>Editar Datos del Equipo: <?php echo htmlspecialchars($lector['numero_serie']); ?></h2>

    <?php if ($mensaje): ?>
        <p style="color: green; font-weight: bold; padding: 10px; background-color: #e8f5e9; border-radius: 4px;"><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color: red; font-weight: bold; padding: 10px; background-color: #ffebee; border-radius: 4px;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST" action="editar.php?id=<?php echo htmlspecialchars($lector_id); ?>" onsubmit="return confirm('¿Confirmas los cambios para este equipo?');">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($lector_id); ?>">

        <!-- El número de serie NO se edita porque es la identidad física del equipo -->
        <div class="campo">
            <label>Número de Serie (Solo lectura)</label>
            <input type="text" value="<?php echo htmlspecialchars($lector['numero_serie']); ?>" disabled style="background-color: #eee;">
        </div>

        <div class="campo">
            <label for="numero_etiqueta">Número de Etiqueta / Inventario</label>
            <input type="text" id="numero_etiqueta" name="numero_etiqueta" value="<?php echo htmlspecialchars($lector['numero_etiqueta']); ?>">
        </div>

        <div class="campo">
            <label for="rpe_asociado">RPE del Trabajador Responsable *</label>
            <input type="text" id="rpe_asociado" name="rpe_asociado" value="<?php echo htmlspecialchars($lector['rpe_asociado'] ?? ''); ?>" required>
        </div>

        <div class="campo">
            <label for="centro_trabajo_id">Centro de Trabajo *</label>
            <select id="centro_trabajo_id" name="centro_trabajo_id" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($centros as $centro): ?>
                    <option value="<?php echo $centro['id']; ?>" <?php echo ($lector['centro_trabajo_id'] == $centro['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($centro['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" style="background-color: #0288d1; margin-top: 15px;">Guardar Cambios</button>
    </form>
</div>

<?php require_once '../includes/pie.php'; ?>