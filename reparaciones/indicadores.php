<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// El técnico y el administrador ven los indicadores de materiales
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ADMINISTRADOR', 'TECNICO'], true)) {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
require_once '../includes/estado.php';
$pdo = obtener_conexion();
$error = '';

// ---------- Periodo ----------
$periodos = [
    'todo' => 'Todo el tiempo',
    'mes'  => 'Este mes',
    'd30'  => 'Últimos 30 días',
    'd90'  => 'Últimos 90 días',
    'anio' => 'Este año',
];
$periodo = $_GET['periodo'] ?? 'todo';
if (!array_key_exists($periodo, $periodos)) {
    $periodo = 'todo';
}

switch ($periodo) {
    case 'mes':  $desde = date('Y-m-01'); break;
    case 'd30':  $desde = date('Y-m-d', strtotime('-30 days')); break;
    case 'd90':  $desde = date('Y-m-d', strtotime('-90 days')); break;
    case 'anio': $desde = date('Y-01-01'); break;
    default:     $desde = null;
}

$f = '';
$params = [];
if ($desde !== null) {
    $f = ' AND r.creado_en >= CAST(:desde AS date)';
    $params['desde'] = $desde;
}

function consultar(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function barra(float $valor, float $maximo): string
{
    $porcentaje = $maximo > 0 ? round($valor * 100 / $maximo) : 0;
    return '<div style="background:#e3f2fd; border-radius:3px;">'
        . '<div style="width:' . $porcentaje . '%; background:#0288d1; color:#fff; '
        . 'padding:2px 6px; border-radius:3px; min-width:fit-content; box-sizing:border-box;">'
        . $valor . '</div></div>';
}

$resultados = ['REPARADO' => 0, 'IRREPARABLE' => 0];
$topMateriales = $porMarca = $porMes = [];
$unidadesTotales = 0;
$materialesDistintos = 0;
$meses = 1;

try {
    // 1. Resultado de las reparaciones
    $filas = consultar($pdo, "SELECT r.resultado, COUNT(*) AS total FROM reparaciones r WHERE 1=1 $f GROUP BY r.resultado", $params);
    foreach ($filas as $fila) {
        $resultados[$fila['resultado']] = (int) $fila['total'];
    }

    // 2. Materiales más usados
    $topMateriales = consultar($pdo,
        "SELECT m.nombre, SUM(rm.cantidad) AS unidades, COUNT(DISTINCT rm.reparacion_id) AS reparaciones
           FROM reparacion_materiales rm
           JOIN reparaciones r ON r.id = rm.reparacion_id
           JOIN materiales m ON m.id = rm.material_id
          WHERE 1=1 $f
          GROUP BY m.id, m.nombre
          ORDER BY unidades DESC, m.nombre", $params);
    foreach ($topMateriales as $t) {
        $unidadesTotales += (int) $t['unidades'];
    }
    $materialesDistintos = count($topMateriales);

    // 3. Materiales por marca de lector
    $filasMarca = consultar($pdo,
        "SELECT COALESCE(l.marca, 'Sin marca') AS marca, m.nombre, SUM(rm.cantidad) AS unidades
           FROM reparacion_materiales rm
           JOIN reparaciones r ON r.id = rm.reparacion_id
           JOIN materiales m ON m.id = rm.material_id
           JOIN lectores l ON l.id = r.lector_id
          WHERE 1=1 $f
          GROUP BY COALESCE(l.marca, 'Sin marca'), m.id, m.nombre
          ORDER BY marca, unidades DESC, m.nombre", $params);
    foreach ($filasMarca as $fila) {
        $porMarca[$fila['marca']][] = $fila;
    }

    // 4. Unidades usadas por mes (últimos 6 meses del periodo)
    $porMes = consultar($pdo,
        "SELECT to_char(date_trunc('month', r.creado_en), 'YYYY-MM') AS mes, SUM(rm.cantidad) AS unidades
           FROM reparacion_materiales rm
           JOIN reparaciones r ON r.id = rm.reparacion_id
          WHERE 1=1 $f
          GROUP BY 1
          ORDER BY 1", $params);
    $porMes = array_slice($porMes, -6);

    // 5. Meses que abarca el periodo (para el promedio mensual)
    $inicio = $desde;
    if ($inicio === null) {
        $primera = consultar($pdo, "SELECT MIN(creado_en)::date AS primera FROM reparaciones");
        $inicio = $primera[0]['primera'] ?? null;
    }
    if ($inicio) {
        $dias = max(1, (int) floor((time() - strtotime($inicio)) / 86400));
        $meses = max(1.0, $dias / 30.4);
    }
} catch (PDOException $e) {
    $error = 'Error al calcular los indicadores: ' . $e->getMessage();
}

$totalReparaciones = array_sum($resultados);
$maxTop = $topMateriales ? (float) $topMateriales[0]['unidades'] : 0;
$maxMes = $porMes ? (float) max(array_column($porMes, 'unidades')) : 0;

$titulo_pagina = 'Indicadores de Materiales - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 1000px; margin: 0 auto;">
    <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
    <h2>Indicadores de Materiales y Reparaciones</h2>

    <?php if ($error): ?>
        <p style="color: red; font-weight: bold; padding: 10px; background-color: #ffebee;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="GET" action="indicadores.php" style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px;">
        <label for="periodo" style="margin: 0;">Periodo:</label>
        <select id="periodo" name="periodo" style="width: auto;" onchange="this.form.submit()">
            <?php foreach ($periodos as $clave => $texto): ?>
                <option value="<?php echo htmlspecialchars((string) $clave); ?>" <?php echo ((string) $clave === $periodo) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($texto); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit">Aplicar</button></noscript>
    </form>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 24px;">
        <?php
        $tarjetas = [
            ['Reparaciones registradas', $totalReparaciones, '#333333'],
            ['Reparados', $resultados['REPARADO'], '#1b5e20'],
            ['Irreparables', $resultados['IRREPARABLE'], '#b71c1c'],
            ['Unidades de material usadas', $unidadesTotales, '#0288d1'],
            ['Materiales distintos', $materialesDistintos, '#0288d1'],
        ];
        foreach ($tarjetas as [$titulo, $valor, $color]): ?>
            <div style="border: 1px solid #ddd; border-left: 5px solid <?php echo $color; ?>; border-radius: 4px; padding: 12px;">
                <div style="font-size: 0.85em; color: #555;"><?php echo htmlspecialchars($titulo); ?></div>
                <div style="font-size: 1.8em; font-weight: bold; color: <?php echo $color; ?>;"><?php echo (int) $valor; ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <h3 style="color: #1a237e;">Materiales más utilizados (para abastecimiento)</h3>
    <?php if ($topMateriales): ?>
        <table style="margin-bottom: 8px;">
            <thead>
                <tr>
                    <th style="width: 30%;">Material</th>
                    <th>Unidades usadas</th>
                    <th style="width: 15%;">Reparaciones</th>
                    <th style="width: 18%;">Promedio mensual</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($topMateriales, 0, 10) as $t): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($t['nombre']); ?></td>
                        <td><?php echo barra((float) $t['unidades'], $maxTop); ?></td>
                        <td><?php echo (int) $t['reparaciones']; ?></td>
                        <td><?php echo number_format(((float) $t['unidades']) / $meses, 1); ?> / mes</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="font-size: 0.85em; color: #555; margin-bottom: 24px;">
            El promedio mensual es una estimación: unidades usadas entre los meses que abarca el periodo (mínimo 1 mes).
        </p>
    <?php else: ?>
        <p>Todavía no hay materiales registrados en reparaciones en este periodo.</p>
    <?php endif; ?>

    <h3 style="color: #1a237e;">Materiales por marca de lector</h3>
    <?php if ($porMarca): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <?php foreach ($porMarca as $marca => $filas): ?>
                <div style="border: 1px solid #ddd; border-radius: 4px; padding: 12px;">
                    <strong><?php echo htmlspecialchars((string) $marca); ?></strong>
                    <table style="margin-top: 8px;">
                        <?php $maxMarca = (float) $filas[0]['unidades']; ?>
                        <?php foreach (array_slice($filas, 0, 5) as $fila): ?>
                            <tr>
                                <td style="padding: 6px; width: 45%;"><?php echo htmlspecialchars($fila['nombre']); ?></td>
                                <td style="padding: 6px;"><?php echo barra((float) $fila['unidades'], $maxMarca); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Sin datos en este periodo.</p>
    <?php endif; ?>

    <h3 style="color: #1a237e;">Unidades usadas por mes</h3>
    <?php if ($porMes): ?>
        <table style="margin-bottom: 24px;">
            <?php foreach ($porMes as $fila): ?>
                <tr>
                    <td style="width: 25%;"><?php echo htmlspecialchars($fila['mes']); ?></td>
                    <td><?php echo barra((float) $fila['unidades'], $maxMes); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Sin datos en este periodo.</p>
    <?php endif; ?>
</div>

<?php require_once '../includes/pie.php'; ?>
