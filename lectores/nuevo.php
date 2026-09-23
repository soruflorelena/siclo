<?php
session_start();

// 1. Proteger la página: Solo Administradores pueden entrar
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'ADMINISTRADOR') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();

$mensaje = '';
$error = '';

// 2. Obtener los centros de trabajo para llenar el <select>
try {
    $stmtCentros = $pdo->query("SELECT id, nombre FROM centros_trabajo WHERE activo = true ORDER BY nombre");
    $centros = $stmtCentros->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar centros de trabajo: " . $e->getMessage();
}

// 3. Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_serie = trim($_POST['numero_serie']);
    // Por ahora la foto será solo texto (ej. "foto1.jpg") para mantenerlo simple.
    $foto_url = trim($_POST['foto_url']); 
    $centro_trabajo_id = $_POST['centro_trabajo_id'];
    $rpe_asociado = trim($_POST['rpe_asociado']);
    $tipo_conector = $_POST['tipo_conector'];
    $numero_etiqueta = trim($_POST['numero_etiqueta']);
    $marca = trim($_POST['marca']);

    if (empty($numero_serie) || empty($centro_trabajo_id) || empty($tipo_conector)) {
        $error = 'Por favor, llena los campos obligatorios (Serie, Centro y Conector).';
    } else {
        try {
            $sql = "INSERT INTO lectores 
                    (numero_serie, foto_url, centro_trabajo_id, rpe_asociado, tipo_conector, numero_etiqueta, marca) 
                    VALUES 
                    (:numero_serie, :foto_url, :centro_trabajo_id, :rpe_asociado, :tipo_conector, :numero_etiqueta, :marca)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'numero_serie' => $numero_serie,
                'foto_url' => empty($foto_url) ? null : $foto_url,
                'centro_trabajo_id' => $centro_trabajo_id,
                'rpe_asociado' => empty($rpe_asociado) ? null : $rpe_asociado,
                'tipo_conector' => $tipo_conector,
                'numero_etiqueta' => empty($numero_etiqueta) ? null : $numero_etiqueta,
                'marca' => empty($marca) ? null : $marca
            ]);

            $mensaje = 'Lector óptico registrado exitosamente en el inventario.';
        } catch (PDOException $e) {
            $error = 'Error al registrar el lector: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alta de Lector - SICLO</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f9; margin: 0; padding: 20px; }
        .contenedor { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .campo { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], select { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background: #00796b; color: white; padding: 10px 15px; border: none; cursor: pointer; border-radius: 4px; }
        a.boton-volver { display: inline-block; margin-bottom: 20px; color: #00796b; text-decoration: none; }
    </style>
</head>
<body>

    <div class="contenedor">
        <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
        
        <h2>Alta de Lector Óptico</h2>

        <?php if ($mensaje): ?>
            <p style="color: green; font-weight: bold; padding: 10px; background-color: #e8f5e9;"><?php echo htmlspecialchars($mensaje); ?></p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p style="color: red; font-weight: bold; padding: 10px; background-color: #ffebee;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST" action="nuevo.php">
            <div class="campo">
                <label for="numero_serie">Número de Serie *</label>
                <input type="text" id="numero_serie" name="numero_serie" required>
            </div>

            <div class="campo">
                <label for="marca">Marca</label>
                <input type="text" id="marca" name="marca">
            </div>

            <div class="campo">
                <label for="tipo_conector">Tipo de Conector *</label>
                <select id="tipo_conector" name="tipo_conector" required>
                    <option value="">-- Seleccione --</option>
                    <option value="USB">USB</option>
                    <option value="DB17">DB17</option>
                </select>
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
                <label for="rpe_asociado">RPE del Trabajador Asociado</label>
                <input type="text" id="rpe_asociado" name="rpe_asociado" placeholder="Ej. 12345">
            </div>

            <div class="campo">
                <label for="numero_etiqueta">Número de Etiqueta (Inventario)</label>
                <input type="text" id="numero_etiqueta" name="numero_etiqueta">
            </div>

            <!-- Por ahora capturamos un nombre de archivo simple. Más adelante se puede añadir subida de imágenes real -->
            <div class="campo">
                <label for="foto_url">Nombre de la Foto (Opcional)</label>
                <input type="text" id="foto_url" name="foto_url" placeholder="ejemplo.jpg">
            </div>

            <button type="submit">Guardar Lector</button>
        </form>
    </div>

</body>
</html>