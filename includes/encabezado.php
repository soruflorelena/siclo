<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
// Asegurar que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Definir el título de la página si no se ha definido
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
        tbody tr:hover { background-color: #d5ece9; color: #212121; }
        
        /* Elementos extra */
        a.boton-volver { display: inline-block; margin-bottom: 20px; color: #00796b; text-decoration: none; font-weight: bold; }
        .etiqueta-accion { background: #e0f7fa; color: #006064; border: 1px solid #80deea; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }

        /* Panel de control (compartido por administrador y usuario) */
        .tarjeta-panel { background: #ffffff; padding: 20px; border-radius: 10px; border: 1px solid #e0e0e0; box-shadow: 0 4px 6px rgba(0,0,0,0.05); display: flex; flex-direction: column; }
        .tarjeta-panel h4 { margin-top: 0; color: #2c3e50; font-size: 1.1em; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
        .btn-panel { display: block; padding: 12px 15px; margin-bottom: 12px; text-align: center; text-decoration: none !important; color: #ffffff !important; font-weight: bold; border-radius: 6px; transition: transform 0.2s, box-shadow 0.2s, filter 0.2s; font-size: 0.95em; }
        .btn-panel:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); filter: brightness(1.1); }
        .btn-panel:last-child { margin-bottom: 0; }
        .btn-blue { background-color: #0288d1; }
        .btn-teal { background-color: #00897b; }
        .btn-orange { background-color: #f57c00; }
        .btn-green { background-color: #388e3c; }
        .btn-red { background-color: #d32f2f; }
        .btn-dark { background-color: #455a64; }
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
                <a href="/panel.php">Inicio</a>
                <a href="/cerrar_sesion.php">Cerrar sesión</a>
            <?php endif; ?>
        </div>
    </header>

    <main>