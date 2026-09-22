<?php
session_start();

// Si no hay un usuario_id en la sesión, lo regresamos al login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel - SICLO</title>
</head>
<body style="font-family: sans-serif; padding: 20px;">
    <h1>Bienvenido a SICLO</h1>
    <p>Has iniciado sesión exitosamente.</p>
    
    <ul>
        <li><strong>RPE:</strong> <?php echo htmlspecialchars($_SESSION['rpe']); ?></li>
        <li><strong>Rol:</strong> <?php echo htmlspecialchars($_SESSION['rol']); ?></li>
    </ul>

    <a href="cerrar_sesion.php" style="color: red;">Cerrar sesión</a>
</body>
</html>