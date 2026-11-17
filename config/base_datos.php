<?php
declare(strict_types=1);

/** Zona horaria del sistema: UTC-6 fijo (GMT-6), sin horario de verano. */
const ZONA_HORARIA = 'Etc/GMT+6';
date_default_timezone_set(ZONA_HORARIA);

/**
 * Lee variables desde .env cuando PHP se ejecuta sin un cargador de entorno.
 * No sustituye variables que ya existan en el servidor.
 */
function cargar_entorno(string $archivo): void
{
    if (!is_readable($archivo)) {
        return;
    }

    $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lineas === false) {
        return;
    }

    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
            continue;
        }

        [$nombre, $valor] = explode('=', $linea, 2);
        $nombre = trim($nombre);
        $valor = trim($valor);

        if ($nombre !== '' && getenv($nombre) === false) {
            putenv($nombre . '=' . $valor);
        }
    }
}

cargar_entorno(dirname(__DIR__) . '/.env');

/** Devuelve una conexión PDO configurada para PostgreSQL. */
function obtener_conexion(): PDO
{
    $host = getenv('SICLO_DB_HOST') ?: '127.0.0.1';
    $puerto = getenv('SICLO_DB_PORT') ?: '5432';
    $base = getenv('SICLO_DB_NAME') ?: 'siclo';
    $usuario = getenv('SICLO_DB_USER') ?: 'postgres';
    $contrasena = getenv('SICLO_DB_PASSWORD') ?: '';

    $dsn = "pgsql:host={$host};port={$puerto};dbname={$base}";

    try {
        $pdo = new PDO($dsn, $usuario, $contrasena, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // La base de datos también trabaja y responde en UTC-6
        $pdo->exec("SET TIME ZONE '" . ZONA_HORARIA . "'");
        return $pdo;
    } catch (PDOException $error) {
        error_log('No fue posible conectar SICLO a PostgreSQL: ' . $error->getMessage());
        throw new RuntimeException('No fue posible conectar con la base de datos. Revise la configuración local.');
    }
}