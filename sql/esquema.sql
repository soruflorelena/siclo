-- Esquema inicial de SICLO (PostgreSQL).
-- Ejecutar dentro de la base de datos siclo.

CREATE TABLE usuarios (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    rpe VARCHAR(30) NOT NULL UNIQUE,
    contrasena_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(20) NOT NULL CHECK (rol IN ('USUARIO', 'ADMINISTRADOR')),
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE centros_trabajo (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL UNIQUE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE lectores (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    numero_serie VARCHAR(100) NOT NULL,
    foto_url TEXT,
    centro_trabajo_id BIGINT NOT NULL REFERENCES centros_trabajo(id),
    rpe_asociado VARCHAR(30) NOT NULL,
    tipo_conector VARCHAR(10) NOT NULL CHECK (tipo_conector IN ('USB', 'DB17')),
    numero_etiqueta VARCHAR(100) NOT NULL,
    marca VARCHAR(100) NOT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reportes_falla (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    folio VARCHAR(100) NOT NULL,
    numero_ticket VARCHAR(100) NOT NULL,
    falla TEXT NOT NULL,
    centro_trabajo_id BIGINT NOT NULL REFERENCES centros_trabajo(id),
    lector_id BIGINT NOT NULL REFERENCES lectores(id),
    usuario_id BIGINT NOT NULL REFERENCES usuarios(id),
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE historial (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    lector_id BIGINT NOT NULL REFERENCES lectores(id),
    accion VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    usuario_id BIGINT NOT NULL REFERENCES usuarios(id),
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Índices para las consultas confirmadas de las etapas posteriores.
CREATE INDEX idx_lectores_centro_trabajo ON lectores(centro_trabajo_id);
CREATE INDEX idx_reportes_falla_usuario ON reportes_falla(usuario_id);
CREATE INDEX idx_reportes_falla_lector ON reportes_falla(lector_id);
CREATE INDEX idx_reportes_falla_centro ON reportes_falla(centro_trabajo_id);
CREATE INDEX idx_historial_lector ON historial(lector_id);
CREATE INDEX idx_historial_usuario ON historial(usuario_id);
