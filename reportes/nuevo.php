<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'USUARIO') {
    header("Location: ../panel.php");
    exit;
}

require_once '../config/base_datos.php';
$pdo = obtener_conexion();
$mensaje = '';
$error = '';
$hoy = date('Y-m-d'); // fecha actual en UTC-6 (ver config/base_datos.php)

// El centro de trabajo del reporte es siempre el de la cuenta del usuario
$centro_usuario_id = null;
$centro_usuario_nombre = '';
try {
    $stmtCentro = $pdo->prepare("SELECT u.centro_trabajo_id, c.nombre
                                   FROM usuarios u
                                   LEFT JOIN centros_trabajo c ON c.id = u.centro_trabajo_id
                                  WHERE u.id = :id");
    $stmtCentro->execute(['id' => $_SESSION['usuario_id']]);
    $datosCentro = $stmtCentro->fetch();
    if ($datosCentro && $datosCentro['centro_trabajo_id'] !== null) {
        $centro_usuario_id = (int) $datosCentro['centro_trabajo_id'];
        $centro_usuario_nombre = $datosCentro['nombre'] ?? '';
    }
} catch (PDOException $e) {
    $error = "Error al cargar tu centro de trabajo: " . $e->getMessage();
}
if ($centro_usuario_id === null && $error === '') {
    $error = 'Tu cuenta no tiene un centro de trabajo asignado. Contacta al administrador para poder reportar fallas.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folio = 'R-' . mt_rand(10000, 99999);
    $centro_trabajo_id = $centro_usuario_id; // no se toma del formulario
    $falla = trim($_POST['falla'] ?? '');
    $numero_ticket = trim($_POST['numero_ticket'] ?? '');

    $serie = strtoupper(trim($_POST['serie'] ?? ''));
    $numero_etiqueta = strtoupper(trim($_POST['numero_etiqueta'] ?? '')); 
    $tipo_conector = $_POST['tipo_conector'] ?? 'USB'; 
    $marca = $_POST['marca'] ?? 'Jeda TTR';
    if ($marca === 'Otro') {
        $marca = trim($_POST['otra_marca'] ?? 'Otra marca');
    }
    $observaciones = trim($_POST['observaciones'] ?? '');

    // Marcas de daño: JSON con una lista de pares [x, y] en porcentaje (puede haber varias)
    $marcas = [];
    $marcas_json = json_decode($_POST['marcas'] ?? '[]', true);
    if (is_array($marcas_json)) {
        foreach (array_slice($marcas_json, 0, 20) as $punto) {
            if (!is_array($punto)) {
                continue;
            }
            $punto = array_values($punto);
            if (count($punto) === 2 && is_numeric($punto[0]) && is_numeric($punto[1])) {
                $mx = round((float) $punto[0], 2);
                $my = round((float) $punto[1], 2);
                if ($mx >= 0 && $mx <= 100 && $my >= 0 && $my <= 100) {
                    $marcas[] = [$mx, $my];
                }
            }
        }
    }
    $usuario_id = $_SESSION['usuario_id'];

    // Fecha de la falla: por defecto hoy, pero puede ser una fecha anterior
    $fecha_falla = trim($_POST['fecha_falla'] ?? $hoy);
    $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_falla);
    $fecha_valida = $fecha_obj && $fecha_obj->format('Y-m-d') === $fecha_falla;

    if ($centro_usuario_id === null) {
        $error = 'Tu cuenta no tiene un centro de trabajo asignado. Contacta al administrador.';
    } elseif (empty($folio) || empty($centro_trabajo_id) || empty($falla) || empty($serie)) {
        $error = 'Por favor, llena los campos obligatorios.';
    } elseif ($numero_ticket !== '' && !ctype_digit($numero_ticket)) {
        $error = 'El número de ticket solo puede contener números.';
    } elseif (!$fecha_valida) {
        $error = 'La fecha de la falla no es válida.';
    } elseif ($fecha_falla > $hoy) {
        $error = 'La fecha de la falla no puede ser posterior a hoy.';
    } else {
        $stmtVerificar = $pdo->prepare("SELECT estado, centro_trabajo_id FROM lectores WHERE numero_serie = :serie");
        $stmtVerificar->execute(['serie' => $serie]);
        $lectorExistente = $stmtVerificar->fetch();

        if ($lectorExistente && (int) $lectorExistente['centro_trabajo_id'] !== (int) $centro_trabajo_id) {
            $error = "El equipo con serie $serie está registrado en otro centro de trabajo, por lo que no puedes reportarlo desde el tuyo. Si el equipo cambió de lugar, pide al administrador que lo reasigne.";
        } elseif ($lectorExistente && in_array($lectorExistente['estado'], ['PENDIENTE RECEPCION', 'EN REVISION'])) {
            $error = "El equipo con serie $serie ya tiene un reporte activo y actualmente se encuentra " . str_replace('_', ' ', $lectorExistente['estado']) . ". No se puede duplicar el reporte.";
        } else {
            try {
                $pdo->beginTransaction();

                $stmtLector = $pdo->prepare("SELECT id FROM lectores WHERE numero_serie = :serie LIMIT 1");
                $stmtLector->execute(['serie' => $serie]);
                $lector = $stmtLector->fetch();

                if (!$lector) {
                    $rpe_sesion = $_SESSION['rpe'];
                    $etiqueta_final = empty($numero_etiqueta) ? 'N/A' : $numero_etiqueta;
                    
                    $sqlNuevoLector = "INSERT INTO lectores (numero_serie, centro_trabajo_id, rpe_asociado, tipo_conector, numero_etiqueta, marca) 
                                    VALUES (:serie, :centro_trabajo_id, :rpe_sesion, :tipo_conector, :etiqueta, :marca)";
                    $stmtNuevo = $pdo->prepare($sqlNuevoLector);
                    $stmtNuevo->execute([
                        'serie' => $serie,
                        'centro_trabajo_id' => $centro_trabajo_id,
                        'rpe_sesion' => $rpe_sesion,
                        'tipo_conector' => $tipo_conector,
                        'etiqueta' => $etiqueta_final,
                        'marca' => $marca
                    ]);
                    $lector_id = $pdo->lastInsertId(); 
                    
                    $sqlHistorialAlta = "INSERT INTO historial (lector_id, accion, descripcion, usuario_id) 
                                         VALUES (:lector_id, 'ALTA_AUTOMATICA', 'Alta automática al reportar falla', :usuario_id)";
                    $stmtHistorialAlta = $pdo->prepare($sqlHistorialAlta);
                    $stmtHistorialAlta->execute([
                        'lector_id' => $lector_id,
                        'usuario_id' => $usuario_id
                    ]);
                } else {
                    $lector_id = $lector['id'];
                }

                $sqlEstado = "UPDATE lectores SET estado = 'PENDIENTE RECEPCION' WHERE id = :lector_id";
                $stmtEstado = $pdo->prepare($sqlEstado);
                $stmtEstado->execute(['lector_id' => $lector_id]);

                $sqlFalla = "INSERT INTO reportes_falla (folio, numero_ticket, falla, centro_trabajo_id, lector_id, usuario_id, observaciones, fecha_falla) 
                            VALUES (:folio, :numero_ticket, :falla, :centro_trabajo_id, :lector_id, :usuario_id, :observaciones, :fecha_falla)
                            RETURNING id";
                $stmtFalla = $pdo->prepare($sqlFalla);
                $stmtFalla->execute([
                    'folio' => $folio,
                    'numero_ticket' => empty($numero_ticket) ? null : $numero_ticket,
                    'falla' => $falla,
                    'centro_trabajo_id' => $centro_trabajo_id,
                    'lector_id' => $lector_id,
                    'usuario_id' => $usuario_id,
                    'observaciones' => empty($observaciones) ? null : $observaciones,
                    'fecha_falla' => $fecha_falla
                ]);
                $reporte_id = (int) $stmtFalla->fetchColumn();

                // Guardar cada marca de daño (puede haber varias)
                $stmtMarca = $pdo->prepare("INSERT INTO reporte_marcas (reporte_id, coordenada_x, coordenada_y, orden)
                                            VALUES (:reporte_id, :x, :y, :orden)");
                foreach ($marcas as $i => [$mx, $my]) {
                    $stmtMarca->execute([
                        'reporte_id' => $reporte_id,
                        'x' => $mx,
                        'y' => $my,
                        'orden' => $i + 1
                    ]);
                }

                $descripcion_historial = "Falla reportada. Folio: $folio. Ticket: " . ($numero_ticket ?: 'N/A')
                    . ". Fecha de la falla: " . date('d/m/Y', strtotime($fecha_falla));
                $sqlHistorial = "INSERT INTO historial (lector_id, accion, descripcion, usuario_id) 
                                 VALUES (:lector_id, 'REPORTE_FALLA', :descripcion, :usuario_id)";
                $stmtHistorial = $pdo->prepare($sqlHistorial);
                $stmtHistorial->execute([
                    'lector_id' => $lector_id,
                    'descripcion' => $descripcion_historial,
                    'usuario_id' => $usuario_id
                ]);

                $pdo->commit();
                $mensaje = "Falla reportada exitosamente con el folio: $folio. El equipo está Pendiente de Recepción.";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error al registrar la falla: ' . $e->getMessage();
            }
        }
    }
}

$titulo_pagina = 'Registrar Falla - SICLO';
require_once '../includes/encabezado.php';
?>

<div class="contenedor" style="max-width: 600px; margin: 0 auto;">
    <a href="../panel.php" class="boton-volver">← Volver al Panel</a>
    <h2>Registrar Falla de Equipo</h2>

    <?php if ($mensaje): ?>
        <p style="color: green; font-weight: bold; padding: 10px; background-color: #e8f5e9;"><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color: red; font-weight: bold; padding: 10px; background-color: #ffebee;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST" action="nuevo.php" autocomplete="off">
        <div class="campo">
            <label for="fecha_dia">Fecha de la falla *</label>
            <div style="display: flex; gap: 8px;">
                <select id="fecha_dia" aria-label="Día" style="flex: 1;"></select>
                <select id="fecha_mes" aria-label="Mes" style="flex: 2;"></select>
                <select id="fecha_anio" aria-label="Año" style="flex: 1.3;"></select>
            </div>
            <small id="aviso_fecha" style="color: #c62828;"></small>
            <!-- Valor que se envía al servidor (AAAA-MM-DD); se actualiza con los tres selectores -->
            <input type="hidden" id="fecha_falla" name="fecha_falla" value="<?php echo htmlspecialchars($hoy); ?>">
        </div>
        <div class="campo">
            <label for="numero_ticket">Número de Ticket</label>
            <input type="text" id="numero_ticket" name="numero_ticket" inputmode="numeric" pattern="[0-9]*" title="Solo números" oninput="this.value = this.value.replace(/\D/g, '')">
        </div>
        <div class="campo">
            <label for="serie">Número de Serie del Lector *</label>
            <input type="text" id="serie" name="serie" placeholder="Ingrese la serie exacta" autocapitalize="characters" oninput="var s = this.selectionStart, e = this.selectionEnd; this.value = this.value.toUpperCase(); this.setSelectionRange(s, e);" required>
        </div>
        <div class="campo">
            <label for="tipo_conector">Tipo de Conector</label>
            <select id="tipo_conector" name="tipo_conector">
                <option value="USB">USB (Por defecto)</option>
                <option value="DB17">DB17</option>
            </select>
        </div>
        <div class="campo">
            <label for="marca">Marca del equipo</label>
            <select id="marca" name="marca" onchange="mostrarCajaOtraMarca(this.value)">
                <option value="Jeda TTR">Jeda TTR</option>
                <option value="Parmo">Parmo</option>
                <option value="Microtex/Electronics">Microtex/Electronics</option>
                <option value="Davad">Davad</option>
                <option value="Intelliprobe">Intelliprobe</option>
                <option value="Otro">Otro (Especificar)</option>
            </select>
        </div>

        <div class="campo" id="div_otra_marca" style="display: none;">
            <label for="otra_marca">Especifique la marca *</label>
            <input type="text" id="otra_marca" name="otra_marca" placeholder="Escriba el nombre de la marca">
        </div>

        <div class="campo" style="margin-top: 20px;">
            <label>Marque con clics en la imagen las áreas dañadas del equipo. Puede marcar varias (Opcional)</label>
            <div id="lienzo_equipo" style="position: relative; display: inline-block; border: 1px solid #ccc; cursor: crosshair; background: #fff; max-width: 100%;">
                <img id="img_conector_interactiva" src="../img/usb.png" alt="Lector Óptico" style="max-width: 400px; height: auto; display: block;">
            </div>
            <div style="margin-top: 6px;">
                <button type="button" id="btn_limpiar_marcas" style="background: #455a64; padding: 4px 10px; font-size: 0.85em;">Limpiar marcas</button>
                <small id="contador_marcas" style="margin-left: 8px; color: #555;">Sin marcas</small>
            </div>
            <input type="hidden" id="marcas" name="marcas" value="[]">
        </div>

        <script>
        function mostrarCajaOtraMarca(valorSeleccionado) {
            var divOculto = document.getElementById('div_otra_marca');
            var inputOtra = document.getElementById('otra_marca');
            if (valorSeleccionado === 'Otro') {
                divOculto.style.display = 'block';
                inputOtra.required = true;
            } else {
                divOculto.style.display = 'none';
                inputOtra.required = false;
                inputOtra.value = '';
            }
        }

        const selectConector = document.getElementById('tipo_conector');
        const imgLienzo = document.getElementById('img_conector_interactiva');
        const lienzo = document.getElementById('lienzo_equipo');
        const inputMarcas = document.getElementById('marcas');
        const btnLimpiar = document.getElementById('btn_limpiar_marcas');
        const contador = document.getElementById('contador_marcas');
        const MAX_MARCAS = 20;
        let marcas = []; // cada marca es [x%, y%] respecto a la imagen

        function actualizarMarcas() {
            lienzo.querySelectorAll('.marca-dano').forEach(function (el) { el.remove(); });
            marcas.forEach(function (p) {
                const m = document.createElement('div');
                m.className = 'marca-dano';
                m.textContent = '\u274C';
                m.style.cssText = 'position: absolute; left: ' + p[0] + '%; top: ' + p[1] + '%; color: red; font-weight: bold; font-size: 28px; transform: translate(-50%, -50%); pointer-events: none; text-shadow: 1px 1px 2px black;';
                lienzo.appendChild(m);
            });
            inputMarcas.value = JSON.stringify(marcas);
            if (marcas.length === 0) {
                contador.textContent = 'Sin marcas';
            } else if (marcas.length === 1) {
                contador.textContent = '1 marca';
            } else {
                contador.textContent = marcas.length + ' marcas' + (marcas.length >= MAX_MARCAS ? ' (máximo)' : '');
            }
        }

        function limpiarMarcas() {
            marcas = [];
            actualizarMarcas();
        }

        selectConector.addEventListener('change', function() {
            if(this.value === 'USB') {
                imgLienzo.src = '../img/usb.png';
            } else {
                imgLienzo.src = '../img/db17.png';
            }
            limpiarMarcas();
        });

        lienzo.addEventListener('click', function(e) {
            if (marcas.length >= MAX_MARCAS) { return; }
            const rect = lienzo.getBoundingClientRect();
            const x = Math.min(100, Math.max(0, ((e.clientX - rect.left) / rect.width) * 100));
            const y = Math.min(100, Math.max(0, ((e.clientY - rect.top) / rect.height) * 100));
            marcas.push([parseFloat(x.toFixed(2)), parseFloat(y.toFixed(2))]);
            actualizarMarcas();
        });

        btnLimpiar.addEventListener('click', limpiarMarcas);
        actualizarMarcas();

        // ----- Selector de fecha (día / mes / año), igual en cualquier navegador o idioma -----
        (function () {
            const partes = '<?php echo htmlspecialchars($hoy); ?>'.split('-').map(Number);
            const anioHoy = partes[0], mesHoy = partes[1], diaHoy = partes[2];
            const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
                           'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            const selDia = document.getElementById('fecha_dia');
            const selMes = document.getElementById('fecha_mes');
            const selAnio = document.getElementById('fecha_anio');
            const oculto = document.getElementById('fecha_falla');
            const aviso = document.getElementById('aviso_fecha');

            // Se puede elegir el año actual y los dos anteriores
            for (let a = anioHoy; a >= anioHoy - 2; a--) {
                selAnio.add(new Option(a, a));
            }
            selAnio.value = anioHoy;

            function reconstruir() {
                const anio = Number(selAnio.value);

                // Meses disponibles (no se permiten meses futuros)
                const mesesMax = (anio === anioHoy) ? mesHoy : 12;
                let mes = Number(selMes.value) || mesHoy;
                if (mes > mesesMax) { mes = mesesMax; }
                selMes.innerHTML = '';
                for (let i = 1; i <= mesesMax; i++) {
                    selMes.add(new Option(MESES[i - 1], i));
                }
                selMes.value = mes;

                // Días disponibles: todos los del mes (28, 29, 30 o 31)
                const diasMax = new Date(anio, mes, 0).getDate();
                let dia = Number(selDia.value) || diaHoy;
                if (dia > diasMax) { dia = diasMax; }
                selDia.innerHTML = '';
                for (let i = 1; i <= diasMax; i++) {
                    selDia.add(new Option(i, i));
                }
                selDia.value = dia;

                // Una fecha posterior a hoy no es válida: se avisa y se bloquea el envío
                const futura = (anio === anioHoy && mes === mesHoy && dia > diaHoy);
                const textoAviso = futura ? 'La fecha de la falla no puede ser posterior a hoy.' : '';
                selDia.setCustomValidity(textoAviso);
                aviso.textContent = textoAviso;

                oculto.value = anio + '-' + String(mes).padStart(2, '0') + '-' + String(dia).padStart(2, '0');
            }

            selAnio.addEventListener('change', reconstruir);
            selMes.addEventListener('change', reconstruir);
            selDia.addEventListener('change', reconstruir);
            reconstruir();
        })();
        </script>

        <div class="campo">
            <label for="numero_etiqueta">Número de Etiqueta</label>
            <input type="text" id="numero_etiqueta" name="numero_etiqueta" autocapitalize="characters" oninput="var s = this.selectionStart, e = this.selectionEnd; this.value = this.value.toUpperCase(); this.setSelectionRange(s, e);">
        </div>
        <div class="campo">
            <label for="centro_trabajo_nombre">Centro de Trabajo</label>
            <input type="text" id="centro_trabajo_nombre" value="<?php echo htmlspecialchars($centro_usuario_nombre); ?>" readonly style="background: #f1f1f1;">
        </div>
        <div class="campo">
            <label for="falla">Descripción de la Falla *</label>
            <textarea id="falla" name="falla" required></textarea>
        </div>
        <div class="campo">
            <label for="observaciones">Observaciones (Opcional)</label>
            <textarea id="observaciones" name="observaciones"></textarea>
        </div>
        <button type="submit">Registrar Falla</button>
    </form>
</div>

<?php require_once '../includes/pie.php'; ?>