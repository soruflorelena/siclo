<?php
session_start();

// Solo el administrador puede recibir equipos
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
    $rpe_entrega = trim($_POST['rpe_entrega'] ?? '');
    $rpe_recibe = trim($_POST['rpe_recibe'] ?? '');
    $usuario_id = $_SESSION['usuario_id'];

    if (empty($lector_id) || empty($rpe_entrega) || empty($rpe_recibe)) {
        $error = "Por favor selecciona un equipo y llena los RPE de entrega y recepción.";
    } else {
        try {
            $pdo->beginTransaction();

            // Cambiar el estado del lector a EN_REVISION
            $sqlUpdate = "UPDATE lectores SET estado = 'EN_REVISION' WHERE id = :id";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute(['id' => $lector_id]);

            // 2. Registrar el movimiento en el historial con los RPE involucrados
            $descripcion = "Equipo recibido físicamente en TIC's. Entregó (RPE): $rpe_entrega. Recibió (RPE): $rpe_recibe.";
            $sqlHistorial = "INSERT INTO historial (lector_id, accion, descripcion, usuario_id) 
                             VALUES (:lector_id, 'RECEPCION_EQUIPO', :descripcion, :usuario_id)";
            $stmtHistorial = $pdo->prepare($sqlHistorial);
            $stmtHistorial->execute([
                'lector_id' => $lector_id,
                'descripcion' => $descripcion,
                'usuario_id' => $usuario_id
            ]);

            $pdo->commit();
            $mensaje = "El equipo ha sido recibido y ahora está EN REVISIÓN por el departamento de TIC's.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error al recibir el equipo: " . $e->getMessage();
        }
    }
}

// Obtener la lista actualizada de equipos pendientes
try {
    $stmtPendientes = $pdo->query("SELECT id, numero_serie FROM lectores WHERE estado = 'PENDIENTE_RECEPCION' ORDER BY creado_en ASC");
    $pendientes = $stmtPendientes->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar equipos: " . $e->getMessage();
}

$titulo_pagina = 'Recepción de Equipos - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 600px; margin: 0 auto;">
    <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
    <h2>Recepción de Equipos Físicos</h2>

    <?php if ($mensaje): ?>
        <p style="color: green; font-weight: bold; padding: 10px; background-color: #e8f5e9;"><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color: red; font-weight: bold; padding: 10px; background-color: #ffebee;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if (count($pendientes) > 0): ?>
        <form method="POST" action="recepcion.php">
            <div class="campo">
                <label for="lector_id">Equipos en Tránsito (Pendientes) *</label>
                <select id="lector_id" name="lector_id" required>
                    <option value="">-- Seleccione el equipo que acaba de recibir --</option>
                    <?php foreach ($pendientes as $equipo): ?>
                        <option value="<?php echo $equipo['id']; ?>">
                            Serie: <?php echo htmlspecialchars($equipo['numero_serie']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div> 

            <div class="campo">
                <label for="rpe_entrega">RPE de quien entrega el equipo físico (Responsable de Entrega) *</label>
                <input type="text" id="rpe_entrega" name="rpe_entrega" required placeholder="Ej. ABCD1">
            </div>

            <div class="campo">
                <label for="rpe_recibe">RPE de quien recibe en TIC's *</label>
                <input type="text" id="rpe_recibe" name="rpe_recibe" required placeholder="Ej. EFGJ2">
            </div>
            
            <button type="submit" style="background-color: #0288d1;">Confirmar Recepción</button>
        </form>
    <?php else: ?>
        <p style="padding: 15px; background-color: #e0f7fa; border-radius: 4px; color: #006064;">
            No hay equipos pendientes de recepción en este momento.
        </p>
    <?php endif; ?>
</div>

<?php require_once '../includes/pie.php'; ?>