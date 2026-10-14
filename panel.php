<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$titulo_pagina = 'Panel Principal - SICLO';

require_once 'includes/encabezado.php';
?>

<h2>Menú de Opciones</h2>

<div style="display: flex; gap: 20px; flex-wrap: wrap;">
    
    <?php if ($_SESSION['rol'] === 'ADMINISTRADOR'): ?>
        <!-- Estilos para mejorar el diseño del panel -->
        <style>
            .tarjeta-panel {
                background: #ffffff;
                padding: 20px;
                border-radius: 10px;
                border: 1px solid #e0e0e0;
                box-shadow: 0 4px 6px rgba(0,0,0,0.05);
                display: flex;
                flex-direction: column;
            }
            .tarjeta-panel h4 {
                margin-top: 0;
                color: #2c3e50;
                font-size: 1.1em;
                border-bottom: 2px solid #eee;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }
            .btn-panel {
                display: block;
                padding: 12px 15px;
                margin-bottom: 12px;
                text-align: center;
                text-decoration: none !important;
                color: #ffffff !important;
                font-weight: bold;
                border-radius: 6px;
                transition: transform 0.2s, box-shadow 0.2s, filter 0.2s;
                font-size: 0.95em;
            }
            .btn-panel:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
                filter: brightness(1.1);
            }
            .btn-panel:last-child {
                margin-bottom: 0;
            }
            /* Colores de los botones */
            .btn-blue { background-color: #0288d1; }
            .btn-teal { background-color: #00897b; }
            .btn-orange { background-color: #f57c00; }
            .btn-green { background-color: #388e3c; }
            .btn-red { background-color: #d32f2f; }
            .btn-dark { background-color: #455a64; }
        </style>

        <div class="contenedor" style="flex: 1; min-width: 200px;">
            <h3 style="border-bottom: 2px solid #0288d1; padding-bottom: 10px; margin-bottom: 20px; color: #1a237e;">Panel de Control</h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <!-- Grupo 1: Inventario -->
                <div class="tarjeta-panel">
                    <h4>Inventario</h4>
                    <a href="lectores/listado.php" class="btn-panel btn-blue">Ver Inventario Total</a>
                    <a href="lectores/nuevo.php" class="btn-panel btn-teal">Alta de Lector Nuevo</a>
                </div>

                <!-- Grupo 2: Reparaciones -->
                <div class="tarjeta-panel">
                    <h4>Reparaciones</h4>
                    <a href="lectores/recepcion.php" class="btn-panel btn-orange">Recibir Equipo Dañado</a>
                    <a href="lectores/devolucion.php" class="btn-panel btn-green">Devolver Equipo Reparado</a>
                </div>

                <!-- Grupo 3: Bajas y Auditoría -->
                <div class="tarjeta-panel">
                    <h4>Control y Bajas</h4>
                    <a href="lectores/baja.php" class="btn-panel btn-red">Dar de Baja un Equipo</a>
                    <a href="historial/general.php" class="btn-panel btn-dark">Auditoría (Historial Global)</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($_SESSION['rol'] === 'USUARIO'): ?>
        <div class="contenedor" style="flex: 1; min-width: 200px;">
            <h3>Mis Reportes</h3>
            <ul style="line-height: 1.8;">
                <li><a href="reportes/nuevo.php">Registrar falla</a></li>
                <li><a href="reportes/historial.php">Mi historial de fallas</a></li>
            </ul>
        </div>
    <?php endif; ?>

</div>

<?php 
require_once 'includes/pie.php'; 
?>