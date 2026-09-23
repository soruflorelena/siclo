<?php
session_start();

// Proteger la página: Solo el rol USUARIO puede registrar fallas
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'USUARIO') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();

$mensaje = '';
$error = '';

// Obtener los centros de trabajo para el selector
try {
    $stmtCentros = $pdo->query("SELECT id, nombre FROM centros_trabajo WHERE activo = true ORDER BY nombre");
    $centros = $stmtCentros->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar centros de trabajo: " . $e->getMessage();
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folio = trim($_POST['folio']);
    $centro_trabajo_id = $_POST['centro_trabajo_id'];
    $falla = trim($_POST['falla']);
    $numero_ticket = trim($_POST['numero_ticket']);
    $serie = trim($_POST['serie']);
    // Estos campos se piden en el formulario por requerimiento, aunque el lector ya los tenga registrados
    $tipo_conector = $_POST['tipo_conector']; 
    $numero_etiqueta = trim($_POST['numero_etiqueta']);

    $usuario_id = $_SESSION['usuario_id']; // El ID se toma de la sesión, no del formulario

    if (empty($folio) || empty($centro_trabajo_id) || empty($falla) || empty($serie)) {
        $error = 'Por favor, llena los campos obligatorios.';
    } else {
        try {
            // 1. Buscar si el lector existe en el inventario mediante su número de serie
            $stmtLector = $pdo->prepare("SELECT id FROM lectores WHERE numero_serie = :serie LIMIT 1");
            $stmtLector->execute(['serie' => $serie]);
            $lector = $stmtLector->fetch();

            if (!$lector) {
                $error = 'El lector con la serie ingresada no está registrado en el inventario. Debe darse de alta primero.';
            } else {
                $lector_id = $lector['id'];

                // Iniciar una transacción porque insertaremos en dos tablas distintas
                $pdo->beginTransaction();

                // 2. Insertar el reporte de falla
                $sqlFalla = "INSERT INTO reportes_falla 
                            (folio, numero_ticket, falla, centro_trabajo_id, lector_id, usuario_id) 
                            VALUES 
                            (:folio, :numero_ticket, :falla, :centro_trabajo_id, :lector_id, :usuario_id)";
                $stmtFalla = $pdo->prepare($sqlFalla);
                $stmtFalla->execute([
                    'folio' => $folio,
                    'numero_ticket' => empty($numero_ticket) ? null : $numero_ticket,
                    'falla' => $falla,
                    'centro_trabajo_id' => $centro_trabajo_id,
                    'lector_id' => $lector_id,
                    'usuario_id' => $usuario_id
                ]);

                // 3. Registrar el movimiento en el historial de trazabilidad
                $descripcion_historial = "Falla reportada. Folio: $folio. Ticket: " . ($numero_ticket ?: 'N/A');
                $sqlHistorial = "INSERT INTO historial (lector_id, accion, descripcion, usuario_id) 
                                 VALUES (:lector_id, 'REPORTE_FALLA', :descripcion, :usuario_id)";
                $stmtHistorial = $pdo->prepare($sqlHistorial);
                $stmtHistorial->execute([
                    'lector_id' => $lector_id,
                    'descripcion' => $descripcion_historial,
                    'usuario_id' => $usuario_id
                ]);

                // Confirmar los cambios en la base de datos
                $pdo->commit();

                $mensaje = 'Falla reportada exitosamente y registrada en el historial del equipo.';
            }
        } catch (PDOException $e) {
            // Si algo falla, deshacemos cualquier cambio incompleto
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Error al registrar la falla: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Falla - SICLO</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f9; margin: 0; padding: 20px; }
        .contenedor { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .campo { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], textarea, select { width: 100%; padding: 8px; box-sizing: border-box; }
        textarea { resize: vertical; height: 100px; }
        button { background: #00796b; color: white; padding: 10px 15px; border: none; cursor: pointer; border-radius: 4px; }
        a.boton-volver { display: inline-block; margin-bottom: 20px; color: #00796b; text-decoration: none; font-weight: bold;}
    </style>
</head>
<body>

    <div class="contenedor">
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
                <label for="tipo_conector">Tipo de Conector</label>
                <select id="tipo_conector" name="tipo_conector">
                    <option value="">-- Seleccione --</option>
                    <option value="USB">USB</option>
                    <option value="DB17">DB17</option>
                </select>
            </div>

            <div class="campo">
                <label for="numero_etiqueta">Número de Etiqueta</label>
                <input type="text" id="numero_etiqueta" name="numero_etiqueta">
            </div>

            <div class="campo">
                <label for="centro_trabajo_id">Centro de Trabajo *</label>
                <select id="centro_trabajo_id" name="centro_trabajo_id" required>
                    <option value="">-- Seleccione --</option>
                    <?php foreach ($centros as $centro): ?>
                        <option value="<?php echo $centro['id']; ?>">
                            <?php echo htmlspecialchars($centro['nombre']); ?>
                        </option>
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

</body>
</html>