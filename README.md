# SICLO

Sistema web para el control de lectores ópticos en centros de trabajo de CFE.

Proyecto de residencias profesionales (Instituto Tecnológico Superior de Misantla) realizado en el
departamento de Tecnologías de la Información y Comunicaciones de la CFE, Teziutlán, Puebla.

SICLO permite llevar el inventario de los lectores ópticos que se usan para la toma de lecturas,
reportar sus fallas, darles seguimiento (recepción, reparación, devolución o baja) y consultar el
historial de cada equipo y los indicadores de operación.

## Perfiles de usuario

| Función | Usuario | Técnico | Administrador |
|---|:---:|:---:|:---:|
| Reportar fallas de los equipos de su centro (con marcas sobre la imagen del lector) | ✔ | | |
| Consultar su historial de fallas reportadas | ✔ | | |
| Consultar el inventario y el historial de cada equipo | | ✔ | ✔ |
| Dar de alta y editar equipos (centro de trabajo y responsable) | | | ✔ |
| Recibir equipo dañado | | ✔ | ✔ |
| Registrar reparación con materiales utilizados, o marcar el equipo como irreparable | | ✔ | |
| Devolver equipo reparado | | ✔ | ✔ |
| Dar de baja equipos | | | ✔ |
| Catálogo de materiales | | ✔ | ✔ |
| Historial de reparaciones e indicadores de materiales | | ✔ | ✔ |
| Historial general e indicadores generales | | | ✔ |

## Ciclo de vida de un equipo

```
ACTIVO → (reporte de falla) → PENDIENTE RECEPCION → (recepción) → EN REVISION
EN REVISION → (reparación registrada y devolución) → ACTIVO
EN REVISION → (irreparable, propuesta del técnico) → (baja del administrador) → BAJA
```

Cada movimiento queda registrado en el historial del equipo.

## Reglas principales

- Cada usuario pertenece a un centro de trabajo y solo puede reportar equipos de ese centro.
  Un administrador puede reasignar un equipo a otro centro desde la edición del equipo.
- No se puede reportar un equipo que ya tiene un reporte activo.
- La fecha de la falla puede ser anterior a hoy, pero no posterior.
- El número de serie y el de etiqueta se guardan en mayúsculas; el número de ticket solo acepta números y es opcional.
- Cada usuario tiene RPE, nombre del trabajador (en mayúsculas) y rol.

## Requisitos

- PHP 8.1 o posterior con las extensiones `pdo_pgsql` y `mbstring`.
- PostgreSQL 14 o posterior y la herramienta `psql`.

## Instalación local

1. **Configurar la conexión.** Copia `.env.example` como `.env` y escribe tus datos
   (las variables deben llamarse `SICLO_DB_*`, como en el ejemplo). El archivo `.env` no se sube al repositorio.
2. **Crear la base:**
   `psql -U postgres -d postgres -f sql/crear_base_datos.sql`
3. **Crear las tablas base:**
   `psql -U postgres -d siclo -f sql/esquema.sql`
4. **Insertar el catálogo de centros de trabajo:**
   `psql -U postgres -d siclo -f sql/datos_iniciales.sql`


   Ejemplo: `psql -U postgres -d siclo -f sql/003_fecha_falla.sql`

   > `sql/esquema.sql` contiene solo la base inicial. Para dejar el esquema completo en un solo archivo,
   > exporta la base ya actualizada con `pg_dump -s -U postgres siclo > sql/esquema.sql`.
5. **Crear el primer administrador.** Edita las variables de `crear_usuarios.php` (RPE, nombre, contraseña,
   rol y, para el rol `USUARIO`, el centro de trabajo) y ábrelo desde `http://localhost:8000/crear_usuarios.php`
   o con `php crear_usuarios.php`. Roles disponibles: `USUARIO`, `ADMINISTRADOR` y `TECNICO`.
6. **Iniciar el servidor** desde la carpeta del proyecto: `php -S localhost:8000`.
   Para comprobar la conexión: `http://localhost:8000/prueba_conexion.php`.

## Estructura del proyecto

```
config/         Conexión a PostgreSQL, carga del .env y zona horaria
includes/       Encabezado, pie, estados del equipo y utilidades (nombres, centros)
lectores/       Inventario, alta, edición, recepción, devolución y baja
reportes/       Reporte de falla, historial de fallas del usuario y detalle del daño
reparaciones/   Registro de reparaciones, catálogo de materiales, historial e indicadores de materiales
historial/      Historial general y por equipo
img/            Imágenes de los conectores (USB y DB17) para marcar el daño
sql/            Scripts de base de datos
login.php, panel.php, indicadores.php, cerrar_sesion.php
```