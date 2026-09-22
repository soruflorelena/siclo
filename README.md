# SICLO 

Base inicial para el Sistema de Control de Lectores Ópticos.

## Requisitos

- PHP 8.1 o posterior con la extensión `pdo_pgsql`.
- PostgreSQL 14 o posterior y la herramienta `psql` disponible.

## Instalación local

Crear la base: `psql -U postgres -d postgres -f sql/crear_base_datos.sql`.
Crear tablas: `psql -U postgres -d siclo -f sql/esquema.sql`.
Insertar el catálogo: `psql -U postgres -d siclo -f sql/datos_iniciales.sql`.
Iniciar PHP desde esta carpeta: `php -S localhost:8000`.
`http://localhost:8000/prueba_conexion.php`. 