<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Solo el técnico registra reparaciones
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'TECNICO') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
require_once '../includes/estado.php';
$pdo = obtener_conexion();
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lector_id = (int) ($_POST['lector_id'] ?? 0);
    $resultado = $_POST['resultado'] ?? '';
    $diagnostico = trim($_POST['diagnostico'] ?? '');
    $trabajo = trim($_POST['trabajo_realizado'] ?? '');
    $usuario_id = $_SESSION['usuario_id'];

    // Materiales usados (solo si el equipo quedó reparado): listas paralelas material_id[] y cantidad[]
    $materiales = [];
    $error_materiales = '';
    if ($resultado === 'REPARADO') {
        $ids_material = $_POST['material_id'] ?? [];
        $cantidades = $_POST['cantidad'] ?? [];
        if (is_array($ids_material) && is_array($cantidades)) {
            foreach ($ids_material as $i => $id_material) {
                $id_material = (int) $id_material;
                if ($id_material <= 0) {
                    continue; // fila sin material elegido
                }
                $cantidad = (int) ($cantidades[$i] ?? 0);
                if ($cantidad < 1 || $cantidad > 999) {
                    $error_materiales = 'La cantidad de cada material debe estar entre 1 y 999.';
                    break;
                }
                $materiales[$id_material] = ($materiales[$id_material] ?? 0) + $cantidad;
            }
        }
    }

    if ($lector_id <= 0 || !in_array($resultado, ['REPARADO', 'IRREPARABLE'], true) || $diagnostico === '') {
        $error = 'Selecciona el equipo, el resultado y escribe el diagnóstico.';
    } elseif ($error_materiales !== '') {
        $error = $error_materiales;
    } else {
        try {
            $pdo->beginTransaction();

            // Confirmar que el equipo sigue en revisión (se bloquea la fila mientras se registra)
            $stmtLector = $pdo->prepare("SELECT numero_serie, estado FROM lectores WHERE id = :id FOR UPDATE");
            $stmtLector->execute(['id' => $lector_id]);
            $lector = $stmtLector->fetch();
            if (!$lector || $lector['estado'] !== 'EN REVISION') {
                throw new RuntimeException('El equipo ya no está en revisión.');
            }

            // Validar los materiales contra el catálogo
            $lista_materiales = [];
            $stmtMat = $pdo->prepare("SELECT nombre FROM materiales WHERE id = :id AND activo = TRUE");
            foreach ($materiales as $id_material => $cantidad) {
                $stmtMat->execute(['id' => $id_material]);
                $nombre_material = $stmtMat->fetchColumn();
                if ($nombre_material === false) {
                    throw new RuntimeException('Uno de los materiales elegidos ya no está disponible en el catálogo.');
                }
                $lista_materiales[] = $nombre_material . ' x' . $cantidad;
            }

            // Registrar la reparación
            $stmtRep = $pdo->prepare("INSERT INTO reparaciones
                    (lector_id, tecnico_id, resultado, diagnostico, trabajo_realizado)
                    VALUES (:lector_id, :tecnico_id, :resultado, :diagnostico, :trabajo)
                    RETURNING id");
            $stmtRep->execute([
                'lector_id'   => $lector_id,
                'tecnico_id'  => $usuario_id,
                'resultado'   => $resultado,
                'diagnostico' => $diagnostico,
                'trabajo'     => ($resultado === 'REPARADO' && $trabajo !== '') ? $trabajo : null
            ]);
            $reparacion_id = (int) $stmtRep->fetchColumn();

            // Registrar los materiales usados
            $stmtMatIns = $pdo->prepare("INSERT INTO reparacion_materiales (reparacion_id, material_id, cantidad)
                                         VALUES (:reparacion_id, :material_id, :cantidad)");
            foreach ($materiales as $id_material => $cantidad) {
                $stmtMatIns->execute([
                    'reparacion_id' => $reparacion_id,
                    'material_id'   => $id_material,
                    'cantidad'      => $cantidad
                ]);
            }

            // Estado del equipo e historial
            if ($resultado === 'REPARADO') {
                $accion = 'REPARACION EQUIPO';
                $descripcion = "Reparación registrada. Resultado: Reparado. Diagnóstico: $diagnostico"
                    . (count($lista_materiales) > 0 ? ' Materiales: ' . implode(', ', $lista_materiales) . '.' : ' Sin materiales.');
                $mensaje = 'Reparación registrada. Cuando el equipo esté listo, regístralo en "Devolver Equipo Reparado".';
            } else {
                $accion = 'REPARACION EQUIPO';
                $descripcion = "Reparación registrada. Resultado: Irreparable (se propone la baja). Diagnóstico: $diagnostico";
                $mensaje = 'Se registró que el equipo es irreparable. El administrador verá la propuesta de baja.';
            }

            $stmtHist = $pdo->prepare("INSERT INTO historial (lector_id, accion, descripcion, usuario_id)
                                       VALUES (:lector_id, :accion, :descripcion, :usuario_id)");
            $stmtHist->execute([
                'lector_id'   => $lector_id,
                'accion'      => $accion,
                'descripcion' => $descripcion,
                'usuario_id'  => $usuario_id
            ]);

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = '';
            $error = 'Error al registrar la reparación: ' . $e->getMessage();
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = '';
            $error = $e->getMessage();
        }
    }
}

// Equipos en revisión (con la última falla reportada para dar contexto) y catálogo de materiales
$equipos = [];
$catalogo = [];
try {
    $equipos = $pdo->query("SELECT l.id, l.numero_serie, l.marca,
                                   (SELECT r.falla FROM reportes_falla r
                                     WHERE r.lector_id = l.id
                                     ORDER BY r.creado_en DESC, r.id DESC LIMIT 1) AS falla
                              FROM lectores l
                             WHERE l.estado = 'EN REVISION'
                             ORDER BY l.numero_serie")->fetchAll();
    $catalogo = $pdo->query("SELECT id, nombre FROM materiales WHERE activo = TRUE ORDER BY nombre")->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al cargar los datos: ' . $e->getMessage();
}

$titulo_pagina = 'Registrar Reparación - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 700px; margin: 0 auto;">
    <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
    <h2>Registrar Reparación</h2>

    <?php if ($mensaje): ?>
        <p style="color: green; font-weight: bold; padding: 10px; background-color: #e8f5e9;"><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color: red; font-weight: bold; padding: 10px; background-color: #ffebee;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if (count($equipos) > 0): ?>
        <form method="POST" action="nueva.php" id="form_reparacion" autocomplete="off">
            <div class="campo">
                <label for="lector_id">Equipo en revisión *</label>
                <select id="lector_id" name="lector_id" required>
                    <option value="">-- Seleccione el equipo --</option>
                    <?php foreach ($equipos as $equipo): ?>
                        <option value="<?php echo (int) $equipo['id']; ?>">
                            Serie: <?php echo htmlspecialchars($equipo['numero_serie']); ?>
                            <?php if (!empty($equipo['marca'])): ?>(<?php echo htmlspecialchars($equipo['marca']); ?>)<?php endif; ?>
                            <?php if (!empty($equipo['falla'])): ?> — Falla: <?php echo htmlspecialchars(recortar_texto($equipo['falla'], 60)); ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label for="diagnostico">Diagnóstico *</label>
                <textarea id="diagnostico" name="diagnostico" rows="3" required></textarea>
            </div>

            <div class="campo">
                <label for="resultado">Resultado *</label>
                <select id="resultado" name="resultado" required>
                    <option value="">-- Seleccione --</option>
                    <option value="REPARADO">Reparado en TIC's</option>
                    <option value="IRREPARABLE">Irreparable (proponer baja)</option>
                </select>
            </div>

            <!-- Solo si el equipo quedó reparado -->
            <div id="seccion_materiales" style="display: none;">
                <div class="campo">
                    <label for="trabajo_realizado">Trabajo realizado (Opcional)</label>
                    <textarea id="trabajo_realizado" name="trabajo_realizado" rows="2"></textarea>
                </div>

                <div class="campo">
                    <label>Materiales o partes utilizadas</label>
                    <div id="lista_materiales"></div>
                    <?php if (count($catalogo) > 0): ?>
                        <button type="button" id="btn_agregar_material" style="background: #0288d1; padding: 6px 12px; font-size: 0.9em;">+ Agregar material</button>
                    <?php else: ?>
                        <p style="color: #bf360c;">El catálogo está vacío. <a href="materiales.php">Agrega materiales aquí</a> y vuelve a esta pantalla.</p>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" style="background-color: #0288d1;">Registrar</button>
        </form>
    <?php else: ?>
        <p style="padding: 15px; background-color: #e0f7fa; border-radius: 4px; color: #006064;">
            No hay equipos en revisión en este momento. Primero hay que recibirlos en "Recibir Equipo Dañado".
        </p>
    <?php endif; ?>
</div>

<script>
    const catalogo = <?php echo json_encode($catalogo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const formulario = document.getElementById('form_reparacion');

    if (formulario) {
        const selResultado = document.getElementById('resultado');
        const secMateriales = document.getElementById('seccion_materiales');
        const lista = document.getElementById('lista_materiales');
        const btnAgregar = document.getElementById('btn_agregar_material');

        function agregarFila() {
            const fila = document.createElement('div');
            fila.style.cssText = 'display: flex; gap: 8px; margin-bottom: 8px; align-items: center;';

            const sel = document.createElement('select');
            sel.name = 'material_id[]';
            sel.style.flex = '3';
            sel.add(new Option('-- Material --', ''));
            catalogo.forEach(function (m) { sel.add(new Option(m.nombre, m.id)); });

            const cant = document.createElement('input');
            cant.type = 'number';
            cant.name = 'cantidad[]';
            cant.min = '1';
            cant.max = '999';
            cant.value = '1';
            cant.style.cssText = 'width: 80px; padding: 8px; box-sizing: border-box;';

            const quitar = document.createElement('button');
            quitar.type = 'button';
            quitar.textContent = 'Quitar';
            quitar.style.cssText = 'background: #d32f2f; padding: 6px 10px; font-size: 0.85em;';
            quitar.addEventListener('click', function () { fila.remove(); });

            fila.appendChild(sel);
            fila.appendChild(cant);
            fila.appendChild(quitar);
            lista.appendChild(fila);
        }

        if (btnAgregar) {
            btnAgregar.addEventListener('click', agregarFila);
        }

        selResultado.addEventListener('change', function () {
            secMateriales.style.display = (this.value === 'REPARADO') ? 'block' : 'none';
            if (this.value === 'REPARADO' && lista.children.length === 0 && catalogo.length > 0) {
                agregarFila();
            }
        });

        formulario.addEventListener('submit', function (e) {
            let pregunta = '';
            if (selResultado.value === 'IRREPARABLE') {
                pregunta = '¿Confirmas que el equipo es irreparable y se propone su baja?';
            }
            if (pregunta !== '' && !confirm(pregunta)) {
                e.preventDefault();
            }
        });
    }
</script>

<?php require_once '../includes/pie.php'; ?>
