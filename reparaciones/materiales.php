<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// El técnico y el administrador administran el catálogo de materiales
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ADMINISTRADOR', 'TECNICO'], true)) {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
require_once '../includes/estado.php';
$pdo = obtener_conexion();
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'agregar') {
            $nombre = trim(preg_replace('/\s+/u', ' ', trim($_POST['nombre'] ?? '')) ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');

            if ($nombre === '') {
                $error = 'Escribe el nombre del material.';
            } elseif (strlen($nombre) > 120) {
                $error = 'El nombre del material es demasiado largo (máximo 120 caracteres).';
            } else {
                $stmt = $pdo->prepare("INSERT INTO materiales (nombre, descripcion) VALUES (:nombre, :descripcion)");
                $stmt->execute([
                    'nombre'      => $nombre,
                    'descripcion' => $descripcion !== '' ? $descripcion : null
                ]);
                $mensaje = 'Material agregado al catálogo.';
            }
        } elseif ($accion === 'estado') {
            $id = (int) ($_POST['id'] ?? 0);
            $activar = ($_POST['activo'] ?? '') === '1';
            if ($id > 0) {
                if ($activar) {
                    $stmt = $pdo->prepare("UPDATE materiales SET activo = TRUE WHERE id = :id");
                } else {
                    $stmt = $pdo->prepare("UPDATE materiales SET activo = FALSE WHERE id = :id");
                }
                $stmt->execute(['id' => $id]);
                $mensaje = $activar ? 'Material activado.' : 'Material desactivado. Ya no aparecerá al registrar reparaciones.';
            }
        }
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23505') {
            $error = 'Ya existe un material con ese nombre.';
        } else {
            $error = 'Error en el catálogo: ' . $e->getMessage();
        }
    }
}

$materiales = [];
try {
    $materiales = $pdo->query("SELECT m.id, m.nombre, m.descripcion, m.activo,
                                      COALESCE(SUM(rm.cantidad), 0) AS usado
                                 FROM materiales m
                                 LEFT JOIN reparacion_materiales rm ON rm.material_id = m.id
                                GROUP BY m.id, m.nombre, m.descripcion, m.activo
                                ORDER BY m.activo DESC, m.nombre")->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al cargar el catálogo: ' . $e->getMessage();
}

$titulo_pagina = 'Catálogo de Materiales - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor">
    <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
    <h2>Catálogo de Materiales</h2>
    <p>Partes y materiales que se usan para reparar los lectores. Registrarlos aquí evita nombres repetidos o escritos de distinta forma y permite sacar los indicadores de abastecimiento.</p>

    <?php if ($mensaje): ?>
        <p style="color: green; font-weight: bold; padding: 10px; background-color: #e8f5e9;"><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color: red; font-weight: bold; padding: 10px; background-color: #ffebee;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST" action="materiales.php" autocomplete="off"
          style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 10px;">
        <input type="hidden" name="accion" value="agregar">
        <div class="campo" style="flex: 2; min-width: 200px; margin-bottom: 0;">
            <label for="nombre">Nuevo material *</label>
            <input type="text" id="nombre" name="nombre" maxlength="120" required>
        </div>
        <div class="campo" style="flex: 3; min-width: 200px; margin-bottom: 0;">
            <label for="descripcion">Descripción (Opcional)</label>
            <input type="text" id="descripcion" name="descripcion">
        </div>
        <button type="submit" style="background-color: #00897b;">Agregar</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Material</th>
                <th>Descripción</th>
                <th>Unidades usadas</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($materiales) > 0): ?>
                <?php foreach ($materiales as $mat): ?>
                    <tr style="<?php echo $mat['activo'] ? '' : 'opacity: 0.55;'; ?>">
                        <td><strong><?php echo htmlspecialchars($mat['nombre']); ?></strong></td>
                        <td><?php echo htmlspecialchars($mat['descripcion'] ?? ''); ?></td>
                        <td><?php echo (int) $mat['usado']; ?></td>
                        <td><?php echo $mat['activo'] ? 'Activo' : 'Inactivo'; ?></td>
                        <td>
                            <form method="POST" action="materiales.php" style="margin: 0;">
                                <input type="hidden" name="accion" value="estado">
                                <input type="hidden" name="id" value="<?php echo (int) $mat['id']; ?>">
                                <input type="hidden" name="activo" value="<?php echo $mat['activo'] ? '0' : '1'; ?>">
                                <button type="submit" style="background: <?php echo $mat['activo'] ? '#757575' : '#388e3c'; ?>; padding: 5px 10px; font-size: 0.9em;">
                                    <?php echo $mat['activo'] ? 'Desactivar' : 'Activar'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align: center;">El catálogo está vacío. Agrega el primer material arriba.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../includes/pie.php'; ?>
