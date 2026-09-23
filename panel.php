<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel - SICLO</title>
</head>
<body style="font-family: sans-serif; margin: 0; padding: 0; background-color: #f4f4f9;">
    
    <header style="background-color: #00796b; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
        <h1 style="margin: 0; font-size: 24px;">SICLO - Panel Principal</h1>
        <div>
            <span><?php echo htmlspecialchars($_SESSION['rpe']); ?> (<?php echo htmlspecialchars($_SESSION['rol']); ?>)</span>
            <a href="cerrar_sesion.php" style="color: #ffcdd2; margin-left: 15px; text-decoration: none;">Cerrar sesión</a>
        </div>
    </header>

    <main style="padding: 20px; max-width: 800px; margin: 0 auto;">
        <h2>Menú de Opciones</h2>
        
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            
            <?php if ($_SESSION['rol'] === 'ADMINISTRADOR'): ?>
                <!-- Opciones exclusivas del Administrador -->
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex: 1; min-width: 200px;">
                    <h3>Gestión de Lectores</h3>
                    <ul style="line-height: 1.8;">
                        <li><a href="lectores/nuevo.php">Agregar nuevo lector (Alta)</a></li>
                        <li><a href="lectores/listado.php">Listado de inventario</a></li>
                        <li><a href="historial/general.php">Historial general</a></li>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($_SESSION['rol'] === 'USUARIO'): ?>
                <!-- Opciones exclusivas del Usuario Normal -->
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex: 1; min-width: 200px;">
                    <h3>Mis Reportes</h3>
                    <ul style="line-height: 1.8;">
                        <li><a href="reportes/nuevo.php">Registrar falla</a></li>
                        <li><a href="reportes/historial.php">Mi historial de fallas</a></li>
                    </ul>
                </div>
            <?php endif; ?>

        </div>
    </main>

</body>
</html>