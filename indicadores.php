<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Solo el administrador puede ver los indicadores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'ADMINISTRADOR') {
    header("Location: panel.php");
    exit;
}

require_once 'config/base_datos.php';
require_once 'includes/estado.php';
$pdo = obtener_conexion();
$error = '';

// ---------- Filtro opcional por fechas (afecta a fallas, reparaciones y bajas) ----------
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$patronFecha = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($patronFecha, $desde)) { $desde = ''; }
if (!preg_match($patronFecha, $hasta)) { $hasta = ''; }

/** Devuelve [condiciones SQL, parámetros] para una columna de fecha. */
function filtro_fechas(string $columna, string $desde, string $hasta): array
{
    $sql = '';
    $params = [];
    if ($desde !== '') {
        $sql .= " AND $columna >= :desde";
        $params['desde'] = $desde;
    }
    if ($hasta !== '') {
        $sql .= " AND $columna < (CAST(:hasta AS date) + 1)";
        $params['hasta'] = $hasta;
    }
    return [$sql, $params];
}

/** Ejecuta una consulta preparada y devuelve todas las filas. */
function consultar(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Barra horizontal simple (sin librerías externas, funciona sin internet). */
function barra(int $valor, int $maximo): string
{
    $porcentaje = $maximo > 0 ? round($valor * 100 / $maximo) : 0;
    return '<div style="background:#e3f2fd; border-radius:3px;">'
        . '<div style="width:' . $porcentaje . '%; background:#0288d1; color:#fff; '
        . 'padding:2px 6px; border-radius:3px; min-width:fit-content; box-sizing:border-box;">'
        . $valor . '</div></div>';
}

function formato_duracion(?float $horas): string
{
    if ($horas === null) {
        return 'Sin datos';
    }
    if ($horas >= 24) {
        return number_format($horas / 24, 1) . ' días';
    }
    return number_format($horas, 1) . ' horas';
}

$estados = [];
$total = $activos = $pendientes = $revision = $bajas = 0;
$disponibilidad = null;
$porCentro = $porMes = $porConector = $topLectores = $bajasMotivo = [];
$tiempoReparacion = null;
$totalFallas = 0;

try {
    // ---------- 1. Estado actual del parque de lectores (no depende del filtro) ----------
    $filas = consultar($pdo, "SELECT REPLACE(estado, '_', ' ') AS estado, COUNT(*) AS total FROM lectores GROUP BY 1");
    foreach ($filas as $f) {
        $estados[$f['estado']] = (int) $f['total'];
    }
    $activos    = $estados['ACTIVO'] ?? 0;
    $pendientes = $estados['PENDIENTE RECEPCION'] ?? 0;
    $revision   = $estados['EN REVISION'] ?? 0;
    $bajas      = $estados['BAJA'] ?? 0;
    $total      = array_sum($estados);
    $enServicio = $total - $bajas;
    $disponibilidad = $enServicio > 0 ? round($activos * 100 / $enServicio, 1) : null;

    // ---------- 2. Fallas por centro de trabajo ----------
    [$f, $p] = filtro_fechas('r.fecha_falla', $desde, $hasta);
    $porCentro = consultar($pdo,
        "SELECT c.nombre, COUNT(*) AS total
           FROM reportes_falla r
           JOIN centros_trabajo c ON c.id = r.centro_trabajo_id
          WHERE 1=1 $f
          GROUP BY c.nombre
          ORDER BY total DESC, c.nombre", $p);

    // ---------- 3. Fallas por mes ----------
    $porMes = consultar($pdo,
        "SELECT to_char(date_trunc('month', r.fecha_falla), 'YYYY-MM') AS mes, COUNT(*) AS total
           FROM reportes_falla r
          WHERE 1=1 $f
          GROUP BY 1
          ORDER BY 1", $p);
    $porMes = array_slice($porMes, -12);

    // ---------- 4. Fallas por tipo de conector ----------
    $porConector = consultar($pdo,
        "SELECT l.tipo_conector, COUNT(*) AS total
           FROM reportes_falla r
           JOIN lectores l ON l.id = r.lector_id
          WHERE 1=1 $f
          GROUP BY l.tipo_conector
          ORDER BY total DESC", $p);

    // ---------- 5. Equipos con más fallas ----------
    $topLectores = consultar($pdo,
        "SELECT l.numero_serie, l.marca, COUNT(*) AS total
           FROM reportes_falla r
           JOIN lectores l ON l.id = r.lector_id
          WHERE 1=1 $f
          GROUP BY l.id, l.numero_serie, l.marca
          ORDER BY total DESC, l.numero_serie
          LIMIT 5", $p);

    $totalFallas = array_sum(array_map('intval', array_column($porCentro, 'total')));

    // ---------- 6. Tiempo promedio de reparación (recepción -> devolución) ----------
    // Acepta 'RECEPCION_EQUIPO' y 'RECEPCION EQUIPO' por si hay registros con ambos nombres.
    [$fd, $pd] = filtro_fechas('d.creado_en', $desde, $hasta);
    $filaTiempo = consultar($pdo, <<<'SQL'
SELECT EXTRACT(EPOCH FROM AVG(d.creado_en - r.creado_en)) / 3600 AS horas
  FROM historial d
  JOIN LATERAL (
        SELECT h.creado_en
          FROM historial h
         WHERE h.lector_id = d.lector_id
           AND h.accion IN ('RECEPCION_EQUIPO', 'RECEPCION EQUIPO')
           AND h.creado_en <= d.creado_en
         ORDER BY h.creado_en DESC
         LIMIT 1
       ) r ON TRUE
 WHERE REPLACE(d.accion, ' ', '_') = 'DEVOLUCION_EQUIPO'
SQL
        . $fd, $pd);
    if (!empty($filaTiempo) && $filaTiempo[0]['horas'] !== null) {
        $tiempoReparacion = (float) $filaTiempo[0]['horas'];
    }

    // ---------- 7. Bajas por motivo ----------
    // El motivo se guarda en la descripción del historial: "Baja por <motivo>. Detalles: ..."
    [$fb, $pb] = filtro_fechas('h.creado_en', $desde, $hasta);
    $bajasMotivo = consultar($pdo, <<<'SQL'
SELECT COALESCE(substring(h.descripcion from 'Baja por (.*)\. Detalles'), 'No especificado') AS motivo,
       COUNT(*) AS total
  FROM historial h
 WHERE REPLACE(h.accion, ' ', '_') = 'BAJA_EQUIPO'
SQL
        . $fb . "\n GROUP BY 1 ORDER BY total DESC", $pb);
} catch (PDOException $e) {
    $error = 'Error al calcular los indicadores: ' . $e->getMessage();
}

$maxCentro   = $porCentro ? (int) max(array_column($porCentro, 'total')) : 0;
$maxMes      = $porMes ? (int) max(array_column($porMes, 'total')) : 0;
$maxConector = $porConector ? (int) max(array_column($porConector, 'total')) : 0;
$maxTop      = $topLectores ? (int) max(array_column($topLectores, 'total')) : 0;
$maxBajas    = $bajasMotivo ? (int) max(array_column($bajasMotivo, 'total')) : 0;

$titulo_pagina = 'Indicadores - SICLO';
require_once 'includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 1000px; margin: 0 auto;">
    <a href="panel.php" class="boton-volver">← Volver al Panel</a>
    <h2>Indicadores de seguimiento</h2>

    <?php if ($error): ?>
        <p style="color: red; font-weight: bold; padding: 10px; background-color: #ffebee;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <!-- Filtro por periodo -->
    <form method="GET" action="indicadores.php" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:20px;">
        <div class="campo">
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?php echo htmlspecialchars($desde); ?>">
        </div>
        <div class="campo">
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?php echo htmlspecialchars($hasta); ?>">
        </div>
        <button type="submit">Aplicar periodo</button>
        <a href="indicadores.php">Limpiar</a>
    </form>

    <!-- Estado actual del parque de lectores -->
    <h3 style="color:#1a237e;">Estado actual de los lectores</h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-bottom:24px;">
        <?php
        $tarjetas = [
            ['Total registrados', $total, '#333333'],
            ['Activos', $activos, color_estado('ACTIVO')],
            ['Pendientes de recepción', $pendientes, color_estado('PENDIENTE RECEPCION')],
            ['En revisión', $revision, color_estado('EN REVISION')],
            ['Dados de baja', $bajas, color_estado('BAJA')],
        ];
        foreach ($tarjetas as [$titulo, $valor, $color]): ?>
            <div style="border:1px solid #ddd; border-left:5px solid <?php echo $color; ?>; border-radius:4px; padding:12px;">
                <div style="font-size:0.85em; color:#555;"><?php echo htmlspecialchars($titulo); ?></div>
                <div style="font-size:1.8em; font-weight:bold; color:<?php echo $color; ?>;"><?php echo (int) $valor; ?></div>
            </div>
        <?php endforeach; ?>
        <div style="border:1px solid #ddd; border-left:5px solid #0288d1; border-radius:4px; padding:12px;">
            <div style="font-size:0.85em; color:#555;">Disponibilidad (activos / en servicio)</div>
            <div style="font-size:1.8em; font-weight:bold; color:#0288d1;">
                <?php echo $disponibilidad === null ? 'N/D' : $disponibilidad . '%'; ?>
            </div>
        </div>
    </div>

    <!-- Uso y desempeño en el periodo -->
    <h3 style="color:#1a237e;">
        Fallas y reparaciones
        <?php if ($desde !== '' || $hasta !== ''): ?>
            <small style="font-weight:normal; color:#555;">
                (<?php echo $desde !== '' ? htmlspecialchars($desde) : 'inicio'; ?>
                 a <?php echo $hasta !== '' ? htmlspecialchars($hasta) : 'hoy'; ?>)
            </small>
        <?php endif; ?>
    </h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px; margin-bottom:24px;">
        <div style="border:1px solid #ddd; border-radius:4px; padding:12px;">
            <div style="font-size:0.85em; color:#555;">Fallas reportadas</div>
            <div style="font-size:1.8em; font-weight:bold;"><?php echo (int) $totalFallas; ?></div>
        </div>
        <div style="border:1px solid #ddd; border-radius:4px; padding:12px;">
            <div style="font-size:0.85em; color:#555;">Tiempo promedio de reparación</div>
            <div style="font-size:1.8em; font-weight:bold;"><?php echo htmlspecialchars(formato_duracion($tiempoReparacion)); ?></div>
        </div>
    </div>

    <h4>Fallas por centro de trabajo</h4>
    <?php if ($porCentro): ?>
        <table style="width:100%; margin-bottom:24px;">
            <?php foreach ($porCentro as $fila): ?>
                <tr>
                    <td style="width:35%;"><?php echo htmlspecialchars($fila['nombre']); ?></td>
                    <td><?php echo barra((int) $fila['total'], $maxCentro); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No hay fallas registradas en este periodo.</p>
    <?php endif; ?>

    <h4>Fallas por mes</h4>
    <?php if ($porMes): ?>
        <table style="width:100%; margin-bottom:24px;">
            <?php foreach ($porMes as $fila): ?>
                <tr>
                    <td style="width:35%;"><?php echo htmlspecialchars($fila['mes']); ?></td>
                    <td><?php echo barra((int) $fila['total'], $maxMes); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Sin datos.</p>
    <?php endif; ?>

    <h4>Fallas por tipo de conector</h4>
    <?php if ($porConector): ?>
        <table style="width:100%; margin-bottom:24px;">
            <?php foreach ($porConector as $fila): ?>
                <tr>
                    <td style="width:35%;"><?php echo htmlspecialchars($fila['tipo_conector']); ?></td>
                    <td><?php echo barra((int) $fila['total'], $maxConector); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Sin datos.</p>
    <?php endif; ?>

    <h4>Equipos con más fallas (top 5)</h4>
    <?php if ($topLectores): ?>
        <table style="width:100%; margin-bottom:24px;">
            <?php foreach ($topLectores as $fila): ?>
                <tr>
                    <td style="width:35%;">
                        <?php echo htmlspecialchars($fila['numero_serie']); ?>
                        <?php if (!empty($fila['marca'])): ?>
                            <small>(<?php echo htmlspecialchars($fila['marca']); ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo barra((int) $fila['total'], $maxTop); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Sin datos.</p>
    <?php endif; ?>

    <h4>Bajas por motivo</h4>
    <?php if ($bajasMotivo): ?>
        <table style="width:100%; margin-bottom:24px;">
            <?php foreach ($bajasMotivo as $fila): ?>
                <tr>
                    <td style="width:35%;"><?php echo htmlspecialchars($fila['motivo']); ?></td>
                    <td><?php echo barra((int) $fila['total'], $maxBajas); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No hay bajas registradas en este periodo.</p>
    <?php endif; ?>
</div>

<?php
require_once 'includes/pie.php';
?>