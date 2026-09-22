<?php
// Iniciar la sesión de PHP
session_start();

// Si el usuario ya tiene una sesión activa, lo mandamos directo al panel
if (isset($_SESSION['usuario_id'])) {
    header("Location: panel.php");
    exit;
}

// Requerir la conexión a la base de datos
require_once 'config/base_datos.php';
$pdo = obtener_conexion();

$error = '';

// Si el formulario fue enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rpe = trim($_POST['rpe']);
    $contrasena = trim($_POST['contrasena']);

    if (empty($rpe) || empty($contrasena)) {
        $error = 'Por favor, ingresa tu RPE y contraseña.';
    } else {
        // Consulta preparada para buscar al usuario por su RPE
        $sql = "SELECT id, rpe, contrasena_hash, rol FROM usuarios WHERE rpe = :rpe";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['rpe' => $rpe]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Validar si el usuario existe y si la contraseña coincide con el hash
        if ($usuario && password_verify($contrasena, $usuario['contrasena_hash'])) {
            // Guardar únicamente la información esencial en la sesión
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['rpe'] = $usuario['rpe'];
            $_SESSION['rol'] = $usuario['rol'];

            // Redirigir al panel principal
            header("Location: panel.php");
            exit;
        } else {
            $error = 'RPE o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SICLO - Iniciar Sesión</title>
</head>
<body>
    <main style="max-width: 400px; margin: 50px auto; font-family: sans-serif;">
        <h2>SICLO</h2>
        <p>Sistema de Control de Lectores Ópticos</p>

        <?php if ($error): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div style="margin-bottom: 15px;">
                <label for="rpe" style="display: block;">RPE:</label>
                <input type="text" id="rpe" name="rpe" required style="width: 100%; padding: 8px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label for="contrasena" style="display: block;">Contraseña:</label>
                <input type="password" id="contrasena" name="contrasena" required style="width: 100%; padding: 8px;">
            </div>
            <button type="submit" style="width: 100%; padding: 10px; background: #00796b; color: white; border: none; cursor: pointer;">
                Entrar
            </button>
        </form>
    </main>
</body>
</html>