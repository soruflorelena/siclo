<?php
/** Normaliza el nombre de un trabajador para guardarlo en la base de datos. */

function normalizar_nombre(string $nombre): string
{
    if (!function_exists('mb_strtoupper')) {
        throw new RuntimeException('Falta la extensión mbstring de PHP. Actívala en php.ini (extension=mbstring).');
    }

    $nombre = preg_replace('/\s+/u', ' ', trim($nombre));

    if ($nombre === null || $nombre === '') {
        throw new InvalidArgumentException('El nombre del trabajador no puede estar vacío.');
    }
    if (mb_strlen($nombre, 'UTF-8') > 150) {
        throw new InvalidArgumentException('El nombre no puede tener más de 150 caracteres.');
    }
    if (!preg_match('/^[\p{L}\p{M}][\p{L}\p{M} .\'’-]*$/u', $nombre)) {
        throw new InvalidArgumentException('El nombre solo puede contener letras (con acentos), espacios, puntos, apóstrofos y guiones.');
    }

    return mb_strtoupper($nombre, 'UTF-8');
}
