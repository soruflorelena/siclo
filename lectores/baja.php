<?php
session_start();

// Solo el Administrador puede dar de baja equipos
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'ADMINISTRADOR') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();
$mensaje = '';
$error = '';

// Procesar el formulario de baja
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lector_id = $_POST['lector_id'] ?? '';
    $motivo = $_POST['motivo'] ?? '';
    $detalles = trim($_POST['detalles'] ?? '');
    $usuario_id = $_SESSION['usuario_id'];

    if (empty($lector_id) || empty($motivo)) {
        $error = "Por favor selecciona un equipo y el motivo de la baja.";
    } else {
        try {
            $pdo->beginTransaction();

            // Cambiar el estado del lector definitivamente a 'BAJA'
            $sqlBaja = "UPDATE lectores SET estado = 'BAJA' WHERE id = :id";
            $stmtBaja = $pdo->prepare($sqlBaja);
            $stmtBaja->execute(['id' => $lector_id]);

            // Registrar la baja en el historial
            $descripcion = "Baja por $motivo. Detalles: " . ($detalles ?: 'Sin detalles adicionales');
            $sqlHistorial = "INSERT INTO historial (lector_id, accion, descripcion, usuario_id) 
                             VALUES (:lector_id, 'BAJA_EQUIPO', :descripcion, :usuario_id)";
            $stmtHistorial = $pdo->prepare($sqlHistorial);
            $stmtHistorial->execute([
                'lector_id' => $lector_id,
                'descripcion' => $descripcion,
                'usuario_id' => $usuario_id
            ]);

            $pdo->commit();
            $mensaje = "El equipo ha sido dado de baja exitosamente.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error al procesar la baja: " . $e->getMessage();
        }
    }
}

// Obtener los lectores que no están de baja 
try {
    $stmtLectores = $pdo->query("SELECT id, numero_serie, estado, numero_etiqueta FROM lectores WHERE estado != 'BAJA' ORDER BY numero_serie");
    $lectores_disponibles = $stmtLectores->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar equipos: " . $e->getMessage();
}

$titulo_pagina = 'Baja de Lector - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 600px; margin: 0 auto;">
    <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
    <h2>Dar de Baja Lector Óptico</h2>

    <?php if ($mensaje): ?>
        <p style="color: green; font-weight: bold; padding: 10px; background-color: #e8f5e9;"><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color: red; font-weight: bold; padding: 10px; background-color: #ffebee;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST" action="baja.php">
        <div class="campo">
            <label for="lector_id">Seleccionar Equipo a desechar *</label>
            <select id="lector_id" name="lector_id" required>
                <option value="">-- Seleccione el Lector --</option>
                <?php foreach ($lectores_disponibles as $lector): ?>
                    <option value="<?php echo $lector['id']; ?>">
                        Serie: <?php echo htmlspecialchars($lector['numero_serie']); ?> 
                        (Estado actual: <?php echo htmlspecialchars($lector['estado']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="motivo">Motivo de la Baja *</label>
            <select id="motivo" name="motivo" required>
                <option value="">-- Seleccione --</option>
                <option value="Reemplazo">Reemplazo</option>
                <option value="Daño">Daño</option>
                <option value="Pérdida">Pérdida</option>
                <option value="Obsolescencia">Obsolescencia</option>
                <option value="Retiro">Retiro</option>
            </select>
        </div>

        <div class="campo">
            <label for="detalles">Detalles adicionales del dictamen (Opcional)</label>
            <textarea id="detalles" name="detalles" placeholder="Especifique cómo ocurrió el daño, la pérdida o el dictamen"></textarea>
        </div>

        <button type="submit" style="background-color: #d32f2f;">Confirmar Baja de Equipo</button>
    </form>
</div>

<?php require_once '../includes/pie.php'; ?>