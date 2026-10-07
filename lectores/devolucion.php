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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lector_id = $_POST['lector_id'] ?? '';
    $rpe_receptor = trim($_POST['rpe_receptor'] ?? '');
    $detalles = trim($_POST['detalles'] ?? '');
    $usuario_id = $_SESSION['usuario_id'];

    if (empty($lector_id) || empty($rpe_receptor)) {
        $error = "Por favor selecciona un equipo e ingresa el RPE de quien lo recibe.";
    } else {
        try {
            $pdo->beginTransaction();

            // Regresar el estado del lector a ACTIVO
            $sqlUpdate = "UPDATE lectores SET estado = 'ACTIVO' WHERE id = :id";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute(['id' => $lector_id]);

            // Registrar la entrega en el historial con lo que se le reparó y añadir el RPE del receptor al historial
            $descripcion = "Equipo devuelto a operación. Entregado a RPE: $rpe_receptor. Detalles: " . ($detalles ?: 'Sin detalles adicionales');
            $sqlHistorial = "INSERT INTO historial (lector_id, accion, descripcion, usuario_id) 
                             VALUES (:lector_id, 'DEVOLUCION_EQUIPO', :descripcion, :usuario_id)";
            $stmtHistorial = $pdo->prepare($sqlHistorial);
            $stmtHistorial->execute([
                'lector_id' => $lector_id,
                'descripcion' => $descripcion,
                'usuario_id' => $usuario_id
            ]);

            $pdo->commit();
            $mensaje = "El equipo ha sido devuelto a operación exitosamente y vuelve a estar ACTIVO.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error al devolver el equipo: " . $e->getMessage();
        }
    }
}

// Obtener solo los equipos que están en revisión 
try {
    $stmtRevision = $pdo->query("SELECT id, numero_serie FROM lectores WHERE estado = 'EN_REVISION' ORDER BY numero_serie ASC");
    $en_revision = $stmtRevision->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar equipos: " . $e->getMessage();
}

$titulo_pagina = 'Devolución de Equipos - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 600px; margin: 0 auto;">
    <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
    <h2>Devolución de Equipos</h2>

    <?php if ($mensaje): ?>
        <p style="color: green; font-weight: bold; padding: 10px; background-color: #e8f5e9;"><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color: red; font-weight: bold; padding: 10px; background-color: #ffebee;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if (count($en_revision) > 0): ?>
        <form method="POST" action="devolucion.php">
            <div class="campo">
                <label for="lector_id">Equipos en Revisión *</label>
                <select id="lector_id" name="lector_id" required>
                    <option value="">-- Seleccione el equipo a devolver --</option>
                    <?php foreach ($en_revision as $equipo): ?>
                        <option value="<?php echo $equipo['id']; ?>">
                            Serie: <?php echo htmlspecialchars($equipo['numero_serie']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="rpe_receptor">RPE de quien recibe el equipo (Recibí Equipo Reparado) *</label>
                <input type="text" id="rpe_receptor" name="rpe_receptor" required placeholder="Ej. ABCD1">
            </div>

            <div class="campo">
                <label for="detalles">¿Qué se le reparó?</label>
                <textarea id="detalles" name="detalles" placeholder="Ej: Se cambió el cable USB, se limpió el lente, etc."></textarea>
            </div>

            <button type="submit" style="background-color: #388e3c;">Confirmar Entrega (Activar)</button>
        </form>
    <?php else: ?>
        <p style="padding: 15px; background-color: #e0f7fa; border-radius: 4px; color: #006064;">
            No hay equipos en revisión actualmente.
        </p>
    <?php endif; ?>
</div>

<?php require_once '../includes/pie.php'; ?>