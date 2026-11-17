-- Actualización del esquema de SICLO para que coincida con lo que usa el código PHP.
-- Es seguro ejecutarlo varias veces. En tu base local, lo que ya exista no se modifica.
-- Antes de correrlo, haz respaldo:  pg_dump -U postgres siclo > respaldo_siclo.sql
-- Ejecutar dentro de la base siclo:  psql -U postgres -d siclo -f sql/002_actualizar_esquema.sql

BEGIN;

-- 1. Columnas que el código usa pero que faltaban en esquema.sql
ALTER TABLE lectores        ADD COLUMN IF NOT EXISTS estado        VARCHAR(30)  NOT NULL DEFAULT 'ACTIVO';
ALTER TABLE reportes_falla  ADD COLUMN IF NOT EXISTS observaciones TEXT;
ALTER TABLE reportes_falla  ADD COLUMN IF NOT EXISTS coordenada_x  NUMERIC(10,2);
ALTER TABLE reportes_falla  ADD COLUMN IF NOT EXISTS coordenada_y  NUMERIC(10,2);

-- 2. Campos que el código deja vacíos (null) cuando el usuario no los llena
ALTER TABLE lectores        ALTER COLUMN rpe_asociado    DROP NOT NULL;
ALTER TABLE lectores        ALTER COLUMN numero_etiqueta DROP NOT NULL;
ALTER TABLE lectores        ALTER COLUMN marca           DROP NOT NULL;
ALTER TABLE reportes_falla  ALTER COLUMN numero_ticket   DROP NOT NULL;

-- 3. Unificar los nombres de estado: siempre con espacio (ej. 'PENDIENTE RECEPCION')
UPDATE lectores SET estado = REPLACE(estado, '_', ' ') WHERE position('_' IN estado) > 0;

-- 4. Unificar el nombre de la acción de recepción en el historial
UPDATE historial SET accion = 'RECEPCION_EQUIPO' WHERE accion = 'RECEPCION EQUIPO';

-- 5. Índice útil para los indicadores y la búsqueda por serie
CREATE INDEX IF NOT EXISTS idx_lectores_estado        ON lectores(estado);
CREATE INDEX IF NOT EXISTS idx_lectores_numero_serie  ON lectores(numero_serie);
CREATE INDEX IF NOT EXISTS idx_historial_accion       ON historial(accion);

-- 6. Opcional (descomenta solo si NO tienes series ni folios repetidos):
-- CREATE UNIQUE INDEX IF NOT EXISTS ux_lectores_numero_serie ON lectores(numero_serie);
-- CREATE UNIQUE INDEX IF NOT EXISTS ux_reportes_falla_folio  ON reportes_falla(folio);

COMMIT;
