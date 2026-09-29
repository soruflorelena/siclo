<?php
// Asegurarnos de que la sesión esté iniciada para poder leer los datos del usuario
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Permitir que cada página defina su propio título, si no, usar uno por defecto
$titulo = $titulo_pagina ?? 'SICLO';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo); ?></title>
    <style>
        /* Estilos globales centralizados */
        body { font-family: sans-serif; background-color: #f4f4f9; margin: 0; padding: 0; }
        
        /* Barra superior */
        header { background-color: #00796b; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        header h1 { margin: 0; font-size: 24px; }
        header .enlaces-header a { color: #ffcdd2; text-decoration: none; margin-left: 15px; }
        header .enlaces-header a:hover { text-decoration: underline; }
        
        /* Contenedor principal */
        main { padding: 20px; max-width: 1000px; margin: 0 auto; }
        .contenedor { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        
        /* Formularios */
        .campo { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"], textarea, select { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background: #00796b; color: white; padding: 10px 15px; border: none; cursor: pointer; border-radius: 4px; }
        
        /* Tablas */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #00796b; color: white; }
        tr:hover { background-color: #f5f5f5; }
        
        /* Elementos extra */
        a.boton-volver { display: inline-block; margin-bottom: 20px; color: #00796b; text-decoration: none; font-weight: bold; }
        .etiqueta-accion { background: #e0f7fa; color: #006064; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
    </style>
</head>
<body>

    <header>
        <div>
            <h1>SICLO</h1>
        </div>
        <div class="enlaces-header">
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <span><?php echo htmlspecialchars($_SESSION['rpe']); ?> (<?php echo htmlspecialchars($_SESSION['rol']); ?>)</span>
                <!-- Usamos /siclo/ para que la ruta funcione sin importar en qué subcarpeta estemos -->
                <a href="/panel.php">Inicio</a>
                <a href="/cerrar_sesion.php">Cerrar sesión</a>
            <?php endif; ?>
        </div>
    </header>

    <main>