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

    <div class="contenedor" style="flex: 1; min-width: 200px;">
        <h3 style="border-bottom: 2px solid #0288d1; padding-bottom: 10px; margin-bottom: 20px; color: #1a237e;">Panel de Control</h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">

            <?php if ($_SESSION['rol'] === 'ADMINISTRADOR'): ?>
                <div class="tarjeta-panel">
                    <h4>Inventario</h4>
                    <a href="lectores/listado.php" class="btn-panel btn-blue">Ver Inventario Total</a>
                    <a href="lectores/nuevo.php" class="btn-panel btn-teal">Alta de Lector Nuevo</a>
                </div>

                <div class="tarjeta-panel">
                    <h4>Recepciones</h4>
                    <a href="lectores/recepcion.php" class="btn-panel btn-orange">Recibir Equipo Dañado</a>
                    <a href="lectores/devolucion.php" class="btn-panel btn-green">Devolver Equipo Reparado</a>
                </div>

                <div class="tarjeta-panel">
                    <h4>Control y Bajas</h4>
                    <a href="lectores/baja.php" class="btn-panel btn-red">Dar de Baja un Equipo</a>
                    <a href="historial/general.php" class="btn-panel btn-dark">Historial General</a>
                </div>
            <?php endif; ?>

            <?php if ($_SESSION['rol'] === 'USUARIO'): ?>
                <div class="tarjeta-panel">
                    <h4>Reportes de Falla</h4>
                    <a href="reportes/nuevo.php" class="btn-panel btn-orange">Registrar Falla</a>
                    <a href="reportes/historial.php" class="btn-panel btn-dark">Mi Historial de Fallas</a>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<?php
require_once 'includes/pie.php';
?>