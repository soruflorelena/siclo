<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Opcional: Definimos el título de esta página antes de llamar al encabezado
$titulo_pagina = 'Panel Principal - SICLO';

// Incluimos la parte superior (diseño)
require_once 'includes/encabezado.php';
?>

<h2>Menú de Opciones</h2>

<div style="display: flex; gap: 20px; flex-wrap: wrap;">
    
    <?php if ($_SESSION['rol'] === 'ADMINISTRADOR'): ?>
        <div class="contenedor" style="flex: 1; min-width: 200px;">
            <h3>Gestión de Lectores</h3>
            <ul style="line-height: 1.8;">
                <li><a href="lectores/nuevo.php">Agregar nuevo lector</a></li>
                <li><a href="lectores/recepcion.php">Recepción de equipos</a></li>
                <li><a href="lectores/devolucion.php">Devolución de equipos</a></li>
                <li><a href="lectores/baja.php">Dar de baja un lector</a></li>
                <li><a href="lectores/listado.php">Listado de inventario</a></li>
                <li><a href="historial/general.php">Historial general</a></li>
            </ul>
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
// Incluimos la parte inferior (cierre de etiquetas)
require_once 'includes/pie.php'; 
?>