<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'ADMINISTRADOR') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();

// 1. Recibir término de búsqueda si existe
$busqueda = trim($_GET['busqueda'] ?? '');

try {
    // 2. Modificar la consulta para incluir el buscador
    $sql = "SELECT l.id, l.numero_serie, l.numero_etiqueta, l.marca, l.estado, c.nombre AS centro_trabajo 
            FROM lectores l 
            LEFT JOIN centros_trabajo c ON l.centro_trabajo_id = c.id";
    
    // Si hay búsqueda, agregamos la condición WHERE
    if (!empty($busqueda)) {
        $sql .= " WHERE l.numero_serie ILIKE :busqueda OR l.numero_etiqueta ILIKE :busqueda";
        $stmt = $pdo->prepare($sql);
        // Los % son comodines para que busque coincidencias parciales
        $stmt->execute(['busqueda' => '%' . $busqueda . '%']);
    } else {
        $sql .= " ORDER BY l.creado_en DESC";
        $stmt = $pdo->query($sql);
    }
    
    $lectores = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al cargar el inventario: " . $e->getMessage());
}

$titulo_pagina = 'Inventario de Lectores - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 1000px; margin: 0 auto;">
    <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
    <h2>Inventario General de Lectores</h2>

    <!-- Buscador -->
    <div style="background-color: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <form method="GET" action="listado.php" style="display: flex; gap: 10px;">
            <input type="text" name="busqueda" placeholder="Buscar por Serie o Inventario..." value="<?php echo htmlspecialchars($busqueda); ?>" style="flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            <button type="submit" style="background-color: #0288d1; padding: 10px 20px;">Buscar</button>
            <a href="listado.php" class="boton" style="background-color: #757575; padding: 10px 20px; text-decoration: none; color: white;">Limpiar</a>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>N° Serie</th>
                <th>N° Etiqueta / Inventario</th>
                <th>Marca</th>
                <th>Ubicación</th>
                <th>Estado</th>
                <th>Acciones</th> <!-- Nueva Columna -->
            </tr>
        </thead>
        <tbody>
            <?php if (count($lectores) > 0): ?>
                <?php foreach ($lectores as $lector): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($lector['numero_serie']); ?></strong></td>
                        <td><?php echo htmlspecialchars($lector['numero_etiqueta'] ?? 'S/N'); ?></td>
                        <td><?php echo htmlspecialchars($lector['marca'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($lector['centro_trabajo'] ?? 'N/A'); ?></td>
                        <td>
                            <?php 
                                $color = '#333';
                                if ($lector['estado'] === 'ACTIVO') $color = 'green';
                                if ($lector['estado'] === 'PENDIENTE_RECEPCION') $color = 'orange';
                                if ($lector['estado'] === 'EN_REVISION') $color = '#0288d1';
                                if ($lector['estado'] === 'BAJA') $color = 'red';
                            ?>
                            <span style="font-weight: bold; color: <?php echo $color; ?>;">
                                <?php echo htmlspecialchars($lector['estado']); ?>
                            </span>
                        </td>
                        <td style="display: flex; gap: 5px;">
                            <!-- Botón para Editar -->
                            <a href="editar.php?id=<?php echo $lector['id']; ?>" style="background-color: #0288d1; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; font-size: 0.9em;">Editar</a>
                            
                            <!-- Botón para Ver Historial -->
                            <a href="../historial/lector.php?id=<?php echo $lector['id']; ?>" style="background-color: #455a64; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; font-size: 0.9em;">Ver Historial</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align: center;">No se encontraron lectores.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../includes/pie.php'; ?>