<?php
session_start();

if (isset($_SESSION['usuario_id'])) {
    header("Location: panel.php");
    exit;
}

require_once 'config/base_datos.php';
$pdo = obtener_conexion();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rpe = trim($_POST['rpe']);
    $contrasena = trim($_POST['contrasena']);

    if (empty($rpe) || empty($contrasena)) {
        $error = 'Por favor, ingresa tu RPE y contraseña.';
    } else {
        $sql = "SELECT id, rpe, contrasena_hash, rol FROM usuarios WHERE rpe = :rpe";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['rpe' => $rpe]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($contrasena, $usuario['contrasena_hash'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['rpe'] = $usuario['rpe'];
            $_SESSION['rol'] = $usuario['rol'];
            header("Location: panel.php");
            exit;
        } else {
            $error = 'RPE o contraseña incorrectos.';
        }
    }
}

$titulo_pagina = 'Iniciar Sesión - SICLO';
require_once 'includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 400px; margin: 50px auto;">
    <h2 style="text-align: center;">Acceso al Sistema</h2>

    <?php if ($error): ?>
        <p style="color: red; font-weight: bold; padding: 10px; background-color: #ffebee;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="campo">
            <label for="rpe">RPE *</label>
            <input type="text" id="rpe" name="rpe" 
                   maxlength="5" minlength="5" 
                   pattern="^[A-Za-z0-9]{5}$" 
                   style="text-transform: uppercase;" 
                   onkeyup="this.value = this.value.toUpperCase();">
        </div>
        <div class="campo">
            <label for="contrasena">Contraseña:</label>
            <input type="password" id="contrasena" name="contrasena" required>
            <div style="margin-top: 8px;">
                <input type="checkbox" id="ver_contrasena">
                <label for="ver_contrasena" style="display: inline; font-weight: normal; cursor: pointer;">Mostrar contraseña</label>
            </div>
        </div>
        <button type="submit" style="width: 100%;">Entrar</button>
    </form>
</div>

<script>
    document.getElementById('ver_contrasena').addEventListener('change', function () {
        document.getElementById('contrasena').type = this.checked ? 'text' : 'password';
    });
</script>

<?php require_once 'includes/pie.php'; ?>